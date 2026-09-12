# Senior School Student Result Management System

SRMS supports Kenyan CBE **Grades 10, 11 and 12** using PHP, MySQL, Apache and the existing Bootstrap interface. Pathways, tracks, subject combinations, core-subject policy and performance descriptors are configurable through the Dean workspace.

## Roles and modules

- **Dean:** learners, teachers, guardians and child links, classes, periods, pathways, assignments, timetables, review/publication, grading configuration and audit/delivery history.
- **Subject teacher:** assigned class/subject mark sheets, CBE assessments, submission, personal timetable and notifications.
- **Class teacher:** assigned class records, attendance, result review, report comments and performance.
- **Parent/guardian:** linked children, published examinations and CBE assessments, academic history, printable reports, notifications, passwords and contact maintenance.

The guardian portal replaces public admission-number result lookup. Retained student subject-placement accounts do **not** grant parent-result access. Historical lower-grade records are retained, but new classes and admissions support Grades 10-12 only.

## Requirements

Docker Engine and Compose v2, Git and a browser. The application image uses PHP 8.4 with Apache/PDO MySQL. Standalone production Compose uses MySQL 8.4. Keep an existing development volume on its current database version until a backed-up migration/restore is verified; never attach a newer-version data directory to an older database image.

Python 3 runs backup and HTTP checks. Browser checks use Playwright and installed Microsoft Edge.

## Fresh local installation

From this repository:

```powershell
Copy-Item .env.example .env
```

Edit `.env` with different, strong database/root passwords and your school identity. Keep SMS/email disabled during testing. Then:

```powershell
docker compose up -d --build
```

On a **new empty MySQL volume**, `srms.sql` creates the complete schema without operational records, credentials or default accounts. Create the first Dean securely:

```powershell
$env:SETUP_DEAN_USERNAME = Read-Host 'Dean username'
$env:SETUP_DEAN_PASSWORD = [System.Net.NetworkCredential]::new('', (Read-Host 'Dean password (12-72 characters)' -AsSecureString)).Password
docker compose exec -T -e SETUP_DEAN_USERNAME -e SETUP_DEAN_PASSWORD web php scripts/setup.php
Remove-Item Env:SETUP_DEAN_PASSWORD
Remove-Item Env:SETUP_DEAN_USERNAME
```

Setup does not overwrite an existing Dean account. It runs repeatable migrations and creates Grades 10-12, an academic year/term and configurable pathway defaults. `SETUP_ACADEMIC_YEAR` can override the current year. No examination or result is automatically published. Review the curriculum defaults and configure school-approved percentage/performance bands in **Performance Scale** before publication.

- Application: [localhost:5000](http://localhost:5000)
- Dean: [admin-login.php](http://localhost:5000/admin-login.php)
- Guardians: [parent-login.php](http://localhost:5000/parent-login.php)
- Local database administration: [localhost:8081](http://localhost:8081)

## Existing installation

The continuation expects the earlier academic and Senior School migrations documented in [CBE workspace](docs/CBE_WORKSPACE.md) and [Senior School](docs/SENIOR_SCHOOL.md). Back up before applying the new migrations:

```powershell
python scripts/backup-academic-database.py
docker compose exec -T web php scripts/migrate-parent-results.php
docker compose exec -T web php scripts/migrate-senior-integrity.php
```

Backups are stored outside the web root at `%USERPROFILE%/.codex/backups/srms`. The migrations add guardian links, subject submissions, publication records, SMS outbox/history, login throttling, session versions and integrity constraints. They retain learner/results history and disable lower-grade catalogue options. They do not infer guardianship from shared phone numbers or turn student accounts into parent accounts.

Integrity checks stop on duplicate admissions/orphan references so valid school records are not deleted. MySQL DDL commits independently; interrupted migrations can be rerun. Rebuilding containers does not migrate a database. Do not import fresh `srms.sql` over a school database or remove its volume as a troubleshooting step.

## Guardian and result workflow

1. Admit the learner, configure their individual pathway/subjects, and assign active teachers for the relevant academic year.
2. In **Parents / Guardians**, create a guardian account, verify guardianship and link learners by admission number. Link siblings to one account. Select which contacts receive result SMS.
3. Create an examination with its maximum marks and entry dates. Subject teachers save and submit **Examination Marks** for registered learners.
4. The class teacher reviews complete submissions. The Dean approves, then publishes the specific class/examination. Publishing one class does not publish another.
5. Guardians sign in, replace their temporary password, then view published reports or print/save them as PDF.

A correction request before publication records a reason, unlocks submissions and notifies affected teachers. Published results remain locked. Repeated publication is idempotent. Old examinations require a reviewed publication record before parent access; the old exam status alone grants no access.

CBE assessments separately require submission, review/approval and publication. Their reports use recorded scores, performance levels and evidence. Guardian links are checked on every request; URL changes cannot grant access to another child. Revoked links/inactive accounts lose access immediately. Password resets invalidate existing guardian and teacher sessions.

## SMS and email

Africa's Talking remains the SMS provider. Configure `AFRICASTALKING_ENV` (`sandbox` or `live`), `AFRICASTALKING_USERNAME`, `AFRICASTALKING_API_KEY` and an approved sender ID when needed. Keys belong only in the private environment.

Publication atomically creates result-ready notifications and SMS jobs. Messages contain the portal link, without marks/rank. Unique event keys prevent duplicates from refreshes or repeated publication. The worker rechecks guardian access, contact and notification preference before sending.

After enabling SMS and testing with an authorized recipient:

```powershell
docker compose exec -T web php scripts/process-parent-sms.php
```

Schedule this every minute, or run `--loop` in a managed worker. **Parent SMS Deliveries** records recipient, attempts, status, provider reference and errors. `accepted` means provider acceptance, not handset-delivery confirmation. Confirmed failures may be requeued by the Dean. Interrupted/uncertain attempts are never automatically resent; reconcile them against provider records first.

`MAIL_*` configures the existing optional teacher email queue. Keep `MAIL_ENABLED=false` until SMTP is ready. Its CLI worker is `process-email-queue.php`; parent publication SMS uses its own outbox.

## Environment variables

| Setting | Purpose |
| --- | --- |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | Required DB connection; no source password fallback |
| `MYSQL_ROOT_PASSWORD` | DB initialization, administration and private backups |
| `APP_URL` | Public school URL used in parent SMS links |
| `APP_ENV` | Environment label |
| `SCHOOL_NAME`, `SCHOOL_ADDRESS` | Portal/report identity |
| `APP_TIMEZONE` | Defaults to Africa/Nairobi |
| `SESSION_COOKIE_SECURE` | HTTPS-only cookies; forced on by production Compose |
| `SESSION_IDLE_SECONDS` | Idle expiry, default 1800 seconds |
| `AFRICASTALKING_*` | Existing SMS provider settings |
| `MAIL_*` | Optional SMTP settings |

## Production installation

Use a dedicated host, private `.env`, HTTPS termination and a **new production database volume**. Set `APP_URL` to the HTTPS school URL. The standalone production stack uses an immutable application image, excludes development source mounts, and exposes neither MySQL nor phpMyAdmin ports.

```text
docker compose -p srms-production -f docker-compose.production.yml up -d --build web
docker compose -p srms-production -f docker-compose.production.yml exec -T -e SETUP_DEAN_USERNAME -e SETUP_DEAN_PASSWORD web php scripts/setup.php
```

Supply/remove setup environment variables securely as above. Put an HTTPS reverse proxy in front of loopback port 5000; production cookies require HTTPS in the browser. Restrict host access, configure encrypted off-host backups and rehearse restores. This repository does not provision a cloud host, domain or TLS certificate. `/health.php` reports DB reachability without credentials or diagnostic details.

After school and delivery setup, enable the managed SMS worker:

```text
docker compose -p srms-production -f docker-compose.production.yml --profile notifications up -d sms-worker
```

Use the same release image for web and worker. Before upgrades, back up and verify the reviewed image/migrations. Restore backups to a separate database for recovery validation; do not assume data directories are portable across database versions.

## Tests and deployment status

```powershell
python scripts/check-academic-pages.py
python -u tests/anonymous-access.py
python -u tests/parent-results-http.py
python -u tests/cbe-class-http.py
python -u tests/result-entry-http.py
python -u tests/cbe-workspace-http.py
python -u tests/timetable-http.py
python -u tests/academic-http.py
python -u tests/academic-period-http.py
python -u tests/senior-school-http.py
python -u tests/production-smoke.py
docker compose exec -T web php tests/academic-relationships.php
```

For browser checks:

```powershell
python -m venv .venv
.\.venv\Scripts\python.exe -m pip install -r requirements-dev.txt
.\.venv\Scripts\python.exe -u tests/parent-results-http.py --browser
```

The production smoke test builds a release image and installs it against a disposable MySQL 8.4 volume; it removes only its uniquely named test stack. The guardian suite installs a fresh schema in a disposable DB, uses synthetic accounts and a stub SMS transport, then removes its container/database. Other suites document temporary fixtures in their source. See [the continuation audit](docs/SENIOR_SYSTEM_AUDIT.md) for **actual outcomes and remaining deployment limitations**. A passing stub does not establish live SMS delivery or hosting readiness.

Further guides: [relationships](docs/RELATIONAL_TEACHER_MANAGEMENT.md), [timetables](docs/TIMETABLE_MODULE.md), [Senior School](docs/SENIOR_SCHOOL.md), [styles](docs/CSS_GUIDE.md).
