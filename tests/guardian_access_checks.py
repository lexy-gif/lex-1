"""Guardian access checks using the disposable parent-results HTTP harness."""
import http.cookiejar
import re
import secrets
import urllib.error
import urllib.request
from pathlib import Path


def verify_access(ctx):
    f, request, query, php, root, run, login, browser = (ctx[k] for k in ['f', 'request', 'query', 'php', 'root', 'run', 'login', 'browser'])
    parent, other_parent, public, teacher, class_teacher, dean = (ctx[k] for k in ['parent', 'other_parent', 'public', 'teacher', 'class_teacher', 'dean'])
    for client in [public, parent, teacher, dean]:
        for page in ['index.php', 'notice-details.php?nid=' + str(f['notice'])]:
            body = request(client, page)[1]
            assert 'Parent/Guardian Login' in body
            assert not re.search(r'href=["\'][^"\']*(?:teacher-login|admin-login|staff/login|student-senior)', body)
    staff = request(public, 'staff/login')[1]
    assert 'Teacher Login' in staff and 'Dean of Studies Login' in staff and 'Class Teacher Login' not in staff
    assert 'teacher-academic-assignments.php' in login(browser(), 'teacher-login.php', 'test_subject', 'Test-teacher-pass-123')[0]
    assert 'teacher-dashboard.php' in login(browser(), 'teacher-login.php', 'test_class', 'Test-teacher-pass-123')[0]
    root(f"UPDATE tblusers SET Role='class_teacher' WHERE id={f['teachers'][0]};", ctx['database'])
    assert 'teacher-dashboard.php' in login(browser(), 'teacher-login.php', 'test_class', 'Test-teacher-pass-123')[0]
    assert 'teacher-login.php' in request(teacher, 'teacher-dashboard.php')[0]
    for page, username, password in [('teacher-login.php', 'guardian', 'Test-parent-pass-123'), ('admin-login.php', 'guardian', 'Test-parent-pass-123'), ('admin-login.php', 'test_subject', 'Test-teacher-pass-123')]:
        assert page in login(browser(), page, username, password)[0]

    # Every root staff endpoint must reject guardians, including POSTs and AJAX endpoints.
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, req, fp, code, msg, headers, newurl):
            return None
    jar = next(h.cookiejar for h in parent.handlers if isinstance(h, urllib.request.HTTPCookieProcessor))
    direct = urllib.request.build_opener(NoRedirect(), urllib.request.HTTPCookieProcessor(jar))
    public_routes = {'index.php', 'notice-details.php', 'health.php', 'admin-login.php', 'teacher-login.php', 'student-senior.php', 'result.php', 'find-result.php'}
    staff_routes = [p.name for p in Path('.').glob('*.php') if p.name not in public_routes and not p.name.startswith('parent-')]
    for page in staff_routes:
        for data in [None, b'action=save&submit=1&csrf_token=invalid']:
            try:
                response = direct.open(urllib.request.Request(ctx['base'] + page, data=data), timeout=30)
            except urllib.error.HTTPError as e:
                response = e
            assert response.code in (301, 302, 303, 401, 403, 404, 405), (page, response.code)
            response.close()

    for page in ['parent-dashboard.php', 'parent-child.php', 'parent-attendance.php', 'parent-timetable.php', 'parent-assessments.php', 'parent-report.php']:
        body = request(parent, page + '?student=' + str(f['students'][0]))[1]
        assert 'id="portal-student"' in body and 'Alpha Child' in body and 'Beta Child' in body and 'Other Child' not in body
        assert not re.search(r'href=["\'][^"\']*(?:teacher-login|admin-login|staff/login|student-senior)', body)
        request(other_parent, page + '?student=' + str(f['students'][0]), expected=404)
        request(parent, page + '?student=' + str(f['students'][2]), expected=404)
    assert 'Biology' in request(parent, f'parent-child.php?student={f["students"][0]}&year={f["previous_year"]}')[1]
    body = request(parent, 'parent-attendance.php?student=' + str(f['students'][0]))[1]
    assert '2026-09-14' in body and '>Present<' in body and '>Absent<' not in body and 'INTERNAL' not in body
    body = request(parent, 'parent-timetable.php?student=' + str(f['students'][0]))[1]
    assert '08:00' in body and 'Biology' in body and 'Publication Test' in body and 'INTERNAL' not in body
    assert all(time not in body for time in ['09:00', '10:00', '11:00', '12:00'])
    # Registered subjects and pathway restrictions both apply to published timetables.
    root(f"UPDATE tblstudentsubjects SET Status=0 WHERE StudentId={f['students'][1]} AND SubjectId={f['subjects'][0]};", ctx['database'])
    assert '08:00' not in request(parent, 'parent-timetable.php?student=' + str(f['students'][1]))[1]
    root(f"UPDATE tblstudentsubjects SET Status=1 WHERE StudentId={f['students'][1]} AND SubjectId={f['subjects'][0]};", ctx['database'])
    pathway = query('SELECT id FROM tblpathways ORDER BY id LIMIT 1')[0]['id']
    root(f"UPDATE tblclasstimetableentries SET PathwayId={pathway} WHERE Status='published';UPDATE tblexamtimetableentries SET PathwayId={pathway} WHERE Status='published';", ctx['database'])
    assert '08:00' not in request(parent, 'parent-timetable.php?student=' + str(f['students'][0]))[1]
    root("UPDATE tblclasstimetableentries SET PathwayId=NULL;UPDATE tblexamtimetableentries SET PathwayId=NULL;", ctx['database'])
    request(parent, f'parent-timetable.php?student={f["students"][0]}&year={f["year"]}&term=999999', expected=404)
    assert 'Guardian school announcement' in request(parent, 'parent-dashboard.php')[1]

    # Simulate an upgrade with real old account/session/notification relationships.
    # Constraint removal is confined to this randomly named disposable database.
    account = f['student_account']
    root(f"ALTER TABLE tblusers DROP CHECK chk_student_accounts_disabled;UPDATE tblusers SET Status=1 WHERE id={account};DELETE FROM tblschemamigrations WHERE Name='guardian-access-v1';", ctx['database'])
    root(f"INSERT INTO tblteachernotifications(TeacherId,Title,Message) VALUES({account},'Historical notice','Retain this history');INSERT INTO tblnotificationdeliveries(UserId,Channel,Destination) VALUES({account},'EMAIL','student@example.invalid');", ctx['database'])
    before = {table: query('SELECT * FROM ' + table) for table in ['tblstudents', 'tblattendance', 'tblresult', 'tblstudentsubjects', 'tblstudentenrollments', 'tblparentstudents', 'tblteachernotifications']}
    old_account = query(f'SELECT * FROM tblusers WHERE id={account}')[0]
    legacy_sid = secrets.token_hex(20)
    php('session_id($argv[1]);session_start();$_SESSION=["student_user_id"=>(int)$argv[2],"student_session_version"=>1];session_write_close();', legacy_sid, account)
    command = ['docker', 'exec', ctx['container'], 'php', 'scripts/migrate-guardian-access.php']
    assert 'Student accounts disabled' in run(command)
    assert 'already applied' in run(command)
    assert all(query('SELECT * FROM ' + table) == rows for table, rows in before.items())
    new_account = query(f'SELECT * FROM tblusers WHERE id={account}')[0]
    assert new_account['Status'] == 0 and new_account['SessionVersion'] == old_account['SessionVersion'] + 1
    assert all(new_account[k] == old_account[k] for k in ['StudentId', 'PasswordHash', 'Username', 'Role'])
    assert query(f'SELECT Status FROM tblnotificationdeliveries WHERE UserId={account}') == [{'Status': 'SKIPPED'}]
    assert php('require "includes/config.php";try{$dbh->exec("UPDATE tblusers SET Status=1 WHERE Role=\'student\'");echo "FAIL";}catch(PDOException $e){echo "blocked";}') == 'blocked'
    legacy = browser(); legacy.addheaders = [('Cookie', 'PHPSESSID=' + legacy_sid)]
    request(legacy, 'index.php')
    assert php('echo is_file(session_save_path()."/sess_".$argv[1])?"retained":"invalidated";', legacy_sid) == 'invalidated'
    assert 'parent-login.php' in request(legacy, 'parent-dashboard.php')[0]
    expired_sid = secrets.token_hex(20)
    php('session_id($argv[1]);session_start();$_SESSION=["alogin"=>"no-longer-a-dean","dean_password_version"=>"expired"];session_write_close();', expired_sid)
    expired = browser(); expired.addheaders = [('Cookie', 'PHPSESSID=' + expired_sid)]
    assert 'Parent/Guardian Login' in request(expired, 'index.php')[1]
    for page in ['student-login.php', 'student-register.php', 'student-forgot-password.php', 'student-dashboard.php']:
        request(public, page, expected=404)
    for data in [None, {'username': 'student_account', 'password': 'Test-student-pass-123', 'login': 1}]:
        body = request(legacy, 'student-senior.php', data, expected=410)[1]
        assert 'Parent/Guardian Login' in body and '<form' not in body
    for action in ['create_account', 'update_status', 'reset_password']:
        request(class_teacher, 'teacher-accounts.php', {action: 1, 'studentid': f['students'][0], 'status': 1}, expected=410)
    assert 'Manage Accounts' not in request(class_teacher, 'teacher-dashboard.php')[1]
    print(f'PASS: parent-only public navigation, direct staff login, role separation, {len(staff_routes)} staff endpoints reject guardian GET/POST; child selector, attendance, subject history, published timetable scope; repeatable account retirement and old session invalidation', flush=True)


def verify_notifications(ctx):
    f, query, php, root, request, post = (ctx[k] for k in ['f', 'query', 'php', 'root', 'request', 'post'])
    sid, pid = f['students'][1], f['parents'][0]
    notify = 'require "includes/config.php";require "includes/result-workflow.php";$dbh->beginTransaction();parent_publication_notify($dbh,(int)$argv[1],$argv[2],"Contact handling test","parent-dashboard.php");$dbh->commit();'
    root(f'UPDATE tblusers SET ParentPhone=NULL WHERE id={pid};', ctx['database'])
    php(notify, sid, 'test:missing-contact')
    rows = query("SELECT d.Status,d.ErrorMessage FROM tblparentsms d JOIN tblparentnotifications n ON n.id=d.NotificationId WHERE n.EventKey='test:missing-contact'")
    assert len(rows) == 1 and rows[0]['Status'] == 'skipped' and 'contact' in rows[0]['ErrorMessage']
    php(notify, sid, 'test:missing-contact')
    assert len(query("SELECT id FROM tblparentnotifications WHERE EventKey='test:missing-contact'")) == 1
    root(f'UPDATE tblparentstudents SET NotifyResults=0 WHERE ParentId={pid} AND StudentId={sid};', ctx['database'])
    php(notify, sid, 'test:opt-out')
    assert len(query("SELECT id FROM tblparentnotifications WHERE EventKey='test:opt-out'")) == 1
    assert not query("SELECT d.id FROM tblparentsms d JOIN tblparentnotifications n ON n.id=d.NotificationId WHERE n.EventKey='test:opt-out'")
    root(f"UPDATE tblusers SET ParentPhone='+254700000000' WHERE id={pid};UPDATE tblparentstudents SET NotifyResults=1 WHERE ParentId={pid} AND StudentId={sid};", ctx['database'])
    php(notify, sid, 'test:contact-change')
    root(f"UPDATE tblusers SET ParentPhone='+254700000001' WHERE id={pid};", ctx['database'])
    assert php('require "includes/config.php";require "includes/parent-sms-queue.php";echo parent_sms_process($dbh,25,function(){throw new Exception("Must not send to changed contact");});') == '0'
    assert query("SELECT d.Status,d.Attempts FROM tblparentsms d JOIN tblparentnotifications n ON n.id=d.NotificationId WHERE n.EventKey='test:contact-change'") == [{'Status': 'skipped', 'Attempts': 0}]
    other_notification = query(f'SELECT id,ReadAt FROM tblparentnotifications WHERE ParentId={f["parents"][1]} ORDER BY id LIMIT 1')[0]
    post(ctx['parent'], 'parent-notifications.php', {'id': other_notification['id']})
    assert query('SELECT id,ReadAt FROM tblparentnotifications WHERE id=' + str(other_notification['id'])) == [other_notification]
    # The former student pathway channel now targets guardians and deduplicates by allocation.
    pathway = query('SELECT id FROM tblpathways ORDER BY id LIMIT 1')[0]['id']
    allocation = php('require "includes/config.php";$dbh->prepare("INSERT INTO tblstudentpathways(StudentId,ClassId,AcademicYearId,PathwayId,PlacementSource) VALUES(?,?,?,?,?)")->execute([$argv[1],$argv[2],$argv[3],$argv[4],"test"]);echo $dbh->lastInsertId();', sid, f['classes'][0], f['year'], pathway)
    pathway_notice = 'require "includes/config.php";require "includes/senior-school.php";$dbh->beginTransaction();senior_notify_pathway($dbh,(int)$argv[1],(int)$argv[2],"INTERNAL staff placement detail",[]);$dbh->commit();'
    php(pathway_notice, sid, allocation); php(pathway_notice, sid, allocation)
    notices = query("SELECT ParentId,Message,ActionUrl FROM tblparentnotifications WHERE EventKey='pathway:" + allocation + "'")
    assert len(notices) == 1 and notices[0]['ParentId'] == pid and 'INTERNAL' not in notices[0]['Message']
    assert 'parent-child.php' in notices[0]['ActionUrl']
    assert 'Subjects and placement for Beta Child' in request(ctx['parent'], 'parent-notifications.php')[1]
    print('PASS: missing contacts, SMS opt-out, changed contact cancellation, duplicate events, notification isolation and pathway updates redirected to guardians', flush=True)
