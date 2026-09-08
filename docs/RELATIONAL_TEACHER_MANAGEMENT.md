# Relational teacher management

The Dean can create one teacher account with multiple subject assignments, a class teacher assignment, and several responsibilities. Academic roles come from relationships, independently of the legacy account category.

## Pages

- **Manage Teachers:** create teachers and assignments together, activate/deactivate accounts, inspect current assignments, and follow the Assignments action to edit them.
- **Teacher Relationships:** filter by teacher, class, subject, year and academic role; assign or change teachers; end assignments; view history and student lists; create responsibility types and assign responsibilities with dates and notes.
- **View Teacher:** account details, subjects taught, classes, responsibilities, registered subject students and all active class students.
- **Manage Subjects:** expand each offered class to see its teachers and registered students, with links to assignment management.
- **Student Subjects:** choose an academic year and student, then select subjects offered in their current class. This is linked from Subject Combination, which continues to manage class subject offerings.
- **My Teaching Assignments:** teachers see their own relationships and students. The teacher ID is taken from the authenticated session, never from a request parameter.
- **Dean dashboard:** eight assignment coverage metrics and quick links. Subject metrics use whole-year and active-term assignments within the selected year.

To move an assignment, select **Change assignment**, change the teacher/class/subject/term, then save. The previous record ends and the replacement is created in the same transaction. Replacing an incumbent teacher prompts for confirmation. Confirmation refers to specific assignment IDs; if the incumbent changes before submission, another confirmation is required.

## Reused relationships

| Purpose | Existing table |
|---|---|
| Teacher identity and login | `tblusers` |
| Class and subject definitions | `tblclasses`, `tblsubjects` |
| Subjects offered in each class | `tblsubjectcombination` |
| Subject teacher assignments | `tblsubjectteacherassignments` |
| Students and their current class | `tblstudents` |
| Academic periods | `tblacademicyears`, `tblterms` |

New tables are `tblclassteacherassignments`, `tblresponsibilitytypes`, `tblteacherresponsibilities`, and `tblstudentsubjects`. `tblschemamigrations` records successful migration completion. Existing subject assignments gain an end timestamp and generated keys for active assignment uniqueness.

Subject teacher students follow `tblusers → tblsubjectteacherassignments → tblstudentsubjects → tblstudents`, matched on subject, class and academic year. Class teacher students follow `tblusers → tblclassteacherassignments → tblstudents`, matched on the current class. Both lists exclude inactive students. History records describe past assignments; expanded current student lists are not historical class rosters.

## Migration

This migration requires the existing foundation, class teacher, Dean, notification, teacher extension and timetable migrations used by the current installation. Docker's seed mount only initializes an empty database; rebuilding an image does not migrate an existing volume.

From the repository root with Docker Compose running:

```powershell
python scripts/backup-academic-database.py
docker compose exec -T web php scripts/migrate-teacher-management.php
```

The backup is stored outside the web root in the current user's `.codex/backups/srms` directory. Run the migration only after the backup succeeds. No database volumes are removed. The CLI migration is repeatable and checks for an existing completion marker. MySQL DDL commits independently, so an interrupted run may leave new schema objects; rerunning completes the remaining steps.

Migration stops if there are overlapping existing primary subject teachers, duplicate legacy class teachers, or no single active academic year. It copies existing class teacher relationships into the active year without deleting legacy account fields. It does **not** assume every student takes every class subject: the Dean must register individual subjects through Student Subjects. Existing results and accounts are retained.

Assignment foreign keys restrict deletion of related classes, subjects, years, terms and accounts, preserving history. Class/subject deletion pages explain when related records prevent deletion. Old `tblusers.ClassId` values are retained as legacy data but are no longer used to authorize teacher class access after migration. Class access is resolved on every protected request from the active year's relationship. Timetable publication recipients also use the relationship.

## Validation and constraints

- Teacher creation and all its academic assignments commit together.
- New assignments require active teacher accounts, valid academic periods and active class subject offerings.
- Term-specific assignments cannot overlap a whole-year primary assignment. Different terms may have different teachers.
- Class row locks serialize assignment changes; generated unique keys additionally prevent duplicate active primary teachers for the same period.
- A class has one active class teacher per year, and a teacher has at most one class teacher class per year.
- Removed teacher assignments remain as ended records. Student registration removal marks the year-specific relationship inactive; registrations in other years remain.
- Responsibility types are extensible; responsibilities support dates, notes, activation and ending without changing account identity.
- All new Dean mutations require the existing Dean session and CSRF token. Teacher views cannot change relationships.

```powershell
docker compose exec -T web php tests/academic-relationships.php
python scripts/check-academic-pages.py
python tests/academic-http.py
```

The integration test rolls back all fixtures and sends no notifications. It covers duplicates, inactive accounts, invalid periods and offerings, overlapping reassignment, history, simultaneous academic duties, multiple responsibilities, student registration visibility and stale-session class access. The smoke check lints PHP and checks authenticated page rendering and unauthenticated redirects using temporary local sessions. The HTTP form test creates uniquely named fixtures, exercises teacher creation with confirmed replacement, atomic editing, student registration, CSRF and teacher authorization, then removes its fixtures in a `finally` block. It uses an undeliverable `example.invalid` email and never starts the email queue processor.
