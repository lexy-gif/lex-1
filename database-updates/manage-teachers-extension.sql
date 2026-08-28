-- Manage Teachers page extension.
-- Apply after teacher-notification-system.sql.

DROP PROCEDURE IF EXISTS add_column_if_missing;

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

DELIMITER ;

CALL add_column_if_missing('tblusers', 'LastLoginAt', 'ALTER TABLE `tblusers` ADD COLUMN `LastLoginAt` datetime DEFAULT NULL AFTER `UpdationDate`');

ALTER TABLE `tblusers`
  MODIFY `Role` enum('class_teacher','subject_teacher','head_of_department','exams_officer','deputy_dean','student','parent','accountant') NOT NULL;

DROP PROCEDURE IF EXISTS add_column_if_missing;
