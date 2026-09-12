<?php
require_once __DIR__.'/cbe-academics.php';

function senior_ready($db) {
    try { return (bool)academic_query($db,"SELECT 1 FROM tblschemamigrations WHERE Name='senior-school-v1'")->fetchColumn(); }
    catch(PDOException $e) { return false; }
}
function senior_id($value, $label, $optional=false) {
    if($optional && ($value===null || $value==='' || $value===0 || $value==='0'))return null;
    $id=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!$id)throw new DomainException('Select a valid '.$label.'.');
    return $id;
}
function senior_ids($values,$label='subjects') {
    if(!is_array($values))throw new DomainException('Select valid '.$label.'.');
    $ids=array_map(fn($v)=>senior_id($v,$label),$values);
    if(count($ids)!==count(array_unique($ids)))throw new DomainException('Duplicate '.$label.' selection.');
    sort($ids); return $ids;
}
function senior_status($value) {
    if(!in_array($value,[0,1,'0','1'],true))throw new DomainException('Select Active or Inactive.');
    return (int)$value;
}
function senior_lock($db) {
    if(!$db->inTransaction())throw new LogicException('Senior School writes require a transaction.');
    if(!senior_ready($db))throw new DomainException('Apply the Senior School migration first.');
    return cbe_one($db,'SELECT * FROM tblacademicsettings WHERE id=1 FOR UPDATE');
}
function senior_class($db,$class) {
    return cbe_one($db,'SELECT c.*,g.GradeNumber,g.Name GradeName,g.Status GradeStatus FROM tblclasses c JOIN tblgrades g ON g.id=c.GradeId WHERE c.id=?',[$class]);
}
function senior_is_class($db,$class) {
    if(!senior_ready($db))return false;
    $c=senior_class($db,$class); return $c && $c['GradeNumber']>=10 && $c['GradeNumber']<=12;
}
function senior_subject($db,$subject,$type=null,$role=null,$grade=null) {
    $s=cbe_one($db,'SELECT * FROM tblsubjects WHERE id=?',[$subject]);
    if(!$s || !$s['Status'] || ($type && $s['SeniorType']!==$type) || ($role && $s['CoreRole']!==$role))
        throw new DomainException('Select an active '.($role?:$type?:'Senior School').' subject.');
    if($grade && !academic_query($db,'SELECT 1 FROM tblsubjectgrades WHERE SubjectId=? AND GradeId=?',[$subject,$grade])->fetchColumn())
        throw new DomainException($s['SubjectName'].' is not configured for this grade.');
    return $s;
}
function senior_track($db,$pathway,$track) {
    $p=cbe_one($db,'SELECT * FROM tblpathways WHERE id=? AND Status=1',[$pathway]);
    $t=cbe_one($db,'SELECT * FROM tblpathwaytracks WHERE id=? AND PathwayId=? AND Status=1',[$track,$pathway]);
    if(!$p || !$t)throw new DomainException('Select an active track belonging to the selected active pathway.');
    return $p;
}
function senior_electives($db,$pathway,$track,$grade=0) {
    return cbe_rows($db,"SELECT DISTINCT s.id,s.SubjectName Label FROM tblsubjects s JOIN tblpathwaysubjects ps ON ps.SubjectId=s.id
        JOIN tblpathways p ON p.id=ps.PathwayId AND p.Status=1
        WHERE s.Status=1 AND s.SeniorType='elective' AND ps.PathwayId=? AND (ps.TrackId IS NULL OR ps.TrackId=?)
        AND (?=0 OR EXISTS(SELECT 1 FROM tblsubjectgrades sg WHERE sg.SubjectId=s.id AND sg.GradeId=?)) ORDER BY s.SubjectName",[$pathway,$track,$grade,$grade]);
}
function senior_validate_electives($db,$subjects,$pathway,$track,$grade=0) {
    $path=senior_track($db,$pathway,$track);
    $settings=cbe_one($db,'SELECT SeniorElectiveCount FROM tblacademicsettings WHERE id=1');
    $count=(int)($path['ElectiveCount']??$settings['SeniorElectiveCount']);
    if(count($subjects)!==$count)throw new DomainException('Select exactly '.$count.' elective subjects for this pathway.');
    $allowed=array_map('intval',array_column(senior_electives($db,$pathway,$track,$grade),'id'));
    foreach($subjects as $subject)if(!in_array($subject,$allowed,true))throw new DomainException('An elective is inactive, unrelated to the pathway/track, or unavailable for the grade.');
}
function senior_save_pathway($db,$p) {
    senior_lock($db); $id=senior_id($p['id']??null,'pathway',true);
    $name=cbe_text($p,'Name',120); $status=senior_status($p['Status']??1);
    $old=$id?cbe_one($db,'SELECT * FROM tblpathways WHERE id=? FOR UPDATE',[$id]):null;
    if($id&&!$old)throw new DomainException('Pathway not found.');
    if(academic_query($db,'SELECT id FROM tblpathways WHERE Name=? AND id<>?',[$name,$id??0])->fetchColumn())throw new DomainException('A pathway with this name already exists.');
    $math=senior_id($p['MathSubjectId']??($old['MathSubjectId']??null),'mathematics subject',true);
    if($math)senior_subject($db,$math,'core','mathematics');
    $count=($p['ElectiveCount']??'')===''?null:senior_id($p['ElectiveCount'],'elective count');
    if($count>12)throw new DomainException('Elective count must be between 1 and 12.');
    $values=[$name,cbe_text($p,'Description',5000,false),$status,$math,$count];
    if($id)academic_query($db,'UPDATE tblpathways SET Name=?,Description=?,Status=?,MathSubjectId=?,ElectiveCount=? WHERE id=?',[...$values,$id]);
    else {academic_query($db,'INSERT INTO tblpathways(Name,Description,Status,MathSubjectId,ElectiveCount) VALUES(?,?,?,?,?)',$values);$id=(int)$db->lastInsertId();}
    cbe_audit($db,'senior_pathway_saved','tblpathways',$id,$old,$values);return $id;
}
function senior_save_track($db,$p) {
    senior_lock($db);$id=senior_id($p['id']??null,'track',true);$path=senior_id($p['PathwayId']??null,'pathway');
    if(!academic_query($db,'SELECT id FROM tblpathways WHERE id=? AND Status=1',[$path])->fetchColumn())throw new DomainException('Select an active pathway.');
    $name=cbe_text($p,'Name',150);$status=senior_status($p['Status']??1);
    $old=$id?cbe_one($db,'SELECT * FROM tblpathwaytracks WHERE id=? FOR UPDATE',[$id]):null;
    if($id&&!$old)throw new DomainException('Track not found.');
    if($old && (int)$old['PathwayId']!==$path && (academic_query($db,'SELECT id FROM tblschoolcombinations WHERE TrackId=? LIMIT 1',[$id])->fetchColumn() || academic_query($db,'SELECT id FROM tblpathwaysubjects WHERE TrackId=? LIMIT 1',[$id])->fetchColumn() || academic_query($db,'SELECT id FROM tblstudentpathways WHERE TrackId=? LIMIT 1',[$id])->fetchColumn()))throw new DomainException('A track with subjects, combinations or history cannot move to another pathway. Create a new track.');
    if(academic_query($db,'SELECT id FROM tblpathwaytracks WHERE PathwayId=? AND Name=? AND id<>?',[$path,$name,$id??0])->fetchColumn())throw new DomainException('This track already exists under the pathway.');
    if($id)academic_query($db,'UPDATE tblpathwaytracks SET PathwayId=?,Name=?,Status=? WHERE id=?',[$path,$name,$status,$id]);
    else {academic_query($db,'INSERT INTO tblpathwaytracks(PathwayId,Name,Status) VALUES(?,?,?)',[$path,$name,$status]);$id=(int)$db->lastInsertId();}
    cbe_audit($db,'senior_track_saved','tblpathwaytracks',$id,$old,$p);return $id;
}
function senior_save_settings($db,$p) {
    $old=senior_lock($db);$count=senior_id($p['SeniorElectiveCount']??null,'elective count');
    if($count>12)throw new DomainException('Elective count must be between 1 and 12.');
    $language=senior_id($p['SeniorLanguageSubjectId']??null,'default language');$math=senior_id($p['SeniorMathSubjectId']??null,'default mathematics');
    senior_subject($db,$language,'core','language');senior_subject($db,$math,'core','mathematics');
    academic_query($db,'UPDATE tblacademicsettings SET SeniorElectiveCount=?,SeniorLanguageSubjectId=?,SeniorMathSubjectId=? WHERE id=1',[$count,$language,$math]);
    cbe_audit($db,'senior_policy_saved','tblacademicsettings',1,$old,$p);
}
function senior_save_subject($db,$p) {
    senior_lock($db);$id=senior_id($p['id']??null,'subject',true);$name=cbe_text($p,'SubjectName',100);$code=cbe_text($p,'SubjectCode',100);
    $old=$id?cbe_one($db,'SELECT * FROM tblsubjects WHERE id=? FOR UPDATE',[$id]):null;
    if($id&&!$old)throw new DomainException('Subject not found.');
    if(academic_query($db,'SELECT id FROM tblsubjects WHERE (SubjectName=? OR SubjectCode=?) AND id<>?',[$name,$code,$id??0])->fetchColumn())throw new DomainException('A subject with this name or code already exists.');
    $type=$p['SeniorType']??'';if(!in_array($type,['core','elective'],true))throw new DomainException('Choose Core or Elective.');
    $role=$type==='core'?($p['CoreRole']??'common'):null;
    if($role && !in_array($role,['common','language','mathematics'],true))throw new DomainException('Select a valid core subject role.');
    if($old && $old['SeniorType'] && ($old['SeniorType']!==$type || $old['CoreRole']!==$role) && academic_query($db,'SELECT id FROM tblstudentsubjects WHERE SubjectId=? LIMIT 1',[$id])->fetchColumn())throw new DomainException('This subject has learner history. Keep its type and core role; create a new subject for a curriculum replacement.');
    $grades=senior_ids($p['Grades']??[],'grades');if(!$grades)throw new DomainException('Select at least one Senior School grade.');
    foreach($grades as $g)if(!academic_query($db,'SELECT id FROM tblgrades WHERE id=? AND GradeNumber BETWEEN 10 AND 12 AND Status=1',[$g])->fetchColumn())throw new DomainException('Select active Grades 10, 11 or 12.');
    $values=[$name,$code,$type,$role,senior_status($p['Status']??1)];
    if($id)academic_query($db,'UPDATE tblsubjects SET SubjectName=?,SubjectCode=?,SeniorType=?,CoreRole=?,Status=? WHERE id=?',[...$values,$id]);
    else {academic_query($db,'INSERT INTO tblsubjects(SubjectName,SubjectCode,SeniorType,CoreRole,Status) VALUES(?,?,?,?,?)',$values);$id=(int)$db->lastInsertId();}
    // Senior configuration must retain mappings used by lower grades.
    academic_query($db,'DELETE sg FROM tblsubjectgrades sg JOIN tblgrades g ON g.id=sg.GradeId WHERE sg.SubjectId=? AND g.GradeNumber BETWEEN 10 AND 12',[$id]);
    foreach($grades as $g)academic_query($db,'INSERT INTO tblsubjectgrades(SubjectId,GradeId) VALUES(?,?)',[$id,$g]);
    cbe_audit($db,'senior_subject_saved','tblsubjects',$id,$old,$p);return $id;
}
function senior_map_subjects($db,$p) {
    senior_lock($db);$path=senior_id($p['PathwayId']??null,'pathway');$track=senior_id($p['TrackId']??null,'track',true);
    if(!academic_query($db,'SELECT id FROM tblpathways WHERE id=? AND Status=1',[$path])->fetchColumn())throw new DomainException('Select an active pathway.');
    if($track)senior_track($db,$path,$track);
    $subjects=senior_ids($p['Subjects']??[]);foreach($subjects as $s)senior_subject($db,$s,'elective');
    $old=cbe_rows($db,'SELECT SubjectId FROM tblpathwaysubjects WHERE PathwayId=? AND TrackScope=?',[$path,$track??0]);
    academic_query($db,'DELETE FROM tblpathwaysubjects WHERE PathwayId=? AND TrackScope=?',[$path,$track??0]);
    foreach($subjects as $s)academic_query($db,'INSERT INTO tblpathwaysubjects(PathwayId,TrackId,SubjectId) VALUES(?,?,?)',[$path,$track,$s]);
    cbe_audit($db,'senior_subject_mapping_saved','tblpathways',$path,$old,['track'=>$track,'subjects'=>$subjects]);
}
function senior_save_combination($db,$p) {
    senior_lock($db);$id=senior_id($p['id']??null,'combination',true);$year=senior_id($p['AcademicYearId']??null,'academic year');academic_period($db,$year);
    $track=senior_id($p['TrackId']??null,'track');$t=cbe_one($db,'SELECT * FROM tblpathwaytracks WHERE id=?',[$track]);
    $path=senior_id($p['PathwayId']??($t['PathwayId']??null),'pathway');
    $subjects=senior_ids($p['Subjects']??[]);senior_validate_electives($db,$subjects,$path,$track);
    $name=cbe_text($p,'Name',150);$code=cbe_text($p,'Code',50,false);$status=senior_status($p['Status']??1);
    $old=$id?cbe_one($db,'SELECT * FROM tblschoolcombinations WHERE id=? FOR UPDATE',[$id]):null;
    if($id&&!$old)throw new DomainException('Combination not found.');
    if(academic_query($db,'SELECT id FROM tblschoolcombinations WHERE AcademicYearId=? AND (Name=? OR (?<>\'\' AND Code=?)) AND id<>?',[$year,$name,$code,$code,$id??0])->fetchColumn())throw new DomainException('This combination name or code already exists in the academic year.');
    if($old && ((int)$old['TrackId']!==$track || (int)$old['AcademicYearId']!==$year) && academic_query($db,'SELECT id FROM tblstudentpathways WHERE CombinationId=? LIMIT 1',[$id])->fetchColumn())throw new DomainException('A combination with learner history cannot change its track or year. Create a new combination.');
    $oldSubjects=$id?academic_query($db,'SELECT SubjectId FROM tblschoolcombinationsubjects WHERE CombinationId=? ORDER BY SubjectId',[$id])->fetchAll(PDO::FETCH_COLUMN):[];
    $values=[$track,$year,$code,$name,$status];
    if($id)academic_query($db,'UPDATE tblschoolcombinations SET TrackId=?,AcademicYearId=?,Code=?,Name=?,Status=? WHERE id=?',[...$values,$id]);
    else {academic_query($db,'INSERT INTO tblschoolcombinations(TrackId,AcademicYearId,Code,Name,Status) VALUES(?,?,?,?,?)',$values);$id=(int)$db->lastInsertId();}
    academic_query($db,'DELETE FROM tblschoolcombinationsubjects WHERE CombinationId=?',[$id]);
    foreach($subjects as $s)academic_query($db,'INSERT INTO tblschoolcombinationsubjects(CombinationId,SubjectId) VALUES(?,?)',[$id,$s]);
    cbe_audit($db,'senior_combination_saved','tblschoolcombinations',$id,['record'=>$old,'subjects'=>$oldSubjects],['record'=>$values,'subjects'=>$subjects]);return $id;
}
function senior_toggle($db,$entity,$id,$status,$delete=false) {
    senior_lock($db);$tables=['pathway'=>'tblpathways','track'=>'tblpathwaytracks','subject'=>'tblsubjects','combination'=>'tblschoolcombinations'];
    if(!isset($tables[$entity]))throw new DomainException('Invalid record type.');$table=$tables[$entity];
    $old=cbe_one($db,"SELECT * FROM $table WHERE id=? FOR UPDATE",[$id]);if(!$old)throw new DomainException('Record not found.');
    if($delete) {
        if($entity!=='combination')throw new DomainException('Deactivate this record to preserve its history.');
        if(academic_query($db,'SELECT id FROM tblstudentpathways WHERE CombinationId=? LIMIT 1',[$id])->fetchColumn() || academic_query($db,'SELECT id FROM tblpathwayguidance WHERE PreferredCombinationId=? LIMIT 1',[$id])->fetchColumn())throw new DomainException('This combination has learner history or guidance references. Deactivate it instead.');
        academic_query($db,'DELETE FROM tblschoolcombinationsubjects WHERE CombinationId=?',[$id]);academic_query($db,'DELETE FROM tblschoolcombinations WHERE id=?',[$id]);
    } else academic_query($db,"UPDATE $table SET Status=? WHERE id=?",[senior_status($status),$id]);
    cbe_audit($db,$delete?'senior_combination_deleted':'senior_status_changed',$table,$id,$old,['Status'=>$status]);
}

function senior_core_subjects($db,$grade,$pathway=0,$language=null,$math=null) {
    $settings=cbe_one($db,'SELECT * FROM tblacademicsettings WHERE id=1');
    $path=$pathway?cbe_one($db,'SELECT * FROM tblpathways WHERE id=?',[$pathway]):null;
    $language=$language?:$settings['SeniorLanguageSubjectId'];
    $math=$math?:($path['MathSubjectId']??$settings['SeniorMathSubjectId']);
    senior_subject($db,$language,'core','language',$grade);senior_subject($db,$math,'core','mathematics',$grade);
    $subjects=academic_query($db,"SELECT s.id FROM tblsubjects s JOIN tblsubjectgrades sg ON sg.SubjectId=s.id WHERE s.SeniorType='core' AND s.CoreRole='common' AND sg.GradeId=? ORDER BY s.id",[$grade])->fetchAll(PDO::FETCH_COLUMN);
    if(!$subjects)throw new DomainException('Configure the common core subjects for this grade first.');
    foreach($subjects as $s)senior_subject($db,$s,'core','common',$grade);
    return senior_ids([...$subjects,$language,$math]);
}
function senior_ensure_offering($db,$class,$subject) {
    $rows=cbe_rows($db,'SELECT id,status FROM tblsubjectcombination WHERE ClassId=? AND SubjectId=? FOR UPDATE',[$class,$subject]);
    if(!$rows)academic_query($db,'INSERT INTO tblsubjectcombination(ClassId,SubjectId,status) VALUES(?,?,1)',[$class,$subject]);
    elseif(!in_array(1,array_column($rows,'status')))throw new DomainException('A required class subject offering is inactive. Activate it in Class Subject Offerings first.');
}
function senior_enroll($db,$student,$year,$class) {
    $c=senior_class($db,$class);if(!$c || !$c['GradeStatus'])throw new DomainException('Select a class with an active configured grade.');
    $old=cbe_one($db,'SELECT * FROM tblstudentenrollments WHERE StudentId=? AND AcademicYearId=? FOR UPDATE',[$student,$year]);
    if($old && ((int)$old['ClassId']!==(int)$class || (int)$old['GradeId']!==(int)$c['GradeId']))throw new DomainException('This academic year already has a different class enrollment. Earlier enrollment must be preserved; use a new year for promotion.');
    if(!$old)academic_query($db,'INSERT INTO tblstudentenrollments(StudentId,AcademicYearId,ClassId,GradeId,RecordedBy) VALUES(?,?,?,?,?)',[$student,$year,$class,$c['GradeId'],cbe_actor()]);
    return $c;
}
function senior_sync_core($db,$student,$year=null) {
    if(!senior_ready($db))return;
    senior_lock($db);$s=cbe_one($db,'SELECT * FROM tblstudents WHERE StudentId=? FOR UPDATE',[$student]);
    if(!$s || !senior_is_class($db,$s['ClassId']))return;
    $year=$year?:academic_year($db);academic_period($db,$year);
    $c=senior_enroll($db,$student,$year,$s['ClassId']);
    $allocation=cbe_one($db,'SELECT * FROM tblstudentpathways WHERE StudentId=? AND AcademicYearId=? AND Status=1',[$student,$year]);
    $core=senior_core_subjects($db,$c['GradeId'],$allocation['PathwayId']??0,$s['SeniorLanguageSubjectId'],$s['SeniorMathSubjectId']);
    foreach($core as $subject)senior_ensure_offering($db,$s['ClassId'],$subject);
    academic_query($db,"UPDATE tblstudentsubjects SET Status=0 WHERE StudentId=? AND ClassId=? AND AcademicYearId=? AND AssignmentSource='core'",[$student,$s['ClassId'],$year]);
    foreach($core as $subject)academic_query($db,"INSERT INTO tblstudentsubjects(StudentId,SubjectId,ClassId,AcademicYearId,AssignmentSource) VALUES(?,?,?,?,'core') ON DUPLICATE KEY UPDATE Status=1,AssignmentSource='core'",[$student,$subject,$s['ClassId'],$year]);
}
function senior_notify_pathway($db,$student,$allocation,$message,$subjects) {
    $s=cbe_one($db,'SELECT * FROM tblstudents WHERE StudentId=?',[$student]);
    $a=cbe_one($db,'SELECT * FROM tblstudentpathways WHERE id=?',[$allocation]);
    $recipients=academic_query($db,'SELECT TeacherId FROM tblclassteacherassignments WHERE ClassId=? AND AcademicYearId=? AND Status=1',[$s['ClassId'],$a['AcademicYearId']])->fetchAll(PDO::FETCH_COLUMN);
    foreach($subjects as $subject)$recipients=[...$recipients,...academic_query($db,'SELECT TeacherId FROM tblsubjectteacherassignments WHERE ClassId=? AND SubjectId=? AND AcademicYearId=? AND Status=1',[$s['ClassId'],$subject,$a['AcademicYearId']])->fetchAll(PDO::FETCH_COLUMN)];
    $studentAccount=academic_query($db,"SELECT id FROM tblusers WHERE StudentId=? AND Role='student' AND Status=1",[$student])->fetchColumn();
    if($studentAccount)$recipients[]=$studentAccount;
    $options=['category'=>'SYSTEM','type'=>'SENIOR_PATHWAY','class_id'=>$s['ClassId'],'related_entity_type'=>'tblstudentpathways','related_entity_id'=>$allocation];
    // A school-wide in-app record remains available even when no recipient account exists yet.
    academic_query($db,"INSERT INTO tblteachernotifications(TeacherId,ClassId,Type,Category,RelatedEntityType,RelatedEntityId,Title,Message,ActionUrl) VALUES(NULL,?,'SENIOR_PATHWAY','SYSTEM','tblstudentpathways',?,'Learner pathway updated',?,?)",[$s['ClassId'],$allocation,$message,'dean-senior-assignments.php?student='.$student.'&year='.$a['AcademicYearId']]);
    foreach(array_unique($recipients) as $recipient) {
        if(!academic_query($db,'SELECT id FROM tblusers WHERE id=? AND Status=1',[$recipient])->fetchColumn())continue;
        $options['action_url']=(int)$recipient===(int)$studentAccount?'student-senior.php':'teacher-senior.php?year='.$a['AcademicYearId'];
        notification_create($db,$recipient,'Learner pathway updated',$message,$options);
    }
}
function senior_assign_learner($db,$student,$p) {
    senior_lock($db);$year=senior_id($p['AcademicYearId']??null,'academic year');academic_period($db,$year);
    $s=cbe_one($db,'SELECT * FROM tblstudents WHERE StudentId=? AND Status=1 FOR UPDATE',[$student]);
    if(!$s || !senior_is_class($db,$s['ClassId']))throw new DomainException('Select an active learner in Grade 10, 11 or 12.');
    $c=senior_enroll($db,$student,$year,$s['ClassId']);
    $combination=senior_id($p['CombinationId']??null,'combination',true);
    $co=$combination?cbe_one($db,'SELECT * FROM tblschoolcombinations WHERE id=? AND AcademicYearId=? AND Status=1',[$combination,$year]):null;
    if($combination&&!$co)throw new DomainException('Select an active combination for the selected academic year.');
    $track=senior_id($p['TrackId']??($co['TrackId']??null),'track');
    $t=cbe_one($db,'SELECT * FROM tblpathwaytracks WHERE id=?',[$track]);
    $path=senior_id($p['PathwayId']??($t['PathwayId']??null),'pathway');
    senior_track($db,$path,$track);
    if($co && (int)$co['TrackId']!==$track)throw new DomainException('The combination must belong to the selected pathway and track.');
    $electives=senior_ids($co?academic_query($db,'SELECT SubjectId FROM tblschoolcombinationsubjects WHERE CombinationId=?',[$combination])->fetchAll(PDO::FETCH_COLUMN):($p['Subjects']??[]));
    senior_validate_electives($db,$electives,$path,$track,$c['GradeId']);
    $language=senior_id($p['SeniorLanguageSubjectId']??$s['SeniorLanguageSubjectId'],'language subject',true);
    $math=senior_id($p['SeniorMathSubjectId']??$s['SeniorMathSubjectId'],'mathematics subject',true);
    $core=senior_core_subjects($db,$c['GradeId'],$path,$language,$math);
    $subjects=senior_ids([...$core,...$electives]);
    $old=cbe_one($db,'SELECT * FROM tblstudentpathways WHERE StudentId=? AND AcademicYearId=? AND Status=1 FOR UPDATE',[$student,$year]);
    if(array_key_exists('ExpectedAllocationId',$p) && (int)$p['ExpectedAllocationId']!==(int)($old['id']??0))throw new DomainException('The pathway assignment changed since this page was opened. Reload and review it before saving.');
    $oldSubjects=$old?array_map('intval',academic_query($db,'SELECT SubjectId FROM tblpathwayallocationsubjects WHERE AllocationId=? ORDER BY SubjectId',[$old['id']])->fetchAll(PDO::FETCH_COLUMN)):[];
    if($old && (int)$old['PathwayId']===$path && (int)$old['TrackId']===$track && (int)$old['CombinationId']===(int)$combination && $oldSubjects===$subjects && (int)$s['SeniorLanguageSubjectId']===(int)$language && (int)$s['SeniorMathSubjectId']===(int)$math)return (int)$old['id'];
    if($old && empty($p['ConfirmReplacement']))throw new DomainException('Confirm replacement of the current pathway or subject selection. Earlier allocations and results will be retained.');
    foreach($subjects as $subject)senior_ensure_offering($db,$s['ClassId'],$subject);
    $before=cbe_rows($db,'SELECT SubjectId,ClassId,AssignmentSource FROM tblstudentsubjects WHERE StudentId=? AND AcademicYearId=? AND Status=1',[$student,$year]);
    if($old)academic_query($db,'UPDATE tblstudentpathways SET Status=0,EndedAt=CURRENT_TIMESTAMP WHERE id=?',[$old['id']]);
    academic_query($db,'INSERT INTO tblstudentpathways(StudentId,ClassId,CombinationId,AcademicYearId,PlacementSource,ReferenceNotes,PathwayId,TrackId,GradeId,SeniorLanguageSubjectId,SeniorMathSubjectId) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$student,$s['ClassId'],$combination,$year,trim($p['PlacementSource']??'')?:'School Dean',cbe_text($p,'ReferenceNotes',5000,false),$path,$track,$c['GradeId'],$language,$math]);
    $id=(int)$db->lastInsertId();
    // End registrations, never delete them or examination results. The allocation snapshot is immutable.
    academic_query($db,'UPDATE tblstudentsubjects SET Status=0 WHERE StudentId=? AND AcademicYearId=?',[$student,$year]);
    foreach($subjects as $subject) {
        $type=in_array($subject,$core,true)?'core':'elective';
        academic_query($db,'INSERT INTO tblstudentsubjects(StudentId,SubjectId,ClassId,AcademicYearId,AssignmentSource) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE Status=1,AssignmentSource=VALUES(AssignmentSource)',[$student,$subject,$s['ClassId'],$year,$type]);
        academic_query($db,'INSERT INTO tblpathwayallocationsubjects(AllocationId,SubjectId,SubjectType) VALUES(?,?,?)',[$id,$subject,$type]);
    }
    academic_query($db,'UPDATE tblstudents SET SeniorLanguageSubjectId=?,SeniorMathSubjectId=? WHERE StudentId=?',[$language,$math,$student]);
    cbe_audit($db,'senior_learner_assigned','tblstudentpathways',$id,['allocation'=>$old,'registrations'=>$before],['pathway'=>$path,'track'=>$track,'combination'=>$combination,'subjects'=>$subjects]);
    $label=academic_query($db,'SELECT Name FROM tblpathways WHERE id=?',[$path])->fetchColumn();
    senior_notify_pathway($db,$student,$id,$s['StudentName'].' has been assigned to '.$label.' / '.$t['Name'].' for '.$c['GradeName'].'.',array_unique([...$subjects,...$oldSubjects]));
    return $id;
}
function senior_assign_bulk($db,$p) {
    senior_lock($db);$students=senior_ids($p['Students']??[],'learners');if(!$students)throw new DomainException('Select at least one learner.');
    foreach($students as $student) {
        $values=$p;
        if(isset($p['ExpectedAllocations'][$student]))$values['ExpectedAllocationId']=$p['ExpectedAllocations'][$student];
        senior_assign_learner($db,$student,$values);
    }
    return count($students);
}
function senior_guard_registration($db,$student,$year,$subjects) {
    if(!senior_ready($db))return;
    $s=cbe_one($db,'SELECT * FROM tblstudents WHERE StudentId=?',[$student]);
    if(!$s || !senior_is_class($db,$s['ClassId']))return;
    $a=cbe_one($db,'SELECT * FROM tblstudentpathways WHERE StudentId=? AND AcademicYearId=? AND Status=1',[$student,$year]);
    $c=senior_class($db,$s['ClassId']);
    $expected=$a?array_map('intval',academic_query($db,'SELECT SubjectId FROM tblpathwayallocationsubjects WHERE AllocationId=? ORDER BY SubjectId',[$a['id']])->fetchAll(PDO::FETCH_COLUMN)):senior_core_subjects($db,$c['GradeId'],0,$s['SeniorLanguageSubjectId'],$s['SeniorMathSubjectId']);
    $submitted=senior_ids($subjects);
    if($submitted!==$expected)throw new DomainException('Senior School core and elective subjects are managed through Assign Learners to Pathways. Use that page to change this learner\'s subjects.');
}
function senior_assign_teacher($db,$p) {
    senior_lock($db);$class=senior_id($p['ClassId']??null,'class');$subject=senior_id($p['SubjectId']??null,'subject');
    if(!senior_is_class($db,$class))throw new DomainException('Select a Senior School class.');
    $c=senior_class($db,$class);$s=senior_subject($db,$subject,null,null,$c['GradeId']);
    $year=senior_id($p['AcademicYearId']??null,'academic year');$teacher=senior_id($p['TeacherId']??null,'teacher');$term=senior_id($p['TermId']??null,'term',true);
    $path=senior_id($p['PathwayId']??null,'pathway',true);
    senior_timetable_validate($db,$class,$subject,$year,$path);
    $old=cbe_one($db,'SELECT id FROM tblsubjectteacherassignments WHERE ClassId=? AND SubjectId=? AND TeacherId=? AND AcademicYearId=? AND COALESCE(TermId,0)=? AND Status=1',[$class,$subject,$teacher,$year,$term??0]);
    if($old)return (int)$old['id'];
    $id=academic_assign($db,'subject',$teacher,$class,$subject,$year,$term,$p['confirmed_assignments']??[]);
    cbe_audit($db,'senior_teacher_assigned','tblsubjectteacherassignments',$id,null,$p);
    return $id;
}
function senior_notify_teacher_assignment($db,$id) {
    $a=cbe_one($db,'SELECT * FROM tblsubjectteacherassignments WHERE id=?',[$id]);
    $c=senior_class($db,$a['ClassId']);$s=cbe_one($db,'SELECT SubjectName FROM tblsubjects WHERE id=?',[$a['SubjectId']]);
    notification_create($db,$a['TeacherId'],'Senior School teaching assignment','You have been assigned to teach '.$c['GradeName'].' '.$s['SubjectName'].' ('.$c['Section'].').',['category'=>'SUBJECT_ASSIGNMENT','type'=>'SENIOR_SUBJECT','class_id'=>$a['ClassId'],'subject_id'=>$a['SubjectId'],'related_entity_type'=>'tblsubjectteacherassignments','related_entity_id'=>$id,'action_url'=>'teacher-senior.php?year='.$a['AcademicYearId']]);
}
function senior_promote($db,$p) {
    senior_lock($db);$sourceYear=senior_id($p['SourceYearId']??null,'source academic year');$year=senior_id($p['AcademicYearId']??null,'target academic year');
    academic_period($db,$sourceYear);academic_period($db,$year);
    $years=cbe_rows($db,'SELECT id,AcademicYear,StartDate FROM tblacademicyears WHERE id IN (?,?)',[$sourceYear,$year]);$starts=[];
    foreach($years as $y)$starts[$y['id']]=$y['StartDate']?:((preg_match('/^\d{4}/',$y['AcademicYear'],$m))?$m[0].'-01-01':null);
    if($year===$sourceYear || !$starts[$sourceYear] || !$starts[$year] || $starts[$year]<=$starts[$sourceYear])throw new DomainException('Promotion requires a later academic year. Configure academic year start dates if needed.');
    $class=senior_id($p['ClassId']??null,'destination class');$target=senior_class($db,$class);
    if(!$target || !$target['GradeStatus'] || $target['GradeNumber']<10 || $target['GradeNumber']>12)throw new DomainException('Select a destination class in Grade 10, 11 or 12.');
    $students=senior_ids($p['Students']??[],'learners');if(!$students)throw new DomainException('Select learners to promote.');
    foreach($students as $student) {
        $s=cbe_one($db,'SELECT * FROM tblstudents WHERE StudentId=? AND Status=1 FOR UPDATE',[$student]);
        $old=$s?senior_class($db,$s['ClassId']):null;
        if(!$old || !in_array((int)$old['GradeNumber'],[10,11],true) || (int)$old['GradeNumber']+1!==(int)$target['GradeNumber'])throw new DomainException('Promote Senior School learners from Grade 10 to 11 or Grade 11 to 12.');
        if(academic_query($db,'SELECT id FROM tblstudentenrollments WHERE StudentId=? AND AcademicYearId=?',[$student,$year])->fetchColumn())throw new DomainException('A selected learner already has an enrollment in the destination year.');
        senior_enroll($db,$student,$sourceYear,$s['ClassId']);
        $a=cbe_one($db,'SELECT * FROM tblstudentpathways WHERE StudentId=? AND AcademicYearId=? AND Status=1',[$student,$sourceYear]);
        if($old['GradeNumber']>=10 && !$a)throw new DomainException('Assign a pathway in the source year before promoting a Senior School learner.');
        academic_query($db,'UPDATE tblstudents SET ClassId=?,UpdationDate=CURRENT_TIMESTAMP WHERE StudentId=?',[$class,$student]);
        academic_query($db,"UPDATE tblusers SET ClassId=? WHERE StudentId=? AND Role='student'",[$class,$student]);
        senior_enroll($db,$student,$year,$class);
        if($a) {
            $electives=academic_query($db,"SELECT SubjectId FROM tblpathwayallocationsubjects WHERE AllocationId=? AND SubjectType='elective'",[$a['id']])->fetchAll(PDO::FETCH_COLUMN);
            $combination=null;
            if($a['CombinationId']) {
                $co=cbe_one($db,'SELECT * FROM tblschoolcombinations WHERE id=?',[$a['CombinationId']]);
                $next=cbe_one($db,'SELECT * FROM tblschoolcombinations WHERE Name=? AND TrackId=? AND AcademicYearId=?',[$co['Name'],$a['TrackId'],$year]);
                if($next) {
                    $items=array_map('intval',academic_query($db,'SELECT SubjectId FROM tblschoolcombinationsubjects WHERE CombinationId=? ORDER BY SubjectId',[$next['id']])->fetchAll(PDO::FETCH_COLUMN));
                    if(!$next['Status'] || $items!==senior_ids($electives))throw new DomainException('The destination year combination has different subjects or is inactive. Review it before promotion.');
                    $combination=$next['id'];
                } else $combination=senior_save_combination($db,['Name'=>$co['Name'],'Code'=>$co['Code'],'TrackId'=>$a['TrackId'],'PathwayId'=>$a['PathwayId'],'AcademicYearId'=>$year,'Subjects'=>$electives,'Status'=>1]);
            }
            senior_assign_learner($db,$student,['AcademicYearId'=>$year,'PathwayId'=>$a['PathwayId'],'TrackId'=>$a['TrackId'],'CombinationId'=>$combination,'Subjects'=>$electives,'PlacementSource'=>'Grade promotion','ReferenceNotes'=>'Promoted from '.$old['GradeName'].'.']);
        } else senior_sync_core($db,$student,$year);
        cbe_audit($db,'senior_learner_promoted','tblstudents',$student,['class'=>$s['ClassId'],'year'=>$sourceYear,'grade'=>$old['GradeId']],['class'=>$class,'year'=>$year,'grade'=>$target['GradeId']]);
    }
    return count($students);
}

function senior_timetable_subjects($db,$class,$year,$pathway=null) {
    if(!$class)return [];
    $rows=cbe_rows($db,'SELECT DISTINCT s.id,s.SubjectName Label,s.SeniorType,s.CoreRole FROM tblsubjectcombination sc JOIN tblsubjects s ON s.id=sc.SubjectId WHERE sc.ClassId=? AND sc.status=1 AND s.Status=1 ORDER BY s.SubjectName',[$class]);
    if(!senior_is_class($db,$class)) {
        if($pathway)throw new DomainException('Pathways apply to Grades 10, 11 and 12.');
        return $rows;
    }
    $c=senior_class($db,$class);
    if($pathway && !academic_query($db,'SELECT id FROM tblpathways WHERE id=? AND Status=1',[$pathway])->fetchColumn())throw new DomainException('Select an active pathway.');
    return array_values(array_filter($rows,function($s) use ($db,$class,$year,$pathway,$c) {
        if(!$s['SeniorType'] || !academic_query($db,'SELECT 1 FROM tblsubjectgrades WHERE SubjectId=? AND GradeId=?',[$s['id'],$c['GradeId']])->fetchColumn())return false;
        if(!$pathway)return true;
        if($s['SeniorType']==='core') {
            if($s['CoreRole']==='common')return true;
            $defaults=senior_core_subjects($db,$c['GradeId'],$pathway);
            if(in_array((int)$s['id'],$defaults,true))return true;
            return (bool)academic_query($db,'SELECT 1 FROM tblstudentsubjects ss JOIN tblstudentpathways a ON a.StudentId=ss.StudentId AND a.AcademicYearId=ss.AcademicYearId AND a.ClassId=ss.ClassId AND a.Status=1 WHERE ss.SubjectId=? AND ss.ClassId=? AND ss.AcademicYearId=? AND ss.Status=1 AND a.PathwayId=? LIMIT 1',[$s['id'],$class,$year,$pathway])->fetchColumn();
        }
        return (bool)academic_query($db,'SELECT 1 FROM tblpathwaysubjects ps LEFT JOIN tblpathwaytracks t ON t.id=ps.TrackId WHERE ps.SubjectId=? AND ps.PathwayId=? AND (ps.TrackId IS NULL OR t.Status=1) LIMIT 1',[$s['id'],$pathway])->fetchColumn();
    }));
}
function senior_timetable_validate($db,$class,$subject,$year,$pathway=null) {
    if(!senior_ready($db))return;
    if(!in_array((int)$subject,array_map('intval',array_column(senior_timetable_subjects($db,$class,$year,$pathway),'id')),true))throw new DomainException('This subject is not active or applicable to the selected class, grade and pathway.');
}
function senior_teaching_options($db,$class,$subject,$year,$term=0) {
    return cbe_rows($db,'SELECT DISTINCT u.id,u.FullName Label FROM tblsubjectteacherassignments a JOIN tblusers u ON u.id=a.TeacherId AND u.Status=1 WHERE a.ClassId=? AND a.SubjectId=? AND a.AcademicYearId=? AND a.Status=1 AND (a.TermId IS NULL OR a.TermId=?) ORDER BY u.FullName',[$class,$subject,$year,$term]);
}
function senior_filters($query) {
    $f=[];foreach(['year','grade','class','pathway','track','combination','student','subject'] as $key)$f[$key]=senior_id($query[$key]??0,$key,true)??0;
    $f['gender']=cbe_text($query,'gender',10,false);$f['q']=cbe_text($query,'q',100,false);
    $f['assignment']=$query['assignment']??'';if(!in_array($f['assignment'],['','assigned','unassigned'],true))throw new DomainException('Choose a valid assignment status.');
    return $f;
}
function senior_learners($db,$f,$teacher=0,$promotions=false) {
    $year=$f['year']?:academic_year($db);academic_period($db,$year);
    $sql='SELECT s.StudentId,s.StudentName,s.RollId,s.Gender,s.Status StudentStatus,c.id ClassId,CONCAT(c.ClassName," ",c.Section) Class,g.id GradeId,g.GradeNumber,
        a.id AllocationId,a.PathwayId,a.TrackId,a.CombinationId,p.Name Pathway,t.Name Track,co.Name Combination,a.AssignedAt,
        s.SeniorLanguageSubjectId,s.SeniorMathSubjectId
        FROM tblstudents s LEFT JOIN tblstudentenrollments en ON en.StudentId=s.StudentId AND en.AcademicYearId=?
        JOIN tblclasses c ON c.id=COALESCE(en.ClassId,s.ClassId) JOIN tblgrades g ON g.id=COALESCE(en.GradeId,c.GradeId)
        LEFT JOIN tblstudentpathways a ON a.StudentId=s.StudentId AND a.AcademicYearId=? AND a.Status=1 AND a.ClassId=c.id
        LEFT JOIN tblpathways p ON p.id=a.PathwayId LEFT JOIN tblpathwaytracks t ON t.id=a.TrackId LEFT JOIN tblschoolcombinations co ON co.id=a.CombinationId
        WHERE g.GradeNumber BETWEEN ? AND ? AND (en.id IS NOT NULL OR ?=?)';
    $params=[$year,$year,10,$promotions?11:12,$year,academic_year($db)];
    foreach(['grade'=>'g.id','class'=>'c.id','pathway'=>'a.PathwayId','track'=>'a.TrackId','combination'=>'a.CombinationId','student'=>'s.StudentId'] as $key=>$column)if(!empty($f[$key])){$sql.=" AND $column=?";$params[]=$f[$key];}
    if(!empty($f['subject'])){$sql.=' AND EXISTS(SELECT 1 FROM tblstudentsubjects ss WHERE ss.StudentId=s.StudentId AND ss.ClassId=c.id AND ss.AcademicYearId=? AND ss.SubjectId=? AND ss.Status=1)';$params[]=$year;$params[]=$f['subject'];}
    if(!empty($f['gender'])){$sql.=' AND s.Gender=?';$params[]=$f['gender'];}
    if(!empty($f['q'])){$sql.=' AND (s.StudentName LIKE ? OR s.RollId LIKE ?)';$params[]='%'.$f['q'].'%';$params[]='%'.$f['q'].'%';}
    if(($f['assignment']??'')==='unassigned')$sql.=' AND a.id IS NULL';
    if(($f['assignment']??'')==='assigned')$sql.=' AND a.id IS NOT NULL';
    if($teacher) {
        $sql.=' AND (EXISTS(SELECT 1 FROM tblclassteacherassignments ca WHERE ca.TeacherId=? AND ca.AcademicYearId=? AND ca.ClassId=c.id AND ca.Status=1)
            OR EXISTS(SELECT 1 FROM tblsubjectteacherassignments ta JOIN tblstudentsubjects ss ON ss.SubjectId=ta.SubjectId AND ss.ClassId=ta.ClassId AND ss.AcademicYearId=ta.AcademicYearId AND ss.Status=1 WHERE ta.TeacherId=? AND ta.AcademicYearId=? AND ta.ClassId=c.id AND ta.Status=1 AND ss.StudentId=s.StudentId))';
        array_push($params,$teacher,$year,$teacher,$year);
    }
    return cbe_rows($db,$sql.' ORDER BY g.GradeNumber,c.Section,s.StudentName,s.StudentId',$params);
}
function senior_learner_subjects($db,$student,$year,$class,$teacher=0) {
    $sql='SELECT s.id,s.SubjectName Subject,s.SubjectCode Code,ss.AssignmentSource Type,IF(s.Status=1,"Active","Inactive") Status FROM tblstudentsubjects ss JOIN tblsubjects s ON s.id=ss.SubjectId WHERE ss.StudentId=? AND ss.AcademicYearId=? AND ss.ClassId=? AND ss.Status=1';
    $p=[$student,$year,$class];
    if($teacher) {
        $sql.=' AND (EXISTS(SELECT 1 FROM tblclassteacherassignments a WHERE a.TeacherId=? AND a.ClassId=? AND a.AcademicYearId=? AND a.Status=1) OR EXISTS(SELECT 1 FROM tblsubjectteacherassignments a WHERE a.TeacherId=? AND a.ClassId=? AND a.AcademicYearId=? AND a.SubjectId=s.id AND a.Status=1))';
        array_push($p,$teacher,$class,$year,$teacher,$class,$year);
    }
    return cbe_rows($db,$sql.' ORDER BY ss.AssignmentSource,s.SubjectName',$p);
}
function senior_report($db,$kind,$f,$teacher=0) {
    $learners=senior_learners($db,$f,$teacher);$year=$f['year']?:academic_year($db);
    if($kind==='enrollment') {
        $totals=[];
        foreach($learners as $learner)foreach(senior_learner_subjects($db,$learner['StudentId'],$year,$learner['ClassId'],$teacher) as $s) {
            $key=$learner['ClassId'].':'.$s['id'];
            if(!isset($totals[$key]))$totals[$key]=['Grade'=>$learner['GradeNumber'],'Class'=>$learner['Class'],'Subject'=>$s['Subject'],'Type'=>$s['Type'],'Learners'=>0];
            $totals[$key]['Learners']++;
        }
        return array_values($totals);
    }
    if($kind==='teachers') {
        $sql='SELECT a.id,g.GradeNumber Grade,CONCAT(c.ClassName," ",c.Section) Class,s.SubjectName Subject,u.FullName Teacher,
            COALESCE(t.TermName,"Whole year") Term,IF(a.Status=1,"Active","Inactive") Status FROM tblsubjectteacherassignments a
            JOIN tblclasses c ON c.id=a.ClassId JOIN tblgrades g ON g.id=c.GradeId JOIN tblsubjects s ON s.id=a.SubjectId
            JOIN tblusers u ON u.id=a.TeacherId LEFT JOIN tblterms t ON t.id=a.TermId
            WHERE a.AcademicYearId=? AND g.GradeNumber BETWEEN 10 AND 12 AND a.Status=1';$p=[$year];
        foreach(['grade'=>'g.id','class'=>'c.id','subject'=>'s.id'] as $key=>$column)if(!empty($f[$key])){$sql.=" AND $column=?";$p[]=$f[$key];}
        if($teacher){$sql.=' AND a.TeacherId=?';$p[]=$teacher;}
        if(!empty($f['pathway'])){$sql.=" AND (s.SeniorType='core' OR EXISTS(SELECT 1 FROM tblpathwaysubjects ps WHERE ps.SubjectId=s.id AND ps.PathwayId=? AND (?=0 OR ps.TrackId IS NULL OR ps.TrackId=?)))";array_push($p,$f['pathway'],$f['track']??0,$f['track']??0);}
        return cbe_rows($db,$sql.' ORDER BY g.GradeNumber,c.Section,s.SubjectName',$p);
    }
    return array_map(fn($s)=>['Admission'=>$s['RollId'],'Learner'=>$s['StudentName'],'Grade'=>$s['GradeNumber'],'Class'=>$s['Class'],'Gender'=>$s['Gender'],'Pathway'=>$s['Pathway']??'Unassigned','Track'=>$s['Track']??'','Combination'=>$s['Combination']??($s['AllocationId']?'Individual selection':''),'Status'=>$s['StudentStatus']?'Active':'Inactive'],$learners);
}
function senior_csv($rows,$name) {
    header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="'.$name.'.csv"');
    header('Cache-Control: no-store');$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
    if($rows)cbe_csv_row($out,array_keys($rows[0]));
    foreach($rows as $row)cbe_csv_row($out,array_map(fn($v)=>preg_match('/^[\s]*[=+@-]/',(string)$v)?"'".$v:($v??''),$row));
    fclose($out);exit;
}
