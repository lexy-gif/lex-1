"""Check legacy/CBE class forms using private fixtures, removed on completion."""
import html
import json
import re
import secrets
import subprocess
import urllib.error
import urllib.parse
import urllib.request

tag = 'cbe_class_' + secrets.token_hex(8)
token = secrets.token_hex(32)
dean, teacher = [secrets.token_hex(16) for _ in range(2)]


def php(code, *args):
    return subprocess.check_output(['docker', 'compose', 'exec', '-T', '--user', 'www-data', 'web', 'php', '-r', code, *args], text=True)


def fixture(mode):
    return json.loads(php(r'''
require 'includes/config.php';
require 'includes/cbe-academics.php';
$mode=$argv[1];$tag=$argv[2];
if(!preg_match('/^cbe_class_[a-f0-9]{16}$/D',$tag))throw new RuntimeException('Invalid fixture tag.');
$dbh->beginTransaction();
try {
    if($mode==='setup') {
        $stream=substr($tag,-5);
        if(academic_query($dbh,'SELECT id FROM tblclasses WHERE Section=?',[$stream])->fetchColumn())throw new RuntimeException('Fixture stream exists; rerun with a new tag.');
        $grades=cbe_rows($dbh,'SELECT id,GradeNumber FROM tblgrades WHERE GradeNumber IN (10,11,12) AND Status=1 ORDER BY GradeNumber');
        if(count($grades)!==3)throw new RuntimeException('Configure active Grades 10, 11 and 12 before running this test.');
        $level=$dbh->query('SELECT id FROM tblschoollevels WHERE Status=1 LIMIT 1')->fetchColumn();
        $number=1+(int)$dbh->query('SELECT MAX(GradeNumber) FROM tblgrades')->fetchColumn();
        $custom=[];
        foreach([1,0] as $i=>$status) {
            academic_query($dbh,'INSERT INTO tblgrades(SchoolLevelId,Name,GradeNumber,Status) VALUES(?,?,?,?)',[$level,$tag.'_grade_'.$i,$number+$i,$status]);
            $custom[]=(int)$dbh->lastInsertId();
        }
        academic_query($dbh,'INSERT INTO tblclasses(ClassName,ClassNameNumeric,Section) VALUES(?,?,?)',[$tag.'_legacy',$number,$stream]);$legacy=(int)$dbh->lastInsertId();
        academic_query($dbh,'INSERT INTO tblstudents(StudentName,RollId,ClassId,Status) VALUES(?,?,?,1)',[$tag,$tag,$legacy]);
        academic_query($dbh,'INSERT INTO tblclasses(ClassName,ClassNameNumeric,GradeId,Section) VALUES(?,?,?,?)',[$tag.'_inactive',$number+1,$custom[1],$stream]);$inactiveClass=(int)$dbh->lastInsertId();
        $result=compact('grades','custom','number','legacy','inactiveClass','stream');
    } elseif($mode==='state') {
        $result=['classes'=>cbe_rows($dbh,'SELECT id,ClassName,ClassNameNumeric,GradeId,Section FROM tblclasses WHERE LEFT(ClassName,CHAR_LENGTH(?))=? ORDER BY id',[$tag,$tag]),'studentClass'=>(int)academic_query($dbh,'SELECT ClassId FROM tblstudents WHERE RollId=?',[$tag])->fetchColumn()];
    } elseif($mode==='cleanup') {
        academic_query($dbh,'DELETE FROM tblstudents WHERE RollId=? AND StudentName=?',[$tag,$tag]);
        academic_query($dbh,'DELETE FROM tblclasses WHERE LEFT(ClassName,CHAR_LENGTH(?))=?',[$tag,$tag]);
        academic_query($dbh,'DELETE FROM tblgrades WHERE Name IN (?,?)',[$tag.'_grade_0',$tag.'_grade_1']);
        academic_query($dbh,'DELETE FROM tblauditlog WHERE Actor=?',[$tag]);
        $result='Class fixtures removed';
    } else throw new RuntimeException('Unknown fixture mode.');
    $dbh->commit();echo json_encode($result);
} catch(Throwable $e) {if($dbh->inTransaction())$dbh->rollBack();throw $e;}
''', mode, tag))


def request(page, data=None, session=dean):
    headers = {'Cookie': 'PHPSESSID=' + session} if session else {}
    data = urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None
    with urllib.request.urlopen(urllib.request.Request('http://localhost:5000/' + page, data=data, headers=headers), timeout=30) as response:
        body = response.read().decode()
        assert '</html>' in body.lower() and not re.search(r'Fatal error|Parse error|Uncaught|Warning:', body), page
        return response.url, body


def denied(page, data=None, status=403):
    try:
        request(page, data)
    except urllib.error.HTTPError as error:
        assert error.code == status, (page, error.code)
    else:
        raise AssertionError('Expected rejection: ' + page)


try:
    f = fixture('setup')
    php('session_id($argv[1]);session_start();$_SESSION=["alogin"=>$argv[2],"csrf_token"=>$argv[3]];session_write_close();', dean, tag, token)
    php('session_id($argv[1]);session_start();$_SESSION=["teacher_username"=>$argv[2],"csrf_token"=>$argv[3]];session_write_close();', teacher, tag, token)
    create = {'submit': '', 'csrf_token': token, 'classname': tag + '_new', 'GradeId': f['grades'][0]['id'], 'section': f['stream'], 'classnamenumeric': 1}
    legacy_page = 'edit-class.php?classid=' + str(f['legacy'])
    for page in ['create-class.php', legacy_page]:
        for session in [None, teacher]:
            url, _ = request(page, create, session)
            assert 'admin-login.php' in url
    denied('create-class.php', dict(create, csrf_token='invalid'))
    denied(legacy_page, dict(create, update='', csrf_token='invalid'))
    denied('edit-class.php?classid=0', status=404)
    _, body = request('create-class.php')
    for grade in f['grades']:
        assert f'>{"Grade " + str(grade["GradeNumber"])}</option>' in body
    assert tag + '_grade_0' in body and tag + '_grade_1' not in body
    for grade in f['grades']:
        _, body = request('create-class.php', dict(create, GradeId=grade['id'], classname=tag + '_' + str(grade['GradeNumber'])))
        assert 'Class Created successfully' in body
    state = fixture('state')
    for grade in f['grades']:
        row = next(r for r in state['classes'] if r['ClassName'] == tag + '_' + str(grade['GradeNumber']))
        assert row['GradeId'] == grade['id'] and row['ClassNameNumeric'] == grade['GradeNumber']
    print('PASS: Grades 10-12 use catalogue links and numbers; class forms enforce Dean access and CSRF', flush=True)

    for value in [0, 'invalid', '999999999', str(f['custom'][0]) + '.5', f['custom'][1]]:
        _, body = request('create-class.php', dict(create, GradeId=value))
        assert 'Select an active configured grade.' in body
    malformed = dict(create)
    del malformed['GradeId']
    malformed['GradeId[]'] = [f['custom'][0]]
    _, body = request('create-class.php', malformed)
    assert 'Select an active configured grade.' in body
    _, body = request('create-class.php', dict(create, section='TOOLONG'))
    assert 'at most 5 characters' in body
    for grade_id in [f['grades'][0]['id'], f['custom'][0]]:
        _, body = request('create-class.php', dict(create, GradeId=grade_id))
        assert 'This grade and stream already exist.' in body
    workspace = 'dean-academics.php?area=structure'
    _, body = request(workspace, {'action': 'class', 'csrf_token': token, 'GradeId': f['grades'][0]['id'], 'Section': f['stream']})
    assert 'This grade and stream already exist.' in body
    assert fixture('state') == state
    print('PASS: both routes reject duplicates, unmapped legacy duplicates and invalid/inactive grades without writes', flush=True)

    row = next(r for r in state['classes'] if r['ClassName'] == tag + '_10')
    edit_page = 'edit-class.php?classid=' + str(row['id'])
    edit = dict(create, update='', classname=tag + '_draft & "name"', GradeId=f['grades'][1]['id'])
    _, body = request(edit_page, edit)
    assert 'This grade and stream already exist.' in body and html.escape(edit['classname'], quote=True) in body
    assert re.search(r'<option value="' + str(edit['GradeId']) + r'"\s+selected', body)
    assert fixture('state') == state
    # Moving this fixture to the custom grade, in a separate stream, retains its ID.
    _, body = request(edit_page, dict(edit, GradeId=f['custom'][0], section='MOVE'))
    assert 'Data has been updated successfully' in body
    moved = next(r for r in fixture('state')['classes'] if r['id'] == row['id'])
    assert moved['GradeId'] == f['custom'][0] and moved['ClassNameNumeric'] == f['number']
    _, body = request(legacy_page)
    assert re.search(r'<option value="' + str(f['custom'][0]) + r'"\s+selected', body)
    assert next(r for r in fixture('state')['classes'] if r['id'] == f['legacy'])['GradeId'] is None
    _, body = request(legacy_page, dict(create, update='', classname=tag + '_legacy', GradeId=f['custom'][0]))
    assert 'Data has been updated successfully' in body
    _, body = request(workspace, {'action': 'class', 'csrf_token': token, 'id': f['legacy'], 'GradeId': f['custom'][0], 'Section': 'EDIT'})
    assert 'Academic changes saved.' in body
    state = fixture('state')
    legacy = next(r for r in state['classes'] if r['id'] == f['legacy'])
    assert legacy['GradeId'] == f['custom'][0] and legacy['ClassName'] == tag + '_legacy'
    assert state['studentClass'] == f['legacy']
    _, body = request('edit-class.php?classid=' + str(f['inactiveClass']))
    assert re.search(r'<option value="' + str(f['custom'][1]) + r'"\s+selected\s+disabled', body)
    print('PASS: custom grades work; rejected edits retain input; legacy mapping preserves class names, IDs and student links', flush=True)
finally:
    try:
        print(fixture('cleanup'), flush=True)
    finally:
        for session in [dean, teacher]:
            php('session_id($argv[1]);session_start();session_destroy();', session)
