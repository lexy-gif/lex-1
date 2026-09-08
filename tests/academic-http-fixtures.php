<?php
if(PHP_SAPI!=='cli') exit;
require __DIR__.'/../includes/config.php'; require __DIR__.'/../includes/academic-assignments.php';
$tag=$argv[2]??'';
if(!preg_match('/^academic_http_[a-f0-9]{16}$/D',$tag)) throw new RuntimeException('Invalid fixture identifier');
$mode=$argv[1]??'';
if($mode==='setup') {
    $dbh->beginTransaction();
    academic_query($dbh,'INSERT INTO tblacademicyears(AcademicYear) VALUES(?)',[substr($tag,-16)]); $year=(int)$dbh->lastInsertId();
    academic_query($dbh,'INSERT INTO tblclasses(ClassName,Section) VALUES(?,?)',[$tag,'TEST']); $class=(int)$dbh->lastInsertId();
    academic_query($dbh,'INSERT INTO tblsubjects(SubjectName) VALUES(?)',[$tag]);$subject=(int)$dbh->lastInsertId();
    academic_query($dbh,'INSERT INTO tblsubjectcombination(ClassId,SubjectId,status) VALUES(?,?,1)',[$class,$subject]);
    academic_query($dbh,"INSERT INTO tblusers(FullName,Username,PasswordHash,Role,Status) VALUES(?,?,?,'subject_teacher',1)",[$tag,$tag,password_hash('fixture-only',PASSWORD_DEFAULT)]);$teacher=(int)$dbh->lastInsertId();
    academic_query($dbh,'INSERT INTO tblstudents(StudentName,RollId,ClassId,Status) VALUES(?,?,?,1)',[$tag,$tag,$class]);$student=(int)$dbh->lastInsertId();
    $dbh->commit();echo json_encode(compact('year','class','subject','teacher','student'));
} elseif($mode==='state') {
    $teacher=academic_query($dbh,'SELECT id FROM tblusers WHERE Username=?',[$tag.'_created'])->fetchColumn();
    $assignments=academic_query($dbh,'SELECT a.* FROM tblsubjectteacherassignments a JOIN tblclasses c ON c.id=a.ClassId WHERE c.ClassName=? ORDER BY a.id',[$tag])->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['createdTeacher'=>(int)$teacher,'assignments'=>$assignments]);
} elseif($mode==='cleanup') {
    $dbh->beginTransaction();
    $teachers=academic_query($dbh,'SELECT id FROM tblusers WHERE Username IN (?,?)',[$tag,$tag.'_created'])->fetchAll(PDO::FETCH_COLUMN);
    foreach($teachers as $id) {
        foreach(['tblnotificationdeliveries'=>'UserId','tblnotificationpreferences'=>'UserId','tblteachernotifications'=>'TeacherId','tblteacherresponsibilities'=>'TeacherId','tblsubjectteacherassignments'=>'TeacherId','tblclassteacherassignments'=>'TeacherId'] as $table=>$column) academic_query($dbh,"DELETE FROM $table WHERE $column=?",[$id]);
        academic_query($dbh,'DELETE FROM tblusers WHERE id=?',[$id]);
    }
    $classes=academic_query($dbh,'SELECT id FROM tblclasses WHERE ClassName=?',[$tag])->fetchAll(PDO::FETCH_COLUMN);
    foreach($classes as $id) {
        academic_query($dbh,'DELETE FROM tblstudentsubjects WHERE ClassId=?',[$id]);
        academic_query($dbh,'DELETE FROM tblstudents WHERE ClassId=? AND RollId=?',[$id,$tag]);
        academic_query($dbh,'DELETE FROM tblsubjectcombination WHERE ClassId=?',[$id]);
        academic_query($dbh,'DELETE FROM tblclasses WHERE id=?',[$id]);
    }
    academic_query($dbh,'DELETE FROM tblsubjects WHERE SubjectName=?',[$tag]);
    academic_query($dbh,'DELETE FROM tblacademicyears WHERE AcademicYear=?',[substr($tag,-16)]);
    academic_query($dbh,'DELETE FROM tblauditlog WHERE Actor=?',[$tag]);
    $dbh->commit();echo 'Fixtures removed';
}
