<?php
require_once __DIR__.'/cbe-academics.php';
function cbe_assessment_access($db,$id,$teacher=0,$write=false) {
    $a=cbe_one($db,'SELECT * FROM tblassessments WHERE id=?'.($write?' FOR UPDATE':''),[$id]);if(!$a)throw new DomainException('Assessment not found.');
    if($teacher) {if((int)$a['TeacherId']!==$teacher)throw new DomainException('You can access only your assigned assessments.');cbe_assignment($db,$teacher,$a['ClassId'],$a['SubjectId'],$a['AcademicYearId'],$a['TermId']);}
    return $a;
}
function cbe_save_assessment($db,$p,$teacher=0) {
    $id=(int)($p['id']??0);$old=$id?cbe_assessment_access($db,$id,$teacher,true):null;
    $tid=$teacher?:(int)($p['TeacherId']??0);$class=(int)($p['ClassId']??0);$subject=(int)($p['SubjectId']??0);$year=(int)($p['AcademicYearId']??0);$term=(int)($p['TermId']??0);
    if(!$term)throw new DomainException('Select a term.');cbe_assignment($db,$tid,$class,$subject,$year,$term);
    $type=(int)($p['AssessmentTypeId']??0);if(!in_array($type,array_column(cbe_options($db,'types'),'id')))throw new DomainException('Select an active assessment type.');
    $status=$p['Status']??'draft';$allowed=$teacher?['draft','open','submitted']:['draft','open','submitted','locked','published','archived'];if(!in_array($status,$allowed,true))throw new DomainException('Invalid assessment status.');
    if($teacher&&$old&&in_array($old['Status'],['locked','published','archived'],true))throw new DomainException('This assessment is locked. Ask the Dean to reopen it.');
    $max=($p['MaximumScore']??'')===''?null:cbe_number($p['MaximumScore'],0.01,999999);
    $outcomes=cbe_ids($p['Outcomes']??[]);
    foreach($outcomes as $o)if(!academic_query($db,'SELECT o.id FROM tbllearningoutcomes o JOIN tblcompetencies c ON c.id=o.CompetencyId WHERE o.id=? AND c.SubjectId=? AND o.Status=1 AND c.Status=1',[$o,$subject])->fetchColumn())throw new DomainException('Outcomes must belong to the selected subject.');
    if($old&&academic_query($db,'SELECT id FROM tblassessmentresults WHERE AssessmentId=? LIMIT 1',[$id])->fetchColumn()) {
        foreach(['TeacherId'=>$tid,'ClassId'=>$class,'SubjectId'=>$subject,'AcademicYearId'=>$year,'TermId'=>$term,'MaximumScore'=>$max] as $key=>$v)if((string)$old[$key]!== (string)$v && !($key==='MaximumScore'&&(float)$old[$key]===(float)$v))throw new DomainException('An assessment with learner results cannot change its academic scope or scoring maximum.');
        $existing=academic_query($db,'SELECT OutcomeId FROM tblassessmentoutcomes WHERE AssessmentId=?',[$id])->fetchAll(PDO::FETCH_COLUMN);sort($existing);sort($outcomes);if($existing!=$outcomes)throw new DomainException('Keep assessed outcomes unchanged once results have been recorded.');
    }
    $v=[cbe_text($p,'Title',200),$type,$subject,$class,$year,$term,$tid,$max,cbe_date($p['AssessmentDate']??''),$status];
    if($id)academic_query($db,'UPDATE tblassessments SET Title=?,AssessmentTypeId=?,SubjectId=?,ClassId=?,AcademicYearId=?,TermId=?,TeacherId=?,MaximumScore=?,AssessmentDate=?,Status=? WHERE id=?',[...$v,$id]);
    else {academic_query($db,'INSERT INTO tblassessments(Title,AssessmentTypeId,SubjectId,ClassId,AcademicYearId,TermId,TeacherId,MaximumScore,AssessmentDate,Status) VALUES(?,?,?,?,?,?,?,?,?,?)',$v);$id=(int)$db->lastInsertId();}
    if(!$old||!academic_query($db,'SELECT id FROM tblassessmentresults WHERE AssessmentId=? LIMIT 1',[$id])->fetchColumn()) {academic_query($db,'DELETE FROM tblassessmentoutcomes WHERE AssessmentId=?',[$id]);foreach($outcomes as $o)academic_query($db,'INSERT INTO tblassessmentoutcomes VALUES(?,?)',[$id,$o]);}
    cbe_audit($db,'assessment_saved','tblassessments',$id,$old,$v);cbe_notify($db,$tid,'Assessment updated',$v[0].' is '.$status.'.','teacher-academics.php?area=assessments');return $id;
}
function cbe_score($db,$p,$teacher=0) {
    $id=(int)($p['AssessmentId']??0);$a=cbe_assessment_access($db,$id,$teacher,true);
    if($a['Status']!=='open')throw new DomainException('Score entry is closed. The Dean must reopen the assessment.');
    $student=(int)($p['StudentId']??0);cbe_student($db,$student,$a['ClassId'],$a['SubjectId'],$a['AcademicYearId']);
    $score=($p['Score']??'')===''?null:cbe_number($p['Score'],0,(float)$a['MaximumScore']);
    if($score!==null&&$a['MaximumScore']===null)throw new DomainException('This assessment uses qualitative evidence or performance levels, not numeric scores.');
    $level=(int)($p['PerformanceLevelId']??0)?:null;if($level&&!in_array($level,array_column(cbe_options($db,'levels-performance'),'id')))throw new DomainException('Select an active performance level.');
    $evidence=cbe_text($p,'Evidence',10000,false);if($score===null&&!$level&&$evidence==='')throw new DomainException('Record a score, performance level or evidence.');
    $old=cbe_one($db,'SELECT * FROM tblassessmentresults WHERE AssessmentId=? AND StudentId=?',[$id,$student]);
    academic_query($db,'INSERT INTO tblassessmentresults(AssessmentId,StudentId,Score,PerformanceLevelId,Evidence,RecordedBy) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE Score=VALUES(Score),PerformanceLevelId=VALUES(PerformanceLevelId),Evidence=VALUES(Evidence),RecordedBy=VALUES(RecordedBy)',[$id,$student,$score,$level,$evidence,cbe_actor()]);
    $outcome=(int)($p['OutcomeId']??0);
    if($outcome) {
        if(!$level||!academic_query($db,'SELECT OutcomeId FROM tblassessmentoutcomes WHERE AssessmentId=? AND OutcomeId=?',[$id,$outcome])->fetchColumn())throw new DomainException('Choose an outcome linked to this assessment and a performance level.');
        academic_query($db,'INSERT INTO tbloutcomeobservations(AssessmentId,StudentId,OutcomeId,PerformanceLevelId,Evidence) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE PerformanceLevelId=VALUES(PerformanceLevelId),Evidence=VALUES(Evidence)',[$id,$student,$outcome,$level,$evidence]);
    }
    cbe_audit($db,'assessment_result_saved','tblassessmentresults',$id,$old,['student'=>$student,'score'=>$score,'level'=>$level,'evidence'=>$evidence,'outcome'=>$outcome]);
}
function cbe_coverage($db,$p,$teacher=0) {
    $tid=$teacher?:(int)($p['TeacherId']??0);$class=(int)($p['ClassId']??0);$subject=(int)($p['SubjectId']??0);$year=(int)($p['AcademicYearId']??0);$term=(int)($p['TermId']??0);
    if(!$term)throw new DomainException('Select a term.');cbe_assignment($db,$tid,$class,$subject,$year,$term);
    academic_query($db,'INSERT INTO tblcurriculumcoverage(TeacherId,ClassId,SubjectId,AcademicYearId,TermId,ContentReference,ExpectedProgress,ActualProgress,ReportDate,Notes) VALUES(?,?,?,?,?,?,?,?,?,?)',[$tid,$class,$subject,$year,$term,cbe_text($p,'ContentReference'),cbe_number($p['ExpectedProgress']??''),cbe_number($p['ActualProgress']??''),cbe_date($p['ReportDate']??''),cbe_text($p,'Notes',5000,false)]);
    cbe_audit($db,'curriculum_progress_recorded','tblcurriculumcoverage',$db->lastInsertId(),null,$p);
}
function cbe_intervention($db,$p) {
    $id=(int)($p['id']??0);$old=$id?cbe_one($db,'SELECT * FROM tblacademicinterventions WHERE id=? FOR UPDATE',[$id]):null;if($id&&!$old)throw new DomainException('Intervention not found.');
    $student=(int)($p['StudentId']??0);$subject=(int)($p['SubjectId']??0);$tid=(int)($p['TeacherId']??0);$year=(int)($p['AcademicYearId']??0);$term=(int)($p['TermId']??0);
    $s=cbe_one($db,'SELECT ClassId FROM tblstudents WHERE StudentId=? AND Status=1',[$student]);if(!$s)throw new DomainException('Select an active learner.');
    if(!$term)throw new DomainException('Select a term.');cbe_student($db,$student,$s['ClassId'],$subject,$year);cbe_assignment($db,$tid,$s['ClassId'],$subject,$year,$term);
    $status=$p['Status']??'active';if(!in_array($status,['active','review','completed','cancelled'],true))throw new DomainException('Invalid intervention status.');
    $v=[$student,$subject,$tid,$year,$term,cbe_text($p,'Reason',5000),cbe_text($p,'ActionPlan',5000),cbe_date($p['ReviewDate']??''),$status,cbe_text($p,'ReviewNotes',5000,false)];
    if($id)academic_query($db,'UPDATE tblacademicinterventions SET StudentId=?,SubjectId=?,TeacherId=?,AcademicYearId=?,TermId=?,Reason=?,ActionPlan=?,ReviewDate=?,Status=?,ReviewNotes=? WHERE id=?',[...$v,$id]);
    else{academic_query($db,'INSERT INTO tblacademicinterventions(StudentId,SubjectId,TeacherId,AcademicYearId,TermId,Reason,ActionPlan,ReviewDate,Status,ReviewNotes) VALUES(?,?,?,?,?,?,?,?,?,?)',$v);$id=$db->lastInsertId();}
    cbe_audit($db,'academic_intervention_saved','tblacademicinterventions',$id,$old,$v);cbe_notify($db,$tid,'Academic intervention updated','Review due '.$v[7].': '.$v[5],'teacher-academics.php?area=interventions');
}
function cbe_exam_writable($db,$exam) {
    $e=cbe_one($db,'SELECT * FROM tblexams WHERE id=?'.($db->inTransaction()?' FOR UPDATE':''),[$exam]);
    if(!$e||!empty($e['EntryLocked'])||$e['Status']!=='marks_entry'||(!empty($e['MarksOpenDate'])&&$e['MarksOpenDate']>date('Y-m-d'))||(!empty($e['MarksDeadline'])&&$e['MarksDeadline']<date('Y-m-d')))throw new DomainException('Exam result entry is closed. The Dean must unlock/open the examination entry period.');
    return $e;
}
