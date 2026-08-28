-- Class Teacher Module schema.
-- Apply after real-srms-foundation.sql.

CREATE TABLE IF NOT EXISTS `tblusers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `FullName` varchar(150) NOT NULL,
  `Username` varchar(100) NOT NULL,
  `Email` varchar(150) DEFAULT NULL,
  `PasswordHash` varchar(255) NOT NULL,
  `Role` enum('class_teacher','subject_teacher','student','parent','accountant') NOT NULL,
  `ClassId` int(11) DEFAULT NULL,
  `StudentId` int(11) DEFAULT NULL,
  `ParentPhone` varchar(30) DEFAULT NULL,
  `Status` tinyint(1) NOT NULL DEFAULT 1,
  `CanCreateAdmin` tinyint(1) NOT NULL DEFAULT 0,
  `CreatedBy` varchar(100) DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`Username`),
  UNIQUE KEY `uk_users_email` (`Email`),
  UNIQUE KEY `uk_users_student` (`StudentId`),
  KEY `idx_users_role_class` (`Role`, `ClassId`),
  CONSTRAINT `fk_users_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_student` FOREIGN KEY (`StudentId`) REFERENCES `tblstudents` (`StudentId`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblteachercomments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `StudentId` int(11) NOT NULL,
  `ClassId` int(11) NOT NULL,
  `ExamId` int(11) DEFAULT NULL,
  `TeacherId` int(11) NOT NULL,
  `CommentText` text NOT NULL,
  `Status` enum('draft','submitted','published') NOT NULL DEFAULT 'draft',
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_teacher_comment_scope` (`StudentId`, `ExamId`, `TeacherId`),
  KEY `idx_teacher_comments_class` (`ClassId`, `ExamId`),
  CONSTRAINT `fk_teacher_comments_student` FOREIGN KEY (`StudentId`) REFERENCES `tblstudents` (`StudentId`) ON DELETE CASCADE,
  CONSTRAINT `fk_teacher_comments_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teacher_comments_exam` FOREIGN KEY (`ExamId`) REFERENCES `tblexams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_teacher_comments_teacher` FOREIGN KEY (`TeacherId`) REFERENCES `tblusers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblresultreviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ClassId` int(11) NOT NULL,
  `ExamId` int(11) NOT NULL,
  `ReviewedBy` int(11) NOT NULL,
  `Status` enum('pending','approved','correction_requested') NOT NULL DEFAULT 'pending',
  `CorrectionReason` text DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_result_review_scope` (`ClassId`, `ExamId`),
  KEY `idx_result_reviews_status` (`Status`),
  CONSTRAINT `fk_result_reviews_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_result_reviews_exam` FOREIGN KEY (`ExamId`) REFERENCES `tblexams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_result_reviews_teacher` FOREIGN KEY (`ReviewedBy`) REFERENCES `tblusers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblteachernotifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `TeacherId` int(11) DEFAULT NULL,
  `ClassId` int(11) DEFAULT NULL,
  `Title` varchar(150) NOT NULL,
  `Message` text NOT NULL,
  `IsRead` tinyint(1) NOT NULL DEFAULT 0,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_teacher_notifications_teacher` (`TeacherId`, `IsRead`),
  KEY `idx_teacher_notifications_class` (`ClassId`, `IsRead`),
  CONSTRAINT `fk_teacher_notifications_teacher` FOREIGN KEY (`TeacherId`) REFERENCES `tblusers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teacher_notifications_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
