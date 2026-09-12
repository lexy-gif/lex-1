"""Lint changed PHP and smoke-test pages using short-lived local test sessions."""
from pathlib import Path
import subprocess, urllib.request, urllib.error, re, secrets

php_files=[str(p).replace('\\','/') for folder in ['.','includes','scripts','tests'] for p in Path(folder).glob('*.php')]
code='foreach(array_slice($argv,1) as $file) { exec("php -l ".escapeshellarg($file)." 2>&1", $output, $status); if($status) {echo implode("\\n",$output);exit(1);} $output=[]; }'
result=subprocess.run(['docker','compose','exec','-T','web','php','-r',code,*php_files],capture_output=True,text=True)
if result.returncode: raise RuntimeError(result.stdout+result.stderr)
print(f'PHP syntax valid: {len(php_files)} files')

def request(page, cookie=None):
    req=urllib.request.Request('http://localhost:5000/'+page,headers={'Cookie':'PHPSESSID='+cookie} if cookie else {})
    with urllib.request.urlopen(req, timeout=30) as response:
        body=response.read().decode()
        assert response.status==200 and not re.search(r'(Fatal error|Parse error|Uncaught PDOException)',body),page
        return response.url,body

sid='academiccheck'+secrets.token_hex(12)
# Session file lives in PHP's session directory, never under the web root.
code='ini_set("session.use_strict_mode","0");session_id($argv[1]); session_start(); $_SESSION["alogin"]="local-validation"; require "tests/session-fixture.php";test_dean_session();session_write_close();'
subprocess.run(['docker','compose','exec','-T','--user','www-data','web','php','-r',code,sid],check=True)
try:
    for page in ['dean-academics.php?area='+a for a in ['dashboard','structure','subjects','pathways','allocation','workload','assessments','coverage','interventions','analytics','reports','class','permissions','exams','timetable']] + ['dashboard.php','dean-academic-periods.php','manage-exams.php','manage-teachers.php','dean-teacher-relationships.php','student-subjects.php','manage-subjects.php','manage-subjectcombination.php','view-teacher.php?id=1','edit-teacher.php?id=1','teacher-assignments.php']:
        url,body=request(page,sid)
        assert 'admin-login.php' not in url,page
        assert '</html>' in body.lower(),page+' incomplete response'
        print('PASS render:',page)
    for page in ['dean-teacher-relationships.php','student-subjects.php','teacher-academic-assignments.php']:
        url,body=request(page)
        assert 'login.php' in url,page+' missing authentication'
        print('PASS authentication:',page)
finally:
    subprocess.run(['docker','compose','exec','-T','--user','www-data','web','php','-r','ini_set("session.use_strict_mode","0");session_id($argv[1]); session_start(); require "tests/session-fixture.php";test_session_cleanup();session_destroy();',sid],check=True)
