# Senior School audit and deployment report

Continuation of the complete-system audit prompt, 12 September 2026. The original checkpoint was commit `26fea3a`: configurable pathways, academic relationships and timetables existed, but guardian result access and controlled publication did not.

## 1. System status

**NOT READY FOR DEPLOYMENT to a live school environment** until the school supplies its HTTPS/provider configuration and verifies delivery to an authorized recipient. Repository implementation, application regression checks and an isolated production installation are complete and passing. Ready for local review/staging preparation at `http://localhost:5000`; no observed failing application check remains. Live SMS/SMTP, the real HTTPS endpoint and off-host restoration have not been verified.

## 2. Problems found

- Admission-number/class lookup exposed examination results without authentication.
- Draft result entry sent marks and rank by SMS before review or publication.
- Publication lacked validated transitions and changed the status of a shared examination for every class.
- Guardian accounts, explicit child links, notifications and report access did not exist.
- Grade configuration accepted lower grades; an obsolete SQL script converted Grade 10 to Grade 9.
- Schema seeds were incomplete and contained obsolete operational/demo data.
- Missing admission uniqueness and historical foreign keys allowed inconsistent records.
- Legacy empty-session comparisons were unsafe under PHP 8, and leading whitespace broke session headers on older routes. These checks are now explicit and covered by anonymous-route tests.
- Sessions did not reliably expire or end after account/password changes; legacy state-changing GET actions lacked CSRF protection.
- Source/configuration files were exposed under Apache, and database settings had credential fallbacks.
- The DataTables bundle loaded duplicate, outdated jQuery/Bootstrap and unused export libraries.
- Legacy management lists loaded unbounded records and result review treated saved marks as submissions.

## 3. Fixes completed

The existing PHP/PDO/page architecture is retained. Implemented guardian administration, account setup/reset, learner profiles, examination/assessment reports, publication controls, outbox processing, delivery history and shared responsive portal components. Hardened old result routes and forms so the same authorization, registration, maximum-score and locking rules apply. Added server-side pagination/search to the main management lists, database-backed dashboard corrections and linked guardian contacts for authorized class teachers.

Updated runtime to PHP 8.4. Added secure bootstrap/error handling, private environment configuration, production Apache/PHP settings, a health endpoint and standalone production Compose without source bind mounts, public MySQL or phpMyAdmin. Bootstrap-compatible assets now load jQuery 3.7.1, Bootstrap 3.4.1 and DataTables 1.13.11 core/integration without duplicate jQuery, Flash, JSZip or pdfmake bundles. Old bundled asset directories are excluded from the image and denied by Apache. The obsolete jQuery `.size()` call was corrected.

## 4. Database changes

- `tblparentstudents`: many-to-many, active/revocable guardian links and result-SMS preferences.
- `tblresultsubmissions`: unique class/subject/examination submission and submitting teacher.
- `tblresultpublications`: unique class/examination publication, actor/time and grading snapshot.
- `tblparentnotifications`: unique guardian/child/event notifications with read state.
- `tblparentsms`: publication outbox, recipient, status, attempts, provider reference/response and errors.
- `tblloginattempts`: expiring login throttling records.
- `tblusers.SessionVersion` and temporary-password enforcement; `tblexams.MaximumMarks`; decimal examination marks.
- Unique learner admission numbers, restrictive learner/class/result foreign keys, wider Dean password hashes and unique Dean usernames.

`migrate-parent-results.php` and `migrate-senior-integrity.php` are repeatable additive migrations. Existing explicit parent-role StudentId links can be retained; shared telephone numbers never establish guardianship. Migrations disable lower-grade catalogue choices while retaining historical records. They stop on duplicate admissions/orphans instead of deleting school data. The new `srms.sql` contains the complete 70-table schema without accounts, passwords or student/result rows. It is for a fresh database only.

A private backup was created before changes outside the web root. Existing-database integrity checks found zero duplicate admissions and zero orphan learner-class/result-student/result-class/result-subject references. The new migrations were applied successfully to the local database.

## 5. Senior School scope

New configuration, classes, admissions, teacher assignments and result workflows support **Grades 10, 11 and 12 only**. Lower-grade creation is rejected on the server. Promotion supports 10 to 11 and 11 to 12 while retaining prior-year enrollments, registrations, allocations and marks.

STEM, Social Sciences and Arts & Sports pathways, tracks, available subjects, core/elective policy and individual combinations use relational configuration. Learners do not inherit a mandatory identical subject list. School-approved performance bands are configured by the Dean; the application does not invent a national grading standard.

## 6. Result module

Subject teacher saves marks -> validates roster/maximum -> submits and locks -> class teacher reviews -> Dean approves -> Dean publishes the particular class/examination -> guardian notifications/SMS are queued -> linked guardians see the published report.

Incomplete/invalid sheets cannot progress. Corrections before publication require an authorized request and reason, unlock submissions and notify affected teachers. Publication is idempotent and leaves other classes unpublished. Published results/comments are immutable. Independent CBE assessments also require complete results, submission, approval and publication. Report performance bands are snapshotted at examination publication.

## 7. Parent portal

The Dean verifies guardianship and links learners by exact admission number. One guardian can have siblings; one child can have multiple authorized guardians. Every profile, history, report and notification request rechecks active account/link/child scope. Changing a URL or student ID grants no additional access. Revocation and account deactivation take effect on the next request. Student placement accounts cannot authenticate as guardians.

The portal provides learner academic profiles, recent published exams, published CBE evidence/performance, report history, submitted teacher comments, school notices, unread/read notifications, contact maintenance and password change. Reports have school identity and print/save-as-PDF styling. Temporary passwords must be replaced before opening results.

## 8. SMS

Africa's Talking is preserved. Publication creates notifications and SMS jobs inside the academic transaction. Unique event keys and notification IDs suppress refresh/repeated-publication duplicates. SMS contains a result-ready message and configured portal link, without marks or rank. Per-link notification preference controls eligible contacts; Kenyan mobile numbers normalize to `+254...`.

Workers claim pending jobs with row locking before provider I/O and recheck account/link/contact validity. Confirmed failures require an explicit Dean retry. Processing/uncertain outcomes are not automatically resent, avoiding duplicate sends after a lost response. `accepted` records provider acceptance, not confirmed handset delivery. Tests use a transport stub and never send to school contacts.

## 9. Security

Prepared statements, escaped output, transaction rollback, role/assignment checks, guardian ownership checks and CSRF protect the new and updated workflows. Staff/guardian logout uses POST with CSRF. Login clears previous roles and regenerates the session. Cookies are HttpOnly/SameSite, secure in production, with idle and absolute expiry. Inactive teachers/guardians and changed session/password versions lose access. Dean password changes invalidate other sessions across old and new routes. Retained student placement login also clears other roles, throttles attempts and checks account session versions after resets. Password setup/reset uses `password_hash`/`password_verify`, mandatory replacement and length validation.

Production errors are logged with a generic HTTP response. Apache denies dotfiles, SQL/configuration, docs, tests and CLI/helper paths. The image excludes private environment files, Git, test tools and obsolete bundles. CSV exports neutralize formula prefixes. No upload endpoint or cross-origin API was introduced.

Dependency references: [jQuery security fixes](https://blog.jquery.com/2020/04/10/jquery-3-5-0-released/), [Bootstrap XSS patch](https://blog.getbootstrap.com/2019/02/13/bootstrap-4-3-1-and-3-4-1/). Bootstrap 3 remains [end of life](https://getbootstrap.com/docs/3.4/); the compatibility update is not a claim of current upstream support.

## 10. Test results

| Check | Executed outcome |
| --- | --- |
| Fresh schema/setup and repeated setup | Passed in disposable guardian database |
| Guardian authentication, siblings, IDOR, CSRF, student-role rejection | Passed |
| Dean creates teacher/learner/guardian and links child; password setup/reset | Passed |
| Marks maximum/decimals; submit/review/approve/publish; draft isolation; immutable publication | Passed |
| CBE assessment lifecycle and guardian publication access | Passed |
| SMS and notification handling | Passed: simultaneous claims, idempotence, failure/uncertain outcomes, explicit retry, phone normalization and revoked links; transport stub only |
| Guardian desktop/tablet/mobile dashboard | Passed with updated assets; mobile screenshot inspected |
| Grade 10/11/12 forms and lower-grade rejection | Passed |
| Timetable clashes, dates, invigilator scope, atomic publication and simultaneous requests | Passed again with real temporary Dean identities and complete fixture cleanup |
| PHP lint and academic management route rendering | 146 PHP files passed; 26 pages rendered with zero new PHP diagnostics |
| Anonymous access to protected routes | 74 routes reject both GET and POST, including legacy aliases and notification count |
| Dean password changes and staff logout | Passed: other sessions invalidated and logout enforces POST/CSRF |
| Historical grading and published comments | Passed: changed bands do not rewrite published grading; published comments cannot be edited |
| Legacy result-entry regression | Passed: configured bounds, subject identity/registration, retained input, mixed-batch rollback, historical display and concurrent submissions |
| Curriculum coverage and academic workspace | Passed: percentage bounds, teacher scope, evidence, reports/CSV, locks and every workspace area |
| Teacher creation, replacement and student subject forms | Passed with real forms, confirmation/rollback and role restrictions |
| Academic relationships and period concurrency | Passed; fixtures rolled back/disposable database removed |
| Senior School browser/regression | Passed after asset-rule correction: responsive controls, retained forms, pathways/subjects, concurrent allocation, role scope, migration idempotence and historical promotion |
| Production MySQL 8.4 image/install/HTTP smoke | Passed: built image, fresh 70-table schema, secure first Dean setup, healthy DB/web, secure cookies, required assets, private-path protection and all 74 anonymous-route checks |
| Live SMS, SMTP, real-domain HTTPS and off-host restore | Not executed; require deployment environment and authorized recipient |

Tests create unique synthetic fixtures, roll back transactions or use disposable databases/containers. All test containers/volumes and temporary identities were cleaned up; the school database volume was preserved. Interrupted Docker/HTTP checks were rerun. Verification found and corrected missing/duplicate helpers, unsafe legacy session checks, stray output, a broad Apache rule, a Docker asset exclusion and an outdated test actor filter; the table records the final successful outcomes. Both migrations were rerun and reported already applied. Development/production Compose validation and `git diff --check` also pass.

## Remediation checklist

- [x] Senior School configuration, seeds and admission scope.
- [x] Guardian relationships, accounts, ownership and published reports.
- [x] Teacher submission, class review, Dean approval/publication and corrections.
- [x] Publication notifications and controlled SMS outbox/retry processing.
- [x] Timetable and academic relationship regression checks.
- [x] Session/authentication, legacy guards, CSRF, validation and error handling.
- [x] Schema integrity, repeatable installation/migrations and private backup.
- [x] Main management lists, shared layouts, real dashboards and browser checks.
- [x] Production image, MySQL 8.4 installation, health and access checks.
- [x] README, migration guidance and this twelve-part readiness report.
- [ ] School HTTPS/provider setup, actual authorized delivery and off-host restore acceptance.

## 11. Remaining issues and launch conditions

The local environment currently has SMS disabled, no configured SMS credentials, optional email disabled and no HTTPS APP_URL. The school must configure its curriculum/performance bands, verified guardian links, credentials, public HTTPS domain, backup/restore and monitoring. Real Africa's Talking delivery and optional SMTP must be checked using authorized contacts before enabling workers. Bootstrap 3 compatibility is retained; a future supported frontend migration should be planned separately. Browser print/save-PDF is provided; no dedicated server PDF service or external hosting is provisioned.

## 12. Deployment steps

1. Use the [README](../README.md) fresh-install procedure with a private `.env` and strong, distinct DB/root credentials. Set school identity and the real HTTPS `APP_URL`.
2. For an existing installation, run `python scripts/backup-academic-database.py`, then the parent-results and Senior-integrity migrations. Preserve the existing database engine/volume until a separate restore is validated.
3. For a fresh production volume run `docker compose -p srms-production -f docker-compose.production.yml up -d --build web`.
4. Supply `SETUP_DEAN_USERNAME` and `SETUP_DEAN_PASSWORD` securely via the host environment and run `docker compose -p srms-production -f docker-compose.production.yml exec -T -e SETUP_DEAN_USERNAME -e SETUP_DEAN_PASSWORD web php scripts/setup.php`. Remove the host setup variables afterward. There is no default account/password.
5. Configure the HTTPS reverse proxy to loopback port 5000 and verify login and `/health.php` through the real hostname.
6. Configure the academic year/term, Grades 10-12 classes, pathways, subjects/registrations, teachers, performance bands and guardian links. Rehearse the publication workflow with approved test data.
7. Verify provider settings/delivery, then enable the managed SMS worker with `docker compose -p srms-production -f docker-compose.production.yml --profile notifications up -d sms-worker`.
8. Establish encrypted off-host backups, restore rehearsal, error/health monitoring and a recorded image/migration rollback plan before admitting real traffic.
