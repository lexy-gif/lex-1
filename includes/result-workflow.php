<?php
require_once __DIR__.'/exam-results.php';
require_once __DIR__.'/sms.php';

function workflow_exam($db, $class, $exam) {
    $e=cbe_one($db,'SELECT * FROM tblexams WHERE id=?'.($db->inTransaction()?' FOR UPDATE':''),[$exam]);
    if (!$e || ($e['ClassId'] && (int)$e['ClassId']!==$class)) throw new DomainException('Select an examination for this class.');
    if (!academic_query($db,'SELECT id FROM tblclasses WHERE id=? AND ClassNameNumeric IN (10,11,12)',[$class])->fetchColumn()) throw new DomainException('Select a Senior School class.');
    return $e;
}
function workflow_editable($db, $class, $exam, $subject=0) {
    if (academic_query($db,'SELECT id FROM tblresultpublications WHERE ClassId=? AND ExamId=?',[$class,$exam])->fetchColumn()) throw new DomainException('Published results are locked.');
    if (academic_query($db,"SELECT id FROM tbldeanapprovals WHERE ClassId=? AND ExamId=? AND Status IN ('approved','published')",[$class,$exam])->fetchColumn()) throw new DomainException('Approved results are locked. Return the class for correction first.');
    if (academic_query($db,"SELECT id FROM tblresultreviews WHERE ClassId=? AND ExamId=? AND Status='approved'",[$class,$exam])->fetchColumn()) throw new DomainException('Reviewed results are locked. Return the class for correction first.');
    if (academic_query($db,"SELECT id FROM tblresultsubmissions WHERE ClassId=? AND ExamId=? AND Status='submitted' AND (?=0 OR SubjectId=?) LIMIT 1",[$class,$exam,$subject,$subject])->fetchColumn()) throw new DomainException('Submitted results are locked. Ask for a correction request.');
}
function workflow_subject_save($db, $teacher, $post) {
    if (!$db->inTransaction()) throw new LogicException('A transaction is required.');
    $class=result_id($post['ClassId']??null); $exam=result_id($post['ExamId']??null); $subject=result_id($post['SubjectId']??null);
    $e=workflow_exam($db,$class,$exam); cbe_exam_writable($db,$exam);
    cbe_assignment($db,$teacher,$class,$subject,$e['AcademicYearId'],$e['TermId']);
    workflow_editable($db,$class,$exam,$subject);
    $students=academic_students($db,$class,$subject,$e['AcademicYearId']);
    $expected=array_map('intval',array_column($students,'StudentId'));
    $marks=$post['marks']??null;
    if (!is_array($marks) || !$expected) throw new DomainException('Enter marks for the registered learners.');
    $ids=array_map('result_id',array_keys($marks)); sort($expected); sort($ids);
    if ($ids!==$expected) throw new DomainException('Learner registrations changed. Reload the mark sheet.');
    foreach ($marks as $student=>$mark) {
        $ctx=result_entry_context($db,$class,$student,$exam);
        if (!isset($ctx['subjects'][$subject])) throw new DomainException('A learner is no longer registered for this subject.');
        $score=cbe_number($mark,0,(float)$e['MaximumMarks']);
        academic_query($db,'INSERT INTO tblresult(StudentId,ClassId,ExamId,SubjectId,marks) VALUES(?,?,?,?,?)
            ON DUPLICATE KEY UPDATE marks=VALUES(marks)',[$student,$class,$exam,$subject,$score]);
    }
    $action=$post['action']??'';
    if (!in_array($action,['save','submit'],true)) throw new DomainException('Select save or submit.');
    $status=$action==='submit'?'submitted':'draft';
    academic_query($db,'INSERT INTO tblresultsubmissions(ClassId,SubjectId,ExamId,TeacherId,Status,SubmittedAt)
        VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE TeacherId=VALUES(TeacherId),Status=VALUES(Status),SubmittedAt=VALUES(SubmittedAt)',
        [$class,$subject,$exam,$teacher,$status,$status==='submitted'?date('Y-m-d H:i:s'):null]);
    cbe_audit($db,'subject_results_'.$status,'tblexams',$exam,null,['class'=>$class,'subject'=>$subject,'teacher'=>$teacher,'count'=>count($marks)]);
}
function workflow_complete($db, $class, $e) {
    $expected=cbe_rows($db,'SELECT ss.StudentId,ss.SubjectId FROM tblstudentsubjects ss
        JOIN tblstudents s ON s.StudentId=ss.StudentId AND s.ClassId=ss.ClassId AND s.Status=1
        JOIN tblsubjects sub ON sub.id=ss.SubjectId AND sub.Status=1
        JOIN tblsubjectcombination sc ON sc.ClassId=ss.ClassId AND sc.SubjectId=ss.SubjectId AND sc.status=1
        WHERE ss.ClassId=? AND ss.AcademicYearId=? AND ss.Status=1',[$class,$e['AcademicYearId']]);
    if (!$expected) throw new DomainException('No registered learners to review.');
    foreach ($expected as $row) {
        $r=cbe_one($db,"SELECT r.marks FROM tblresult r JOIN tblresultsubmissions rs
            ON rs.ClassId=r.ClassId AND rs.SubjectId=r.SubjectId AND rs.ExamId=r.ExamId AND rs.Status='submitted'
            WHERE r.StudentId=? AND r.SubjectId=? AND r.ClassId=? AND r.ExamId=?",
            [$row['StudentId'],$row['SubjectId'],$class,$e['id']]);
        if (!$r) throw new DomainException('Every registered subject must have complete, submitted marks before review.');
        cbe_number($r['marks'],0,(float)$e['MaximumMarks']);
    }
    $extra=academic_query($db,'SELECT r.id FROM tblresult r WHERE r.ClassId=? AND r.ExamId=? AND NOT EXISTS
        (SELECT 1 FROM tblstudentsubjects ss JOIN tblstudents s ON s.StudentId=ss.StudentId AND s.Status=1 AND s.ClassId=ss.ClassId
        JOIN tblsubjects sub ON sub.id=ss.SubjectId AND sub.Status=1
        JOIN tblsubjectcombination sc ON sc.ClassId=ss.ClassId AND sc.SubjectId=ss.SubjectId AND sc.status=1
        WHERE ss.StudentId=r.StudentId AND ss.SubjectId=r.SubjectId AND ss.ClassId=r.ClassId AND ss.AcademicYearId=? AND ss.Status=1) LIMIT 1',[$class,$e['id'],$e['AcademicYearId']])->fetchColumn();
    if ($extra) throw new DomainException('Resolve results with inactive or changed enrolment before publication.');
}
function workflow_return($db, $class, $exam, $reason) {
    if ($reason==='') throw new DomainException('Enter a correction reason.');
    $teachers=academic_query($db,'SELECT DISTINCT TeacherId FROM tblresultsubmissions WHERE ClassId=? AND ExamId=?',[$class,$exam])->fetchAll(PDO::FETCH_COLUMN);
    academic_query($db,"UPDATE tblresultsubmissions SET Status='draft',SubmittedAt=NULL WHERE ClassId=? AND ExamId=?",[$class,$exam]);
    academic_query($db,"UPDATE tblresultreviews SET Status='correction_requested',CorrectionReason=? WHERE ClassId=? AND ExamId=?",[$reason,$class,$exam]);
    academic_query($db,"UPDATE tbldeanapprovals SET Status='rejected',DecisionReason=? WHERE ClassId=? AND ExamId=?",[$reason,$class,$exam]);
    foreach ($teachers as $teacher) cbe_notify($db,$teacher,'Results returned for correction',$reason,'teacher-mark-entry.php?class='.$class.'&exam='.$exam,'RESULTS');
}
function workflow_review($db, $teacher, $class, $exam, $action, $reason) {
    $e=workflow_exam($db,$class,$exam);
    if (!academic_query($db,'SELECT a.id FROM tblclassteacherassignments a JOIN tblusers u ON u.id=a.TeacherId AND u.Status=1 WHERE a.TeacherId=? AND a.ClassId=? AND a.AcademicYearId=? AND a.Status=1',[$teacher,$class,$e['AcademicYearId']])->fetchColumn()) throw new DomainException('An active class teacher assignment for this exam year is required.');
    if (academic_query($db,'SELECT id FROM tblresultpublications WHERE ClassId=? AND ExamId=?',[$class,$exam])->fetchColumn()) throw new DomainException('Published results are locked.');
    if (!in_array($action,['approve','correction'],true)) throw new DomainException('Select a valid review decision.');
    if ($action==='approve') workflow_complete($db,$class,$e); else workflow_return($db,$class,$exam,$reason);
    academic_query($db,'INSERT INTO tblresultreviews(ClassId,ExamId,ReviewedBy,Status,CorrectionReason) VALUES(?,?,?,?,?)
        ON DUPLICATE KEY UPDATE ReviewedBy=VALUES(ReviewedBy),Status=VALUES(Status),CorrectionReason=VALUES(CorrectionReason)',[$class,$exam,$teacher,$action==='approve'?'approved':'correction_requested',$reason]);
    cbe_audit($db,'class_result_review','tblexams',$exam,null,['class'=>$class,'decision'=>$action,'reason'=>$reason]);
}
function parent_publication_notify($db, $student, $event, $title, $url) {
    $s=cbe_one($db,'SELECT StudentName FROM tblstudents WHERE StudentId=? AND Status=1',[$student]); if (!$s) return;
    $parents=cbe_rows($db,"SELECT u.id,u.ParentPhone,ps.NotifyResults FROM tblparentstudents ps JOIN tblusers u
        ON u.id=ps.ParentId AND u.Role='parent' AND u.Status=1 WHERE ps.StudentId=? AND ps.Status=1",[$student]);
    $portal=rtrim(getenv('APP_URL')?:'','/');
    $message='Dear Parent/Guardian, results for '.$s['StudentName'].' for '.$title.' are now available. Please log in to the school parent portal'.($portal?' at '.$portal.'/parent-login.php':'').'.';
    foreach ($parents as $parent) {
        $q=academic_query($db,'INSERT IGNORE INTO tblparentnotifications(ParentId,StudentId,EventKey,Title,Message,ActionUrl) VALUES(?,?,?,?,?,?)',[$parent['id'],$student,$event,'Results published',$message,$url]);
        if (!$q->rowCount()) continue;
        $notification=(int)$db->lastInsertId();
        if (!$parent['NotifyResults']) continue;
        $phone=normalize_phone_number($parent['ParentPhone']??'');
        academic_query($db,'INSERT INTO tblparentsms(NotificationId,ParentId,StudentId,Destination,Message,Status,ErrorMessage) VALUES(?,?,?,?,?,?,?)',
            [$notification,$parent['id'],$student,$phone,$message,$phone?'pending':'skipped',$phone?null:'No valid Kenyan mobile contact.']);
    }
}
function workflow_decide($db, $class, $exam, $decision, $reason) {
    if (!$db->inTransaction()) throw new LogicException('A transaction is required.');
    $e=workflow_exam($db,$class,$exam);
    if (!in_array($decision,['approve','publish','reject'],true)) throw new DomainException('Select a valid decision.');
    if (academic_query($db,'SELECT id FROM tblresultpublications WHERE ClassId=? AND ExamId=?',[$class,$exam])->fetchColumn()) {
        if ($decision==='publish') return; throw new DomainException('Published results are locked.');
    }
    if ($decision==='reject') workflow_return($db,$class,$exam,$reason);
    else {
        workflow_complete($db,$class,$e);
        if (!academic_query($db,"SELECT id FROM tblresultreviews WHERE ClassId=? AND ExamId=? AND Status='approved'",[$class,$exam])->fetchColumn()) throw new DomainException('The class teacher must review the submitted results first.');
        if ($decision==='publish' && !academic_query($db,"SELECT id FROM tbldeanapprovals WHERE ClassId=? AND ExamId=? AND Status='approved'",[$class,$exam])->fetchColumn()) throw new DomainException('Approve these results before publishing.');
    }
    $status=['approve'=>'approved','publish'=>'published','reject'=>'rejected'][$decision];
    academic_query($db,'INSERT INTO tbldeanapprovals(ClassId,ExamId,ApprovedBy,Status,DecisionReason) VALUES(?,?,?,?,?)
        ON DUPLICATE KEY UPDATE ApprovedBy=VALUES(ApprovedBy),Status=VALUES(Status),DecisionReason=VALUES(DecisionReason)',[$class,$exam,cbe_actor(),$status,$reason]);
    if ($decision==='publish') {
        $scale=cbe_rows($db,'SELECT Grade,MinMark,MaxMark,Remark FROM tblgradingscales ORDER BY MinMark');
        $next=0;foreach($scale as $band){if(abs((float)$band['MinMark']-$next)>0.001)throw new DomainException('Configure contiguous performance bands covering 0-100 before publication.');$next=round((float)$band['MaxMark']+0.01,2);}
        if(abs($next-100.01)>0.001)throw new DomainException('Configure performance bands covering 0-100 before publication.');
        academic_query($db,'INSERT INTO tblresultpublications(ClassId,ExamId,PublishedBy,GradeScale) VALUES(?,?,?,?)',[$class,$exam,cbe_actor(),json_encode($scale,JSON_THROW_ON_ERROR)]);
        $publication=(int)$db->lastInsertId();
        academic_query($db,"UPDATE tblteachercomments SET Status='published' WHERE ClassId=? AND ExamId=? AND Status='submitted'",[$class,$exam]);
        foreach (academic_query($db,'SELECT DISTINCT StudentId FROM tblresult WHERE ClassId=? AND ExamId=?',[$class,$exam])->fetchAll(PDO::FETCH_COLUMN) as $student) {
            parent_publication_notify($db,$student,'exam:'.$publication,$e['ExamName'],'parent-report.php?student='.$student.'&exam='.$exam.'&class='.$class);
        }
    }
    cbe_audit($db,'dean_results_'.$status,'tblexams',$exam,null,['class'=>$class,'reason'=>$reason]);
}
