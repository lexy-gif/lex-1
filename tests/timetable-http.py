"""Exercise timetable forms, publication rollback and teacher visibility in Docker."""
import concurrent.futures
import html
import json
import re
import secrets
import subprocess
import urllib.error
import urllib.parse
import urllib.request

tag = 'timetable_' + secrets.token_hex(8)
token = secrets.token_hex(32)
sessions = [secrets.token_hex(16) for _ in range(6)]
dean, other_dean, *teacher_sessions = sessions


def php(code, *args):
    return subprocess.check_output(['docker', 'compose', 'exec', '-T', '--user', 'www-data', 'web', 'php', '-r', code, *args], text=True)


def fixture(mode):
    return json.loads(subprocess.check_output(['docker', 'compose', 'exec', '-T', 'web', 'php', 'tests/timetable-fixtures.php', mode, tag], text=True))


def request(page, data=None, session=dean):
    headers = {'Cookie': 'PHPSESSID=' + session} if session else {}
    data = urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None
    with urllib.request.urlopen(urllib.request.Request('http://localhost:5000/' + page, data=data, headers=headers), timeout=45) as response:
        body = response.read().decode()
        assert '</html>' in body.lower() and not re.search(r'Fatal error|Parse error|Uncaught|Warning:', body), page
        return response.url, body


def reject(page, data, message):
    _, body = request(page, data)
    assert message in body, (message, re.findall(r'<div class="alert alert-danger"[^>]*>(.*?)</div>', body))
    return body


def saved(page, data):
    url, body = request(page, data)
    assert 'saved=1' in url, re.findall(r'<div class="alert alert-danger"[^>]*>(.*?)</div>', body)
    query = urllib.parse.parse_qs(urllib.parse.urlsplit(url).query)
    return int(query['id'][0])


def table_text(page, session=dean):
    _, body = request(page, session=session)
    return html.unescape(' '.join(re.findall(r'<tbody>(.*?)</tbody>', body, re.S)))


try:
    f = fixture('setup')
    for sid in [dean, other_dean]:
        php('session_id($argv[1]);session_start();$_SESSION=["alogin"=>$argv[2],"csrf_token"=>$argv[3]];session_write_close();', sid, tag, token)
    for i, sid in enumerate(teacher_sessions):
        php('session_id($argv[1]);session_start();$_SESSION=["teacher_user_id"=>(int)$argv[2],"teacher_username"=>$argv[3],"teacher_role"=>"subject_teacher","csrf_token"=>$argv[4]];session_write_close();', sid, str(f['teachers'][i]), tag + '_' + str(i), token)
    context = urllib.parse.urlencode({'year': f['year'], 'term': f['terms'][0]})
    exams_page = 'dean-exam-timetable.php?' + context
    lessons_page = 'dean-class-timetable.php?' + context
    workspace = 'dean-academics.php?area=timetable&' + context
    teacher_page = 'teacher-academics.php?area=timetable&' + context
    for page in [exams_page, lessons_page]:
        for sid in [None, teacher_sessions[0]]:
            url, _ = request(page, session=sid)
            assert 'admin-login.php' in url
        try:
            request(page, {'action': 'publish_exam', 'csrf_token': 'invalid'})
        except urllib.error.HTTPError as error:
            assert error.code == 403
        else:
            raise AssertionError('Missing CSRF enforcement')
    exam = {'action': 'exam_session', 'csrf_token': token, 'ExamId': f['exams'][0], 'ClassId': f['classes'][0], 'SubjectId': f['subject'], 'RoomId': f['rooms'][0], 'Invigilators[]': f['teachers'][:2], 'ExamDate': f['date'], 'StartTime': '09:00', 'EndTime': '10:00', 'Status': 'draft', 'ChangeReason': tag + ' exam'}
    first = saved(exams_page, exam)
    state = fixture('state')
    assert {r['TeacherId'] for r in state['members'] if r['SessionId'] == first} == set(f['teachers'][:2])
    assert not state['notifications']
    assert tag + '_0' not in table_text(teacher_page, teacher_sessions[0])
    second = dict(exam, ExamId=f['exams'][1], ClassId=f['classes'][1], RoomId=f['rooms'][1], StartTime='09:30', EndTime='10:30', **{'Invigilators[]': f['teachers'][1:3]})
    reject(exams_page, second, 'Invigilator conflict')
    reject(workspace, second, 'Invigilator conflict')
    reject(exams_page, dict(second, RoomId=f['rooms'][0], **{'Invigilators[]': [f['teachers'][2]]}), 'Room conflict')
    reject(exams_page, dict(second, ExamId=f['exams'][2], ClassId=f['classes'][0], **{'Invigilators[]': [f['teachers'][2]]}), 'Class conflict')
    reject(exams_page, dict(exam, EndTime='08:00'), 'End time must be after start time')
    reject(exams_page, dict(exam, ExamDate='2099-05-20'), 'within the examination dates')
    reject(exams_page, dict(exam, ClassId=f['classes'][1]), 'examination offered to this class')
    reject(exams_page, dict(exam, **{'Invigilators[]': [f['teachers'][4]]}), 'Only active teachers')
    reject(exams_page, dict(exam, **{'Invigilators[]': [f['teachers'][0]] * 2}), 'Duplicate invigilator')
    reject(exams_page, dict(exam, **{'Invigilators[]': [str(f['teachers'][0]) + '.5']}), 'Select valid invigilators')
    assert fixture('state') == state
    second.update(StartTime='10:00', EndTime='11:00')
    second_id = saved(exams_page, second)
    last = dict(second, StartTime='11:00', EndTime='12:00')
    last_id = saved(exams_page, last)
    # A concurrent duty in another class makes a later invigilator edit invalid.
    third = dict(exam, ExamId=f['exams'][2], ClassId=f['classes'][2], RoomId=f['rooms'][2], **{'Invigilators[]': [f['teachers'][2]]})
    saved(exams_page, third)
    saved(exams_page, dict(exam, id=first))  # A session must not conflict with itself.
    state = fixture('state')
    body = reject(exams_page + '&id=' + str(first), dict(exam, id=first, ChangeReason='retain my draft', **{'Invigilators[]': [f['teachers'][0], f['teachers'][2]]}), 'Invigilator conflict')
    assert 'retain my draft</textarea>' in body
    assert fixture('state') == state
    print('PASS: authenticated forms enforce cross-exam class/room/all-invigilator conflicts, dates, ownership and retained edits', flush=True)

    fixture('block_exam_publication')
    before = fixture('state')
    publish_exam = {'action': 'publish_exam', 'csrf_token': token, 'ExamId': f['exams'][1]}
    reject(exams_page, publish_exam, 'invigilator is unavailable')
    assert fixture('state') == before  # The earlier valid entry, versions and notifications roll back too.
    fixture('unblock_exam_publication')
    url, _ = request(exams_page, publish_exam)
    assert 'published=1' in url
    state = fixture('state')
    assert all(r['Status'] == 'published' for r in state['exams'] if r['id'] in [second_id, last_id])
    assert sorted(n['TeacherId'] for n in state['notifications']) == sorted(f['teachers'][1:3])
    reject(exams_page, publish_exam, 'No unpublished timetable entries')
    assert fixture('state') == state
    assert tag + '_1' in table_text(teacher_page, teacher_sessions[2])
    _, _ = request(exams_page, dict(publish_exam, ExamId=f['exams'][0]))
    assert tag + '_0' in table_text(teacher_page, teacher_sessions[3])  # Class teacher can see the class schedule.
    before = fixture('state')
    saved(exams_page, dict(exam, id=first, Status='published', **{'Invigilators[]': [f['teachers'][0], f['teachers'][3]]}))
    state = fixture('state')
    notified = [n['TeacherId'] for n in state['notifications'][len(before['notifications']):]]
    assert set(notified) == {f['teachers'][0], f['teachers'][1], f['teachers'][3]}
    # Removed teachers lose visibility but receive the change notification.
    assert f'<td>{first}</td>' not in table_text(teacher_page, teacher_sessions[1])
    assert f['date'] in table_text('teacher-assignments.php?id=' + str(f['teachers'][2]))
    _, profile = request('view-teacher.php?id=' + str(f['teachers'][2]))
    expected_duties = sum(r['TeacherId'] == f['teachers'][2] for r in state['members'])
    assert re.search(r'<strong>Exam Duties:</strong>\s*' + str(expected_duties) + r'\s*</p>', profile)
    counts = table_text('manage-teachers.php?search=' + urllib.parse.quote(tag + '_2'))
    assert str(expected_duties) + ' exam duties' in counts
    legacy = fixture('legacy_session')
    _, body = request(exams_page + '&id=' + str(legacy))
    assert re.search(r'<option value="' + str(f['teachers'][3]) + r'" selected', body)
    assert tag + '_3' in table_text(exams_page)
    _, _ = request(exams_page, dict(publish_exam, ExamId=f['exams'][2]))
    state = fixture('state')
    assert next(r for r in state['exams'] if r['id'] == legacy)['CreatedBy'] == 'legacy-' + tag
    history = next(v for v in reversed(state['versions']) if v['TimetableType'] == 'exam' and v['RelatedId'] == legacy)
    assert json.loads(history['PreviousValue'])['Invigilators'] == [f['teachers'][3]]
    fixture('active_class_view')
    text = table_text('teacher-timetable.php', teacher_sessions[3])
    assert tag + '_active' in text and tag + '_0, ' + tag + '_1' in text
    assert tag + '_2' not in text  # Older/other-year examinations are outside this view.
    print('PASS: publication rolls back atomically, notifies every invigilator once, preserves legacy duties and scopes teacher views', flush=True)

    lesson = {'action': 'lesson', 'csrf_token': token, 'AcademicYearId': f['year'], 'TermId': f['terms'][0], 'ClassId': f['classes'][0], 'SubjectId': f['subject'], 'TeacherId': f['teachers'][0], 'RoomId': f['rooms'][0], 'DayOfWeek': 'Monday', 'StartTime': '08:00', 'EndTime': '09:00', 'Status': 'draft', 'ChangeReason': tag + ' lesson'}
    lesson_id = saved(lessons_page, lesson)
    state = fixture('state')
    reject(lessons_page, dict(lesson, TeacherId=f['teachers'][2]), 'active teaching assignment')
    reject(lessons_page, dict(lesson, TeacherId=''), 'Select a valid Teacher')
    reject(lessons_page, dict(lesson, EndTime='07:00'), 'End time must be after start time')
    reject(lessons_page, dict(lesson, ClassId=f['classes'][1], RoomId=f['rooms'][1]), 'Teacher conflict')
    reject(lessons_page, dict(lesson, ClassId=f['classes'][1], TeacherId=f['teachers'][1], SubjectId=f['otherSubject']), 'Room conflict')
    reject(workspace, dict(lesson, RoomId=f['rooms'][1]), 'Class conflict')
    reject(lessons_page, dict(lesson, ClassId=f['classes'][2], TeacherId=f['teachers'][2], StartTime='12:00', EndTime='13:00'), 'Teacher is unavailable')
    assert fixture('state') == state
    term_two = saved(lessons_page, dict(lesson, TermId=f['terms'][1]))
    archived = saved(lessons_page, dict(lesson, StartTime='10:00', EndTime='11:00', Status='archived'))
    cancelled = saved(lessons_page, dict(lesson, StartTime='10:00', EndTime='11:00', Status='cancelled'))
    later_lesson = saved(lessons_page, dict(lesson, StartTime='09:00', EndTime='10:00', RoomId=f['rooms'][1]))
    saved(lessons_page, dict(lesson, StartTime='10:00', EndTime='11:00'))
    fixture('block_lesson_publication')
    before = fixture('state')
    publish_class = {'action': 'publish_class', 'csrf_token': token, 'ClassId': f['classes'][0]}
    reject(lessons_page, publish_class, 'Select an active room')
    assert fixture('state') == before
    fixture('unblock_lesson_publication')
    url, _ = request(lessons_page, publish_class)
    assert 'published=1' in url
    state = fixture('state')
    status = {r['id']: r['Status'] for r in state['lessons']}
    assert status[lesson_id] == status[later_lesson] == 'published'
    assert status[term_two] == 'draft' and status[archived] == 'archived' and status[cancelled] == 'cancelled'
    notices = state['notifications'][len(before['notifications']):]
    assert sorted(n['TeacherId'] for n in notices) == sorted([f['teachers'][0], f['teachers'][3]])
    reject(lessons_page.replace('term=' + str(f['terms'][0]), 'term=0'), publish_class, 'Select one academic term')
    assert fixture('state') == state
    text = table_text(teacher_page, teacher_sessions[3])
    assert '08:00:00' in text and 'archived' not in text and 'cancelled' not in text
    print('PASS: lesson assignments, availability and clashes are enforced; publication preserves other terms and closed entries', flush=True)

    race = dict(exam, ExamId=f['exams'][2], ClassId=f['classes'][2], RoomId=f['rooms'][2], ExamDate='2099-05-06', StartTime='16:00', EndTime='17:00', **{'Invigilators[]': [f['teachers'][2]]})
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        results = list(pool.map(lambda sid: request(exams_page, race, sid), [dean, other_dean]))
    assert sum('saved=1' in url for url, _ in results) == 1
    assert sum('Class conflict' in body for _, body in results) == 1
    state = fixture('state')
    assert sum(r['ExamDate'] == '2099-05-06' and r['StartTime'] == '16:00:00' for r in state['exams']) == 1
    print('PASS: simultaneous requests cannot create overlapping examination sessions', flush=True)

    workspace_id = saved(workspace, dict(lesson, DayOfWeek='Tuesday', TermId=f['terms'][1]))
    row = next(r for r in fixture('state')['lessons'] if r['id'] == workspace_id)
    assert row['AcademicYearId'] == f['year'] and row['TermId'] == f['terms'][1]
    # Forms already open before the integration still use the same validation.
    old_form = {'save_lesson': '', 'csrf_token': token, 'classid': f['classes'][0], 'subjectid': f['subject'], 'teacherid': f['teachers'][0], 'roomid': f['rooms'][0], 'dayofweek': 'Wednesday', 'starttime': '08:00', 'endtime': '09:00', 'status': 'draft', 'reason': tag}
    saved(lessons_page, old_form)
    reject(lessons_page, old_form, 'Class conflict')
    print('PASS: workspace edits retain their period and existing form submissions use the shared checks', flush=True)
finally:
    try:
        print(fixture('cleanup'), flush=True)
    finally:
        for sid in sessions:
            php('session_id($argv[1]);session_start();session_destroy();', sid)
