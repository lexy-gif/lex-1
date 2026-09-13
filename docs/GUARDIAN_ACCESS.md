# Guardian access and student account retirement

Public entry: `/parent-login.php`. Staff entry: `/staff/login` (Apache redirects to `/staff/login/`). The staff directory uses `index.php`, so no rewrite module or new routing framework is required. Existing `/teacher-login.php` and `/admin-login.php` bookmarks remain valid. Public and guardian pages do not link to either staff login.

## Relationships and migration

The inspected schema stores academic identity in `tblstudents.StudentId`. Results, attendance, subject registrations, enrollments, pathways, assessments and teacher comments refer directly to that ID. `tblusers.StudentId` is a legacy account-to-learner pointer. Guardian access uses the explicit many-to-many `tblparentstudents(ParentId,StudentId)` relationship.

`tblusers` also has historical foreign-key references from notifications, deliveries, preferences, reminders and staff records. The migration retains old student user rows as disabled archives, so none of these references are deleted or reassigned. Legacy delivery content is not copied into guardian accounts: those records have no guardian publication/visibility guarantee. New examination and assessment publication events use the guardian outbox. Pathway-change notifications formerly sent to student accounts now create a deduplicated guardian dashboard update with a learner-profile link and no internal staff notes. Internal teacher notifications remain assigned to staff.

Back up the existing database, then run:

```powershell
python scripts/backup-academic-database.py
docker compose exec -T web php scripts/migrate-guardian-access.php
```

Requires the existing parent-results migration. New installations run it through `scripts/setup.php`; `srms.sql` includes the account constraint. Do not import the fresh schema over an existing database.

The migration is repeatable and serialized by a database lock. It copies only existing explicit legacy parent links, preserves revoked links, skips pending/retrying student deliveries, sets student accounts inactive and increments their session versions. `chk_student_accounts_disabled` prevents creating or reactivating an active student account. MySQL DDL commits separately; interrupted runs can be repeated. Neither student credentials nor phone-number matches establish guardianship.

The common PHP bootstrap removes student session identities and rotates their session IDs on the next request, including requests to public pages. The old student portal returns HTTP 410 and never checks a password. Unknown student registration/recovery/dashboard routes remain unavailable. The Dean-only `student-subjects.php` manages learner records and stays available to the Dean.

## Guardian visibility

Every student page authenticates the active guardian account and checks its current explicit link. A missing/revoked link returns the same HTTP 404 as an unknown learner. Changing URLs, exam IDs, years or notification IDs cannot grant another child's access.

The selector lists only linked active Senior School learners. Switching students clears report and assessment selection. Subject profiles retain the previous student portal's year, pathway, track, combination and subject information, using historical enrollment for earlier years. Attendance includes dates, classes and recorded statuses; internal remarks have no publication flag and stay private. Timetables require published rows, the selected year/term's learner class, registered subjects and any matching pathway. Draft, approved, updated, cancelled and archived timetable rows are hidden. Reports require class-specific publication; assessments and teacher feedback require published status. Report forms use the existing browser Print / Save PDF workflow.

## Notifications and configuration

Examination and assessment publication creates guardian dashboard notifications and SMS jobs in the same database transaction. Unique guardian/student/event keys and SMS notification keys prevent duplicate publication jobs. SMS asks the guardian to log in; it includes no marks or ranking. Missing/invalid contacts are recorded as skipped. Opted-out guardians still receive dashboard notifications. Workers recheck active links, notification preference and the contact before sending.

Configure `APP_URL` and `AFRICASTALKING_*` in the private environment and schedule `php scripts/process-parent-sms.php` every minute (or use its `--loop` mode). The Dean's Parent SMS Deliveries page shows pending, accepted, failed, uncertain and skipped outcomes; confirmed failures can be explicitly retried. Provider acceptance is not delivery confirmation. Reconcile uncertain/interrupted attempts with the provider before any manual resend.

The production Compose file already defines `sms-worker` under the `notifications` profile. After configuring SMS, enable that worker with `docker compose -f docker-compose.production.yml --profile notifications up -d sms-worker`. For local development, run the CLI worker explicitly; the default development Compose stack does not schedule it.

The school must create guardian accounts, verify each child link and contact, and configure active academic periods and published timetables. No guardian accounts or links are inferred by this change.

## Regression verification

```powershell
python tests/parent-results-http.py --browser
python tests/anonymous-access.py
python tests/senior-school-http.py
```

The parent suite creates a disposable database/container with provider SMS/email disabled. It checks fresh setup and upgrade idempotence, record/reference preservation, retired sessions/routes, public navigation, staff role boundaries and guardian GET/POST rejection on staff endpoints, sibling selection and IDOR, subject history, attendance, timetable publication/registration/pathway scope, full marks submission/review/approval/publication, report/feedback visibility, notification isolation, concurrent SMS claims, deduplication, opt-out/missing/changed contacts and failed/uncertain delivery handling. Browser mode checks desktop/tablet/mobile layouts, selector navigation and generates `test-artifacts/guardian-report.pdf` through the browser print engine. No provider messages are sent.
