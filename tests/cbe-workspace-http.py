"""Exercise the CBE workspace through HTTP with isolated fixtures and session cleanup."""
import csv
import html
import io
import json
import re
import secrets
import subprocess
import urllib.error
import urllib.parse
import urllib.request

tag = 'cbe_http_' + secrets.token_hex(8)
token = secrets.token_hex(32)
sessions = [secrets.token_hex(16) for _ in range(2)]


def php(code, *args):
    return subprocess.check_output(['docker', 'compose', 'exec', '-T', '--user', 'www-data', 'web', 'php', '-r', code, *args], text=True)


def fixture(mode):
    return subprocess.check_output(['docker', 'compose', 'exec', '-T', 'web', 'php', 'tests/cbe-workspace-fixtures.php', mode, tag], text=True)


def request(page, data=None, session=None):
    headers = {'Cookie': 'PHPSESSID=' + session} if session else {}
    req = urllib.request.Request('http://localhost:5000/' + page, data=urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None, headers=headers)
    with urllib.request.urlopen(req, timeout=30) as response:
        body = response.read().decode()
        assert not re.search(r'Fatal error|Parse error|Uncaught|Warning:', body), page + ' PHP error'
        if 'text/html' in response.headers.get('Content-Type', ''):
            assert '</html>' in body.lower(), page + ' incomplete response'
        return response.url, body


def denied(page, data=None, session=None, status=403):
    try:
        request(page, data, session)
    except urllib.error.HTTPError as error:
        assert error.code == status, (page, error.code)
    else:
        raise AssertionError('Expected rejection: ' + page)


try:
    f = json.loads(fixture('setup'))
    dean, teacher = sessions
    php('session_id($argv[1]);session_start();$_SESSION=["alogin"=>$argv[2],"csrf_token"=>$argv[3]];session_write_close();', dean, tag, token)
    php('session_id($argv[1]);session_start();$_SESSION=["teacher_user_id"=>(int)$argv[2],"teacher_username"=>$argv[3],"teacher_role"=>"subject_teacher","csrf_token"=>$argv[4]];session_write_close();', teacher, str(f['teachers'][0]), tag + '_0', token)
    query = {'area': 'assessments', 'year': f['year'], 'term': f['term'], 'class': f['classes'][0], 'subject': f['subjects'][0]}
    page = 'dean-academics.php?' + urllib.parse.urlencode(query)
    _, body = request('dashboard.php', session=dean)
    assert 'Open Academic Workspace' in body
    _, body = request(page, session=dean)
    assert 'Academic Workspace' in body
    assessment = {'csrf_token': token, 'action': 'assessment', 'Title': tag + ' current term', 'AssessmentTypeId': f['type'], 'AcademicYearId': f['year'], 'TermId': f['term'], 'ClassId': f['classes'][0], 'SubjectId': f['subjects'][0], 'TeacherId': f['teachers'][0], 'MaximumScore': 100, 'AssessmentDate': '2026-09-06', 'Outcomes[]': [f['outcomes'][0]], 'Status': 'open'}
    url, body = request(page, assessment, dean)
    saved = urllib.parse.parse_qs(urllib.parse.urlsplit(url).query)
    assert saved['entity'] == ['assessment'] and saved['saved'] == ['1']
    assessment_id = int(saved['id'][0])
    assert saved['term'] == [str(f['term'])] and saved['class'] == [str(f['classes'][0])]
    assert tag + ' outcome 0' in body
    outcome_select = re.search(r'<select[^>]*name="OutcomeId"[^>]*>(.*?)</select>', body, re.S).group(1)
    assert str(f['outcomes'][1]) + '"' not in outcome_select
    edit_page = url.split('http://localhost:5000/')[1]
    score = {'csrf_token': token, 'action': 'score', 'AssessmentId': assessment_id, 'StudentId': f['students'][0], 'Score': 74, 'PerformanceLevelId': f['level'], 'OutcomeId': f['outcomes'][0], 'Evidence': tag + ' evidence'}
    url, body = request(edit_page, score, dean)
    assert 'saved=1' in url and 'id=' + str(assessment_id) in url and tag + ' evidence' in body
    print('PASS: workspace navigation, assessment creation and repeated evidence entry retain context', flush=True)

    bad_edit = edit_page.replace('&saved=1', '')
    _, body = request(bad_edit, dict(score, Score=101, Evidence='keep this draft'), dean)
    assert 'between 0 and 100' in body and 'keep this draft</textarea>' in body
    denied(edit_page, dict(score, csrf_token='invalid'), dean)
    _, body = request(bad_edit, dict(score, Score=25, OutcomeId=f['outcomes'][1]), dean)
    assert 'outcome linked to this assessment' in body
    report_page = 'dean-academics.php?' + urllib.parse.urlencode(dict(query, area='reports', report='performance', student=f['students'][0], teacher=f['teachers'][0]))
    _, body = request(report_page, session=dean)
    assert 'name="report" value="performance"' in body
    assert 'name="student" value="' + str(f['students'][0]) + '"' in body
    csv_link = html.unescape(re.search(r'href="([^"]+export=1)"', body).group(1))
    _, body = request(csv_link, session=dean)
    rows = list(csv.DictReader(io.StringIO(body)))
    assert len(rows) == 1 and float(rows[0]['Score']) == 74, rows
    print('PASS: invalid evidence rolls back; validation retains input; CSV preserves report filters', flush=True)

    teacher_page = 'teacher-academics.php?' + urllib.parse.urlencode(dict(query, entity='assessment', id=assessment_id, teacher=f['teachers'][1]))
    _, body = request(teacher_page, session=teacher)
    assert 'My Academic Workspace' in body and tag + '_1' not in body
    denied('teacher-academics.php?' + urllib.parse.urlencode(dict(query, entity='assessment', id=f['otherAssessment'])), session=teacher)
    _, body = request(teacher_page, dict(score, Score=78, TeacherId=f['teachers'][1]), teacher)
    assert 'Academic changes saved.' in body
    _, body = request(bad_edit, dict(assessment, id=assessment_id, Status='locked'), dean)
    assert 'Evidence entry is closed' in body and 'Save Evidence' not in body
    _, body = request(teacher_page, score, teacher)
    assert 'Score entry is closed' in body
    for action in ['config', 'exam_lock', 'permission']:
        _, body = request('teacher-academics.php?' + urllib.parse.urlencode(query), {'csrf_token': token, 'action': action}, teacher)
        assert 'Only the Dean can perform this change' in body
    denied('teacher-academics.php?area=reports', session=teacher)
    url, _ = request('dean-academics.php', session=teacher)
    assert 'admin-login.php' in url
    for portal in ['dean', 'teacher']:
        url, _ = request(portal + '-academics.php')
        assert 'login.php' in url
    print('PASS: teacher ownership, Dean-only actions, locked assessment entry and authentication', flush=True)

    exam_page = 'dean-academics.php?' + urllib.parse.urlencode(dict(query, area='exams'))
    exam_lock = {'csrf_token': token, 'action': 'exam_lock', 'ExamId': f['exams'][0], 'EntryLocked': 1}
    request(exam_page, exam_lock, dean)
    marks = {'csrf_token': token, 'submit': '', 'class': f['classes'][0], 'examid': f['exams'][0], 'studentid': f['students'][0], f"marks[{f['subjects'][0]}]": 65}
    _, body = request('add-result.php', marks, dean)
    assert 'Exam result entry is closed' in body and len(json.loads(fixture('state'))) == 1
    request(exam_page, dict(exam_lock, EntryLocked=0), dean)
    _, body = request('add-result.php', marks, dean)
    assert 'Result info added successfully' in body
    state = json.loads(fixture('state'))
    result_id = next(r['id'] for r in state if r['ExamId'] == f['exams'][0])
    result_page = 'edit-result.php?' + urllib.parse.urlencode({'stid': f['students'][0], 'examid': f['exams'][0]})
    edit_marks = {'csrf_token': token, 'submit': '', 'id[]': [result_id], 'marks[]': [80]}
    request(exam_page, exam_lock, dean)
    _, body = request(result_page, edit_marks, dean)
    assert 'Exam result entry is closed' in body
    request(exam_page, dict(exam_lock, EntryLocked=0), dean)
    _, body = request(result_page, dict(edit_marks, **{'id[]': [result_id, f['otherResult']], 'marks[]': [80, 95]}), dean)
    assert 'does not belong to this student and examination' in body
    state = json.loads(fixture('state'))
    assert next(r['marks'] for r in state if r['id'] == result_id) == 65
    assert next(r['marks'] for r in state if r['id'] == f['otherResult']) == 60
    _, body = request(result_page, edit_marks, dean)
    assert 'Result info updated successfully' in body
    assert next(r['marks'] for r in json.loads(fixture('state')) if r['id'] == result_id) == 80
    print('PASS: Dean exam locks govern both result forms; cross-exam edits roll back together', flush=True)

    for area in ['dashboard', 'structure', 'subjects', 'pathways', 'allocation', 'workload', 'assessments', 'coverage', 'interventions', 'analytics', 'reports', 'class', 'permissions', 'exams', 'timetable']:
        request('dean-academics.php?' + urllib.parse.urlencode(dict(query, area=area)), session=dean)
    for area in ['assessments', 'coverage', 'interventions', 'workload', 'timetable', 'department']:
        request('teacher-academics.php?' + urllib.parse.urlencode(dict(query, area=area)), session=teacher)
    for report in ['performance', 'legacy', 'workload', 'assignments', 'pathways', 'combinations', 'assessments', 'coverage', 'interventions', 'unallocated']:
        request('dean-academics.php?' + urllib.parse.urlencode(dict(query, area='reports', report=report, export=1)), session=dean)
    print('PASS: every Dean and teacher workspace area and all CSV report types render', flush=True)
    print(fixture('verify'), flush=True)
finally:
    try:
        print(fixture('cleanup'), flush=True)
    finally:
        for session in sessions:
            php('session_id($argv[1]);session_start();session_destroy();', session)
