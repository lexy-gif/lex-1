# CBE academic workspace

## Open the workspace

Sign in through `admin-login.php`, then select **Open Academic Workspace** on the existing dashboard or **Academic Workspace** in the sidebar. The entry point is `dean-academics.php`. Teachers use `teacher-academics.php`, linked as **My Academic Workspace**.

The existing dashboard and page routes remain available. The workspace groups academic structure, learning areas, pathways, learner guidance, workload, assessments, coverage, interventions, analytics, reports, class profiles, department permissions, exam entry controls and timetables.

## Periods and reports

- Opening a workspace selects the active academic year and its active term. A link that specifies another year defaults to that year's active term. **All terms** is an explicit choice.
- Changing the year refreshes its term choices. A URL containing a term from a different year is rejected.
- Views show the filters relevant to their data. Report selection, edits and CSV downloads preserve the selected context.
- Assignment reports include whole-year teaching alongside the selected term's assignments. Weekly workload ratings require a single term; totals across all terms are not classified against weekly limits.
- Dashboard assessment/exam counts and class timetable conflict counts follow the selected term. Student and class totals describe the school's current population. The term comparison chart deliberately compares all terms in the selected year.

## Assessment entry

Create an assessment for an assigned teacher, class, subject and period. Choose **Open** to allow evidence entry. After creation or an evidence save, the same assessment stays open so another learner's evidence can be recorded.

Only registered learners appear in its evidence form. The outcome selector lists outcomes attached to that assessment. Numeric scores must be within its configured maximum; qualitative assessments accept evidence and performance levels. Validation errors retain the submitted values, and rejected writes roll back together.

Teachers can access their own assessments, subject to their teaching assignment. Teacher class and subject selectors use their assignments for the selected year. The Dean can lock, publish or archive assessments; these states close evidence entry. Department read grants do not authorize Dean configuration changes.

## Classes and configured grades

The existing **Create Class** and **Edit Class** pages now select from the active grade catalogue in **Academic Structure**, including configured Grades 10–12 and any additional school grades. Saving records both the grade link and its numeric grade; there is no separate numeric field to keep in sync. The CBE workspace and these pages share the same transactional save and duplicate grade/section checks. Sections follow the existing database limit of five characters.

Existing class names, IDs and student links are preserved when mapping a legacy class to a grade. The edit form preselects a matching numeric grade for an unmapped class but writes the link only when saved. Inactive grades are labelled on existing classes and require an active selection to save. Validation errors retain the submitted class name, grade and section.

## Examination entry controls

The workspace's **Examination Entry Control** locks/unlocks the existing examination records. Both `add-result.php` and `edit-result.php` enforce the lock, `marks_entry` status, opening date and deadline. Unlocking opens marks entry, but does not bypass its dates. Exam rows stay locked in the database while the result write is in progress.

Result edits verify every submitted row belongs to the selected learner and exam. Marks are validated from 0 to 100 and saved together. Historical results without an exam remain readable; they cannot be edited through this exam-controlled flow.

## Timetable scheduling and publication

The existing **Class Timetable** and **Exam Timetable** routes and the workspace share scheduling validation. The existing routes support editing, retained form values, and year/term filters. Lessons require an active teaching assignment for the selected class, subject and period. Schedules check time ordering, active rooms, class/teacher/room overlaps and configured teacher unavailability. Examination dates must fit the exam's dates and class, and every selected invigilator must be active. Exam clashes are checked across all exams on the same date, including secondary invigilators and older single-invigilator records. Adjacent time slots are allowed; cancelled and archived entries do not reserve slots.

Publishing revalidates pending entries and saves the batch, version history and notification queue together. A failure rolls back the batch. Class publication affects only the chosen class, year and single term; exam publication affects the selected examination. Cancelled and archived rows are skipped. Each affected teacher receives one publication notification, including class teachers for the corresponding academic year. Repeating publication without pending entries makes no changes.

Teacher workspace views show published personal duties and timetables for their assigned classes in the selected year. The older Class Teacher page shows the active year's active term. All invigilators appear in timetable displays and Dean duty counts. Draft changes do not notify teachers; published changes notify both current and removed assignees. Version history retains the original author and previous/new invigilator memberships.

The checks compare weekly lessons within one year/term, and exam sessions across exams on a date. They do not generate timetables automatically or decide whether a dated exam replaces a weekly lesson.

## Database prerequisite

This workspace requires the existing foundation, teacher relationships and timetable migrations, followed by `scripts/migrate-cbe-academics.php`. The local database already had the CBE migration when work resumed on 6 September 2026; this continuation made no schema changes.

For another installation, take a private backup before applying the migration:

```powershell
python scripts/backup-academic-database.py
docker compose exec -T web php scripts/migrate-cbe-academics.php
```

The backup is written under the user's `.codex/backups/srms` directory, outside the web root. Rebuilding the container does not migrate an existing database volume.

## Validation

Run from the repository root with the local Docker services running:

```powershell
python scripts/check-academic-pages.py
python -u tests/cbe-class-http.py
python -u tests/timetable-http.py
python -u tests/cbe-workspace-http.py
docker compose exec -T web php tests/academic-relationships.php
```

The workspace HTTP test creates unique temporary academic records and sessions, exercises the actual forms, and removes them in a `finally` block. Fixture teachers have external delivery disabled and learners have no parent phone numbers. It covers assessment creation/evidence, validation rollback, ownership, closed entry, legacy exam locks, report context, every workspace area and all CSV report types. The relationship test rolls back its test transaction.

These checks validate rendering and the listed workflows. They do not establish correctness of every pathway, timetable or curriculum configuration.

The class HTTP test covers Grades 10–12, an additional configured grade, invalid/inactive selections, duplicate sections across both routes, retained form input, authentication/CSRF and legacy mapping without changing student links. It creates temporary classes, grades, a learner with no parent contacts and sessions, then removes them in a `finally` block. It requires active Grades 10–12 in the local catalogue.

The timetable HTTP test covers conflict checks through both scheduling routes, invigilator changes, legacy records, publication rollback, notification recipients, period isolation, closed states, teacher visibility and simultaneous saves. Its unique fixture teachers have no external addresses and email delivery is disabled. Fixtures and sessions are removed in a `finally` block; an active school year and term are required, and their activation is not changed by the test.

## Remaining integration work from the original plan

- Complete transactional academic-period activation and review the remaining legacy result subject-registration flow.
- Add workflow tests for pathway allocation/replacement and curriculum coverage.

The original analysis and curriculum configuration policy are preserved in [CBE_DEAN_IMPLEMENTATION.md](CBE_DEAN_IMPLEMENTATION.md).
