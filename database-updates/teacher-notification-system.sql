-- Teacher Account and Notification System extension.
-- Apply after class-teacher-module.sql and timetable-module.sql.

DROP PROCEDURE IF EXISTS add_column_if_missing;
DROP PROCEDURE IF EXISTS add_index_if_missing;

DELIMITER $$

CREATE PROCEDURE add_column_if_missing(IN table_name_param varchar(64), IN column_name_param varchar(64), IN alter_sql text)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = table_name_param
      AND COLUMN_NAME = column_name_param
  ) THEN
    SET @stmt = alter_sql;
    PREPARE prepared_stmt FROM @stmt;
    EXECUTE prepared_stmt;
    DEALLOCATE PREPARE prepared_stmt;
  END IF;
END$$

CREATE PROCEDURE add_index_if_missing(IN table_name_param varchar(64), IN index_name_param varchar(64), IN alter_sql text)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = table_name_param
      AND INDEX_NAME = index_name_param
  ) THEN
    SET @stmt = alter_sql;
    PREPARE prepared_stmt FROM @stmt;
    EXECUTE prepared_stmt;
    DEALLOCATE PREPARE prepared_stmt;
  END IF;
END$$

DELIMITER ;

CALL add_column_if_missing('tblusers', 'FirstName', 'ALTER TABLE `tblusers` ADD COLUMN `FirstName` varchar(80) DEFAULT NULL AFTER `id`');
CALL add_column_if_missing('tblusers', 'MiddleName', 'ALTER TABLE `tblusers` ADD COLUMN `MiddleName` varchar(80) DEFAULT NULL AFTER `FirstName`');
CALL add_column_if_missing('tblusers', 'LastName', 'ALTER TABLE `tblusers` ADD COLUMN `LastName` varchar(80) DEFAULT NULL AFTER `MiddleName`');
CALL add_column_if_missing('tblusers', 'StaffNumber', 'ALTER TABLE `tblusers` ADD COLUMN `StaffNumber` varchar(50) DEFAULT NULL AFTER `LastName`');
CALL add_column_if_missing('tblusers', 'PhoneNumber', 'ALTER TABLE `tblusers` ADD COLUMN `PhoneNumber` varchar(30) DEFAULT NULL AFTER `Email`');
CALL add_column_if_missing('tblusers', 'Department', 'ALTER TABLE `tblusers` ADD COLUMN `Department` varchar(100) DEFAULT NULL AFTER `Role`');
CALL add_column_if_missing('tblusers', 'EmailStatus', 'ALTER TABLE `tblusers` ADD COLUMN `EmailStatus` enum(''UNVERIFIED'',''VERIFIED'',''BOUNCED'',''DISABLED'') NOT NULL DEFAULT ''UNVERIFIED'' AFTER `Email`');
CALL add_column_if_missing('tblusers', 'EmailVerifiedAt', 'ALTER TABLE `tblusers` ADD COLUMN `EmailVerifiedAt` datetime DEFAULT NULL AFTER `EmailStatus`');
CALL add_column_if_missing('tblusers', 'MustChangePassword', 'ALTER TABLE `tblusers` ADD COLUMN `MustChangePassword` tinyint(1) NOT NULL DEFAULT 1 AFTER `PasswordHash`');

UPDATE `tblusers`
SET `Email` = LOWER(TRIM(`Email`))
WHERE `Email` IS NOT NULL AND `Email` <> '';

CALL add_index_if_missing('tblusers', 'uk_users_staff_number', 'ALTER TABLE `tblusers` ADD UNIQUE KEY `uk_users_staff_number` (`StaffNumber`)');

CALL add_column_if_missing('tblteachernotifications', 'Type', 'ALTER TABLE `tblteachernotifications` ADD COLUMN `Type` varchar(80) NOT NULL DEFAULT ''GENERAL'' AFTER `ClassId`');
CALL add_column_if_missing('tblteachernotifications', 'Category', 'ALTER TABLE `tblteachernotifications` ADD COLUMN `Category` enum(''ACCOUNT'',''CLASS_ASSIGNMENT'',''SUBJECT_ASSIGNMENT'',''TIMETABLE'',''EXAM_TIMETABLE'',''RESULTS'',''MARKS'',''DEADLINE'',''ATTENDANCE'',''SYSTEM'') NOT NULL DEFAULT ''SYSTEM'' AFTER `Type`');
CALL add_column_if_missing('tblteachernotifications', 'SubjectId', 'ALTER TABLE `tblteachernotifications` ADD COLUMN `SubjectId` int(11) DEFAULT NULL AFTER `Category`');
CALL add_column_if_missing('tblteachernotifications', 'RelatedEntityType', 'ALTER TABLE `tblteachernotifications` ADD COLUMN `RelatedEntityType` varchar(80) DEFAULT NULL AFTER `SubjectId`');
CALL add_column_if_missing('tblteachernotifications', 'RelatedEntityId', 'ALTER TABLE `tblteachernotifications` ADD COLUMN `RelatedEntityId` int(11) DEFAULT NULL AFTER `RelatedEntityType`');
CALL add_column_if_missing('tblteachernotifications', 'ActionUrl', 'ALTER TABLE `tblteachernotifications` ADD COLUMN `ActionUrl` varchar(255) DEFAULT NULL AFTER `RelatedEntityId`');
CALL add_column_if_missing('tblteachernotifications', 'ReadAt', 'ALTER TABLE `tblteachernotifications` ADD COLUMN `ReadAt` datetime DEFAULT NULL AFTER `IsRead`');

UPDATE `tblteachernotifications`
SET `ReadAt` = `CreationDate`
WHERE `IsRead` = 1 AND `ReadAt` IS NULL;

CALL add_index_if_missing('tblteachernotifications', 'idx_teacher_notifications_filter', 'ALTER TABLE `tblteachernotifications` ADD KEY `idx_teacher_notifications_filter` (`TeacherId`, `Category`, `ReadAt`, `CreationDate`)');
CALL add_index_if_missing('tblteachernotifications', 'idx_teacher_notifications_subject', 'ALTER TABLE `tblteachernotifications` ADD KEY `idx_teacher_notifications_subject` (`SubjectId`)');

CREATE TABLE IF NOT EXISTS `tblnotificationdeliveries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `NotificationId` int(11) DEFAULT NULL,
  `UserId` int(11) NOT NULL,
  `Channel` enum('IN_APP','EMAIL','SMS','WHATSAPP','PUSH') NOT NULL,
  `Destination` varchar(180) DEFAULT NULL,
  `Status` enum('PENDING','SENT','FAILED','RETRYING','SKIPPED') NOT NULL DEFAULT 'PENDING',
  `SentAt` datetime DEFAULT NULL,
  `FailedAt` datetime DEFAULT NULL,
  `RetryCount` int(11) NOT NULL DEFAULT 0,
  `ErrorMessage` text DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notification_deliveries_notification` (`NotificationId`),
  KEY `idx_notification_deliveries_user` (`UserId`, `Channel`, `Status`),
  CONSTRAINT `fk_notification_deliveries_notification` FOREIGN KEY (`NotificationId`) REFERENCES `tblteachernotifications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notification_deliveries_user` FOREIGN KEY (`UserId`) REFERENCES `tblusers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblnotificationpreferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `UserId` int(11) NOT NULL,
  `Category` enum('ACCOUNT','CLASS_ASSIGNMENT','SUBJECT_ASSIGNMENT','TIMETABLE','EXAM_TIMETABLE','RESULTS','MARKS','DEADLINE','ATTENDANCE','SYSTEM') NOT NULL,
  `EmailEnabled` tinyint(1) NOT NULL DEFAULT 1,
  `InAppEnabled` tinyint(1) NOT NULL DEFAULT 1,
  `CreationDate` timestamp NULL DEFAULT current_timestamp(),
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_notification_preferences_user_category` (`UserId`, `Category`),
  CONSTRAINT `fk_notification_preferences_user` FOREIGN KEY (`UserId`) REFERENCES `tblusers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP PROCEDURE IF EXISTS add_column_if_missing;
DROP PROCEDURE IF EXISTS add_index_if_missing;
