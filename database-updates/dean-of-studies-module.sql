-- Dean of Studies / Academic Administrator schema extensions.
-- Apply after class-teacher-module.sql.

ALTER TABLE `tblexams`
  MODIFY `Status` enum('draft','marks_entry','submitted','under_review','approved','published','archived') NOT NULL DEFAULT 'draft',
  ADD COLUMN `StartDate` date DEFAULT NULL AFTER `ClassId`,
  ADD COLUMN `EndDate` date DEFAULT NULL AFTER `StartDate`,
  ADD COLUMN `MarksOpenDate` date DEFAULT NULL AFTER `EndDate`,
  ADD COLUMN `MarksDeadline` date DEFAULT NULL AFTER `MarksOpenDate`;

CREATE TABLE IF NOT EXISTS `tbldepartments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `DepartmentName` varchar(120) NOT NULL,
  `HeadTeacherId` int(11) DEFAULT NULL,
  `Status` tinyint(1) NOT NULL DEFAULT 1,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_department_name` (`DepartmentName`),
  CONSTRAINT `fk_departments_head_teacher` FOREIGN KEY (`HeadTeacherId`) REFERENCES `tblusers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblsubjectteacherassignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `TeacherId` int(11) NOT NULL,
  `ClassId` int(11) NOT NULL,
  `SubjectId` int(11) NOT NULL,
  `AcademicYearId` int(11) NOT NULL,
  `TermId` int(11) DEFAULT NULL,
  `Status` tinyint(1) NOT NULL DEFAULT 1,
  `AssignedBy` varchar(100) DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_subject_teacher_assignment` (`TeacherId`, `ClassId`, `SubjectId`, `AcademicYearId`, `TermId`),
  KEY `idx_subject_teacher_class` (`ClassId`, `SubjectId`),
  CONSTRAINT `fk_sta_teacher` FOREIGN KEY (`TeacherId`) REFERENCES `tblusers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sta_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sta_subject` FOREIGN KEY (`SubjectId`) REFERENCES `tblsubjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sta_year` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sta_term` FOREIGN KEY (`TermId`) REFERENCES `tblterms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tbldeanapprovals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ClassId` int(11) NOT NULL,
  `ExamId` int(11) NOT NULL,
  `ApprovedBy` varchar(100) NOT NULL,
  `Status` enum('pending','approved','rejected','published') NOT NULL DEFAULT 'pending',
  `DecisionReason` text DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dean_approval_scope` (`ClassId`, `ExamId`),
  KEY `idx_dean_approvals_status` (`Status`),
  CONSTRAINT `fk_dean_approvals_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dean_approvals_exam` FOREIGN KEY (`ExamId`) REFERENCES `tblexams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblacademiccalendar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `AcademicYearId` int(11) DEFAULT NULL,
  `TermId` int(11) DEFAULT NULL,
  `EventTitle` varchar(150) NOT NULL,
  `EventType` varchar(80) DEFAULT NULL,
  `StartDate` date NOT NULL,
  `EndDate` date DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `Status` tinyint(1) NOT NULL DEFAULT 1,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_calendar_dates` (`StartDate`, `EndDate`),
  CONSTRAINT `fk_calendar_year` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_calendar_term` FOREIGN KEY (`TermId`) REFERENCES `tblterms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tbltimetable` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ClassId` int(11) NOT NULL,
  `SubjectId` int(11) NOT NULL,
  `TeacherId` int(11) DEFAULT NULL,
  `DayOfWeek` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `StartTime` time NOT NULL,
  `EndTime` time NOT NULL,
  `Room` varchar(50) DEFAULT NULL,
  `Status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_timetable_class_day` (`ClassId`, `DayOfWeek`),
  KEY `idx_timetable_teacher_day` (`TeacherId`, `DayOfWeek`),
  CONSTRAINT `fk_timetable_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_timetable_subject` FOREIGN KEY (`SubjectId`) REFERENCES `tblsubjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_timetable_teacher` FOREIGN KEY (`TeacherId`) REFERENCES `tblusers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
