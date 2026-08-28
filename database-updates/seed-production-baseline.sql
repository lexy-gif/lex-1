-- Seed essential SRMS setup data without demo students/results/notices.
-- Use this after clearing data or on a fresh production-style database.

SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO `tblclasses` (`ClassName`, `ClassNameNumeric`, `Section`)
SELECT 'Grade 1', 1, 'A'
WHERE NOT EXISTS (SELECT 1 FROM `tblclasses` WHERE `ClassNameNumeric` = 1 AND `Section` = 'A');

INSERT INTO `tblclasses` (`ClassName`, `ClassNameNumeric`, `Section`)
SELECT 'Grade 2', 2, 'A'
WHERE NOT EXISTS (SELECT 1 FROM `tblclasses` WHERE `ClassNameNumeric` = 2 AND `Section` = 'A');

INSERT INTO `tblclasses` (`ClassName`, `ClassNameNumeric`, `Section`)
SELECT 'Grade 3', 3, 'A'
WHERE NOT EXISTS (SELECT 1 FROM `tblclasses` WHERE `ClassNameNumeric` = 3 AND `Section` = 'A');

INSERT INTO `tblclasses` (`ClassName`, `ClassNameNumeric`, `Section`)
SELECT 'Grade 4', 4, 'A'
WHERE NOT EXISTS (SELECT 1 FROM `tblclasses` WHERE `ClassNameNumeric` = 4 AND `Section` = 'A');

INSERT INTO `tblclasses` (`ClassName`, `ClassNameNumeric`, `Section`)
SELECT 'Grade 5', 5, 'A'
WHERE NOT EXISTS (SELECT 1 FROM `tblclasses` WHERE `ClassNameNumeric` = 5 AND `Section` = 'A');

INSERT INTO `tblclasses` (`ClassName`, `ClassNameNumeric`, `Section`)
SELECT 'Grade 6', 6, 'A'
WHERE NOT EXISTS (SELECT 1 FROM `tblclasses` WHERE `ClassNameNumeric` = 6 AND `Section` = 'A');

INSERT INTO `tblclasses` (`ClassName`, `ClassNameNumeric`, `Section`)
SELECT 'Grade 7', 7, 'A'
WHERE NOT EXISTS (SELECT 1 FROM `tblclasses` WHERE `ClassNameNumeric` = 7 AND `Section` = 'A');

INSERT INTO `tblclasses` (`ClassName`, `ClassNameNumeric`, `Section`)
SELECT 'Grade 8', 8, 'A'
WHERE NOT EXISTS (SELECT 1 FROM `tblclasses` WHERE `ClassNameNumeric` = 8 AND `Section` = 'A');

INSERT INTO `tblclasses` (`ClassName`, `ClassNameNumeric`, `Section`)
SELECT 'Grade 9', 9, 'A'
WHERE NOT EXISTS (SELECT 1 FROM `tblclasses` WHERE `ClassNameNumeric` = 9 AND `Section` = 'A');

SET FOREIGN_KEY_CHECKS = 1;

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
