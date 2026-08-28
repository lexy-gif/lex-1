-- Real SRMS foundation tables.
-- Apply after srms.sql. These tables are designed to extend the current app
-- without immediately breaking existing pages.

CREATE TABLE IF NOT EXISTS `tblacademicyears` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `AcademicYear` varchar(20) NOT NULL,
  `StartDate` date DEFAULT NULL,
  `EndDate` date DEFAULT NULL,
  `IsActive` tinyint(1) NOT NULL DEFAULT 0,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_academic_year` (`AcademicYear`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblterms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `AcademicYearId` int(11) NOT NULL,
  `TermName` varchar(50) NOT NULL,
  `StartDate` date DEFAULT NULL,
  `EndDate` date DEFAULT NULL,
  `IsActive` tinyint(1) NOT NULL DEFAULT 0,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_year_term` (`AcademicYearId`, `TermName`),
  KEY `idx_terms_year` (`AcademicYearId`),
  CONSTRAINT `fk_terms_academic_year` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblexams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `AcademicYearId` int(11) NOT NULL,
  `TermId` int(11) NOT NULL,
  `ExamName` varchar(100) NOT NULL,
  `ClassId` int(11) DEFAULT NULL,
  `Status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_exam_scope` (`AcademicYearId`, `TermId`, `ExamName`, `ClassId`),
  KEY `idx_exams_term` (`TermId`),
  KEY `idx_exams_class` (`ClassId`),
  CONSTRAINT `fk_exams_academic_year` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`),
  CONSTRAINT `fk_exams_term` FOREIGN KEY (`TermId`) REFERENCES `tblterms` (`id`),
  CONSTRAINT `fk_exams_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblgradingscales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Grade` varchar(10) NOT NULL,
  `MinMark` decimal(5,2) NOT NULL,
  `MaxMark` decimal(5,2) NOT NULL,
  `Remark` varchar(100) DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_grade` (`Grade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblattendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `StudentId` int(11) NOT NULL,
  `ClassId` int(11) NOT NULL,
  `AttendanceDate` date NOT NULL,
  `Status` enum('present','absent','late','excused') NOT NULL DEFAULT 'present',
  `Remarks` varchar(255) DEFAULT NULL,
  `RecordedBy` int(11) DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_attendance_date` (`StudentId`, `AttendanceDate`),
  KEY `idx_attendance_class_date` (`ClassId`, `AttendanceDate`),
  CONSTRAINT `fk_attendance_student` FOREIGN KEY (`StudentId`) REFERENCES `tblstudents` (`StudentId`),
  CONSTRAINT `fk_attendance_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblfeetypes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `FeeName` varchar(100) NOT NULL,
  `Description` varchar(255) DEFAULT NULL,
  `Status` tinyint(1) NOT NULL DEFAULT 1,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fee_name` (`FeeName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblfeestructures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ClassId` int(11) NOT NULL,
  `FeeTypeId` int(11) NOT NULL,
  `AcademicYearId` int(11) NOT NULL,
  `TermId` int(11) DEFAULT NULL,
  `Amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `DueDate` date DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fee_structure` (`ClassId`, `FeeTypeId`, `AcademicYearId`, `TermId`),
  KEY `idx_fee_structure_year` (`AcademicYearId`),
  KEY `idx_fee_structure_term` (`TermId`),
  CONSTRAINT `fk_fee_structure_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`),
  CONSTRAINT `fk_fee_structure_type` FOREIGN KEY (`FeeTypeId`) REFERENCES `tblfeetypes` (`id`),
  CONSTRAINT `fk_fee_structure_year` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`),
  CONSTRAINT `fk_fee_structure_term` FOREIGN KEY (`TermId`) REFERENCES `tblterms` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblpayments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `StudentId` int(11) NOT NULL,
  `FeeStructureId` int(11) NOT NULL,
  `AmountPaid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `PaymentDate` date NOT NULL,
  `PaymentMode` varchar(50) DEFAULT NULL,
  `ReceiptNo` varchar(50) NOT NULL,
  `ReceivedBy` int(11) DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_receipt_no` (`ReceiptNo`),
  KEY `idx_payments_student` (`StudentId`),
  KEY `idx_payments_fee_structure` (`FeeStructureId`),
  CONSTRAINT `fk_payments_student` FOREIGN KEY (`StudentId`) REFERENCES `tblstudents` (`StudentId`),
  CONSTRAINT `fk_payments_fee_structure` FOREIGN KEY (`FeeStructureId`) REFERENCES `tblfeestructures` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblauditlog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Actor` varchar(100) DEFAULT NULL,
  `Action` varchar(100) NOT NULL,
  `EntityType` varchar(100) DEFAULT NULL,
  `EntityId` varchar(100) DEFAULT NULL,
  `Details` text DEFAULT NULL,
  `IpAddress` varchar(45) DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_entity` (`EntityType`, `EntityId`),
  KEY `idx_audit_action_date` (`Action`, `CreationDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `tblresult`
  ADD COLUMN `ExamId` int(11) DEFAULT NULL AFTER `ClassId`,
  ADD KEY `idx_result_exam` (`ExamId`),
  ADD UNIQUE KEY `uk_result_student_subject_exam` (`StudentId`, `ClassId`, `SubjectId`, `ExamId`);

ALTER TABLE `tblresult`
  ADD CONSTRAINT `fk_result_exam` FOREIGN KEY (`ExamId`) REFERENCES `tblexams` (`id`);

INSERT IGNORE INTO `tblacademicyears` (`AcademicYear`, `IsActive`)
VALUES ('2026', 1);

INSERT IGNORE INTO `tblterms` (`AcademicYearId`, `TermName`, `IsActive`)
SELECT `id`, 'Term 1', 1
FROM `tblacademicyears`
WHERE `AcademicYear` = '2026';

INSERT IGNORE INTO `tblexams` (`AcademicYearId`, `TermId`, `ExamName`, `ClassId`, `Status`)
SELECT ay.`id`, t.`id`, 'Term 1 Exam', NULL, 'published'
FROM `tblacademicyears` ay
JOIN `tblterms` t ON t.`AcademicYearId` = ay.`id`
WHERE ay.`AcademicYear` = '2026' AND t.`TermName` = 'Term 1';

INSERT IGNORE INTO `tblgradingscales` (`Grade`, `MinMark`, `MaxMark`, `Remark`) VALUES
('A', 80.00, 100.00, 'Excellent'),
('B', 70.00, 79.99, 'Very Good'),
('C', 60.00, 69.99, 'Good'),
('D', 50.00, 59.99, 'Fair'),
('E', 0.00, 49.99, 'Needs Improvement');
