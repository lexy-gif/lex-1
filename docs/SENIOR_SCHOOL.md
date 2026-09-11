# Senior School

The Dean sidebar includes **Senior School**, with pages for pathways and tracks,
subjects, combinations, learner allocation, teaching assignments, promotion and
reports. These pages use the existing PHP, Bootstrap and academic relationships.

## Install on an existing SRMS database

Apply the foundation, teacher relationships, timetable and CBE academic migrations
first, as described in [CBE workspace](CBE_WORKSPACE.md). Start Docker Compose and
save a private backup before applying the Senior School migration:

```powershell
docker compose up -d --build
python scripts/backup-academic-database.py
docker compose exec -T web php scripts/migrate-senior-school.php
```

The backup is outside the web root, under the user's `.codex/backups/srms`
directory. Rebuilding containers does not migrate an existing database volume.
The Senior School migration can be run again safely. It adds curriculum metadata,
pathway subject mappings, enrollment history and allocation subject snapshots.
Existing subjects and their IDs are reused where names or configured aliases
match. Ambiguous matches stop the migration for review. Existing learner records,
registrations and marks are retained. Existing pathway allocations receive subject
snapshots and, where their classes have configured grades, historical enrollments.

`database-updates/senior-school-defaults.json` supplies editable starter subjects,
pathways and tracks for the first migration. The school should review these
defaults against its curriculum and offered subjects before allocating learners.
After installation, change configuration through the Dean pages; rerunning the
migration does not reset it.

## Configure and assign learners

1. Configure Grades 10–12 and their classes in Academic Structure.
2. Review **Senior School Subjects** and each subject's applicable grades.
3. Configure pathways, tracks and their elective subjects. Pathway mappings apply
   to all its tracks; track mappings add subjects for that track.
4. Review **Core Subject Policy** and each pathway's mathematics default.
5. Create optional subject combinations for an academic year, then use **Assign
   Learners** to select learners and assign a pathway, track and combination or
   individual electives.

The starter policy adds English and Community Service Learning as common core
subjects, one language and one mathematics subject, plus three electives. A
learner override takes precedence over the pathway and school defaults. The
elective count is configurable per school and pathway. Missing or inactive
required subjects and inactive existing class offerings reject the save.

Assignments automatically create missing class offerings and register the learner's
subjects for that year. Existing result entry reads these registrations. The
general Student Subjects page prevents changes that bypass Senior School policy.
Changing a combination does not silently change already allocated learners:
review and reapply it through Assign Learners.

Replacing an allocation requires explicit review. The earlier allocation and its
subject snapshot remain available. Stale forms and invalid selections reject the
whole batch. Notifications are recorded for affected active accounts through the
existing notification service and its delivery preferences.

## Teaching and learner access

Teachers open **Senior School** in their sidebar (`teacher-senior.php`). Class
teachers see their assigned class's learners and subjects; subject teachers see
learners registered in their assigned subjects and only those subjects. Access
uses the selected year's active teaching relationships, including historical
class enrollment after promotion.

Learners open **Senior School Subjects** on the home page (`student-senior.php`)
and sign in using the student account created by their class teacher in Manage
Accounts. The learner identity comes from the active account on every request.
Changing URL parameters cannot select another learner. Deactivating either the
account or learner removes access. The page shows that learner's placement and
subjects for the selected year.

## Promotion and reports

Promote one grade at a time into a later academic year: Grade 9 to 10, 10 to 11,
or 11 to 12. Senior School learners need a pathway in the source year. Promotion
retains their pathway, track and recorded electives, validates destination grade
availability, and updates the student account's class. Previous enrollment,
subject registrations and result rows keep their original class and year.
Promotion does not activate the destination academic year.

Reports support filters, CSV export and browser printing. Class and examination
timetables validate the selected grade, pathway and subject using the existing
scheduling and clash rules.

## Validation

```powershell
python scripts/check-academic-pages.py
python -u tests/senior-school-http.py
```

With Python Playwright and a Chromium browser installed, add `--browser` to the
Senior School test for dropdown dependencies, retained invalid input,
confirmation dialogs and page widths of 1280, 768 and 320 pixels. On Windows it
uses an installed Chrome or Edge; `SRMS_BROWSER_EXECUTABLE` can specify another
browser path.

The Senior School test copies schema only into a uniquely named disposable
database, starts a separate local web container, and removes both on completion.
It uses synthetic learners and accounts without external contact details and
with email delivery disabled. It checks migration reuse and history, Dean page
rendering, authentication, CSRF, core/elective registration, rejected batch
rollback, replacement review, stale/concurrent saves, reports, teacher/learner
access, deactivation and promotion history. It verifies the source database's
learner/result counts and migration list remain unchanged.
