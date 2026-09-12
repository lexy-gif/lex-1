"""Every protected page must reject an anonymous GET and form POST before accessing records."""
from pathlib import Path
import os
import urllib.error
import urllib.request
import urllib.parse


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


public = {'index.php','admin-login.php','teacher-login.php','parent-login.php','student-senior.php',
          'find-result.php','result.php','notice-details.php','health.php'}
cli = {'password_hash.php','process-email-queue.php'}
opener = urllib.request.build_opener(NoRedirect())
base = os.environ.get('SRMS_TEST_URL','http://localhost:5000/').rstrip('/')+'/'
pages = sorted(p.name for p in Path('.').glob('*.php') if p.name not in public | cli)
for number,page in enumerate(pages,1):
    for data in [None,b'submit=1&action=save&csrf_token=invalid']:
        try:
            response = opener.open(urllib.request.Request(base+page,data=data),timeout=60)
        except urllib.error.HTTPError as error:
            response = error
        except TimeoutError as error:
            raise RuntimeError('Timed out checking '+page) from error
        assert response.code in (301,302,303,401,403,404,405), (page,response.code)
        for _ in range(5):
            if response.code not in (301,302,303): break
            destination = response.headers.get('Location','')
            if 'login.php' in destination or destination == 'index.php': break
            alias = urllib.parse.urlsplit(destination)
            assert not alias.scheme and not alias.netloc and alias.path in pages, (page,destination)
            response.close()
            try:
                response = opener.open(base+destination,timeout=60)
            except urllib.error.HTTPError as error:
                response = error
            assert response.code in (301,302,303,401,403,404,405), (page,destination,response.code)
        else:
            raise AssertionError('Redirect loop: '+page)
        response.close()
    if number%15==0:print(f'Checked {number}/{len(pages)} protected routes...',flush=True)
print(f'PASS: {len(pages)} protected routes reject anonymous GET and POST requests.')
