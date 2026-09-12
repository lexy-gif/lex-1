<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/config.php';require __DIR__.'/../includes/cbe-academics.php';require __DIR__.'/../includes/dean-account.php';
$name='senior-integrity-v1';
$lock='srms:integrity:'.substr(hash('sha256',DB_NAME),0,32);
if((int)academic_query($dbh,'SELECT GET_LOCK(?,30)',[$lock])->fetchColumn()!==1)throw new RuntimeException('Another integrity migration is running. Try again after it finishes.');
register_shutdown_function(function()use($dbh,$lock){academic_query($dbh,'SELECT RELEASE_LOCK(?)',[$lock]);});
if(academic_query($dbh,'SELECT 1 FROM tblschemamigrations WHERE Name=?',[$name])->fetchColumn()){echo "Integrity migration already applied.\n";exit;}
$checks=[
 'Duplicate admission numbers'=>'SELECT COUNT(*) FROM (SELECT RollId FROM tblstudents WHERE RollId IS NOT NULL GROUP BY RollId HAVING COUNT(*)>1) d',
 'Orphan student class'=>'SELECT COUNT(*) FROM tblstudents s LEFT JOIN tblclasses c ON c.id=s.ClassId WHERE s.ClassId IS NOT NULL AND c.id IS NULL',
 'Orphan result student'=>'SELECT COUNT(*) FROM tblresult r LEFT JOIN tblstudents s ON s.StudentId=r.StudentId WHERE r.StudentId IS NOT NULL AND s.StudentId IS NULL',
 'Orphan result class'=>'SELECT COUNT(*) FROM tblresult r LEFT JOIN tblclasses c ON c.id=r.ClassId WHERE r.ClassId IS NOT NULL AND c.id IS NULL',
 'Orphan result subject'=>'SELECT COUNT(*) FROM tblresult r LEFT JOIN tblsubjects s ON s.id=r.SubjectId WHERE r.SubjectId IS NOT NULL AND s.id IS NULL'
];
foreach($checks as $label=>$sql)if($dbh->query($sql)->fetchColumn())throw new RuntimeException($label.': resolve the existing records before applying constraints; no records have been deleted.');
function integrity_index($db,$table,$name,$sql){if(!academic_query($db,'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[$table,$name])->fetchColumn())$db->exec($sql);}
function integrity_fk($db,$table,$name,$column,$target,$key){if(!academic_query($db,'SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME=?',[$name])->fetchColumn())$db->exec("ALTER TABLE $table ADD CONSTRAINT $name FOREIGN KEY ($column) REFERENCES $target($key)");}
integrity_index($dbh,'tblstudents','uk_student_admission','ALTER TABLE tblstudents ADD UNIQUE KEY uk_student_admission(RollId)');
integrity_fk($dbh,'tblstudents','fk_student_class','ClassId','tblclasses','id');
integrity_fk($dbh,'tblresult','fk_result_student','StudentId','tblstudents','StudentId');
integrity_fk($dbh,'tblresult','fk_result_class','ClassId','tblclasses','id');
integrity_fk($dbh,'tblresult','fk_result_subject','SubjectId','tblsubjects','id');
$table=dean_account_table($dbh);$dbh->exec("ALTER TABLE `$table` MODIFY Password VARCHAR(255)");
integrity_index($dbh,$table,'uk_dean_username',"ALTER TABLE `$table` ADD UNIQUE KEY uk_dean_username(UserName)");
if(!academic_query($dbh,"SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblresultpublications' AND COLUMN_NAME='GradeScale'")->fetchColumn())$dbh->exec('ALTER TABLE tblresultpublications ADD GradeScale LONGTEXT NULL');
academic_query($dbh,'INSERT INTO tblschemamigrations(Name) VALUES(?)',[$name]);echo "Admission uniqueness, historical foreign keys and report grading snapshots installed.\n";
