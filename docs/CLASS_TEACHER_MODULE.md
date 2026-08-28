# Class Teacher Module

## What It Adds

- Separate class teacher login at `teacher-login.php`.
- Assigned-class dashboard.
- Assigned-class student list.
- Student account creation, activation/deactivation, and password reset.
- Result monitoring by exam.
- Result review workflow: approve or request correction.
- Attendance recording by date.
- Report-card preview with class teacher comments.
- Performance analysis and support flags.
- Class reports and notifications.
- Profile page.
- Audit logging for important teacher actions.

## Access Control

Class teachers are stored in `tblusers` with role `class_teacher`.

Each class teacher has one assigned `ClassId`. Teacher pages use that `ClassId` on the backend, so teachers can only view or update records for their own assigned class.

## Setup Steps

1. Apply `database-updates/class-teacher-module.sql`.
2. Log in as administrator.
3. Open `Class Teachers` from the admin sidebar.
4. Create a class teacher account and assign a grade/class.
5. Open `teacher-login.php`.
6. Sign in with the teacher username and password.

## Current Scope

This is the first integrated version of the module. It supports class teacher workflows while keeping the existing admin module unchanged.

Subject teacher entry, parent portal, and full PDF export can be added later on top of this structure.
