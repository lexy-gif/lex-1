# Dean of Studies Module

## Role Definition

The existing SRMS administrator is now treated as the Dean of Studies / Academic Administrator.

The Dean has school-wide academic access. This is different from the Class Teacher module, where access is restricted to one assigned class.

## Current Dean Capabilities

- Dean login through `admin-login.php`.
- School-wide academic dashboard through `dashboard.php`.
- Academic year and term setup through `dean-academic-periods.php`.
- Grade/class and stream management through the existing class pages.
- Subject and class-subject setup through the existing subject pages.
- Teacher account creation, activation/deactivation, and password reset through `manage-teachers.php`.
- Examination setup through `manage-exams.php`.
- Result entry and result management through the existing result pages.
- Class teacher review visibility through `dean-result-approvals.php`.
- Dean final result approval, return for correction, and publication through `dean-result-approvals.php`.
- School-wide audit viewing through `dean-audit-logs.php`.

## Academic Workflow

The module now supports this first production-oriented academic workflow:

1. Dean creates academic year and term.
2. Dean creates grades/classes and subjects.
3. Dean creates teacher accounts and assigns class teachers.
4. Dean creates examinations and marks-entry deadlines.
5. Marks are entered for students.
6. Class teacher reviews class results.
7. Dean approves, returns, or publishes results.
8. Students only see published examination results.

## Permission Boundary

The Dean module is for academic administration. It should not be used for unrelated technical or finance-only responsibilities such as server secrets, database passwords, payroll, or bank/accounting controls.

## Next Extensions

- Subject teacher mark-entry module.
- Department and Head of Department workflows.
- Subject teacher allocation UI.
- Timetable UI.
- Academic calendar UI.
- Result correction request workflow with old/new mark history.
- CSV/PDF exports for academic reports.
