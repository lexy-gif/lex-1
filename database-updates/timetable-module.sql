-- Automated Timetable Management Module schema.
-- Apply after dean-of-studies-module.sql.

CREATE TABLE IF NOT EXISTS `tblrooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `RoomName` varchar(80) NOT NULL,
  `Capacity` int(11) DEFAULT NULL,
  `Status` tinyint(1) NOT NULL DEFAULT 1,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_room_name` (`RoomName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tbltimetableperiods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `PeriodName` varchar(80) NOT NULL,
  `DayOfWeek` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `StartTime` time NOT NULL,
  `EndTime` time NOT NULL,
  `PeriodType` enum('lesson','break','lunch','assembly','games','club','activity') NOT NULL DEFAULT 'lesson',
  `Status` tinyint(1) NOT NULL DEFAULT 1,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_timetable_period` (`DayOfWeek`, `StartTime`, `EndTime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblclasstimetableentries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `AcademicYearId` int(11) NOT NULL,
  `TermId` int(11) NOT NULL,
  `ClassId` int(11) NOT NULL,
  `SubjectId` int(11) NOT NULL,
  `TeacherId` int(11) DEFAULT NULL,
  `RoomId` int(11) DEFAULT NULL,
  `DayOfWeek` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `StartTime` time NOT NULL,
  `EndTime` time NOT NULL,
  `Status` enum('draft','ready_for_review','approved','published','updated','archived','cancelled') NOT NULL DEFAULT 'draft',
  `ChangeReason` varchar(255) DEFAULT NULL,
  `CreatedBy` varchar(100) DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_class_timetable_class` (`AcademicYearId`, `TermId`, `ClassId`, `DayOfWeek`),
  KEY `idx_class_timetable_teacher` (`TeacherId`, `DayOfWeek`, `StartTime`, `EndTime`),
  KEY `idx_class_timetable_room` (`RoomId`, `DayOfWeek`, `StartTime`, `EndTime`),
  CONSTRAINT `fk_cte_year` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cte_term` FOREIGN KEY (`TermId`) REFERENCES `tblterms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cte_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cte_subject` FOREIGN KEY (`SubjectId`) REFERENCES `tblsubjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cte_teacher` FOREIGN KEY (`TeacherId`) REFERENCES `tblusers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cte_room` FOREIGN KEY (`RoomId`) REFERENCES `tblrooms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblexamtimetableentries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ExamId` int(11) NOT NULL,
  `ClassId` int(11) NOT NULL,
  `SubjectId` int(11) NOT NULL,
  `RoomId` int(11) DEFAULT NULL,
  `InvigilatorId` int(11) DEFAULT NULL,
  `ExamDate` date NOT NULL,
  `StartTime` time NOT NULL,
  `EndTime` time NOT NULL,
  `Status` enum('draft','ready_for_review','approved','published','updated','archived','cancelled') NOT NULL DEFAULT 'draft',
  `ChangeReason` varchar(255) DEFAULT NULL,
  `CreatedBy` varchar(100) DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_exam_timetable_class` (`ExamId`, `ClassId`, `ExamDate`),
  KEY `idx_exam_timetable_room` (`RoomId`, `ExamDate`, `StartTime`, `EndTime`),
  KEY `idx_exam_timetable_invigilator` (`InvigilatorId`, `ExamDate`, `StartTime`, `EndTime`),
  CONSTRAINT `fk_ete_exam` FOREIGN KEY (`ExamId`) REFERENCES `tblexams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ete_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ete_subject` FOREIGN KEY (`SubjectId`) REFERENCES `tblsubjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ete_room` FOREIGN KEY (`RoomId`) REFERENCES `tblrooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ete_invigilator` FOREIGN KEY (`InvigilatorId`) REFERENCES `tblusers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblteacheravailability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `TeacherId` int(11) NOT NULL,
  `DayOfWeek` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `StartTime` time NOT NULL,
  `EndTime` time NOT NULL,
  `Reason` varchar(255) DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_teacher_availability` (`TeacherId`, `DayOfWeek`, `StartTime`, `EndTime`),
  CONSTRAINT `fk_teacher_availability_teacher` FOREIGN KEY (`TeacherId`) REFERENCES `tblusers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tbltimetableversions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `TimetableType` enum('class','exam') NOT NULL,
  `RelatedId` int(11) DEFAULT NULL,
  `VersionNo` int(11) NOT NULL DEFAULT 1,
  `Action` varchar(80) NOT NULL,
  `PreviousValue` text DEFAULT NULL,
  `NewValue` text DEFAULT NULL,
  `Reason` varchar(255) DEFAULT NULL,
  `CreatedBy` varchar(100) DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_timetable_versions_related` (`TimetableType`, `RelatedId`, `VersionNo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblscheduledreminders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ReminderType` enum('lesson','exam') NOT NULL,
  `RelatedId` int(11) NOT NULL,
  `RecipientUserId` int(11) DEFAULT NULL,
  `ReminderMinutes` int(11) NOT NULL DEFAULT 15,
  `SentAt` timestamp NULL DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_scheduled_reminder` (`ReminderType`, `RelatedId`, `RecipientUserId`, `ReminderMinutes`),
  CONSTRAINT `fk_scheduled_reminder_user` FOREIGN KEY (`RecipientUserId`) REFERENCES `tblusers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `tblrooms` (`RoomName`) VALUES
('Room 1'), ('Room 2'), ('Room 3'), ('Room 4'), ('Science Lab'), ('Computer Lab');

INSERT IGNORE INTO `tbltimetableperiods` (`PeriodName`, `DayOfWeek`, `StartTime`, `EndTime`, `PeriodType`) VALUES
('Period 1', 'Monday', '08:00:00', '08:40:00', 'lesson'),
('Period 2', 'Monday', '08:40:00', '09:20:00', 'lesson'),
('Break', 'Monday', '09:20:00', '09:40:00', 'break'),
('Period 3', 'Monday', '09:40:00', '10:20:00', 'lesson'),
('Period 4', 'Monday', '10:20:00', '11:00:00', 'lesson'),
('Period 1', 'Tuesday', '08:00:00', '08:40:00', 'lesson'),
('Period 2', 'Tuesday', '08:40:00', '09:20:00', 'lesson'),
('Break', 'Tuesday', '09:20:00', '09:40:00', 'break'),
('Period 3', 'Tuesday', '09:40:00', '10:20:00', 'lesson'),
('Period 4', 'Tuesday', '10:20:00', '11:00:00', 'lesson'),
('Period 1', 'Wednesday', '08:00:00', '08:40:00', 'lesson'),
('Period 2', 'Wednesday', '08:40:00', '09:20:00', 'lesson'),
('Break', 'Wednesday', '09:20:00', '09:40:00', 'break'),
('Period 3', 'Wednesday', '09:40:00', '10:20:00', 'lesson'),
('Period 4', 'Wednesday', '10:20:00', '11:00:00', 'lesson'),
('Period 1', 'Thursday', '08:00:00', '08:40:00', 'lesson'),
('Period 2', 'Thursday', '08:40:00', '09:20:00', 'lesson'),
('Break', 'Thursday', '09:20:00', '09:40:00', 'break'),
('Period 3', 'Thursday', '09:40:00', '10:20:00', 'lesson'),
('Period 4', 'Thursday', '10:20:00', '11:00:00', 'lesson'),
('Period 1', 'Friday', '08:00:00', '08:40:00', 'lesson'),
('Period 2', 'Friday', '08:40:00', '09:20:00', 'lesson'),
('Break', 'Friday', '09:20:00', '09:40:00', 'break'),
('Period 3', 'Friday', '09:40:00', '10:20:00', 'lesson'),
('Period 4', 'Friday', '10:20:00', '11:00:00', 'lesson');
