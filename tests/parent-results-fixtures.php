<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../includes/config.php';require __DIR__.'/../includes/cbe-academics.php';
if(!preg_match('/^srms_parent_test_[a-f0-9]{16}$/D',DB_NAME))throw new RuntimeException('An isolated test database is required.');
$ids=['classes'=>[],'students'=>[],'subjects'=>[],'teachers'=>[],'parents'=>[]];
$dbh->beginTransaction();
foreach([10,11,12] as $n)$ids['classes'][]=(int)academic_query($dbh,'SELECT id FROM tblclasses WHERE ClassNameNumeric=? ORDER BY id LIMIT 1',[$n])->fetchColumn();
$ids['year']=(int)$dbh->query('SELECT id FROM tblacademicyears WHERE IsActive=1')->fetchColumn();
$ids['term']=(int)$dbh->query('SELECT id FROM tblterms WHERE IsActive=1')->fetchColumn();
foreach(['Biology','Chemistry'] as $name)$ids['subjects'][]=(int)academic_query($dbh,'SELECT id FROM tblsubjects WHERE SubjectName=?',[$name])->fetchColumn();
foreach(['class','subject','outsider'] as $role){
    academic_query($dbh,"INSERT INTO tblusers(FullName,Username,PasswordHash,Role) VALUES(?,?,?,'subject_teacher')",['Test '.$role,'test_'.$role,password_hash('Test-teacher-pass-123',PASSWORD_DEFAULT)]);$ids['teachers'][]=(int)$dbh->lastInsertId();
}
academic_query($dbh,'INSERT INTO tblclassteacherassignments(TeacherId,ClassId,AcademicYearId) VALUES(?,?,?)',[$ids['teachers'][0],$ids['classes'][0],$ids['year']]);
foreach([0,1] as $index){
    academic_query($dbh,'INSERT INTO tblsubjectteacherassignments(TeacherId,ClassId,SubjectId,AcademicYearId) VALUES(?,?,?,?)',[$ids['teachers'][1],$ids['classes'][0],$ids['subjects'][$index],$ids['year']]);
    academic_query($dbh,'INSERT INTO tblsubjectcombination(ClassId,SubjectId,status) VALUES(?,?,1)',[$ids['classes'][0],$ids['subjects'][$index]]);
}
foreach(['Alpha Child','Beta Child','Other Child'] as $i=>$name){
    academic_query($dbh,'INSERT INTO tblstudents(StudentName,RollId,ClassId,Status) VALUES(?,?,?,1)',[$name,'PARENT-TEST-'.$i,$ids['classes'][0]]);$student=(int)$dbh->lastInsertId();$ids['students'][]=$student;
    foreach($ids['subjects'] as $subject)academic_query($dbh,'INSERT INTO tblstudentsubjects(StudentId,ClassId,SubjectId,AcademicYearId) VALUES(?,?,?,?)',[$student,$ids['classes'][0],$subject,$ids['year']]);
}
foreach(['guardian','otherguardian'] as $name){academic_query($dbh,"INSERT INTO tblusers(FullName,Username,PasswordHash,Role,ParentPhone) VALUES(?,?,?,'parent',?)",['Test '.$name,$name,password_hash('Test-parent-pass-123',PASSWORD_DEFAULT),'+254700000000']);$ids['parents'][]=(int)$dbh->lastInsertId();}
foreach([0,1] as $i)academic_query($dbh,"INSERT INTO tblparentstudents(ParentId,StudentId,CreatedBy) VALUES(?,?,'test')",[$ids['parents'][0],$ids['students'][$i]]);
academic_query($dbh,"INSERT INTO tblparentstudents(ParentId,StudentId,CreatedBy) VALUES(?,?,'test')",[$ids['parents'][1],$ids['students'][2]]);
academic_query($dbh,"INSERT INTO tblusers(FullName,Username,PasswordHash,Role,StudentId) VALUES('Student account','student_account',?,'student',?)",[password_hash('Test-student-pass-123',PASSWORD_DEFAULT),$ids['students'][0]]);$ids['student_account']=(int)$dbh->lastInsertId();
academic_query($dbh,"INSERT INTO tblexams(AcademicYearId,TermId,ExamName,Status,MaximumMarks) VALUES(?,?,'Publication Test','marks_entry',50)",[$ids['year'],$ids['term']]);$ids['exam']=(int)$dbh->lastInsertId();
$dbh->exec("INSERT INTO tblgradingscales(Grade,MinMark,MaxMark,Remark) VALUES('TEST',0,100,'Synthetic test band')");
$dbh->exec('UPDATE tblusers SET MustChangePassword=0');
$dbh->commit();echo json_encode($ids);
