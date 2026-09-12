"""Exercise actual Dean forms with uniquely identified fixtures and guaranteed cleanup."""
import subprocess, secrets, json, urllib.request, urllib.parse, urllib.error, re
tag='academic_http_'+secrets.token_hex(8)
sid=secrets.token_hex(16)
token=secrets.token_hex(32)
def php(code,*args):
    return subprocess.check_output(['docker','compose','exec','-T','--user','www-data','web','php','-r',code,*args],text=True)
def fixture(mode):
    return subprocess.check_output(['docker','compose','exec','-T','web','php','tests/academic-http-fixtures.php',mode,tag],text=True)
def request(page,data=None,session=sid):
    req=urllib.request.Request('http://localhost:5000/'+page,data=urllib.parse.urlencode(data,doseq=True).encode() if data is not None else None,headers={'Cookie':'PHPSESSID='+session})
    with urllib.request.urlopen(req,timeout=30) as response:
        body=response.read().decode();assert '</html>' in body.lower(),page+' incomplete response';return response.url,body
try:
    f=json.loads(fixture('setup'))
    php('ini_set("session.use_strict_mode","0");session_id($argv[1]);session_start();$_SESSION=["alogin"=>$argv[2],"csrf_token"=>$argv[3]];require "tests/session-fixture.php";test_dean_session();session_write_close();',sid,tag,token)
    assignment={'csrf_token':token,'action':'subject','year':f['year'],'teacher':f['teacher'],'class':f['class'],'subject':f['subject'],'term':''}
    url,body=request('dean-teacher-relationships.php',assignment)
    assert 'saved=1' in url,'Subject assignment did not save'
    creation={'csrf_token':token,'submit':'','firstname':'Fixture','middlename':'','lastname':tag,'staffnumber':tag,'username':tag+'_created','email':tag+'@example.invalid','phonenumber':'','department':'','role':'subject_teacher','password':'fixture-only-123','confirmpassword':'fixture-only-123','status':'1','academic_year':f['year'],'academic_term':'','subject_role':'1','assignment_class[]':[f['class']],'assignment_subject[]':[f['subject']],'class_role':'1','academic_class':f['class']}
    url,body=request('manage-teachers.php',creation)
    state=json.loads(fixture('state'))
    assert state['createdTeacher']==0,'Unconfirmed creation was not rolled back'
    assert 'currently assigned' in body,'Missing conflict message'
    creation['confirmed_assignments[]']=['subject:'+str(state['assignments'][0]['id'])]
    url,body=request('manage-teachers.php',creation)
    state=json.loads(fixture('state'))
    assert state['createdTeacher'] and 'created successfully' in body,'Teacher and assignments were not saved together'
    assert len(state['assignments'])==2 and state['assignments'][0]['Status']==0,'Creation did not preserve replacement history'
    print('PASS: Teacher creation rejects unconfirmed replacement, rolls back account, then saves both roles after confirmation')
    current=state['assignments'][-1]
    edit=dict(assignment,edit_kind='subject',edit_id=current['id'])
    url,body=request('dean-teacher-relationships.php',edit)
    assert 'saved=1' in url,'Edit did not save'
    state=json.loads(fixture('state'))
    assert len(state['assignments'])==3 and sum(a['Status'] for a in state['assignments'])==1,'Edit lost history or duplicated active assignment'
    print('PASS: Assignment editing replaces atomically and keeps history')
    registration={'csrf_token':token,'year':f['year'],'student':f['student'],'subjects[]':[f['subject']]}
    url,body=request('student-subjects.php',registration)
    assert 'saved=1' in url,'Registration did not save'
    url,body=request('dean-teacher-relationships.php?'+urllib.parse.urlencode({'year':f['year'],'teacher':f['teacher']}))
    assert tag+' ('+tag+')' in body,'Registered student not visible in assigned subject'
    print('PASS: Student subject form feeds teacher relationship student lists')
    try: request('student-subjects.php',dict(registration,csrf_token='invalid'));raise AssertionError('Invalid CSRF accepted')
    except urllib.error.HTTPError as e: assert e.code==403
    print('PASS: Invalid CSRF rejected')
    teacher_sid=secrets.token_hex(16)
    try:
        php('ini_set("session.use_strict_mode","0");session_id($argv[1]);session_start();$_SESSION=["teacher_user_id"=>(int)$argv[2],"teacher_session_version"=>1,"teacher_role"=>"subject_teacher"];require "includes/config.php";$dbh->prepare("UPDATE tblusers SET MustChangePassword=0 WHERE id=?")->execute([(int)$argv[2]]);session_write_close();',teacher_sid,str(f['teacher']))
        for page in ['dean-teacher-relationships.php','student-subjects.php']:
            url,body=request(page,session=teacher_sid);assert 'admin-login.php' in url,'Teacher reached Dean page'
        url,body=request('teacher-academic-assignments.php?teacher='+str(state['createdTeacher']),session=teacher_sid)
        assert 'My Teaching Assignments' in body and 'Fixture '+tag not in body,'Teacher ID override exposed another profile'
        print('PASS: Teacher cannot access Dean mutations or override assignment owner')
    finally: php('ini_set("session.use_strict_mode","0");session_id($argv[1]);session_start();require "tests/session-fixture.php";test_session_cleanup();session_destroy();',teacher_sid)
finally:
    print(fixture('cleanup'))
    php('ini_set("session.use_strict_mode","0");session_id($argv[1]);session_start();require "tests/session-fixture.php";test_session_cleanup();session_destroy();',sid)
