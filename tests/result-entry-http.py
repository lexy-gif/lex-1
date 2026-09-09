"""Exercise learner-specific exam results with temporary records and no external contacts."""
import concurrent.futures
import json
import re
import secrets
import subprocess
import urllib.error
import urllib.parse
import urllib.request

tag = 'result_http_' + secrets.token_hex(8)
token = secrets.token_hex(32)
sessions = [secrets.token_hex(16) for _ in range(3)]
dean, other_dean, teacher = sessions


def run(args):
    return subprocess.check_output(args, text=True, encoding='utf-8', timeout=90)


def php(code, *args):
    return run(['docker', 'compose', 'exec', '-T', '--user', 'www-data', 'web', 'php', '-r', code, *args])


def fixture(mode):
    return json.loads(run(['docker', 'compose', 'exec', '-T', 'web', 'php', 'tests/result-entry-fixtures.php', mode, tag]))


def request(page, data=None, session=dean):
    data = urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None
    headers = {'Cookie': 'PHPSESSID=' + session} if session else {}
    with urllib.request.urlopen(urllib.request.Request('http://localhost:5000/' + page, data=data, headers=headers), timeout=30) as response:
        body = response.read().decode()
        assert not re.search(r'Fatal error|Parse error|Uncaught|Warning:', body), (page, body[:1000])
        if 'application/json' in response.headers.get('Content-Type', ''):
            return response.url, json.loads(body)
        assert '</html>' in body.lower(), (page, body[:1000])
        return response.url, body


def rejected(page, data, message=None, status=None):
    before = fixture('state')
    try:
        _, body = request(page, data)
    except urllib.error.HTTPError as error:
        assert error.code == status, error.code
        body = error.read().decode()
    else:
        assert status is None, (page, status)
    if message:
        assert message in body, (message, body[:1000])
    assert fixture('state') == before, 'Rejected request changed stored marks'
    return body


def fields(response):
    return {int(s) for s in re.findall(r'name="marks\[(\d+)\]"', response['fields'])}


try:
    f = fixture('setup')
    c, other_c = f['classes']
    s, classmate, inactive, other_s = f['students']
    a, b, elective, other_subject = f['subjects']
    exam, next_exam, other_exam, shared_exam = f['exams']
    for sid in [dean, other_dean]:
        php('session_id($argv[1]);session_start();$_SESSION=["alogin"=>$argv[2],"csrf_token"=>$argv[3]];session_write_close();', sid, tag, token)
    php('session_id($argv[1]);session_start();$_SESSION=["teacher_user_id"=>1,"teacher_role"=>"subject_teacher"];session_write_close();', teacher)
    selection = {'class': c, 'studentid': s, 'examid': exam}
    add = dict(selection, csrf_token=token, submit='', **{f'marks[{b}]': 81, f'marks[{a}]': 67})
    for page in ['add-result.php', 'edit-result.php?stid=' + str(s), 'get_student.php']:
        for sid in [None, teacher]:
            url, _ = request(page, session=sid)
            assert 'admin-login.php' in url
    rejected('add-result.php', dict(add, csrf_token='bad'), status=403)
    _, response = request('get_student.php', {'classid': c})
    assert {r['StudentId'] for r in response['students']} == {s, classmate}
    _, response = request('get_student.php', selection)
    assert fields(response) == {a, b} and response['canSubmit']
    _, response = request('get_student.php', dict(selection, studentid=classmate))
    assert fields(response) == {elective}
    _, response = request('get_student.php', dict(selection, examid=next_exam))
    assert fields(response) == {elective}
    rejected('get_student.php', dict(selection, examid=other_exam), 'does not belong to this class', 422)
    rejected('get_student.php', dict(selection, studentid=inactive), 'Select an active student', 422)
    rejected('get_student.php', {'classid1': c}, 'Select valid result records', 422)
    print('PASS: authenticated lookup follows the learner and exam year; no class-wide fallback', flush=True)

    rejected('add-result.php', dict(add, **{f'marks[{elective}]': 99}), 'Subject registrations changed')
    missing = {k: v for k, v in add.items() if k != f'marks[{b}]'}
    rejected('add-result.php', missing, 'Subject registrations changed')
    body = rejected('add-result.php', dict(add, **{f'marks[{b}]': 101}), 'between 0 and 100')
    assert f'name="marks[{a}]" value="67"' in body and f'name="marks[{b}]" value="101"' in body
    assert re.search(r'<option value="' + str(s) + r'" selected>', body)
    rejected('add-result.php', dict(selection, csrf_token=token, submit='', **{'marks[]': [67, 81]}), 'Select valid result records')
    rejected('add-result.php', dict(add, studentid=other_s), 'Select an active student')
    rejected('add-result.php', dict(add, examid=other_exam), 'does not belong to this class')
    rejected('add-result.php', dict(add, **{f'marks[{a}][]': ['bad']}), 'between 0 and 100')

    registration = {'csrf_token': token, 'year': f['years'][0], 'student': s, 'subjects[]': [a, elective]}
    request('student-subjects.php', registration)
    rejected('add-result.php', add, 'Subject registrations changed')
    request('student-subjects.php', dict(registration, **{'subjects[]': []}))
    rejected('add-result.php', add, 'No active subject registrations')
    _, response = request('get_student.php', selection)
    assert not response['canSubmit'] and not fields(response)
    request('student-subjects.php', dict(registration, **{'subjects[]': [a, b]}))
    fixture('disable_offering')
    rejected('add-result.php', add, 'Subject registrations changed')
    fixture('restore_offering')
    print('PASS: forged, missing, stale and inactive subjects reject the whole save; input is retained', flush=True)

    _, body = request('add-result.php', add)
    assert 'Result info added successfully' in body
    rows = sorted((r for r in fixture('state') if r['ExamId'] == exam), key=lambda r: r['SubjectId'])
    assert {r['SubjectId']: r['marks'] for r in rows} == {a: 67, b: 81}
    rejected('add-result.php', add, 'Result already declared')
    _, response = request('get_student.php', selection)
    assert not response['canSubmit'] and 'already declared' in response['message']
    request('add-result.php', dict(selection, examid=next_exam, csrf_token=token, submit='', **{f'marks[{elective}]': 73}))
    edit_page = f'edit-result.php?stid={s}&examid={exam}'
    row_ids = [r['id'] for r in rows]
    edit = {'csrf_token': token, 'submit': '', 'id[]': row_ids, 'marks[]': [79, 89]}
    rejected(edit_page, dict(edit, csrf_token='bad'), status=403)
    next_row = next(r for r in fixture('state') if r['ExamId'] == next_exam)
    rejected(edit_page, dict(edit, **{'id[]': [row_ids[0], next_row['id']]}), 'does not belong to this student and examination')
    rejected(edit_page, dict(edit, **{'id[]': [row_ids[0], row_ids[0]]}), 'Duplicate or invalid')
    rejected(edit_page, dict(edit, **{'marks[]': [79, 101]}), 'between 0 and 100')
    request('student-subjects.php', dict(registration, **{'subjects[]': [a]}))
    rejected(edit_page, edit, 'not actively registered')
    _, body = request(edit_page)
    assert 'registration or offering is inactive' in body and f'value="{row_ids[1]}"' not in body
    assert '81' in body  # The historical mark remains readable.
    request('student-subjects.php', dict(registration, **{'subjects[]': [a, b]}))
    _, body = request(edit_page, edit)
    assert 'Result info updated successfully' in body
    assert {r['SubjectId']: r['marks'] for r in fixture('state') if r['ExamId'] == exam} == {a: 79, b: 89}
    _, body = request(f'edit-result.php?stid={s}')
    assert 'Historical results without an exam are read-only' in body and 'name="marks[]"' not in body
    print('PASS: subject IDs preserve marks; edits enforce registrations and roll back mixed invalid batches', flush=True)

    simultaneous = dict(add, examid=shared_exam)
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        responses = list(pool.map(lambda sid: request('add-result.php', simultaneous, sid)[1], [dean, other_dean]))
    assert sum('Result info added successfully' in body for body in responses) == 1
    assert sum('Result already declared' in body for body in responses) == 1
    assert len([r for r in fixture('state') if r['ExamId'] == shared_exam]) == 2
    fixture('disable_subject')
    rejected(edit_page, edit, 'not actively registered')
    fixture('move_student')
    rejected(edit_page, edit, 'Select an active student')
    _, body = request(edit_page)
    assert tag + '_0' in body and 'name="marks[]"' not in body
    print('PASS: simultaneous submissions create one result; inactive subjects and class transfers prevent edits', flush=True)
finally:
    try:
        print(fixture('cleanup'), flush=True)
    finally:
        for sid in sessions:
            php('session_id($argv[1]);session_start();session_destroy();', sid)
