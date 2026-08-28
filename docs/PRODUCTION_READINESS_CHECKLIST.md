# SRMS Production Readiness Checklist

## Completed Baseline

- Removed demo operational data from the local database while keeping the admin account.
- Seeded Grade 1 through Grade 9.
- Seeded academic year `2026`, `Term 1`, and `Term 1 Exam`.
- Seeded grading scale A to E.
- Added exam-aware result entry and viewing.
- Made `password_hash.php` CLI-only so it cannot be used from the browser.
- Added database environment variables to `.env.example`.
- Added CSRF protection to admin create, update, delete, activate, and deactivate actions.
- Added the first version of the Class Teacher module with assigned-class access, student account management, attendance, result review, comments, reports, notifications, and audit logging.
- Reframed the existing administrator role as the Dean of Studies with school-wide academic administration, approval/publication workflow, teacher account management, academic period setup, and audit log viewing.

## Before Real Deployment

- Change the default admin password.
- Use strong values for `MYSQL_ROOT_PASSWORD`, `DB_PASS`, and SMS API keys.
- Set `AFRICASTALKING_SMS_ENABLED=true` only after live credentials are tested.
- Disable public database access in production. Keep MySQL bound to the Docker network only.
- Run the app behind HTTPS.
- Back up the MySQL volume regularly.
- Extend role-based access to subject teachers, parents, and student portal routes.
- Add audit logging for login, result edits, student edits, and fee/payment changes.
- Review all delete actions and add confirmation plus access control.

## Recommended Next Feature

Build the attendance module next:

- Attendance table is already available from the foundation migration.
- Add daily attendance entry by grade/class.
- Add attendance reports by student and grade.
- Optionally send SMS to parents when a learner is marked absent.
