<?php
require_once __DIR__.'/cbe-academics.php';
require_once __DIR__.'/timetable.php';
function cbe_time($value) {if(!is_string($value)||!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D',$value))throw new DomainException('Enter a valid time in HH:MM format.');return strlen($value)===5?$value.':00':$value;}
function cbe_schedule_lock($db) {
    if(!$db->inTransaction())throw new LogicException('Timetable changes require a transaction.');
    if(!academic_query($db,'SELECT id FROM tblacademicsettings WHERE id=1 FOR UPDATE')->fetchColumn())throw new DomainException('Academic timetable setup is missing.');
}
function cbe_schedule_id($p,$key,$optional=false) {
    $value=$p[$key]??null;
    if($optional&&($value===null||$value===''))return null;
    $id=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!$id)throw new DomainException('Select a valid '.preg_replace('/Id$/','',$key).'.');
    return $id;
}
function cbe_schedule_save($db,$p,$exam=false,$sendNotifications=true) {
    cbe_schedule_lock($db);
    $id=filter_var($p['id']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);if($id===false)throw new DomainException('Timetable entry not found.');
    $table=$exam?'tblexamtimetableentries':'tblclasstimetableentries';$old=$id?cbe_one($db,"SELECT * FROM $table WHERE id=? FOR UPDATE",[$id]):null;if($id&&!$old)throw new DomainException('Timetable entry not found.');
    if($exam&&$old)$old['Invigilators']=timetable_exam_invigilators($db,$id);
    $class=cbe_schedule_id($p,'ClassId');$subject=cbe_schedule_id($p,'SubjectId');$room=cbe_schedule_id($p,'RoomId',true);$start=cbe_time($p['StartTime']??'');$end=cbe_time($p['EndTime']??'');if($end<=$start)throw new DomainException('End time must be after start time.');
    academic_class_lock($db,$class);academic_offering($db,$class,$subject);if($room&&!in_array($room,array_column(cbe_options($db,'rooms'),'id')))throw new DomainException('Select an active room.');
    $status=$p['Status']??'draft';if(!in_array($status,['draft','ready_for_review','approved','published','updated','cancelled','archived'],true))throw new DomainException('Invalid timetable status.');
    $reason=cbe_text($p,'ChangeReason',255,false);$notify=[];
    if($exam) {
        $examId=cbe_schedule_id($p,'ExamId');$e=cbe_one($db,'SELECT * FROM tblexams WHERE id=? FOR UPDATE',[$examId]);if(!$e||($e['ClassId']&&(int)$e['ClassId']!==$class))throw new DomainException('Select an examination offered to this class.');
        academic_period($db,$e['AcademicYearId'],$e['TermId']);
        $date=cbe_date(cbe_text($p,'ExamDate',10));if(($e['StartDate']&&$date<$e['StartDate'])||($e['EndDate']&&$date>$e['EndDate']))throw new DomainException('The session date must fall within the examination dates.');
        $teachers=$p['Invigilators']??[];if(!is_array($teachers))throw new DomainException('Select valid invigilators.');$teachers=array_values($teachers);
        foreach($teachers as &$t) {$t=filter_var($t,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$t)throw new DomainException('Select valid invigilators.');}unset($t);
        if(count($teachers)!==count(array_unique($teachers)))throw new DomainException('Duplicate invigilator selection.');
        foreach($teachers as $t)academic_teacher($db,$t);
        if(!in_array($status,['cancelled','archived'],true)) {
            $conflicts=timetable_exam_conflicts($db,$examId,$class,$room,$teachers,$date,$start,$end,$id);if($conflicts)throw new DomainException(implode(' ',$conflicts));
            foreach($teachers as $t)if(academic_query($db,'SELECT id FROM tblteacheravailability WHERE TeacherId=? AND DayOfWeek=? AND StartTime<? AND ?<EndTime',[$t,date('l',strtotime($date)),$end,$start])->fetchColumn())throw new DomainException('An invigilator is unavailable during this time.');
        }
        $v=[$examId,$class,$subject,$room,$teachers[0]??null,$date,$start,$end,$status,$reason,cbe_actor()];
        $cols='ExamId,ClassId,SubjectId,RoomId,InvigilatorId,ExamDate,StartTime,EndTime,Status,ChangeReason,CreatedBy';$notify=$teachers;
    } else {
        $year=cbe_schedule_id($p,'AcademicYearId');$term=cbe_schedule_id($p,'TermId');$teacher=cbe_schedule_id($p,'TeacherId');cbe_assignment($db,$teacher,$class,$subject,$year,$term);
        $day=$p['DayOfWeek']??'';if(!in_array($day,['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],true))throw new DomainException('Select a valid school day.');
        if(!in_array($status,['cancelled','archived'],true)) {
            $conflicts=timetable_lesson_conflicts($db,$year,$term,$class,$teacher,$room,$day,$start,$end,$id);if($conflicts)throw new DomainException(implode(' ',$conflicts));
            if(academic_query($db,'SELECT id FROM tblteacheravailability WHERE TeacherId=? AND DayOfWeek=? AND StartTime<? AND ?<EndTime',[$teacher,$day,$end,$start])->fetchColumn())throw new DomainException('Teacher is unavailable during this time.');
        }
        $v=[$year,$term,$class,$subject,$teacher,$room,$day,$start,$end,$status,$reason,cbe_actor()];$cols='AcademicYearId,TermId,ClassId,SubjectId,TeacherId,RoomId,DayOfWeek,StartTime,EndTime,Status,ChangeReason,CreatedBy';$notify=[$teacher];
    }
    // Editing or publishing an existing entry preserves its original author.
    if($old)$v[count($v)-1]=$old['CreatedBy'];
    if($id)academic_query($db,"UPDATE $table SET ".implode(',',array_map(fn($c)=>$c.'=?',explode(',',$cols))).' WHERE id=?',[...$v,$id]);
    else{academic_query($db,"INSERT INTO $table($cols) VALUES(".implode(',',array_fill(0,count($v),'?')).')',$v);$id=(int)$db->lastInsertId();}
    if($exam){academic_query($db,'DELETE FROM tblexaminvigilators WHERE SessionId=?',[$id]);foreach($teachers as $t)academic_query($db,'INSERT INTO tblexaminvigilators VALUES(?,?)',[$id,$t]);}
    $saved=array_combine(explode(',',$cols),$v);if($exam)$saved['Invigilators']=$teachers;
    timetable_record_version($db,$exam?'exam':'class',$id,'academic_schedule_saved',json_encode($old),json_encode($saved),$reason,cbe_actor());
    cbe_audit($db,$exam?'exam_session_saved':'lesson_saved',$table,$id,$old,$saved);
    if($sendNotifications&&($status==='published'||($old['Status']??'')==='published')) {
        $previous=$exam?($old['Invigilators']??[]):array_filter([$old['TeacherId']??null]);
        $notify=[...$notify,...academic_query($db,'SELECT a.TeacherId FROM tblclassteacherassignments a JOIN tblusers u ON u.id=a.TeacherId AND u.Status=1 WHERE a.ClassId=? AND a.AcademicYearId=? AND a.Status=1',[$class,$exam?$e['AcademicYearId']:$year])->fetchAll(PDO::FETCH_COLUMN)];
        $url='teacher-academics.php?area=timetable&year='.($exam?$e['AcademicYearId']:$year).'&term='.($exam?$e['TermId']:$term);
        foreach(array_unique([...$notify,...$previous]) as $t)cbe_notify($db,$t,$exam?'Invigilation timetable updated':'Teaching timetable updated','Your academic timetable has changed. Please review the schedule.',$url,$exam?'EXAM_TIMETABLE':'TIMETABLE');
    }
    return $id;
}

function cbe_schedule_publish($db,$scope,$year=0,$term=0,$exam=false) {
    cbe_schedule_lock($db);
    if($exam) {
        $ex=cbe_one($db,'SELECT * FROM tblexams WHERE id=? FOR UPDATE',[$scope]);
        if(!$ex)throw new DomainException('Select a valid examination.');
        $year=(int)$ex['AcademicYearId'];$term=(int)$ex['TermId'];academic_period($db,$year,$term);
        $rows=cbe_rows($db,"SELECT * FROM tblexamtimetableentries WHERE ExamId=? AND Status NOT IN ('cancelled','archived') ORDER BY id FOR UPDATE",[$scope]);
    } else {
        if(!$term)throw new DomainException('Select one academic term before publishing.');
        academic_period($db,$year,$term);academic_class_lock($db,$scope);
        $rows=cbe_rows($db,"SELECT * FROM tblclasstimetableentries WHERE ClassId=? AND AcademicYearId=? AND TermId=? AND Status NOT IN ('cancelled','archived') ORDER BY id FOR UPDATE",[$scope,$year,$term]);
    }
    $pending=array_filter($rows,fn($row)=>$row['Status']!=='published');
    if(!$pending)throw new DomainException('No unpublished timetable entries were found.');
    $recipients=[];$classes=[];
    foreach($pending as $row) {
        $row['Status']='published';
        if($exam)$row['Invigilators']=timetable_exam_invigilators($db,$row['id']);
        cbe_schedule_save($db,$row,$exam,false);
        $recipients=[...$recipients,...($exam?$row['Invigilators']:[$row['TeacherId']])];
        $classes[(int)$row['ClassId']]=true;
    }
    foreach(array_keys($classes) as $class)$recipients=[...$recipients,...academic_query($db,'SELECT a.TeacherId FROM tblclassteacherassignments a JOIN tblusers u ON u.id=a.TeacherId AND u.Status=1 WHERE a.ClassId=? AND a.AcademicYearId=? AND a.Status=1',[$class,$year])->fetchAll(PDO::FETCH_COLUMN)];
    foreach(array_unique(array_filter($recipients)) as $teacher)cbe_notify($db,$teacher,$exam?'Exam timetable published':'Class timetable published','The Dean has published a timetable affecting you. Review your academic timetable.','teacher-academics.php?area=timetable&year='.$year.'&term='.$term,$exam?'EXAM_TIMETABLE':'TIMETABLE');
    cbe_audit($db,$exam?'exam_timetable_published':'class_timetable_published',$exam?'tblexams':'tblclasses',$scope,null,['year'=>$year,'term'=>$term,'entries'=>array_column($pending,'id')]);
    return count($pending);
}
