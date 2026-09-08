<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../includes/config.php';
require __DIR__.'/../includes/cbe-learning.php';
require __DIR__.'/../includes/cbe-reports.php';
require __DIR__.'/../includes/cbe-context.php';
$tag=$argv[2]??'';
if(!preg_match('/^cbe_http_[a-f0-9]{16}$/D',$tag))throw new RuntimeException('Invalid fixture identifier.');
$_SESSION=['alogin'=>$tag];
$mode=$argv[1]??'';
$dbh->beginTransaction();
try {
    if($mode==='setup') {
        academic_query($dbh,'INSERT INTO tblacademicyears(AcademicYear) VALUES(?)',[substr($tag,-16)]);$year=(int)$dbh->lastInsertId();
        $terms=[];
        foreach([1,0] as $i=>$active) {
            academic_query($dbh,'INSERT INTO tblterms(AcademicYearId,TermName,IsActive) VALUES(?,?,?)',[$year,'Fixture term '.($i+1),$active]);$terms[]=(int)$dbh->lastInsertId();
        }
        [$term,$term2]=$terms;
        $classes=[];$subjects=[];$teachers=[];$students=[];
        for($i=0;$i<2;$i++) {
            academic_query($dbh,'INSERT INTO tblclasses(ClassName,Section) VALUES(?,?)',[$tag.'_'.$i,'TEST']);$classes[]=(int)$dbh->lastInsertId();
            academic_query($dbh,'INSERT INTO tblsubjects(SubjectName) VALUES(?)',[$tag.'_'.$i]);$subjects[]=(int)$dbh->lastInsertId();
            academic_query($dbh,'INSERT INTO tblsubjectcombination(ClassId,SubjectId,status) VALUES(?,?,1)',[$classes[$i],$subjects[$i]]);
            academic_query($dbh,"INSERT INTO tblusers(FullName,Username,PasswordHash,Role,Status) VALUES(?,?,?,'subject_teacher',1)",[$tag.'_'.$i,$tag.'_'.$i,password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);$teachers[]=(int)$dbh->lastInsertId();
            // Test accounts have no email address and all external delivery is disabled.
            academic_query($dbh,"INSERT INTO tblnotificationpreferences(UserId,Category,InAppEnabled,EmailEnabled) VALUES(?,'SYSTEM',1,0)",[$teachers[$i]]);
            academic_assign($dbh,'subject',$teachers[$i],$classes[$i],$subjects[$i],$year,$i?$term2:null);
            academic_query($dbh,'INSERT INTO tblstudents(StudentName,RollId,ClassId,Status) VALUES(?,?,?,1)',[$tag.'_'.$i,$tag.'_'.$i,$classes[$i]]);$students[]=(int)$dbh->lastInsertId();
            academic_register_student($dbh,$students[$i],$year,[$subjects[$i]]);
        }
        academic_query($dbh,'INSERT INTO tblassessmenttypes(Name) VALUES(?)',[$tag]);$type=(int)$dbh->lastInsertId();
        academic_query($dbh,'INSERT INTO tblperformancelevels(Name) VALUES(?)',[$tag]);$level=(int)$dbh->lastInsertId();
        $outcomes=[];
        foreach($subjects as $i=>$subject) {
            academic_query($dbh,'INSERT INTO tblcompetencies(SubjectId,Title) VALUES(?,?)',[$subject,$tag.'_'.$i]);$competency=(int)$dbh->lastInsertId();
            academic_query($dbh,'INSERT INTO tbllearningoutcomes(CompetencyId,Title) VALUES(?,?)',[$competency,$tag.' outcome '.$i]);$outcomes[]=(int)$dbh->lastInsertId();
        }
        $otherAssessment=cbe_save_assessment($dbh,['Title'=>$tag.' other term','AssessmentTypeId'=>$type,'ClassId'=>$classes[1],'SubjectId'=>$subjects[1],'AcademicYearId'=>$year,'TermId'=>$term2,'TeacherId'=>$teachers[1],'MaximumScore'=>100,'AssessmentDate'=>date('Y-m-d'),'Status'=>'open']);
        cbe_score($dbh,['AssessmentId'=>$otherAssessment,'StudentId'=>$students[1],'Score'=>35]);
        $exams=[];
        foreach($classes as $i=>$class) {
            academic_query($dbh,"INSERT INTO tblexams(AcademicYearId,TermId,ClassId,ExamName,Status) VALUES(?,?,?,?,'marks_entry')",[$year,$terms[$i],$class,$tag.'_'.$i]);$exams[]=(int)$dbh->lastInsertId();
        }
        academic_query($dbh,'INSERT INTO tblresult(StudentId,ClassId,ExamId,SubjectId,marks) VALUES(?,?,?,?,?)',[$students[1],$classes[1],$exams[1],$subjects[1],60]);$otherResult=(int)$dbh->lastInsertId();
        $dbh->commit();echo json_encode(compact('year','term','term2','classes','subjects','teachers','students','type','level','outcomes','otherAssessment','exams','otherResult'));
    } elseif($mode==='state') {
        $rows=cbe_rows($dbh,'SELECT r.id,r.StudentId,r.ExamId,r.marks FROM tblresult r JOIN tblexams e ON e.id=r.ExamId JOIN tblacademicyears y ON y.id=e.AcademicYearId WHERE y.AcademicYear=? ORDER BY r.id',[substr($tag,-16)]);
        $dbh->rollBack();echo json_encode($rows);
    } elseif($mode==='verify') {
        $year=(int)academic_query($dbh,'SELECT id FROM tblacademicyears WHERE AcademicYear=?',[substr($tag,-16)])->fetchColumn();
        $terms=academic_query($dbh,'SELECT id FROM tblterms WHERE AcademicYearId=? ORDER BY id',[$year])->fetchAll(PDO::FETCH_COLUMN);
        if(cbe_period_context($dbh,['year'=>$year])!==[$year,(int)$terms[0]])throw new RuntimeException('Active term was not selected.');
        if(cbe_period_context($dbh,['year'=>$year,'term'=>0])!==[$year,0])throw new RuntimeException('All-terms selection was lost.');
        if(count(cbe_report($dbh,'assignments',$year,$terms[0]))!==1||count(cbe_report($dbh,'assignments',$year,$terms[1]))!==2)throw new RuntimeException('Assignment report mixed terms or dropped whole-year teaching.');
        $teacher=(int)academic_query($dbh,'SELECT id FROM tblusers WHERE Username=?',[$tag.'_0'])->fetchColumn();
        if(cbe_report($dbh,'workload',$year,0,$teacher)[0]['Workload']!=='SELECT TERM')throw new RuntimeException('Combined terms received a weekly workload rating.');
        $class=(int)academic_query($dbh,'SELECT id FROM tblclasses WHERE ClassName=?',[$tag.'_0'])->fetchColumn();
        academic_query($dbh,'UPDATE tblstudentsubjects SET Status=0 WHERE AcademicYearId=?',[$year]);
        if(count(cbe_report($dbh,'unallocated',$year,0,0,$class))!==1)throw new RuntimeException('Unallocated learner report ignored the class.');
        $exam=(int)academic_query($dbh,'SELECT id FROM tblexams WHERE AcademicYearId=? ORDER BY id LIMIT 1',[$year])->fetchColumn();
        foreach([['EntryLocked'=>1],['Status'=>'published'],['Status'=>'approved'],['MarksOpenDate'=>date('Y-m-d',strtotime('+1 day'))],['MarksDeadline'=>date('Y-m-d',strtotime('-1 day'))]] as $closed) {
            academic_query($dbh,"UPDATE tblexams SET EntryLocked=0,Status='marks_entry',MarksOpenDate=NULL,MarksDeadline=NULL WHERE id=?",[$exam]);
            foreach($closed as $column=>$value)academic_query($dbh,"UPDATE tblexams SET $column=? WHERE id=?",[$value,$exam]);
            try {cbe_exam_writable($dbh,$exam);throw new RuntimeException('Closed examination accepted marks.');}catch(DomainException $expected){}
        }
        $dbh->rollBack();echo 'PASS: period defaults, report scopes, workload bands and examination dates/statuses';
    } elseif($mode==='cleanup') {
        $year=academic_query($dbh,'SELECT id FROM tblacademicyears WHERE AcademicYear=?',[substr($tag,-16)])->fetchColumn();
        if($year) {
            academic_query($dbh,'DELETE r FROM tblresult r JOIN tblexams e ON e.id=r.ExamId WHERE e.AcademicYearId=?',[$year]);
            academic_query($dbh,'DELETE FROM tblexams WHERE AcademicYearId=?',[$year]);
            foreach(['tbloutcomeobservations','tblassessmentresults','tblassessmentoutcomes'] as $table)academic_query($dbh,"DELETE r FROM $table r JOIN tblassessments a ON a.id=r.AssessmentId WHERE a.AcademicYearId=?",[$year]);
            foreach(['tblassessments','tblstudentsubjects','tblsubjectteacherassignments','tblclassteacherassignments'] as $table)academic_query($dbh,"DELETE FROM $table WHERE AcademicYearId=?",[$year]);
            $teachers=academic_query($dbh,'SELECT id FROM tblusers WHERE Username IN (?,?)',[$tag.'_0',$tag.'_1'])->fetchAll(PDO::FETCH_COLUMN);
            foreach($teachers as $teacher) {
                foreach(['tblnotificationdeliveries'=>'UserId','tblnotificationpreferences'=>'UserId','tblteachernotifications'=>'TeacherId'] as $table=>$column)academic_query($dbh,"DELETE FROM $table WHERE $column=?",[$teacher]);
                academic_query($dbh,'DELETE FROM tblusers WHERE id=?',[$teacher]);
            }
            academic_query($dbh,'DELETE o FROM tbllearningoutcomes o JOIN tblcompetencies c ON c.id=o.CompetencyId WHERE c.Title IN (?,?)',[$tag.'_0',$tag.'_1']);
            academic_query($dbh,'DELETE FROM tblcompetencies WHERE Title IN (?,?)',[$tag.'_0',$tag.'_1']);
            academic_query($dbh,'DELETE FROM tblassessmenttypes WHERE Name=?',[$tag]);
            academic_query($dbh,'DELETE FROM tblperformancelevels WHERE Name=?',[$tag]);
            foreach([$tag.'_0',$tag.'_1'] as $name) {
                $class=academic_query($dbh,'SELECT id FROM tblclasses WHERE ClassName=?',[$name])->fetchColumn();
                academic_query($dbh,'DELETE FROM tblstudents WHERE ClassId=? AND RollId=?',[$class,$name]);
                academic_query($dbh,'DELETE FROM tblsubjectcombination WHERE ClassId=?',[$class]);
                academic_query($dbh,'DELETE FROM tblclasses WHERE id=?',[$class]);
                academic_query($dbh,'DELETE FROM tblsubjects WHERE SubjectName=?',[$name]);
            }
            academic_query($dbh,'DELETE FROM tblterms WHERE AcademicYearId=?',[$year]);
            academic_query($dbh,'DELETE FROM tblacademicyears WHERE id=?',[$year]);
        }
        academic_query($dbh,'DELETE FROM tblauditlog WHERE Actor IN (?,?,?)',[$tag,$tag.'_0',$tag.'_1']);
        $dbh->commit();echo 'Workspace fixtures removed';
    } else throw new RuntimeException('Unknown fixture mode.');
} catch(Throwable $e) {
    if($dbh->inTransaction())$dbh->rollBack();throw $e;
}
