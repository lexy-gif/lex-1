"""Test global period changes in a disposable database and Compose web container."""
import concurrent.futures
import json
import re
import secrets
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request

suffix = secrets.token_hex(8)
database = 'srms_period_test_' + suffix
container = 'srms-period-test-' + suffix
sessions = [secrets.token_hex(16) for _ in range(3)]
dean, other_dean, teacher = sessions
token = secrets.token_hex(32)
created_database = granted = created_container = False


def run(args, data=None):
    result = subprocess.run(args, input=data, capture_output=True, text=True, encoding='utf-8', timeout=120)
    if result.returncode:
        raise RuntimeError(result.stderr[-2000:] or 'Test command failed')
    return result.stdout


def root_sql(sql, target=None):
    args = ['docker', 'compose', 'exec', '-T', 'db', 'sh', '-c', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root "$@"', 'period-test']
    if target:
        assert target == database and re.fullmatch(r'srms_period_test_[a-f0-9]{16}', target)
        args.append(target)
    return run(args, sql)


def php(code, *args):
    return run(['docker', 'exec', '--user', 'www-data', container, 'php', '-r', code, *args])


def fixture(mode):
    return json.loads(run(['docker', 'exec', container, 'php', 'tests/academic-period-fixtures.php', mode]))


def request(page, data=None, session=dean):
    headers = {'Cookie': 'PHPSESSID=' + session} if session else {}
    data = urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None
    with urllib.request.urlopen(urllib.request.Request(base + page, data=data, headers=headers), timeout=30) as response:
        body = response.read().decode()
        assert '</html>' in body.lower() and not re.search(r'Fatal error|Parse error|Uncaught|Warning:', body), page
        return response.url, body


def active(state):
    years = [r['id'] for r in state['years'] if r['IsActive']]
    terms = [r for r in state['terms'] if r['IsActive']]
    assert len(years) == len(terms) == 1 and terms[0]['AcademicYearId'] == years[0], (years, terms)
    pair = (years[0], terms[0]['id'])
    assert tuple(state['context']) == pair
    assert (state['timetable']['AcademicYearId'], state['timetable']['TermId']) == pair
    return pair


def activation(period):
    return {'activate_period': '', 'csrf_token': token, 'yearid': period['AcademicYearId'], 'termid': period['TermId']}


def reject(page, data, message):
    _, body = request(page, data)
    assert message in body, (page, message)
    return body


source_code = 'require "includes/config.php"; echo json_encode(["schema"=>DB_NAME,"account"=>$dbh->query("SELECT CURRENT_USER()")->fetchColumn(),"years"=>$dbh->query("SELECT id,IsActive FROM tblacademicyears ORDER BY id")->fetchAll(PDO::FETCH_ASSOC),"terms"=>$dbh->query("SELECT id,AcademicYearId,IsActive FROM tblterms ORDER BY id")->fetchAll(PDO::FETCH_ASSOC)]);'
source_command = ['docker', 'compose', 'exec', '-T', 'web', 'php', '-r', source_code]
try:
    original = json.loads(run(source_command))
    user, host = original['account'].rsplit('@', 1)
    quote = lambda value: "'" + value.replace('\\', '\\\\').replace("'", "''") + "'"
    principal = quote(user) + '@' + quote(host)
    assert re.fullmatch(r'srms_period_test_[a-f0-9]{16}', database) and database != original['schema']
    root_sql(f'CREATE DATABASE `{database}`;')
    created_database = True
    root_sql(f'GRANT ALL PRIVILEGES ON `{database}`.* TO {principal};')
    granted = True
    # Copy schema only; no school records or account data enter the test database.
    schema = run(['docker', 'compose', 'exec', '-T', 'db', 'sh', '-c', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump -u root --no-data --skip-comments --skip-lock-tables --no-tablespaces --set-gtid-purged=OFF --skip-triggers "$1"', 'period-test', original['schema']])
    assert 'CREATE DATABASE' not in schema.upper() and not re.search(r'^\s*USE\s', schema, re.M | re.I)
    schema = schema.replace('REFERENCES `' + original['schema'] + '`.', 'REFERENCES `' + database + '`.')
    root_sql(schema, database)
    run(['docker', 'compose', 'run', '-d', '--no-deps', '--name', container, '--publish', '127.0.0.1::80', '-e', 'DB_NAME=' + database, '-e', 'AFRICASTALKING_SMS_ENABLED=false', 'web'])
    created_container = True
    assert php('require "includes/config.php";echo $dbh->query("SELECT DATABASE()")->fetchColumn();') == database
    address = run(['docker', 'port', container, '80/tcp']).strip()
    assert re.fullmatch(r'127\.0\.0\.1:\d+', address)
    base = 'http://' + address + '/'
    f = fixture('setup')
    for sid in [dean, other_dean]:
        php('session_id($argv[1]);session_start();$_SESSION=["alogin"=>"period-validation","csrf_token"=>$argv[2]];session_write_close();', sid, token)
    php('session_id($argv[1]);session_start();$_SESSION=["teacher_user_id"=>(int)$argv[2],"teacher_role"=>"class_teacher","csrf_token"=>$argv[3]];session_write_close();', teacher, str(f['teacher']), token)
    page = 'dean-academic-periods.php'
    a, a2 = f['periods'][0]
    b, b2 = f['periods'][1]
    before = fixture('state')
    pair = active(before)
    for route in [page, 'manage-exams.php']:
        for sid in [None, teacher]:
            url, _ = request(route, activation(b), sid)
            assert 'admin-login.php' in url
        try:
            request(route, {'submit': '', 'activate_period': '', 'csrf_token': 'invalid'})
        except urllib.error.HTTPError as error:
            assert error.code == 403
        else:
            raise AssertionError('CSRF was not checked')
    assert fixture('state') == before
    save = {'save_period': '', 'csrf_token': token, 'academicyear': '2027-test', 'termname': 'Term 1', 'startdate': '2027-01-11', 'enddate': '2027-04-11'}
    request(page, save)
    state = fixture('state')
    assert active(state) == pair
    assert state['years'] == before['years']  # A term edit must not overwrite year dates/flags.
    request(page, dict(save, termname='Term 3'))
    assert active(fixture('state')) == pair
    request(page, dict(save, academicyear='Future-test', termname='Future term'))
    state = fixture('state')
    assert active(state) == pair and not next(y for y in state['years'] if y['AcademicYear'] == 'Future-test')['IsActive']
    before = state
    bad = dict(save, activate='1', termname='Retain this draft', enddate='2026-12-01')
    body = reject(page, bad, 'Term end date cannot precede its start date')
    assert 'Retain this draft' in body and 'checked' in body
    reject(page, dict(save, activate='1', startdate='2027-02-30'), 'Enter valid Term dates')
    reject(page, dict(save, academicyear='X' * 21, activate='1'), 'at most 20 characters')
    reject(page, dict(activation(b), termid=a['TermId']), 'term must belong')
    reject(page, dict(activation(b), termid='99999999'), 'term must belong')
    assert fixture('state') == before
    print('PASS: period saves preserve activation/year dates; invalid selections and dates make no changes', flush=True)

    request(page, activation(b))
    state = fixture('state')
    assert active(state) == (b['AcademicYearId'], b['TermId']) and state['teacherClass'] == f['classes'][1]
    before = state
    request(page, activation(b))
    assert fixture('state') == before  # Repeating activation is idempotent.
    request(page, activation(a2))
    state = fixture('state')
    assert active(state) == (a2['AcademicYearId'], a2['TermId']) and state['teacherClass'] == f['classes'][0]
    before = state
    root_sql(f"""DELIMITER //
CREATE TRIGGER period_test_fail BEFORE UPDATE ON tblterms FOR EACH ROW
BEGIN
 IF NEW.IsActive=1 AND (NEW.id={b['TermId']} OR NEW.TermName='FAIL_AFTER_WRITE') THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected period activation failure';
 END IF;
END//
DELIMITER ;
""", database)
    reject(page, activation(b), 'Academic period could not be saved')
    reject(page, dict(save, academicyear='Failed-new-year', termname='FAIL_AFTER_WRITE', activate='1'), 'Academic period could not be saved')
    assert fixture('state') == before
    root_sql('DROP TRIGGER period_test_fail;', database)
    print('PASS: activation switches teacher/default context together; failures roll back clears, new records and date edits', flush=True)

    exam = {'submit': '', 'csrf_token': token, 'academicyear': 'Exam-only-year', 'termname': 'Exam-only-term', 'examname': 'Period test exam', 'class': '', 'status': 'draft', 'startdate': '2029-03-01', 'enddate': '2029-03-02', 'marksopendate': '', 'marksdeadline': ''}
    _, body = request('manage-exams.php', exam)
    assert 'Exam created successfully' in body
    state = fixture('state')
    assert active(state) == active(before)
    assert not next(y for y in state['years'] if y['AcademicYear'] == exam['academicyear'])['IsActive']
    assert not next(t for t in state['terms'] if t['TermName'] == exam['termname'])['IsActive']
    before = state
    reject('manage-exams.php', exam, 'already exists')
    reject('manage-exams.php', dict(exam, academicyear='Failed-exam-year', termname='New term', **{'class': '99999999'}), 'Select a valid class')
    assert fixture('state') == before
    _, body = request('manage-exams.php', dict(exam, academicyear='2027-test', termname='Term 2', examname='Active-period exam'))
    assert 'Exam created successfully' in body and active(fixture('state')) == active(before)
    print('PASS: exam creation never activates periods; failed exams roll back new periods and duplicates are rejected', flush=True)

    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        results = list(pool.map(lambda item: request(page, activation(item[0]), item[1]), [(a, dean), (b2, other_dean)]))
    assert all('activated=1' in url for url, _ in results)
    assert active(fixture('state')) in [(a['AcademicYearId'], a['TermId']), (b2['AcademicYearId'], b2['TermId'])]
    # Creating an exam must not undo activation, regardless of which concurrent request wins the lock.
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        jobs = [pool.submit(request, page, dict(save, academicyear='Concurrent-year', termname='Concurrent-term', activate='1'), dean), pool.submit(request, 'manage-exams.php', dict(exam, academicyear='Concurrent-year', termname='Concurrent-term'), other_dean)]
        results = [job.result() for job in jobs]
    assert 'saved=1' in results[0][0] and 'Exam created successfully' in results[1][1]
    state = fixture('state')
    concurrent_years = [y for y in state['years'] if y['AcademicYear'] == 'Concurrent-year']
    concurrent_terms = [t for t in state['terms'] if t['TermName'] == 'Concurrent-term']
    assert len(concurrent_years) == len(concurrent_terms) == 1
    assert active(state) == (concurrent_years[0]['id'], concurrent_terms[0]['id'])
    print('PASS: competing activations and exam creation serialize into one consistent active pair', flush=True)
finally:
    try:
        if created_container:
            run(['docker', 'rm', '-f', container])
    finally:
        if created_database:
            assert re.fullmatch(r'srms_period_test_[a-f0-9]{16}', database) and database != original['schema']
            try:
                if granted:
                    root_sql(f'REVOKE ALL PRIVILEGES ON `{database}`.* FROM {principal};')
            finally:
                root_sql(f'DROP DATABASE `{database}`;')
            print('Isolated period test container/database removed', flush=True)
        if 'original' in globals():
            assert json.loads(run(source_command)) == original, 'School period state changed during isolated validation'
