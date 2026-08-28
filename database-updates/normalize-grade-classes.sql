-- Normalize class names to Grade 1 through Grade 9 for an existing database.

UPDATE `tblclasses`
SET `ClassName` = CONCAT('Grade ', `ClassNameNumeric`),
    `UpdationDate` = CURRENT_TIMESTAMP
WHERE `ClassNameNumeric` BETWEEN 1 AND 9;

UPDATE `tblclasses`
SET `ClassName` = 'Grade 9',
    `ClassNameNumeric` = 9,
    `Section` = 'A',
    `UpdationDate` = CURRENT_TIMESTAMP
WHERE `ClassNameNumeric` = 10 OR `ClassName` IN ('Tenth', 'Ten', 'Grade 10');

INSERT INTO `tblclasses` (`ClassName`, `ClassNameNumeric`, `Section`)
SELECT 'Grade 3', 3, 'A'
WHERE NOT EXISTS (
  SELECT 1 FROM `tblclasses` WHERE `ClassNameNumeric` = 3
);

INSERT INTO `tblclasses` (`ClassName`, `ClassNameNumeric`, `Section`)
SELECT 'Grade 5', 5, 'A'
WHERE NOT EXISTS (
  SELECT 1 FROM `tblclasses` WHERE `ClassNameNumeric` = 5
);
