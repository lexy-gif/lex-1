-- Legacy seed entry point. Use php scripts/setup.php on a fresh database.
-- Existing data is never cleared; no accounts, grading rules or exams are pre-published.
INSERT INTO tblclasses(ClassName,ClassNameNumeric,GradeId,Section)
SELECT g.Name,g.GradeNumber,g.id,'A' FROM tblgrades g WHERE g.Status=1 AND g.GradeNumber IN (10,11,12)
AND NOT EXISTS(SELECT 1 FROM tblclasses c WHERE c.ClassNameNumeric=g.GradeNumber AND c.Section='A');
