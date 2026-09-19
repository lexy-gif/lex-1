<?php
require_once __DIR__.'/cbe-academics.php';

function planning_period($db,$p) {
    $year=(int)($p['AcademicYearId']??0);$term=(int)($p['TermId']??0);
    if(!$term)throw new DomainException('Select a term.');
    academic_period($db,$year,$term);
    return [$year,$term];
}
function planning_save($db,$p) {
    staff_assert('planning.manage');
    if(!$db->inTransaction())throw new LogicException('Planning changes require a transaction.');
    [$year,$term]=planning_period($db,$p);
    $id=(int)($p['id']??0);$action=$p['action']??'';
    if($action==='calendar') {
        $kind=$p['EventType']??'';
        if(!in_array($kind,['teaching','assessment','academic_meeting','reporting','submission_deadline'],true))throw new DomainException('Select an academic calendar event type.');
        $old=$id?cbe_one($db,'SELECT * FROM tblacademiccalendar WHERE id=? FOR UPDATE',[$id]):null;
        if($id&&(!$old||!in_array($old['EventType'],['teaching','assessment','academic_meeting','reporting','submission_deadline'],true)))throw new DomainException('Only academic calendar events can be changed here.');
        $start=cbe_date($p['StartDate']??'');$end=cbe_date($p['EndDate']??'');
        if($end<$start)throw new DomainException('The end date must follow the start date.');
        $period=cbe_one($db,'SELECT StartDate,EndDate FROM tblterms WHERE id=?',[$term]);
        if(($period['StartDate']&&$start<$period['StartDate'])||($period['EndDate']&&$end>$period['EndDate']))throw new DomainException('The event must fall within the term dates.');
        $values=[$year,$term,cbe_text($p,'EventTitle',150),$kind,$start,$end,cbe_text($p,'Description',5000,false),(int)cbe_number($p['Status']??1,0,1)];
        if($id)academic_query($db,'UPDATE tblacademiccalendar SET AcademicYearId=?,TermId=?,EventTitle=?,EventType=?,StartDate=?,EndDate=?,Description=?,Status=? WHERE id=?',[...$values,$id]);
        else {academic_query($db,'INSERT INTO tblacademiccalendar(AcademicYearId,TermId,EventTitle,EventType,StartDate,EndDate,Description,Status) VALUES(?,?,?,?,?,?,?,?)',$values);$id=(int)$db->lastInsertId();}
        cbe_audit($db,'academic_calendar_saved','tblacademiccalendar',$id,$old,$values);
        return $id;
    }
    if($action!=='plan')throw new DomainException('Unknown planning action.');
    $old=$id?cbe_one($db,'SELECT * FROM tblacademicplans WHERE id=? FOR UPDATE',[$id]):null;
    if($id&&!$old)throw new DomainException('Academic follow-up not found.');
    $kind=$p['Kind']??'';$status=$p['Status']??'open';
    if(!in_array($kind,['target','coverage_follow_up','missed_lesson','learning_activity','reporting'],true)||!in_array($status,['open','in_progress','completed','cancelled'],true))throw new DomainException('Select a valid academic activity and status.');
    $teacher=(int)($p['TeacherId']??0);academic_teacher($db,$teacher);
    $outcome=cbe_text($p,'Outcome',5000,$status==='completed');
    $values=[$year,$term,$kind,cbe_text($p,'Title',200),$teacher,cbe_date($p['DueDate']??''),$status,cbe_text($p,'Details',5000),$outcome];
    if($id)academic_query($db,'UPDATE tblacademicplans SET AcademicYearId=?,TermId=?,Kind=?,Title=?,TeacherId=?,DueDate=?,Status=?,Details=?,Outcome=? WHERE id=?',[...$values,$id]);
    else {academic_query($db,'INSERT INTO tblacademicplans(AcademicYearId,TermId,Kind,Title,TeacherId,DueDate,Status,Details,Outcome,CreatedBy) VALUES(?,?,?,?,?,?,?,?,?,?)',[...$values,cbe_actor()]);$id=(int)$db->lastInsertId();}
    cbe_audit($db,'academic_follow_up_saved','tblacademicplans',$id,$old,$values);
    cbe_notify($db,$teacher,'Academic follow-up assigned',$values[3].' — review by '.$values[5].'.','teacher-academics.php?area=followups&year='.$year.'&term='.$term);
    return $id;
}
function planning_teacher_update($db,$p,$teacher) {
    if(!$db->inTransaction())throw new LogicException('Follow-up updates require a transaction.');
    $id=(int)($p['id']??0);$old=cbe_one($db,'SELECT * FROM tblacademicplans WHERE id=? AND TeacherId=? FOR UPDATE',[$id,$teacher]);
    if(!$old)throw new DomainException('Select a follow-up assigned to you.');
    if(in_array($old['Status'],['completed','cancelled'],true))throw new DomainException('This follow-up is closed. Ask the academic coordinator to reopen it.');
    $status=$p['Status']??'';if(!in_array($status,['in_progress','completed'],true))throw new DomainException('Select in progress or completed.');
    $outcome=cbe_text($p,'Outcome',5000);
    academic_query($db,'UPDATE tblacademicplans SET Status=?,Outcome=? WHERE id=?',[$status,$outcome,$id]);
    cbe_audit($db,'academic_follow_up_updated','tblacademicplans',$id,$old,['Status'=>$status,'Outcome'=>$outcome]);
}
function planning_resource($db,$p) {
    if(!$db->inTransaction())throw new LogicException('Resource requests require a transaction.');
    if(($p['action']??'')==='resource_decision') {
        staff_assert('resources.approve');$id=(int)($p['id']??0);
        $old=cbe_one($db,'SELECT * FROM tblacademicresourcerequests WHERE id=? FOR UPDATE',[$id]);
        if(!$old||$old['Status']!=='submitted')throw new DomainException('Select a resource request awaiting review.');
        $status=$p['Status']??'';if(!in_array($status,['approved','returned'],true))throw new DomainException('Select approved or returned.');
        if($old['RequestedBy']===cbe_actor())throw new DomainException('Another authorised manager must review your own request.');
        $reason=cbe_text($p,'DecisionReason',5000);
        academic_query($db,'UPDATE tblacademicresourcerequests SET Status=?,DecisionBy=?,DecisionReason=? WHERE id=?',[$status,cbe_actor(),$reason,$id]);
        cbe_audit($db,'academic_resource_decision','tblacademicresourcerequests',$id,$old,['Status'=>$status,'Reason'=>$reason]);
        return;
    }
    staff_assert('resources.request');[$year,$term]=planning_period($db,$p);
    $quantity=filter_var($p['Quantity']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]]);
    if(!$quantity)throw new DomainException('Enter a whole quantity between 1 and 100000.');
    $values=[$year,$term,cbe_text($p,'Title',200),cbe_text($p,'Justification',5000),$quantity,cbe_date($p['RequiredDate']??''),cbe_actor()];
    academic_query($db,'INSERT INTO tblacademicresourcerequests(AcademicYearId,TermId,Title,Justification,Quantity,RequiredDate,RequestedBy) VALUES(?,?,?,?,?,?,?)',$values);
    cbe_audit($db,'academic_resource_requested','tblacademicresourcerequests',$db->lastInsertId(),null,$values);
}
function planning_remind($db,$kind,$id) {
    staff_assert($kind==='assessment'?'assessments.manage':'planning.manage');
    if(!$db->inTransaction())throw new LogicException('Reminders require a transaction.');
    if($kind==='assessment') {
        $row=cbe_one($db,"SELECT a.TeacherId,a.Title FROM tblassessments a JOIN tblassessmentdeadlines d ON d.AssessmentId=a.id WHERE a.id=? AND a.Status IN ('draft','open') AND d.DueDate<CURRENT_DATE FOR UPDATE",[$id]);
        $url='teacher-academics.php?area=assessments&entity=assessment&id='.$id;
    } else {
        $row=cbe_one($db,"SELECT TeacherId,Title FROM tblacademicplans WHERE id=? AND Status IN ('open','in_progress') AND DueDate<CURRENT_DATE FOR UPDATE",[$id]);
        $url='teacher-academics.php?area=followups';
    }
    if(!$row)throw new DomainException('Only overdue, outstanding work can be reminded.');
    $q=academic_query($db,'INSERT IGNORE INTO tblacademicreminders(EntityType,EntityId,TeacherId,ReminderDate) VALUES(?,?,?,CURRENT_DATE)',[$kind,$id,$row['TeacherId']]);
    if(!$q->rowCount())throw new DomainException('A reminder was already sent today.');
    cbe_notify($db,$row['TeacherId'],'Academic work overdue',$row['Title'].' is overdue. Please submit an update.',$url);
    cbe_audit($db,'academic_reminder_sent',$kind,$id,null,['TeacherId'=>$row['TeacherId']]);
}
