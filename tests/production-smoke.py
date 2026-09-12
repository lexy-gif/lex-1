"""Build and install the production image with a fresh, disposable MySQL 8.4 volume."""
import json
import os
from pathlib import Path
import re
import secrets
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

suffix = secrets.token_hex(8)
project = 'srms-release-check-' + suffix
assert re.fullmatch(r'srms-release-check-[a-f0-9]{16}', project)


def run(args, **kwargs):
    result = subprocess.run(args, capture_output=True, text=True, encoding='utf-8', timeout=600, **kwargs)
    if result.returncode:
        raise RuntimeError((result.stdout + result.stderr)[-4000:])
    return result.stdout.strip()


with tempfile.TemporaryDirectory(prefix='srms-release-check-') as directory:
    private = Path(directory).resolve()
    assert private.parent == Path(tempfile.gettempdir()).resolve()
    settings = private / 'test.env'
    values = dict(DB_NAME='srms_release_test', DB_USER='release_test', DB_PASS=secrets.token_hex(24),
                  MYSQL_ROOT_PASSWORD=secrets.token_hex(24), WEB_PORT='0', SRMS_RELEASE='check-' + suffix,
                  APP_URL='https://school.example.invalid', SCHOOL_NAME='Release Check',
                  AFRICASTALKING_SMS_ENABLED='false', MAIL_ENABLED='false', APP_TIMEZONE='Africa/Nairobi')
    settings.write_text(''.join(k+'='+v+'\n' for k,v in values.items()), encoding='utf-8')
    override = private / 'compose-test.json'
    override.write_text(json.dumps({'services': {
        'web': {'env_file': [str(settings)]},
        'db': {'command': ['--innodb-buffer-pool-size=67108864', '--mysqlx=OFF']}
    }}), encoding='utf-8')
    compose = ['docker','compose','-p',project,'--env-file',str(settings),'-f','docker-compose.production.yml','-f',str(override)]
    try:
        print('Building release image and starting an isolated MySQL 8.4 database...', flush=True)
        run(compose + ['up','-d','--build','web'])
        print('PASS: production image builds; MySQL 8.4 initializes schema and reaches healthy', flush=True)
        env = os.environ.copy()
        env.update(SETUP_DEAN_USERNAME='release_dean', SETUP_DEAN_PASSWORD='Release-' + secrets.token_hex(20))
        assert 'Senior School installed' in run(compose + ['exec','-T','-e','SETUP_DEAN_USERNAME','-e','SETUP_DEAN_PASSWORD','web','php','scripts/setup.php'], env=env)
        address = run(compose + ['port','web','80'])
        assert re.fullmatch(r'127\.0\.0\.1:\d+', address)
        base = 'http://' + address + '/'
        for page in ['health.php','parent-login.php','admin-login.php','teacher-login.php','js/jquery/jquery-3.7.1.min.js','js/bootstrap/bootstrap.min.js','js/DataTables/datatables.min.js','js/DataTables/datatables.min.css']:
            try:
                response=urllib.request.urlopen(base+page,timeout=30)
            except urllib.error.HTTPError as error:
                raise AssertionError(f'Production route {page} returned HTTP {error.code}') from error
            with response:
                assert response.status == 200
                if page.endswith('login.php'):
                    cookie = response.headers['Set-Cookie'].lower()
                    assert 'secure' in cookie and 'httponly' in cookie and 'samesite=lax' in cookie
        for page in ['.env','.git/config','srms.sql','includes/config.php','scripts/setup.php','tests/parent-results-fixtures.php','schema-review.tmp.txt','js/DataTables/jQuery-2.2.4/jquery-2.2.4.js']:
            try:
                urllib.request.urlopen(base+page,timeout=30)
            except urllib.error.HTTPError as error:
                assert error.code in (403,404), (page,error.code)
            else:
                raise AssertionError('Private path exposed: '+page)
        check_env=os.environ.copy();check_env['SRMS_TEST_URL']=base
        print(run([os.sys.executable,'-u','tests/anonymous-access.py'],env=check_env),flush=True)
        image_files = run(compose + ['exec','-T','web','php','-r', 'echo json_encode([is_dir("tests"),is_dir(".venv"),is_dir(".git"),file_exists(".env"),is_dir("js/DataTables/jQuery-2.2.4")]);'])
        assert json.loads(image_files) == [False]*5, image_files
        cid = run(compose + ['ps','-q','web'])
        for _ in range(40):
            health = run(['docker','inspect','--format','{{.State.Health.Status}}',cid])
            if health == 'healthy': break
            time.sleep(2)
        assert health == 'healthy', health
        print('PASS: fresh production setup, HTTP health, secure cookies, private-path protection and excluded development files', flush=True)
    finally:
        # This unique project was created here; its new volume contains synthetic data only.
        run(compose + ['down','--volumes','--remove-orphans'])
        subprocess.run(['docker','image','rm','srms-school:check-'+suffix], capture_output=True)
        print('Removed isolated release-check containers, network and test volume.', flush=True)
