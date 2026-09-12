"""Validate Senior School migration and HTTP workflows in a disposable database."""
import concurrent.futures
import http.cookiejar
import json
import re
import secrets
import subprocess
import sys
import runpy
from pathlib import Path
import urllib.error
import urllib.parse
import urllib.request

suffix = secrets.token_hex(8)
database = 'srms_senior_test_' + suffix
container = 'srms-senior-test-' + suffix
created_database = granted = created_container = False
dean, second_dean, class_teacher, subject_teacher, outsider = [secrets.token_hex(16) for _ in range(5)]
token = secrets.token_hex(32)


def run(args, data=None):
    result = subprocess.run(args, input=data, capture_output=True, text=True, encoding='utf-8', timeout=120)
    if result.returncode:
        raise RuntimeError(result.stderr[-3000:] or result.stdout[-3000:] or 'Test command failed')
    return result.stdout


def root_sql(sql, target=None):
    args = ['docker', 'compose', 'exec', '-T', 'db', 'sh', '-c', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root "$@"', 'senior-test']
    if target:
        assert target == database and re.fullmatch(r'srms_senior_test_[a-f0-9]{16}', target)
        args.append(target)
    return run(args, sql)


def php(code, *args):
    return run(['docker', 'exec', '--user', 'www-data', container, 'php', '-r', code, *args])


def query(sql):
    return json.loads(php('require "includes/config.php";echo json_encode($dbh->query($argv[1])->fetchAll(PDO::FETCH_ASSOC));', sql))


def fixture(mode):
    return json.loads(run(['docker', 'exec', container, 'php', 'tests/senior-school-fixtures.php', mode]))


def request(page, data=None, session=dean, opener=None):
    headers = {'Cookie': 'PHPSESSID=' + session} if session else {}
    body = urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None
    response = (opener.open if opener else urllib.request.urlopen)(urllib.request.Request(base + page, data=body, headers=headers), timeout=30)
    with response:
        text = response.read().decode('utf-8-sig')
        assert not re.search(r'Fatal error|Parse error|Uncaught|Warning:', text), (page, text[:1000])
        return response.url, text


def post(page, data):
    return request(page, dict(data, csrf_token=token))


def reject(page, data, message):
    before = fixture('state')
    _, body = post(page, data)
    assert message in body, (message, body[-1500:])
    assert fixture('state') == before, 'Rejected write changed academic data'
    return body


def status(page, expected, data=None, session=dean):
    try:
        request(page, data, session)
    except urllib.error.HTTPError as error:
        assert error.code == expected, (page, error.code)
    else:
        raise AssertionError((page, 'Expected HTTP', expected))


source_code = 'require "includes/config.php";echo json_encode(["schema"=>DB_NAME,"account"=>$dbh->query("SELECT CURRENT_USER()")->fetchColumn(),"students"=>$dbh->query("SELECT COUNT(*) FROM tblstudents")->fetchColumn(),"results"=>$dbh->query("SELECT COUNT(*) FROM tblresult")->fetchColumn(),"migrations"=>$dbh->query("SELECT Name FROM tblschemamigrations ORDER BY Name")->fetchAll(PDO::FETCH_COLUMN)]);'
source_command = ['docker', 'compose', 'exec', '-T', 'web', 'php', '-r', source_code]
try:
    original = json.loads(run(source_command))
    user, host = original['account'].rsplit('@', 1)
    quote = lambda value: "'" + value.replace('\\', '\\\\').replace("'", "''") + "'"
    principal = quote(user) + '@' + quote(host)
    assert re.fullmatch(r'srms_senior_test_[a-f0-9]{16}', database) and database != original['schema']
    root_sql(f'CREATE DATABASE `{database}`;')
    created_database = True
    root_sql(f'GRANT ALL PRIVILEGES ON `{database}`.* TO {principal};')
    granted = True
    schema = run(['docker', 'compose', 'exec', '-T', 'db', 'sh', '-c', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump -u root --no-data --skip-comments --skip-lock-tables --no-tablespaces --set-gtid-purged=OFF --skip-triggers "$1"', 'senior-test', original['schema']])
    assert 'CREATE DATABASE' not in schema.upper() and not re.search(r'^\s*USE\s', schema, re.M | re.I)
    schema = schema.replace('REFERENCES `' + original['schema'] + '`.', 'REFERENCES `' + database + '`.')
    root_sql(schema, database)
    run(['docker', 'compose', 'run', '-d', '--no-deps', '--name', container, '--publish', '127.0.0.1::80', '-e', 'DB_NAME=' + database, '-e', 'AFRICASTALKING_SMS_ENABLED=false', 'web'])
    created_container = True
    assert php('require "includes/config.php";echo $dbh->query("SELECT DATABASE()")->fetchColumn();') == database
    address = run(['docker', 'port', container, '80/tcp']).strip()
    assert re.fullmatch(r'127\.0\.0\.1:\d+', address)
    base = 'http://' + address + '/'
    legacy = fixture('base')
    migration = ['docker', 'exec', container, 'php', 'scripts/migrate-senior-school.php']
    run(migration)
    subjects = query('SELECT * FROM tblsubjects ORDER BY id')
    assert 'already applied' in run(migration)
    assert query('SELECT * FROM tblsubjects ORDER BY id') == subjects
    legacy_row = next(s for s in subjects if s['id'] == legacy['legacySubject'])
    assert legacy_row['SubjectName'] == 'Computer' and legacy_row['SubjectCode'] == 'LEGACY-COMP' and legacy_row['SeniorType'] == 'elective'
    historical = query(f"SELECT ClassId FROM tblstudentenrollments WHERE StudentId={legacy['legacyStudent']} AND AcademicYearId={legacy['legacyYear']}")
    assert historical == [{'ClassId':legacy['legacyClass']}]
    assert query(f"SELECT SubjectId FROM tblpathwayallocationsubjects WHERE AllocationId={legacy['legacyAllocation']}") == [{'SubjectId':legacy['legacySubject']}]
    f = fixture('setup')
    for sid in [dean, second_dean]:
        php('ini_set("session.use_strict_mode","0");session_id($argv[1]);session_start();$_SESSION=["alogin"=>"senior-validation","csrf_token"=>$argv[2]];require "tests/session-fixture.php";test_dean_session();session_write_close();', sid, token)
    for sid, teacher in zip([class_teacher, subject_teacher, outsider], f['teachers']):
        php('ini_set("session.use_strict_mode","0");session_id($argv[1]);session_start();$_SESSION=["teacher_user_id"=>(int)$argv[2],"teacher_session_version"=>1,"teacher_role"=>"subject_teacher","csrf_token"=>$argv[3]];require "includes/config.php";$dbh->prepare("UPDATE tblusers SET MustChangePassword=0 WHERE id=?")->execute([(int)$argv[2]]);session_write_close();', sid, str(teacher), token)
    pages = ['dean-senior-' + area + '.php' for area in ['pathways','subjects','combinations','assignments','teachers','promotions','reports']]
    for page in pages:
        assert '</html>' in request(page)[1].lower()
        assert 'admin-login.php' in request(page, session=None)[0]
        assert 'admin-login.php' in request(page, session=class_teacher)[0]
        status(page, 403, {'action':'assign','csrf_token':'bad'})
    print('PASS: migration reuse/idempotence, Dean page rendering, authentication and CSRF', flush=True)
    if '--browser' in sys.argv:
        before_browser = fixture('state')
        runpy.run_path(str(Path(__file__).with_name('senior-school-browser.py')))['check_browser'](base, dean, f)
        assert fixture('state') == before_browser, 'Browser checks changed academic records'

    year, future = f['years']
    student, peer, foreign, inactive, junior = f['students']
    electives = [f['subjects'][s] for s in ['Biology','Chemistry','Physics']]
    allocation = dict(action='assign', AcademicYearId=year, PathwayId=f['pathway'], TrackId=f['track'], **{'Students[]':[student], 'Subjects[]':electives})
    page = 'dean-senior-assignments.php'
    status('dean-senior-pathways.php?id=-1', 400)
    malformed = dict(allocation)
    del malformed['Students[]']
    reject(page, dict(malformed, Students='invalid'), 'Select valid learners')
    reject(page, dict(allocation, **{'Subjects[]':electives[:2]}), 'Select exactly 3')
    reject(page, dict(allocation, **{'Students[]':[peer,inactive]}), 'Select an active learner')
    assert 'saved=1' in post(page, allocation)[0]
    state = fixture('state')
    registrations = [r for r in state['tblstudentsubjects'] if r['StudentId']==student and r['Status']==1]
    assert len(registrations)==7 and sum(r['AssignmentSource']=='core' for r in registrations)==4
    assert any(r['SubjectId']==f['subjects']['Core Mathematics'] for r in registrations)
    assert all(r['Status']=='SKIPPED' for r in state['tblnotificationdeliveries'])
    before = state
    post(page, allocation)
    assert fixture('state') == before, 'Repeated allocation should be idempotent'
    replacement = dict(allocation, **{'Subjects[]':[electives[0], electives[1], f['subjects']['Agriculture']]})
    reject(page, replacement, 'Confirm replacement')
    old_id = next(a['id'] for a in state['tblstudentpathways'] if a['StudentId']==student)
    reject(page, dict(replacement, ConfirmReplacement=1, **{f'ExpectedAllocations[{student}]':0}), 'changed since this page')
    assert 'saved=1' in post(page, dict(replacement, ConfirmReplacement=1, **{f'ExpectedAllocations[{student}]':old_id}))[0]
    state = fixture('state')
    allocations = [a for a in state['tblstudentpathways'] if a['StudentId']==student]
    assert len(allocations)==2 and allocations[0]['Status']==0
    assert state['tblresult'] == before['tblresult']
    assert len([r for r in state['tblpathwayallocationsubjects'] if r['AllocationId']==old_id])==7
    print('PASS: core/elective registration, atomic rejection, replacement review, stale forms and preserved results', flush=True)

    for kind in ['pathways','tracks','combinations','enrollment','teachers']:
        assert request('dean-senior-reports.php?report='+kind+'&export=csv')[1]
    assert 'Senior learner 0' in request('teacher-senior.php?student='+str(student), session=class_teacher)[1]
    body = request('teacher-senior.php?student='+str(student), session=subject_teacher)[1]
    assert '<td>Biology</td>' in body and '<td>Chemistry</td>' not in body
    status('teacher-senior.php?student='+str(foreign), 403, session=class_teacher)
    status('teacher-senior.php?student='+str(student), 403, session=outsider)
    status('teacher-senior.php', 405, allocation, class_teacher)
    # Real student login and an attempted learner-ID override exercise account-bound access.
    browser = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    body = request('student-senior.php', session=None, opener=browser)[1]
    student_token = re.search(r'name="csrf_token" value="([^"]+)"', body)[1]
    body = request('student-senior.php', {'csrf_token':student_token,'username':'senior_student','password':'wrong'}, session=None, opener=browser)[1]
    assert 'Invalid student login details.' in body
    request('student-senior.php', {'csrf_token':student_token,'username':'senior_student','password':'test-student-only'}, session=None, opener=browser)
    body = request('student-senior.php?student='+str(foreign), session=None, opener=browser)[1]
    assert 'Senior learner 0' in body and 'Senior learner 2' not in body and '<td>Agriculture</td>' in body
    root_sql(f"UPDATE tblusers SET Status=0 WHERE id={f['account']};", database)
    assert 'Student Sign In' in request('student-senior.php', session=None, opener=browser)[1]
    root_sql(f"UPDATE tblusers SET Status=1 WHERE id={f['account']};", database)
    print('PASS: reports, teacher class/subject restrictions and student login/identity/deactivation', flush=True)

    # Two Deans submit the same stale initial allocation; only one can win.
    competing = dict(allocation, csrf_token=token, **{'Students[]':[peer], f'ExpectedAllocations[{peer}]':0})
    alternative = dict(competing, **{'Subjects[]':[electives[0], electives[1], f['subjects']['Agriculture']]})
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        results = list(pool.map(lambda item: request(page, item[0], item[1]), [(competing,dean),(alternative,second_dean)]))
    assert sum('saved=1' in url for url,_ in results)==1
    assert sum('changed since this page' in body for _,body in results)==1
    before = fixture('state')
    promotion = dict(action='promote', SourceYearId=year, AcademicYearId=future, ClassId=f['classes'][2], **{'Students[]':[student]})
    assert 'saved=1' in post('dean-senior-promotions.php', promotion)[0]
    state = fixture('state')
    assert state['tblresult']==before['tblresult']
    old_registration = [r for r in before['tblstudentsubjects'] if r['AcademicYearId']==year]
    assert [r for r in state['tblstudentsubjects'] if r['AcademicYearId']==year]==old_registration
    history = [r for r in state['tblstudentenrollments'] if r['StudentId']==student]
    assert len(history)==2 and {r['ClassId'] for r in history}=={f['classes'][0],f['classes'][2]}
    assert query(f"SELECT ClassId FROM tblusers WHERE id={f['account']}")[0]['ClassId']==f['classes'][2]
    assert 'Senior learner 0' in request('teacher-senior.php?year='+str(year)+'&student='+str(student), session=class_teacher)[1]
    assert 'Promote Senior School learners' in post('dean-senior-promotions.php', dict(promotion, ClassId=f['classes'][0], **{'Students[]':[junior]}))[1]
    print('PASS: competing allocations, Grade 9 rejection and Senior School promotion with historical enrollments/subjects/results retained', flush=True)
finally:
    try:
        if created_container:
            run(['docker', 'rm', '-f', container])
    finally:
        if created_database:
            assert re.fullmatch(r'srms_senior_test_[a-f0-9]{16}', database) and database != original['schema']
            try:
                if granted:
                    root_sql(f'REVOKE ALL PRIVILEGES ON `{database}`.* FROM {principal};')
            finally:
                root_sql(f'DROP DATABASE `{database}`;')
            print('Isolated Senior School test container/database removed', flush=True)
        if 'original' in globals():
            assert json.loads(run(source_command))==original, 'School records or migrations changed during isolated validation'
