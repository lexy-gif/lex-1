# SRMS Gap Analysis and Upgrade Roadmap

> Historical design notes. The current Senior School scope, guardian portal and deployment status are documented in [SENIOR_SYSTEM_AUDIT.md](SENIOR_SYSTEM_AUDIT.md) and the [README](../README.md). Earlier plans below are not current installation instructions.

## Current System Snapshot

This project is a focused Student Result Management System. It already supports:

- Admin login and password change.
- Class and section management.
- Subject management.
- Class-subject combinations.
- Student admission and management.
- Result declaration, result editing, public result lookup, total marks, percentage, class position, and print view.
- Notices and announcements.
- Parent phone capture and SMS notification support when results are declared.
- Docker Compose setup with PHP, MySQL, and phpMyAdmin.

## What Real SRMS/School ERP Systems Usually Include

Surveyed school/SRMS platforms commonly include these modules:

- Connected student profile: admission details, guardian contacts, documents, class history, status, and academic history.
- Academic year, term, exam, grading scheme, report card, rank, and transcript management.
- Attendance with daily class marking, absence alerts, and attendance reports.
- Parent/student portal for results, notices, fees, attendance, and profile details.
- Teacher/staff accounts with role-based permissions, not only a single admin login.
- Fee management with fee structures, invoices, receipts, payments, balances, and reminders.
- Communication center for notices, SMS/email/WhatsApp, and message history.
- Timetable, class teacher allocation, and subject teacher allocation.
- Reports and analytics: class performance, subject averages, pass/fail lists, merit lists, low attendance, fee arrears.
- Audit trail and security controls for sensitive academic records.
- Import/export workflows for students, marks, reports, and backups.

## Main Distinctions

| Area | Current Project | Real SRMS Expectation | Gap |
| --- | --- | --- | --- |
| Users and access | One admin/class teacher login backed by `admin` table | Admin, teacher, accountant, parent, student roles | Add role-based users and permissions |
| Student records | Basic name, registration ID, email, parent phone, gender, DOB, class, status | Full profile, guardian details, address, documents, admission history | Expand student profile gradually |
| Results | Marks per student/class/subject | Academic year, term, exam type, grades, remarks, report cards, transcripts | Add exam/term/grading foundation |
| Attendance | Missing | Daily attendance, absence reasons, parent alerts, attendance reports | Add attendance module |
| Fees | Missing | Fee structures, invoices, payments, receipts, balances, reminders | Add fees module |
| Parent portal | Public result lookup only | Parent login/dashboard with child results, notices, attendance, fees | Add parent accounts or secure parent lookup |
| Teacher workflow | Login label says class teacher, but account model is admin-only | Teachers enter marks/attendance only for assigned classes/subjects | Add staff/teacher records and allocation |
| Communication | Notices plus result SMS | Notice targeting, SMS history, parent messaging | Add message log and targeted notices |
| Reports | Basic counts and result view | PDF/print report cards, class result summaries, subject performance | Add report dashboards/export |
| Security | PDO prepared statements in many areas; password hash migration started | Password hashing, CSRF protection, session hardening, audit logs, least privilege | Add security helpers and audit table |
| Data integrity | Few constraints and duplicate subject combinations possible | Foreign keys, uniqueness rules, status controls | Add indexes and constraints carefully |

## Recommended Build Order

### Phase 1: Make Results Production-Ready

- Add academic years, terms, exams, and grading scales.
- Link every mark to an exam.
- Prevent duplicate result entries for the same student, subject, class, and exam.
- Generate report cards with grade, remark, total, percentage, rank, and print view.

### Phase 2: Add Real User Roles

- Replace single `admin` table with a `tblusers` table or extend login to support admin, teacher, parent, and student roles.
- Add teacher records and assign teachers to classes/subjects.
- Restrict marks entry to assigned teachers.

### Phase 3: Add Attendance

- Create attendance sessions by date/class.
- Mark present, absent, late, excused.
- Show student attendance percentage.
- Send optional parent SMS for absences.

### Phase 4: Add Parent/Student Portal

- Secure portal login or OTP-style lookup.
- Show results, report cards, notices, attendance, and profile data.
- Support linked siblings under one parent account.

### Phase 5: Add Fees

- Create fee types and class fee structures.
- Generate invoices per student/term.
- Record payments and balances.
- Print receipts and list defaulters.

### Phase 6: Add Reporting and Audit

- Class performance report.
- Subject average report.
- Merit list.
- Pass/fail report.
- Attendance report.
- Fee balance report.
- Audit log for login, result changes, student changes, and fee changes.

## First Implementation Target

The best first feature to add is the academic year, term, exam, and grading foundation. This improves the existing result module without changing the whole application at once. It also prepares the database for report cards, transcripts, teacher mark entry, and parent portal features.

## Sources Used for Comparison

- Skoo School Management System features: admissions, fees, attendance, exams/results, parent app, security, audit.
- VidyaSuite school management features: student management, fees, attendance, exams/results, parent portal, communication, timetable.
- DAB Inventive school management overview: shared student record, parent/teacher portals, fee reminders, exams, timetables.
- Sangani Group school management features: admissions, attendance, timetables, exams, fees, parent/teacher portal.
- ClassTotal school management features: staff/student records, classes, timetables, attendance, fees, exams, roles/access, parent/student portal.
