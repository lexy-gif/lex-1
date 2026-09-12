# Manage Teachers Module

## Purpose

The Dean of Studies can manage teacher accounts from one dedicated area without deleting historical records.

## Main Pages

- `manage-teachers.php` shows teacher summaries, search, filters, pagination, account status actions, password reset, and create-teacher form.
- `edit-teacher.php` edits teacher account details.
- `view-teacher.php` shows a teacher profile, activity, status, and assignment summary.
- `teacher-assignments.php` shows all teacher assignments or one teacher's assignment details.
- `teacher-departments.php` summarizes teachers by department.
- `create-teacher.php` opens the create-teacher section on Manage Teachers.

## Database

Run this migration after the teacher notification migration:

Use the repeatable setup/migration procedure in the [README](../README.md); it reads database credentials from the private environment.

The migration adds `LastLoginAt` and expands teacher role support to:

- Class Teacher
- Subject Teacher
- Head of Department
- Exams Officer
- Deputy Dean

## Security

Teacher management pages require Dean authentication. Status changes do not delete teacher records. Inactive teachers are blocked at login and are logged out on the next protected teacher request.

## Validation

Use:

```bash
docker compose run --rm web sh -lc 'for f in manage-teachers.php edit-teacher.php view-teacher.php teacher-assignments.php teacher-departments.php create-teacher.php; do php -l "$f" || exit 1; done'
```
