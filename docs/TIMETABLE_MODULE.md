# Timetable Management Module

## Purpose

This module adds class lesson timetables and examination timetables to the SRMS. The Dean of Studies manages the setup, schedules lessons and exam sessions, checks conflicts, publishes timetables, and notifies the relevant teachers. Class Teachers can view the published timetable for their assigned class.

## Database

The current timetable routes require the foundation, teacher relationship, timetable and CBE migrations described in [CBE workspace setup](CBE_WORKSPACE.md). The September 2026 scheduling integration requires no additional schema changes. The existing timetable migration creates:

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
- `dean-class-timetable.php` creates and edits class lessons, validates teaching assignments and teacher/class/room conflicts, and publishes timetables for a selected year and term.
- `dean-exam-timetable.php` creates and edits exam sessions with multiple invigilators, detects class/room/invigilator conflicts across exams on the same date, and publishes exam timetables.

Both routes use the same scheduling helper as **Academic Timetables** in the workspace. Publishing revalidates every pending entry and commits status changes, version records and notifications together. A failed entry rolls back the whole batch. Cancelled and archived entries remain closed; repeating publication without pending entries creates no extra notifications. Every affected teacher, including secondary invigilators and the relevant year's class teachers, is included once in the publication notification recipients.

## Class Teacher Workflow

Class Teachers use:

- `teacher-timetable.php`

The page shows published lessons and examinations for the teacher's assigned class in the active year and term. It also allows the class teacher to notify subject teachers for those lessons. The teacher academic workspace shows published personal duties and assigned class timetables for its selected year/term. Timetable displays show all invigilators, including older single-invigilator records.

## Current Scope

Implemented:

- Room setup
- Period setup
- Manual class timetable creation
- Manual exam timetable creation
- Editing and multiple invigilators
- Conflict detection
- Teacher assignment and availability validation
- Publish actions
- Version records
- Teacher notifications
- Class Teacher timetable view
- Dashboard and sidebar integration
- Subject Teacher workspace timetable view

Not yet implemented:

- Full automatic timetable generation engine
- Background reminder job
- Advanced teacher availability UI
- PDF/print timetable exports

## Validation

Syntax validation:

```powershell
python scripts/check-academic-pages.py
python -u tests/timetable-http.py
```

The HTTP test creates private temporary records and sessions, disables external delivery for fixture teachers, and cleans up on completion. It checks cross-exam and weekly clashes, all invigilators, adjacent times, inactive accounts, legacy duties, teacher visibility, atomic publication failure, closed statuses, period isolation and simultaneous conflicting saves. An active school year and term are required.

Smoke checks:

- `admin-login.php` should return HTTP 200.
- `teacher-login.php` should return HTTP 200.
- Dean and teacher timetable pages should redirect to login when accessed while logged out.
