<?php
// Integration tests run inside one rolled-back transaction; no notifications sent.
if(PHP_SAPI!=='cli') exit;
require __DIR__.'/../includes/config.php'; require __DIR__.'/../includes/academic-assignments.php';
function check($condition,$message) { if(!$condition) throw new RuntimeException($message); echo "PASS: $message\n"; }
function rejects($callback,$message) { try {$callback();} catch(DomainException $e) {check(true,$message);return;} throw new RuntimeException($message); }
$dbh->beginTransaction();
try {
    $tag='test_'.bin2hex(random_bytes(5));
    academic_query($dbh,'INSERT INTO tblacademicyears(AcademicYear) VALUES(?)',[$tag]);$year=(int)$dbh->lastInsertId();
    academic_query($dbh,'INSERT INTO tblterms(AcademicYearId,TermName) VALUES(?,?)',[$year,'Term 1']);$term=(int)$dbh->lastInsertId();
    academic_query($dbh,'INSERT INTO tblclasses(ClassName,Section) VALUES(?,?)',[$tag,'T']);$class=(int)$dbh->lastInsertId();
    academic_query($dbh,'INSERT INTO tblclasses(ClassName,Section) VALUES(?,?)',[$tag.'2','T']);$class2=(int)$dbh->lastInsertId();
    academic_query($dbh,'INSERT INTO tblsubjects(SubjectName) VALUES(?)',[$tag]);$subject=(int)$dbh->lastInsertId();
    foreach([$class,$class2] as $c) academic_query($dbh,'INSERT INTO tblsubjectcombination(ClassId,SubjectId,status) VALUES(?,?,1)',[$c,$subject]);
    $teachers=[];
    for($i=0;$i<3;$i++) { academic_query($dbh,"INSERT INTO tblusers(FullName,Username,PasswordHash,Role,Status) VALUES(?,?,?,'subject_teacher',?)",[$tag.$i,$tag.$i,password_hash('test-only',PASSWORD_DEFAULT),$i===2?0:1]);$teachers[]=(int)$dbh->lastInsertId(); }
    [$a,$b,$inactive]=$teachers;
    academic_assign($dbh,'subject',$a,$class,$subject,$year,$term);
    academic_assign($dbh,'subject',$a,$class2,$subject,$year,$term);
    academic_assign($dbh,'class',$a,$class,0,$year);
    check((int)academic_query($dbh,'SELECT COUNT(*) FROM tblsubjectteacherassignments WHERE TeacherId=? AND Status=1',[$a])->fetchColumn()===2,'One teacher teaches multiple classes and is also a class teacher');
    rejects(function()use($dbh,$a,$class,$subject,$year,$term){academic_assign($dbh,'subject',$a,$class,$subject,$year,$term);},'Exact duplicate rejected');
    rejects(function()use($dbh,$inactive,$class,$subject,$year,$term){academic_assign($dbh,'subject',$inactive,$class,$subject,$year,$term);},'Inactive teacher rejected');
    rejects(function()use($dbh,$a,$class,$subject,$year){academic_assign($dbh,'subject',$a,$class,$subject,$year,999999);},'Invalid term rejected');
    rejects(function()use($dbh,$a,$class,$year){academic_assign($dbh,'subject',$a,$class,999999,$year);},'Unoffered subject rejected');
    try {academic_assign($dbh,'subject',$b,$class,$subject,$year,null);throw new RuntimeException('Missing conflict');} catch(AcademicConflict $e) {$key=$e->assignmentKey;}
    academic_assign($dbh,'subject',$b,$class,$subject,$year,null,[$key]);
    check((int)academic_query($dbh,'SELECT Status FROM tblsubjectteacherassignments WHERE id=?',[(int)explode(':',$key)[1]])->fetchColumn()===0,'Whole-year replacement confirms overlapping term and preserves history');
    try {academic_assign($dbh,'class',$b,$class,0,$year);throw new RuntimeException('Missing class conflict');} catch(AcademicConflict $e) {$key=$e->assignmentKey;}
    academic_assign($dbh,'class',$b,$class,0,$year,null,[$key]);
    check((int)academic_query($dbh,'SELECT COUNT(*) FROM tblclassteacherassignments WHERE ClassId=? AND AcademicYearId=? AND Status=1',[$class,$year])->fetchColumn()===1,'Class replacement leaves exactly one active class teacher');
    $students=[];
    foreach([1,1,0] as $i=>$status) {academic_query($dbh,'INSERT INTO tblstudents(StudentName,RollId,ClassId,Status) VALUES(?,?,?,?)',[$tag.$i,$tag.$i,$class,$status]);$students[]=(int)$dbh->lastInsertId();}
    academic_register_student($dbh,$students[0],$year,[$subject]);
    check(count(academic_students($dbh,$class,$subject,$year))===1,'Subject teacher sees only registered active students');
    check(count(academic_students($dbh,$class,0,$year))===2,'Class teacher sees all active class students');
    rejects(function()use($dbh,$students,$year,$subject){academic_register_student($dbh,$students[2],$year,[$subject]);},'Inactive student registration rejected');
    rejects(function()use($dbh,$students,$year){academic_register_student($dbh,$students[0],$year,[999999]);},'Student subject must be offered by class');
    academic_register_student($dbh,$students[0],$year,[]);
    check(count(academic_students($dbh,$class,$subject,$year))===0,'Removing a student subject removes subject teacher visibility');
    $types=$dbh->query('SELECT id FROM tblresponsibilitytypes WHERE Active=1 LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
    foreach($types as $type) academic_responsibility($dbh,$a,(int)$type,$year,'2026-01-01',null,'Test');
    check((int)academic_query($dbh,'SELECT COUNT(*) FROM tblteacherresponsibilities WHERE TeacherId=?',[$a])->fetchColumn()===2,'Multiple responsibilities supported');
    // Refresh class access from relationships, independent of a stale session class or account category.
    require __DIR__.'/../includes/teacher-auth.php';
    academic_query($dbh,'UPDATE tblacademicyears SET IsActive=0');academic_query($dbh,'UPDATE tblacademicyears SET IsActive=1 WHERE id=?',[$year]);
    $_SESSION=['teacher_user_id'=>$a,'teacher_role'=>'subject_teacher','teacher_class_id'=>$class];
    check(teacher_class_id()===0,'Reassigned teacher loses stale session class access');
    $_SESSION['teacher_user_id']=$b;
    check(teacher_class_id()===$class,'Subject teacher account gains class access through assignment');
    $dbh->rollBack(); echo "All integration tests passed; test records rolled back.\n";
} catch(Throwable $e) {if($dbh->inTransaction())$dbh->rollBack();fwrite(STDERR,$e."\n");exit(1);}
