# CBE Dean module: current-system analysis and implementation plan

Timetable continuation on 8 September 2026: the older timetable routes now share scheduling validation with the workspace, support editing and multiple invigilators, and revalidate publication in a transaction. Teacher displays and Dean duty counts include secondary and legacy invigilators. Timetable HTTP checks cover conflicts, publication rollback, period isolation and concurrent saves. No schema changes were required; see [CBE_WORKSPACE.md](CBE_WORKSPACE.md).

Continuation on 8 September 2026: legacy class creation/editing now use the configured grade catalogue and the same transactional save as the workspace. Grades above 9 are supported, duplicate sections are checked across mapped and unmapped classes, and legacy mapping preserves class names and student links. No schema changes were required. Usage and regression checks are documented in [CBE_WORKSPACE.md](CBE_WORKSPACE.md).

Continuation on 6 September 2026: workspace navigation, period/report context, assessment evidence flow and legacy exam-entry lock enforcement are integrated. See [CBE_WORKSPACE.md](CBE_WORKSPACE.md) for usage, validation and the remaining integration work. The analysis below records the original starting point.

Inspected 5 September 2026: application PHP entry points and shared helpers, SQL migrations, the running MySQL schema, existing module documentation and Bootstrap assets. The live database uses `tbldean`; `includes/dean-account.php` already supports the older `admin` name. The application remains page-based PHP with PDO, PHP sessions, CSRF helpers and Docker Compose.

## 1. Existing Dean functionality

Teacher accounts, simultaneous subject/class assignments, responsibilities, individual student subjects and assignment history are implemented. Other existing pages cover academic years/terms, classes, subjects, students, exam definitions, class and exam timetables, rooms/periods, result review/approval/publication, notification deliveries and audit history. Teacher pages include class students, attendance, account management, class result review, report cards, performance and personal assignments.

## 2. Existing database tables

- Identity: `tbldean`, `tblusers` (teachers and learner/parent accounts). There is no separate teacher identity table and none is needed.
- Structure: `tblclasses`, `tblstudents`, `tblsubjects`, `tbldepartments`, `tblacademicyears`, `tblterms`.
- Assignments: `tblsubjectcombination` (class offerings, despite its name), `tblsubjectteacherassignments`, `tblclassteacherassignments`, `tblstudentsubjects`, `tblresponsibilitytypes`, `tblteacherresponsibilities`.
- Results: `tblexams`, `tblresult`, `tblgradingscales`, `tblteachercomments`, `tblresultreviews`, `tbldeanapprovals`.
- Scheduling: `tblclasstimetableentries`, `tblexamtimetableentries`, `tblrooms`, `tbltimetableperiods`, `tblteacheravailability`, `tbltimetableversions`, `tblscheduledreminders`; `tbltimetable` is an older equivalent and will not become a second scheduling source.
- Communications: `tblteachernotifications`, `tblnotificationpreferences`, `tblnotificationdeliveries`, `tblnotice`, `tblauditlog`, `tblacademiccalendar`.
- Other existing data: `tblattendance`, `tblfeetypes`, `tblfeestructures`, `tblpayments`, `tblschemamigrations`. Finance stays outside the Dean module.

## 3. Existing relationships

`tblusers → teaching assignment → class + subject + year + optional term`; `tblusers → class teacher assignment → class + year`; `student → student subjects → class + subject + year`. Subject student lists already use individual registrations; class teacher lists use active class membership. The current student's class is stored on `tblstudents`; this is not a historical enrolment register.

## 4. Reuse

Keep login identities, session conventions, teacher/class assignment services, all student IDs, result records, exam workflows, notification delivery queue, audit log, timetable version history, Bootstrap styling, DataTables and existing JavaScript chart assets. Add shared domain helpers without adopting a framework or replacing the application architecture.

## 5. Missing features and verified defects

- Class create/edit still reject grades above 9. An old normalization SQL script even converts Grade 10 to Grade 9; it must not be reapplied.
- No configured school levels/grade catalogue, subject grade eligibility, subject status/department relation, pathways, tracks, school combinations, pathway placement or guidance history.
- Workload counts exist but have no configurable limits or dedicated per-period breakdown.
- Exam clash checks currently restrict comparisons to the same exam ID, so two separate exams may double-book a class/room/invigilator. A session supports only one invigilator. Timetable pages create/publish but need coherent editing and validation.
- Results are exam marks. There are no extensible assessment types, outcomes, rubric levels, evidence records, coverage records or interventions.
- Existing exam status is not consistently enforced on result writes. Period activation is not transactional, and exam creation can accidentally mark additional years/terms active.
- Dashboard data mixes all years and contains a literal zero for timetable conflicts. No pathway/assessment/workload charts.
- Teacher authentication is assignment-aware, but HOD access has no explicit department permission grants. No separate technical-admin login model exists in this installation; do not invent or grant such a role through academic screens.

## 6. Proposed additive migrations

1. School levels/grades, class-to-grade link, subject status/department, subject-grade offerings and school workload configuration. Preserve existing class IDs and names; map numeric grades only where unambiguous.
2. Pathways/tracks, school combination headers and members, learner pathway allocations and guidance records. Keep these separate from existing class offerings because they represent different relationships.
3. Assessment types, competencies/outcomes, configurable performance levels, assessment definitions, assessment/outcome links and learner observations/results. Keep legacy examination marks in `tblresult`.
4. Curriculum coverage and interventions with auditable changes; additional invigilator membership for existing exam sessions; explicit department read grants.

Use indexed foreign keys and restrictive deletion for academic history. Back up first. Migration steps are repeatable and recorded with the existing migration ledger. Never delete Docker volumes or silently transfer old assignments into a new year.

## 7. Pages/routes

Extend the current dashboard, navigation, class/subject forms, period management, timetables and result entry. Add focused pages for academic structure, pathways/combinations, pathway allocation/guidance, workload, class academic profile, assessments/results, curriculum coverage, interventions, analytics and printable/CSV reports. Teacher entry points reuse scoped domain helpers for their assessments, coverage and timetable. Existing routes remain linked or serve the upgraded workflow.

## 8. Permissions

Dean sessions manage academic configuration, assignments, assessments, allocations and reports. Teacher sessions may read/write only their assigned class-subject-period assessments/coverage; class teacher access remains tied to current class assignment. HOD department visibility requires an explicit Dean-managed read grant and does not imply write access. Technical administration and finance are not added to the Dean role. Every mutation requires CSRF and server-side scope checks.

## 9. Breaking-change risks

Grade mapping, restrictive historical foreign keys, status enforcement, old timetable fields and current-class versus historical membership require care. Retain legacy columns/results and extend their callers; do not automatically rename existing students/classes or assume every learner takes each offered subject. Record external placement as staff-entered information, not official government placement performed by SRMS. No invented curriculum content or legal teacher workload limits.

## 10. Implementation sequence and checks

1. Structure and migration; test grade/class compatibility and old pages.
2. Reuse teacher assignments; add workload, permissions and notification/audit integration; run existing assignment tests.
3. Subject configuration and individual allocations; test eligibility and duplicates.
4. Pathways, school combinations, allocation/guidance history; test pathway/track/year and class offering constraints.
5. Timetable editing and multi-invigilator conflicts; test cross-exam and overlapping bookings.
6. Assessments/outcomes/scoring and exam locks; test teacher scope, registration, score bounds and locking.
7. Coverage, interventions, analytics and class profiles; test period isolation and history.
8. Dashboard, reports, navigation, export, notifications and audits; lint and exercise authenticated/unauthenticated HTTP flows.

## Curriculum reference policy

Initial pathway/track records follow the requested configurable structure and the Ministry's published combination catalogue. The school chooses its own offerings; SRMS does not seed every national combination, compulsory-subject policy, official assessment weighting or workload threshold. Administrators can update configuration when official guidance changes.

- KICD Grade 10 curriculum designs: https://kicd.ac.ke/cbc-materials/curriculum-designs/grade-ten/
- Ministry Grade 10 subject combination catalogue: https://selection.education.go.ke/uploads/1750333580754-subject-combinations-1750333524964.pdf

These sources guide configuration, not a claim of official placement integration or certification.
