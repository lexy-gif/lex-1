"""Fresh installation and guardian/result workflows; all writes stay in a disposable DB."""
import concurrent.futures
import http.cookiejar
import json
import re
import secrets
import subprocess
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

suffix = secrets.token_hex(8)
database = 'srms_parent_test_' + suffix
container = 'srms-parent-test-' + suffix
created = granted = started = False
password = 'Setup-test-only-' + secrets.token_hex(12)


def run(args, data=None):
    r = subprocess.run(args, input=data, capture_output=True, text=True, encoding='utf-8', timeout=180)
    if r.returncode:
        raise RuntimeError(r.stderr[-4000:] or r.stdout[-4000:])
    return r.stdout


def root(sql, target=None):
    args = ['docker', 'compose', 'exec', '-T', 'db', 'sh', '-c', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root "$@"', 'parent-test']
    if target:
        assert target == database and re.fullmatch(r'srms_parent_test_[a-f0-9]{16}', target)
        args.append(target)
    return run(args, sql)


def php(code, *args):
    return run(['docker', 'exec', '--user', 'www-data', container, 'php', '-r', code, *map(str, args)])


def query(sql):
    return json.loads(php('require "includes/config.php";echo json_encode($dbh->query($argv[1])->fetchAll(PDO::FETCH_ASSOC));', sql))


def browser():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def request(client, page, data=None, expected=200):
    body = urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None
    try:
        r = client.open(urllib.request.Request(base + page, data=body), timeout=40)
    except urllib.error.HTTPError as e:
        r = e
    text = r.read().decode('utf-8-sig')
    assert r.code == expected, (page, r.code, text[:1200])
    assert not re.search(r'Fatal error|Parse error|Uncaught|Warning:', text), (page, text[:1000])
    return r.url, text


def token(body):
    return re.search(r'name="csrf_token" value="([^"]+)"', body)[1]


def post(client, page, data, expected=200):
    csrf = token(request(client, page)[1])
    return request(client, page, dict(data, csrf_token=csrf), expected)


def login(client, page, username, pwd):
    return post(client, page, {'login': '1', 'username': username, 'password': pwd})


source = run(['docker', 'compose', 'exec', '-T', 'web', 'php', '-r', 'require "includes/config.php";echo json_encode(["database"=>DB_NAME,"account"=>$dbh->query("SELECT CURRENT_USER()")->fetchColumn(),"students"=>$dbh->query("SELECT COUNT(*) FROM tblstudents")->fetchColumn(),"results"=>$dbh->query("SELECT COUNT(*) FROM tblresult")->fetchColumn()]);'])
original = json.loads(source)
try:
    user, host = original['account'].rsplit('@', 1)
    quote = lambda s: "'" + s.replace('\\', '\\\\').replace("'", "''") + "'"
    principal = quote(user) + '@' + quote(host)
    assert database != original['database']
    root(f'CREATE DATABASE `{database}`;'); created = True
    root(f'GRANT ALL PRIVILEGES ON `{database}`.* TO {principal};'); granted = True
    root(Path('srms.sql').read_text(encoding='utf-8'), database)
    run(['docker', 'compose', 'run', '-d', '--no-deps', '--name', container, '--publish', '127.0.0.1::80', '-e', 'DB_NAME=' + database, '-e', 'AFRICASTALKING_SMS_ENABLED=false', '-e', 'MAIL_ENABLED=false', '-e', 'SETUP_DEAN_USERNAME=test_dean', '-e', 'SETUP_DEAN_PASSWORD=' + password, '-e', 'SETUP_ACADEMIC_YEAR=2026', 'web'])
    started = True
    address = run(['docker', 'port', container, '80/tcp']).strip()
    assert re.fullmatch(r'127\.0\.0\.1:\d+', address)
    base = 'http://' + address + '/'
    setup = ['docker', 'exec', container, 'php', 'scripts/setup.php']
    assert 'Senior School installed' in run(setup)
    assert 'already exists' in run(setup)
    assert [r['ClassNameNumeric'] for r in query('SELECT ClassNameNumeric FROM tblclasses ORDER BY ClassNameNumeric')] == [10, 11, 12]
    f = json.loads(run(['docker', 'exec', container, 'php', 'tests/parent-results-fixtures.php']))
    print('PASS: fresh schema, installation, repeat setup, Grades 10–12 seeds', flush=True)
    dean, teacher, class_teacher, outsider, parent, other_parent, student, public = [browser() for _ in range(8)]
    assert 'dashboard.php' in login(dean, 'admin-login.php', 'test_dean', password)[0]
    for client, name in [(teacher, 'subject'), (class_teacher, 'class'), (outsider, 'outsider')]:
        assert 'teacher-login.php' not in login(client, 'teacher-login.php', 'test_' + name, 'Test-teacher-pass-123')[0]
    assert 'parent-dashboard.php' in login(parent, 'parent-login.php', 'guardian', 'Test-parent-pass-123')[0]
    login(other_parent, 'parent-login.php', 'otherguardian', 'Test-parent-pass-123')
    assert 'parent-login.php' in login(student, 'parent-login.php', 'student_account', 'Test-student-pass-123')[0]
    for private in ['.env','.git/config','srms.sql','includes/config.php','tests/parent-results-fixtures.php','scripts/setup.php']:
        request(public,private,expected=403)
    assert 'Alpha Child' in request(parent,'parent-child.php?student='+str(f['students'][0]))[1]
    request(other_parent,'parent-child.php?student='+str(f['students'][0]),expected=404)
    body = request(parent, 'parent-dashboard.php')[1]
    assert 'Alpha Child' in body and 'Beta Child' in body and 'Other Child' not in body
    assert 'parent-login.php' in request(public, 'result.php', {'rollid': 'PARENT-TEST-0', 'class': f['classes'][0]})[0]
    request(parent, 'parent-report.php?student=' + str(f['students'][2]), expected=404)
    assert 'admin-login.php' in request(parent, 'manage-parents.php')[0]
    assert 'teacher-login.php' in request(parent, 'teacher-mark-entry.php')[0]
    request(dean, 'manage-parents.php', {'action': 'save', 'csrf_token': 'invalid'}, expected=403)
    mixed = browser()
    login(mixed,'parent-login.php','guardian','Test-parent-pass-123')
    login(mixed,'student-senior.php','student_account','Test-student-pass-123')
    assert 'parent-login.php' in request(mixed,'parent-dashboard.php')[0]
    print('PASS: real Dean/teacher/guardian login, siblings, student role rejection, public lookup closure, IDOR and CSRF', flush=True)

    for page in ['manage-students.php', 'manage-results.php', 'manage-classes.php', 'manage-subjects.php', 'manage-parents.php', 'dean-grading.php', 'dean-sms-deliveries.php']:
        assert '</html>' in request(dean, page)[1].lower()
    teacher_form = dict(submit='1', firstname='Created', middlename='', lastname='Teacher', staffnumber='TEST-CREATED', username='created_teacher', email='created@example.invalid', phonenumber='', department='', role='subject_teacher', password='Created-teacher-pass-123', confirmpassword='Created-teacher-pass-123', status=1, academic_year=f['year'])
    _, body = post(dean, 'manage-teachers.php', teacher_form)
    assert query("SELECT id FROM tblusers WHERE Username='created_teacher'"), body[-2000:]
    student_form = dict(submit='1', fullanme='Created Learner', rollid='CREATED-LEARNER', emailid='', parentphone='', gender='Female', dob='2010-01-01', **{'class': f['classes'][2]})
    assert 'Student info added successfully' in post(dean, 'add-students.php', student_form)[1]
    new_student = query("SELECT StudentId FROM tblstudents WHERE RollId='CREATED-LEARNER'")[0]['StudentId']
    assert 'already registered' in post(dean, 'add-students.php', student_form)[1]
    guardian_form = dict(action='save', id=0, FullName='Created Guardian', Username='created_guardian', Email='', ParentPhone='', Status=1, Password='Created-parent-pass-123')
    url, _ = post(dean, 'manage-parents.php', guardian_form)
    new_parent = query("SELECT id FROM tblusers WHERE Username='created_guardian'")[0]['id']
    post(dean, 'manage-parents.php?id=' + str(new_parent), dict(action='link', id=new_parent, Admission='CREATED-LEARNER', Relationship='Guardian'))
    new_browser = browser()
    assert 'parent-profile.php' in login(new_browser, 'parent-login.php', 'created_guardian', 'Created-parent-pass-123')[0]
    assert 'parent-profile.php' in request(new_browser, 'parent-dashboard.php')[0]
    post(new_browser, 'parent-profile.php', dict(CurrentPassword='Created-parent-pass-123', NewPassword='Personal-parent-pass-123', ConfirmPassword='Personal-parent-pass-123', Email='', Phone=''))
    assert 'Created Learner' in request(new_browser, 'parent-dashboard.php')[1]
    request(new_browser, 'parent-report.php?student=' + str(f['students'][0]), expected=404)
    post(dean, 'manage-parents.php?id=' + str(new_parent), dict(action='reset', id=new_parent, Password='Reset-parent-pass-123'))
    assert 'parent-login.php' in request(new_browser, 'parent-dashboard.php')[0]
    print('PASS: Dean creates teacher, admits learner, prevents duplicate admission, links guardian, password setup/reset and management pages', flush=True)

    for grade in [7, 8, 9, 13]:
        body = post(dean, 'dean-academics.php?area=structure', {'action': 'config', 'entity': 'grades', 'SchoolLevelId': query('SELECT id FROM tblschoollevels WHERE SeniorSchool=1')[0]['id'], 'Name': 'Grade ' + str(grade), 'GradeNumber': grade, 'PathwayEntry': 0, 'GuidanceEligible': 0, 'Status': 1})[1]
        assert 'Only Grades 10, 11 and 12' in body
    c, e = f['classes'][0], f['exam']
    page = f'teacher-mark-entry.php?class={c}&exam={e}&subject={f["subjects"][0]}'
    request(outsider, page, expected=403)
    marks = {f'marks[{sid}]': '42.50' for sid in f['students']}
    fields = dict(ClassId=c, ExamId=e, SubjectId=f['subjects'][0], action='save', **marks)
    for mark in ['-1', '51', 'NaN', '1e999']:
        data = dict(fields, **{f'marks[{f["students"][0]}]': mark})
        assert 'Enter a number' in post(teacher, page, data)[1]
        assert not query('SELECT id FROM tblresult')
    assert 'saved=1' in post(teacher, page, fields)[0]
    assert not query('SELECT id FROM tblparentsms')
    post(teacher, page, dict(fields, action='submit'))
    assert 'Submitted results are locked' in post(teacher, page, fields)[1]
    decision = dict(classid=c, examid=e, reason='', decision='publish')
    assert 'complete, submitted' in post(dean, 'dean-result-approvals.php', decision)[1]
    assert not query('SELECT id FROM tblresultpublications')
    fields['SubjectId'] = f['subjects'][1]
    post(teacher, page, dict(fields, action='submit'))
    assert 'class teacher must review' in post(dean, 'dean-result-approvals.php', decision)[1]
    post(class_teacher, 'teacher-results.php', dict(review_action=1, action='approve', examid=e, correction_reason=''))
    assert 'Approve these results' in post(dean, 'dean-result-approvals.php', decision)[1]
    post(class_teacher,'teacher-report-cards.php',dict(save_comment=1,studentid=f['students'][0],examid=e,status='submitted',comment='Consistent progress in practical work.'))
    post(dean, 'dean-result-approvals.php', dict(decision, decision='approve'))
    report = f'parent-report.php?student={f["students"][0]}&exam={e}&class={c}'
    request(parent, report, expected=404)
    post(dean, 'dean-result-approvals.php', decision)
    body = request(parent, report)[1]
    assert '42.50' in body and '50.00' in body and 'Published report card' in body
    assert 'Consistent progress in practical work.' in body
    root("UPDATE tblgradingscales SET Grade='CHANGED',Remark='Future reports only';",database)
    assert '>TEST<' in request(parent,report)[1]
    assert 'Published report comments are locked' in post(class_teacher,'teacher-report-cards.php',dict(save_comment=1,studentid=f['students'][0],examid=e,status='draft',comment='Attempted rewrite'))[1]
    request(other_parent, report, expected=404)
    request(parent, report.replace('class=' + str(c), 'class=' + str(f['classes'][1])), expected=404)
    before = query('SELECT * FROM tblparentsms')
    assert len(before) == 3 and all(r['Status'] == 'pending' for r in before)
    assert all('42.50' not in r['Message'] and 'Rank:' not in r['Message'] for r in before)
    for _ in range(2):
        request(parent, report); post(dean, 'dean-result-approvals.php', decision)
    assert query('SELECT * FROM tblparentsms') == before
    assert 'Published results are locked' in post(teacher, page, fields)[1]
    assert 'Published results are locked' in post(dean, 'dean-result-approvals.php', dict(decision, decision='reject', reason='Correction'))[1]
    print('PASS: subject scope, configured maximum/decimal scores, submission locks, complete review/approve/publish lifecycle, unpublished isolation and class-specific publication', flush=True)
    # Independent CBE assessments follow submitted -> approved -> published, with the same guardian isolation.
    assessment_type = query('SELECT id FROM tblassessmenttypes ORDER BY id LIMIT 1')[0]['id']
    assessment_fields = dict(action='assessment', Title='Parent CBE assessment', AssessmentTypeId=assessment_type, AcademicYearId=f['year'], TermId=f['term'], ClassId=c, SubjectId=f['subjects'][0], TeacherId=f['teachers'][1], MaximumScore=20, AssessmentDate='2026-09-12', Status='open')
    assessment_url, body = post(dean, 'dean-academics.php?area=assessments', assessment_fields)
    assessment_id = int(urllib.parse.parse_qs(urllib.parse.urlsplit(assessment_url).query)['id'][0])
    cbe_page = 'dean-academics.php?area=assessments&entity=assessment&id=' + str(assessment_id)
    for sid in f['students']:
        post(dean, cbe_page, dict(action='score', AssessmentId=assessment_id, StudentId=sid, Score=17, Evidence='Published CBE evidence'))
    assert 'Approve the assessment' in post(dean, cbe_page, dict(assessment_fields, id=assessment_id, Status='published'))[1]
    for status in ['submitted', 'approved', 'published']:
        assert 'saved=1' in post(dean, cbe_page, dict(assessment_fields, id=assessment_id, Status=status))[0]
    assessment_report = f'parent-assessments.php?student={f["students"][0]}&assessment={assessment_id}'
    assert 'Published CBE evidence' in request(parent, assessment_report)[1]
    request(other_parent, assessment_report, expected=404)
    assert 'Published assessments are locked' in post(dean, cbe_page, dict(assessment_fields, id=assessment_id, Status='open'))[1]
    assert len(query('SELECT id FROM tblparentsms')) == 6
    print('PASS: CBE assessment submission/approval/publication, guardian visibility and immutable published assessment', flush=True)

    # Two workers claim distinct pending jobs; failures and uncertain outcomes never auto-resend.
    assert php('require "includes/config.php";require "includes/parent-sms-queue.php";echo parent_sms_process($dbh,1,function(){return ["success"=>false,"message"=>"Confirmed test rejection"];});') == '1'
    assert php('require "includes/config.php";require "includes/parent-sms-queue.php";echo parent_sms_process($dbh,1,function(){throw new RuntimeException("Test transport interrupted");});') == '1'
    # Transport stub exercises outbox execution without any provider connection.
    sender = 'require "includes/config.php";require "includes/parent-sms-queue.php";echo parent_sms_process($dbh,25,function($phone,$message){return ["success"=>true,"reference"=>"TEST-ACCEPTED"];});'
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        assert sum(int(result) for result in pool.map(lambda _:php(sender),range(2))) == 4
    assert php('require "includes/config.php";require "includes/parent-sms-queue.php";echo parent_sms_process($dbh,25,function(){throw new Exception("Duplicate send");});') == '0'
    statuses = query('SELECT * FROM tblparentsms')
    assert sorted(r['Status'] for r in statuses) == ['accepted']*4+['failed','uncertain']
    assert all(r['Attempts']==1 for r in statuses)
    failed = next(r['id'] for r in statuses if r['Status']=='failed')
    uncertain = next(r['id'] for r in statuses if r['Status']=='uncertain')
    assert 'Only a confirmed failed' in post(dean,'dean-sms-deliveries.php',dict(id=uncertain))[1]
    post(dean,'dean-sms-deliveries.php',dict(id=failed))
    assert php(sender)=='1'
    assert query('SELECT Status,Attempts FROM tblparentsms WHERE id='+str(failed))==[{'Status':'accepted','Attempts':2}]
    assert php('require "includes/sms.php";echo json_encode([normalize_phone_number("0712345678"),normalize_phone_number("0112345678"),normalize_phone_number("+254712345678"),normalize_phone_number("254112345678"),normalize_phone_number("+255712345678")]);')=='["+254712345678","+254112345678","+254712345678","+254112345678",""]'
    post(parent, 'parent-notifications.php', {'action': 'all'})
    assert not query(f'SELECT id FROM tblparentnotifications WHERE ParentId={f["parents"][0]} AND ReadAt IS NULL')
    root(f'UPDATE tblparentstudents SET Status=0 WHERE ParentId={f["parents"][0]} AND StudentId={f["students"][0]};', database)
    request(parent, report, expected=404)
    root(f'UPDATE tblusers SET Status=0 WHERE id={f["teachers"][1]};', database)
    assert 'teacher-login.php' in request(teacher, page)[0]
    assert 'teacher-login.php' in login(browser(), 'teacher-login.php', 'test_subject', 'Test-teacher-pass-123')[0]
    print('PASS: concurrent SMS claims, failures/uncertain outcomes, explicit retries, phone normalization (stub only), notifications, revoked guardianship and inactive teacher rejection', flush=True)
    other_dean = browser()
    login(other_dean,'admin-login.php','test_dean',password)
    assert 'successfully changed' in post(dean,'change-password.php',dict(submit=1,password=password,newpassword='Updated-dean-pass-123',confirmpassword='Updated-dean-pass-123'))[1]
    assert 'admin-login.php' in request(other_dean,'dashboard.php')[0]
    assert 'dashboard.php' in request(dean,'dashboard.php')[0]
    print('PASS: Dean password changes invalidate other sessions',flush=True)
    for client,route,login_route in [(dean,'logout.php','index.php'),(class_teacher,'teacher-logout.php','teacher-login.php')]:
        request(client,route,expected=405)
        request(client,route,dict(csrf_token='invalid'),expected=403)
        csrf=token(request(client,'dashboard.php' if client is dean else 'teacher-dashboard.php')[1])
        assert login_route in request(client,route,dict(csrf_token=csrf))[0]
    if '--browser' in __import__('sys').argv:
        from playwright.sync_api import sync_playwright
        with sync_playwright() as p:
            b = p.chromium.launch(channel='msedge', headless=True)
            pg = b.new_page()
            pg.goto(base + 'parent-login.php')
            pg.locator('[name=username]').fill('guardian'); pg.locator('[name=password]').fill('Test-parent-pass-123'); pg.locator('button').click()
            pg.wait_for_url('**/parent-dashboard.php')
            for width in [1280, 768, 320]:
                pg.set_viewport_size({'width': width, 'height': 900})
                assert pg.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), width
                Path('test-artifacts').mkdir(exist_ok=True)
                pg.screenshot(path=str(Path('test-artifacts') / f'parent-dashboard-{width}.png'), full_page=True)
            b.close()
        print('PASS: parent dashboard desktop/tablet/mobile widths', flush=True)
finally:
    try:
        if started:
            if __import__('sys').exc_info()[0]:
                logs = subprocess.run(['docker','logs','--tail','25',container],capture_output=True,text=True,encoding='utf-8'); print((logs.stdout+logs.stderr)[-5000:],flush=True)
            run(['docker', 'rm', '-f', container])
    finally:
        if created:
            assert re.fullmatch(r'srms_parent_test_[a-f0-9]{16}', database) and database != original['database']
            if granted: root(f'REVOKE ALL PRIVILEGES ON `{database}`.* FROM {principal};')
            root(f'DROP DATABASE `{database}`;')
        print('Removed isolated guardian test database/container; no SMS sent to a provider.', flush=True)
