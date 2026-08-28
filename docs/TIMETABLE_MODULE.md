# Timetable Management Module

## Purpose

This module adds class lesson timetables and examination timetables to the SRMS. The Dean of Studies manages the setup, schedules lessons and exam sessions, checks conflicts, publishes timetables, and notifies the relevant teachers. Class Teachers can view the published timetable for their assigned class.

## Database

Run this migration on a fresh or existing SRMS database:

```bash
Get-Content database-updates\timetable-module.sql | docker compose exec -T db mysql -usrms_user -psrms_password srms
```

The migration creates:

- `tblrooms`
- `tbltimetableperiods`
- `tblclasstimetableentries`
- `tblexamtimetableentries`
- `tblteacheravailability`
- `tbltimetableversions`
- `tblscheduledreminders`

It also seeds default rooms and Monday to Friday timetable periods.

## Dean of Studies Workflow

The Dean uses the Timetable menu in the admin area:

- `dean-timetable-setup.php` manages rooms and school periods.
- `dean-class-timetable.php` creates class lesson schedules, detects teacher/class/room conflicts, and publishes class timetables.
- `dean-exam-timetable.php` schedules exam sessions, detects class/room/invigilator conflicts, and publishes exam timetables.

Publishing records a timetable version and creates notifications for affected teachers where possible.

## Class Teacher Workflow

Class Teachers use:

- `teacher-timetable.php`

The page shows the published lesson timetable and published exam timetable for the teacher's assigned class. It also allows the class teacher to notify subject teachers for already-published lessons.

## Current Scope

Implemented:

- Room setup
- Period setup
- Manual class timetable creation
- Manual exam timetable creation
- Conflict detection
- Publish actions
- Version records
- Teacher notifications
- Class Teacher timetable view
- Dashboard and sidebar integration

Not yet implemented:

- Full automatic timetable generation engine
- Background reminder job
- Subject Teacher portal view
- Advanced teacher availability UI
- PDF/print timetable exports

## Validation

Syntax validation:

```bash
docker compose run --rm web sh -lc 'for f in includes/timetable.php dean-timetable-setup.php dean-class-timetable.php dean-exam-timetable.php teacher-timetable.php teacher-dashboard.php dashboard.php includes/leftbar.php includes/teacher-leftbar.php; do php -l "$f" || exit 1; done'
```

Smoke checks:

- `admin-login.php` should return HTTP 200.
- `teacher-login.php` should return HTTP 200.
- Dean and teacher timetable pages should redirect to login when accessed while logged out.
