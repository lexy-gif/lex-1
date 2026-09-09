<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../includes/config.php';
require __DIR__.'/../includes/exam-results.php';
$tag=$argv[2]??'';
if(!preg_match('/^result_http_[a-f0-9]{16}$/D',$tag))throw new RuntimeException('Invalid fixture identifier.');
$_SESSION=['alogin'=>$tag];
$mode=$argv[1]??'';
$dbh->beginTransaction();
try {
    if($mode==='setup') {
        $years=[];$terms=[];$classes=[];$subjects=[];$students=[];$exams=[];
        for($i=0;$i<2;$i++) {
            academic_query($dbh,'INSERT INTO tblacademicyears(AcademicYear) VALUES(?)',[substr($tag,-16).'_'.$i]);$years[]=(int)$dbh->lastInsertId();
            academic_query($dbh,'INSERT INTO tblterms(AcademicYearId,TermName) VALUES(?,?)',[$years[$i],'Fixture term']);$terms[]=(int)$dbh->lastInsertId();
            academic_query($dbh,'INSERT INTO tblclasses(ClassName,Section) VALUES(?,?)',[$tag.'_'.$i,'TEST']);$classes[]=(int)$dbh->lastInsertId();
        }
        for($i=0;$i<4;$i++) {
            academic_query($dbh,'INSERT INTO tblsubjects(SubjectName,Status) VALUES(?,1)',[$tag.' subject '.$i]);$subjects[]=(int)$dbh->lastInsertId();
            academic_query($dbh,'INSERT INTO tblsubjectcombination(ClassId,SubjectId,status) VALUES(?,?,1)',[$classes[$i===3?1:0],$subjects[$i]]);
            // No parent contacts: fixture requests cannot send parent messages.
            academic_query($dbh,'INSERT INTO tblstudents(StudentName,RollId,ClassId,Status) VALUES(?,?,?,?)',[$tag.'_'.$i,$tag.'_'.$i,$classes[$i===3?1:0],$i===2?0:1]);$students[]=(int)$dbh->lastInsertId();
        }
        academic_register_student($dbh,$students[0],$years[0],[$subjects[0],$subjects[1]]);
        academic_register_student($dbh,$students[1],$years[0],[$subjects[2]]);
        academic_register_student($dbh,$students[0],$years[1],[$subjects[2]]);
        academic_register_student($dbh,$students[3],$years[0],[$subjects[3]]);
        foreach([[0,0],[1,0],[0,1],[0,null]] as $i=>[$y,$c]) {
            academic_query($dbh,"INSERT INTO tblexams(AcademicYearId,TermId,ClassId,ExamName,Status) VALUES(?,?,?,?,'marks_entry')",[$years[$y],$terms[$y],$c===null?null:$classes[$c],$tag.'_'.$i]);$exams[]=(int)$dbh->lastInsertId();
        }
        academic_query($dbh,'INSERT INTO tblresult(StudentId,ClassId,SubjectId,marks) VALUES(?,?,?,44)',[$students[0],$classes[0],$subjects[0]]);
        $dbh->commit();echo json_encode(compact('years','terms','classes','subjects','students','exams'));
    } elseif($mode==='state') {
        $rows=cbe_rows($dbh,'SELECT r.id,r.StudentId,r.ClassId,r.ExamId,r.SubjectId,r.marks FROM tblresult r JOIN tblstudents s ON s.StudentId=r.StudentId WHERE s.RollId IN (?,?,?,?) ORDER BY r.id',[$tag.'_0',$tag.'_1',$tag.'_2',$tag.'_3']);
        $dbh->rollBack();echo json_encode($rows);
    } elseif(in_array($mode,['disable_subject','disable_offering','restore_offering','move_student'],true)) {
        $subject=(int)academic_query($dbh,'SELECT id FROM tblsubjects WHERE SubjectName=?',[$tag.' subject 1'])->fetchColumn();
        $class=(int)academic_query($dbh,'SELECT id FROM tblclasses WHERE ClassName=?',[$tag.'_0'])->fetchColumn();
        if($mode==='disable_subject')academic_query($dbh,'UPDATE tblsubjects SET Status=0 WHERE id=?',[$subject]);
        elseif($mode==='move_student') {
            $otherClass=(int)academic_query($dbh,'SELECT id FROM tblclasses WHERE ClassName=?',[$tag.'_1'])->fetchColumn();
            academic_query($dbh,'UPDATE tblstudents SET ClassId=? WHERE RollId=?',[$otherClass,$tag.'_0']);
        } else academic_query($dbh,'UPDATE tblsubjectcombination SET status=? WHERE ClassId=? AND SubjectId=?',[$mode==='restore_offering'?1:0,$class,$subject]);
        $dbh->commit();echo json_encode(['changed'=>$mode]);
    } elseif($mode==='cleanup') {
        $years=academic_query($dbh,'SELECT id FROM tblacademicyears WHERE AcademicYear IN (?,?)',[substr($tag,-16).'_0',substr($tag,-16).'_1'])->fetchAll(PDO::FETCH_COLUMN);
        $classes=academic_query($dbh,'SELECT id FROM tblclasses WHERE ClassName IN (?,?)',[$tag.'_0',$tag.'_1'])->fetchAll(PDO::FETCH_COLUMN);
        foreach($classes as $class)academic_query($dbh,'DELETE FROM tblresult WHERE ClassId=?',[$class]);
        foreach($years as $year) {
            academic_query($dbh,'DELETE FROM tblexams WHERE AcademicYearId=?',[$year]);
            academic_query($dbh,'DELETE FROM tblstudentsubjects WHERE AcademicYearId=?',[$year]);
            academic_query($dbh,'DELETE FROM tblterms WHERE AcademicYearId=?',[$year]);
            academic_query($dbh,'DELETE FROM tblacademicyears WHERE id=?',[$year]);
        }
        foreach($classes as $class) {
            academic_query($dbh,'DELETE FROM tblstudents WHERE ClassId=?',[$class]);
            academic_query($dbh,'DELETE FROM tblsubjectcombination WHERE ClassId=?',[$class]);
            academic_query($dbh,'DELETE FROM tblclasses WHERE id=?',[$class]);
        }
        for($i=0;$i<4;$i++)academic_query($dbh,'DELETE FROM tblsubjects WHERE SubjectName=?',[$tag.' subject '.$i]);
        academic_query($dbh,'DELETE FROM tblauditlog WHERE Actor=?',[$tag]);
        $dbh->commit();echo json_encode(['cleanup'=>'complete']);
    } else throw new RuntimeException('Unknown fixture mode.');
} catch(Throwable $e) { if($dbh->inTransaction())$dbh->rollBack();throw $e; }
