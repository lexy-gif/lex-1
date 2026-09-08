<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../includes/config.php';
require __DIR__.'/../includes/cbe-timetable.php';
$tag=$argv[2]??'';$mode=$argv[1]??'';
if(!preg_match('/^timetable_[a-f0-9]{16}$/D',$tag))throw new RuntimeException('Invalid fixture identifier.');
$_SESSION=['alogin'=>$tag];
$dbh->beginTransaction();
try {
    if($mode==='setup') {
        academic_query($dbh,'INSERT INTO tblacademicyears(AcademicYear) VALUES(?)',[substr($tag,-16)]);$year=(int)$dbh->lastInsertId();
        $terms=[];
        foreach([1,0] as $i=>$active){academic_query($dbh,'INSERT INTO tblterms(AcademicYearId,TermName,IsActive) VALUES(?,?,?)',[$year,$tag.'_'.$i,$active]);$terms[]=(int)$dbh->lastInsertId();}
        $classes=[];$rooms=[];$teachers=[];
        academic_query($dbh,'INSERT INTO tblsubjects(SubjectName,Status) VALUES(?,1)',[$tag]);$subject=(int)$dbh->lastInsertId();
        academic_query($dbh,'INSERT INTO tblsubjects(SubjectName,Status) VALUES(?,1)',[$tag.'_other']);$otherSubject=(int)$dbh->lastInsertId();
        for($i=0;$i<3;$i++) {
            academic_query($dbh,'INSERT INTO tblclasses(ClassName,Section) VALUES(?,?)',[$tag.'_'.$i,'TEST']);$classes[]=(int)$dbh->lastInsertId();
            academic_query($dbh,'INSERT INTO tblsubjectcombination(ClassId,SubjectId,status) VALUES(?,?,1)',[$classes[$i],$subject]);
            academic_query($dbh,'INSERT INTO tblsubjectcombination(ClassId,SubjectId,status) VALUES(?,?,1)',[$classes[$i],$otherSubject]);
            academic_query($dbh,'INSERT INTO tblrooms(RoomName) VALUES(?)',[$tag.'_'.$i]);$rooms[]=(int)$dbh->lastInsertId();
        }
        for($i=0;$i<5;$i++) {
            academic_query($dbh,"INSERT INTO tblusers(FullName,Username,PasswordHash,Role,Status) VALUES(?,?,?,'subject_teacher',?)",[$tag.'_'.$i,$tag.'_'.$i,password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),$i===4?0:1]);$teachers[]=(int)$dbh->lastInsertId();
            foreach(['SYSTEM','TIMETABLE','EXAM_TIMETABLE'] as $category)academic_query($dbh,'INSERT INTO tblnotificationpreferences(UserId,Category,InAppEnabled,EmailEnabled) VALUES(?,?,1,0)',[$teachers[$i],$category]);
        }
        foreach([[0,0,$subject],[0,1,$subject],[1,1,$otherSubject],[2,2,$subject]] as [$t,$c,$s])academic_query($dbh,'INSERT INTO tblsubjectteacherassignments(TeacherId,ClassId,SubjectId,AcademicYearId) VALUES(?,?,?,?)',[$teachers[$t],$classes[$c],$s,$year]);
        academic_assign($dbh,'class',$teachers[3],$classes[0],null,$year);
        $active=timetable_active_period($dbh);
        if(!$active)throw new RuntimeException('An active school year and term are required for teacher timetable validation.');
        academic_assign($dbh,'class',$teachers[3],$classes[0],null,$active->AcademicYearId);
        academic_query($dbh,"INSERT INTO tblteacheravailability(TeacherId,DayOfWeek,StartTime,EndTime,Reason) VALUES(?,'Monday','12:00','13:00',?)",[$teachers[2],$tag]);
        $date='2099-05-04';$exams=[];
        foreach([$classes[0],$classes[1],null] as $i=>$class) {
            academic_query($dbh,"INSERT INTO tblexams(AcademicYearId,TermId,ClassId,ExamName,StartDate,EndDate) VALUES(?,?,?,?,'2099-05-01','2099-05-10')",[$year,$terms[0],$class,$tag.'_'.$i]);$exams[]=(int)$dbh->lastInsertId();
        }
        $result=compact('year','terms','classes','rooms','teachers','subject','otherSubject','date','exams');
    } elseif($mode==='state') {
        $result=['lessons'=>cbe_rows($dbh,'SELECT e.* FROM tblclasstimetableentries e JOIN tblclasses c ON c.id=e.ClassId WHERE LEFT(c.ClassName,CHAR_LENGTH(?))=? ORDER BY e.id',[$tag,$tag]),'exams'=>cbe_rows($dbh,'SELECT e.* FROM tblexamtimetableentries e JOIN tblclasses c ON c.id=e.ClassId WHERE LEFT(c.ClassName,CHAR_LENGTH(?))=? ORDER BY e.id',[$tag,$tag]),'members'=>cbe_rows($dbh,'SELECT i.* FROM tblexaminvigilators i JOIN tblexamtimetableentries e ON e.id=i.SessionId JOIN tblclasses c ON c.id=e.ClassId WHERE LEFT(c.ClassName,CHAR_LENGTH(?))=? ORDER BY i.SessionId,i.TeacherId',[$tag,$tag]),'versions'=>cbe_rows($dbh,'SELECT * FROM tbltimetableversions WHERE CreatedBy=? ORDER BY id',[$tag]),'notifications'=>cbe_rows($dbh,'SELECT n.TeacherId,n.Category,n.ActionUrl FROM tblteachernotifications n JOIN tblusers u ON u.id=n.TeacherId WHERE LEFT(u.Username,CHAR_LENGTH(?))=? ORDER BY n.id',[$tag,$tag])];
    } elseif($mode==='block_exam_publication') {
        $teacher=academic_query($dbh,'SELECT id FROM tblusers WHERE Username=?',[$tag.'_2'])->fetchColumn();
        academic_query($dbh,"INSERT INTO tblteacheravailability(TeacherId,DayOfWeek,StartTime,EndTime,Reason) VALUES(?,'Monday','11:30','11:45',?)",[$teacher,$tag.'_publish']);$result=true;
    } elseif($mode==='unblock_exam_publication') {
        academic_query($dbh,'DELETE FROM tblteacheravailability WHERE Reason=?',[$tag.'_publish']);$result=true;
    } elseif($mode==='block_lesson_publication'||$mode==='unblock_lesson_publication') {
        academic_query($dbh,'UPDATE tblrooms SET Status=? WHERE RoomName=?',[$mode==='block_lesson_publication'?0:1,$tag.'_1']);$result=true;
    } elseif($mode==='legacy_session') {
        $class=academic_query($dbh,'SELECT id FROM tblclasses WHERE ClassName=?',[$tag.'_0'])->fetchColumn();
        $teacher=academic_query($dbh,'SELECT id FROM tblusers WHERE Username=?',[$tag.'_3'])->fetchColumn();
        $subject=academic_query($dbh,'SELECT id FROM tblsubjects WHERE SubjectName=?',[$tag])->fetchColumn();
        $exam=academic_query($dbh,'SELECT id FROM tblexams WHERE ExamName=?',[$tag.'_2'])->fetchColumn();
        academic_query($dbh,"INSERT INTO tblexamtimetableentries(ExamId,ClassId,SubjectId,InvigilatorId,ExamDate,StartTime,EndTime,CreatedBy) VALUES(?,?,?,?,'2099-05-04','14:00','15:00',?)",[$exam,$class,$subject,$teacher,'legacy-'.$tag]);$result=(int)$dbh->lastInsertId();
    } elseif($mode==='active_class_view') {
        $active=timetable_active_period($dbh);
        $class=academic_query($dbh,'SELECT id FROM tblclasses WHERE ClassName=?',[$tag.'_0'])->fetchColumn();
        $subject=academic_query($dbh,'SELECT id FROM tblsubjects WHERE SubjectName=?',[$tag])->fetchColumn();
        $teachers=academic_query($dbh,'SELECT id FROM tblusers WHERE Username IN (?,?) ORDER BY Username',[$tag.'_0',$tag.'_1'])->fetchAll(PDO::FETCH_COLUMN);
        academic_query($dbh,'INSERT INTO tblexams(AcademicYearId,TermId,ClassId,ExamName) VALUES(?,?,?,?)',[$active->AcademicYearId,$active->TermId,$class,$tag.'_active']);$exam=(int)$dbh->lastInsertId();
        $result=cbe_schedule_save($dbh,['ExamId'=>$exam,'ClassId'=>$class,'SubjectId'=>$subject,'Invigilators'=>$teachers,'ExamDate'=>'2099-05-05','StartTime'=>'09:00','EndTime'=>'10:00','Status'=>'published'],true,false);
    } elseif($mode==='cleanup') {
        $classes=academic_query($dbh,'SELECT id FROM tblclasses WHERE LEFT(ClassName,CHAR_LENGTH(?))=?',[$tag,$tag])->fetchAll(PDO::FETCH_COLUMN);
        foreach($classes as $class) {
            academic_query($dbh,'DELETE i FROM tblexaminvigilators i JOIN tblexamtimetableentries e ON e.id=i.SessionId WHERE e.ClassId=?',[$class]);
            academic_query($dbh,'DELETE FROM tblexamtimetableentries WHERE ClassId=?',[$class]);
            academic_query($dbh,'DELETE FROM tblclasstimetableentries WHERE ClassId=?',[$class]);
            foreach(['tblsubjectteacherassignments','tblclassteacherassignments','tblsubjectcombination'] as $table)academic_query($dbh,"DELETE FROM $table WHERE ClassId=?",[$class]);
        }
        academic_query($dbh,'DELETE FROM tblexams WHERE LEFT(ExamName,CHAR_LENGTH(?))=?',[$tag,$tag]);
        foreach($classes as $class)academic_query($dbh,'DELETE FROM tblclasses WHERE id=?',[$class]);
        $teachers=academic_query($dbh,'SELECT id FROM tblusers WHERE LEFT(Username,CHAR_LENGTH(?))=?',[$tag,$tag])->fetchAll(PDO::FETCH_COLUMN);
        foreach($teachers as $teacher) {
            foreach(['tblnotificationdeliveries'=>'UserId','tblnotificationpreferences'=>'UserId','tblteachernotifications'=>'TeacherId','tblteacheravailability'=>'TeacherId'] as $table=>$column)academic_query($dbh,"DELETE FROM $table WHERE $column=?",[$teacher]);
            academic_query($dbh,'DELETE FROM tblusers WHERE id=?',[$teacher]);
        }
        academic_query($dbh,'DELETE FROM tblrooms WHERE LEFT(RoomName,CHAR_LENGTH(?))=?',[$tag,$tag]);
        academic_query($dbh,'DELETE FROM tblsubjects WHERE SubjectName IN (?,?)',[$tag,$tag.'_other']);
        $year=academic_query($dbh,'SELECT id FROM tblacademicyears WHERE AcademicYear=?',[substr($tag,-16)])->fetchColumn();
        academic_query($dbh,'DELETE FROM tblterms WHERE AcademicYearId=?',[$year]);academic_query($dbh,'DELETE FROM tblacademicyears WHERE id=?',[$year]);
        academic_query($dbh,'DELETE FROM tbltimetableversions WHERE CreatedBy=?',[$tag]);academic_query($dbh,'DELETE FROM tblauditlog WHERE Actor=?',[$tag]);$result='Timetable fixtures removed';
    } else throw new RuntimeException('Unknown fixture mode.');
    $dbh->commit();echo json_encode($result);
}catch(Throwable $e){if($dbh->inTransaction())$dbh->rollBack();throw $e;}
