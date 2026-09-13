-- Run through scripts/migrate-guardian-access.php, which locks and records this migration.
-- Academic relationships already use tblstudents.StudentId. Keep legacy user IDs,
-- StudentId mappings, hashes and historical notification references as inactive archives.
-- Preserve only existing, explicit parent links; never infer them from student credentials.
INSERT IGNORE INTO tblparentstudents(ParentId,StudentId,CreatedBy)
 SELECT u.id,u.StudentId,'migration:existing-parent-link'
 FROM tblusers u JOIN tblstudents s ON s.StudentId=u.StudentId
 WHERE u.Role='parent' AND u.StudentId IS NOT NULL;

UPDATE tblnotificationdeliveries d JOIN tblusers u ON u.id=d.UserId
 SET d.Status='SKIPPED',d.ErrorMessage='Student account access retired. Guardian notifications use verified links.'
 WHERE u.Role='student' AND d.Status IN ('PENDING','RETRYING');

UPDATE tblusers SET Status=0,SessionVersion=SessionVersion+1
 WHERE Role='student' AND Status<>0;
