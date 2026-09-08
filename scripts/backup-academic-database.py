"""Save a private pre-migration database dump outside the web root."""
from pathlib import Path
from datetime import datetime
import subprocess
backup=Path.home()/'.codex'/'backups'/'srms'
backup.mkdir(parents=True,exist_ok=True)
target=backup/('before-teacher-relationships-'+datetime.now().strftime('%Y%m%d-%H%M%S')+'.sql')
with target.open('wb') as output:
    subprocess.run(['docker','compose','exec','-T','db','sh','-c','MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump --set-gtid-purged=OFF --no-tablespaces --single-transaction -uroot "$MYSQL_DATABASE"'],stdout=output,check=True)
print('Database backup saved:',target)
