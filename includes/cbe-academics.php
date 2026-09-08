<?php
require_once __DIR__.'/academic-assignments.php';
require_once __DIR__.'/audit.php';
require_once __DIR__.'/notification-service.php';
function cbe_one($db,$sql,$p=[]) { return academic_query($db,$sql,$p)->fetch(PDO::FETCH_ASSOC); }
function cbe_rows($db,$sql,$p=[]) { return academic_query($db,$sql,$p)->fetchAll(PDO::FETCH_ASSOC); }
function cbe_text($p,$key,$max=255,$required=true) {
    $v=$p[$key]??'';if(!is_scalar($v))throw new DomainException('Invalid '.$key.'.');$v=trim((string)$v);
    if(($required && $v==='') || strlen($v)>$max)throw new DomainException($key.' is required and must be at most '.$max.' characters.');return $v;
}
function cbe_date($value) { $d=DateTime::createFromFormat('!Y-m-d',(string)$value);if(!$d || $d->format('Y-m-d')!==$value)throw new DomainException('Enter a valid date.');return $value; }
function cbe_number($value,$min=0,$max=100) {if(!is_numeric($value)||!is_finite((float)$value)||(float)$value<$min||(float)$value>$max)throw new DomainException('Enter a number between '.$min.' and '.$max.'.');return (float)$value;}
function cbe_ids($value) {if(!is_array($value))throw new DomainException('Select valid records.');$ids=array_map('intval',$value);if(count($ids)!==count(array_unique($ids))||in_array(0,$ids,true))throw new DomainException('Duplicate or invalid selection.');return $ids;}
function cbe_actor() {return $_SESSION['alogin']??$_SESSION['teacher_username']??('teacher:'.($_SESSION['teacher_user_id']??0));}
function cbe_audit($db,$action,$table,$id,$before,$after) {audit_log($db,$action,$table,$id,json_encode(['old'=>$before,'new'=>$after],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));}
function cbe_notify($db,$teacher,$title,$message,$url,$category='SYSTEM') {notification_create($db,$teacher,$title,$message,['category'=>$category,'action_url'=>$url,'type'=>'ACADEMIC_UPDATE']);}
function cbe_assignment($db,$teacher,$class,$subject,$year,$term) {
    academic_period($db,$year,$term);
    if(!academic_query($db,'SELECT a.id FROM tblsubjectteacherassignments a JOIN tblusers u ON u.id=a.TeacherId AND u.Status=1 JOIN tblsubjects s ON s.id=a.SubjectId AND s.Status=1 WHERE a.TeacherId=? AND a.ClassId=? AND a.SubjectId=? AND a.AcademicYearId=? AND a.Status=1 AND (a.TermId IS NULL OR a.TermId=?)',[$teacher,$class,$subject,$year,$term])->fetchColumn())throw new DomainException('An active teaching assignment for this class, subject and period is required.');
    academic_offering($db,$class,$subject);
}
function cbe_student($db,$student,$class,$subject,$year) {
    if(!academic_query($db,'SELECT s.StudentId FROM tblstudents s JOIN tblstudentsubjects ss ON ss.StudentId=s.StudentId AND ss.ClassId=s.ClassId AND ss.Status=1 WHERE s.StudentId=? AND s.Status=1 AND s.ClassId=? AND ss.SubjectId=? AND ss.AcademicYearId=?',[$student,$class,$subject,$year])->fetchColumn())throw new DomainException('The active learner must be registered for this subject in this class and year.');
}
function cbe_configs() {
    return [
      'levels'=>['tblschoollevels','School levels',['Name'=>'text','SeniorSchool'=>'bool','Status'=>'bool']],
      'grades'=>['tblgrades','Grades',['SchoolLevelId'=>'levels','Name'=>'text','GradeNumber'=>'number','PathwayEntry'=>'bool','GuidanceEligible'=>'bool','Status'=>'bool']],
      'departments'=>['tbldepartments','Departments',['DepartmentName'=>'text','HeadTeacherId'=>'teachers?','Status'=>'bool']],
      'pathways'=>['tblpathways','Pathways',['Name'=>'text','Description'=>'textarea','Status'=>'bool']],
      'tracks'=>['tblpathwaytracks','Tracks',['PathwayId'=>'pathways','Name'=>'text','Status'=>'bool']],
      'types'=>['tblassessmenttypes','Assessment types',['Name'=>'text','Status'=>'bool']],
      'levels-performance'=>['tblperformancelevels','Performance levels',['Name'=>'text','Description'=>'textarea','SortOrder'=>'number','Status'=>'bool']],
      'competencies'=>['tblcompetencies','Competencies',['SubjectId'=>'subjects','Title'=>'text','Description'=>'textarea','Status'=>'bool']],
      'outcomes'=>['tbllearningoutcomes','Learning outcomes',['CompetencyId'=>'competencies','Title'=>'text','Status'=>'bool']]
    ];
}
function cbe_options($db,$kind) {
    $queries=[
      'levels'=>'SELECT id,Name Label FROM tblschoollevels', 'grades'=>'SELECT id,Name Label FROM tblgrades WHERE Status=1 ORDER BY GradeNumber',
      'teachers'=>'SELECT id,FullName Label FROM tblusers WHERE Status=1 AND Role IN ('.ACADEMIC_TEACHER_ROLES.') ORDER BY FullName',
      'subjects'=>'SELECT id,SubjectName Label FROM tblsubjects WHERE Status=1 ORDER BY SubjectName',
      'classes'=>'SELECT id,CONCAT(ClassName," ",Section) Label FROM tblclasses ORDER BY ClassNameNumeric,Section',
      'years'=>'SELECT id,AcademicYear Label FROM tblacademicyears ORDER BY AcademicYear DESC',
      'terms'=>'SELECT t.id,CONCAT(y.AcademicYear," / ",t.TermName) Label FROM tblterms t JOIN tblacademicyears y ON y.id=t.AcademicYearId ORDER BY y.AcademicYear DESC,t.id',
      'departments'=>'SELECT id,DepartmentName Label FROM tbldepartments WHERE Status=1',
      'pathways'=>'SELECT id,Name Label FROM tblpathways WHERE Status=1',
      'tracks'=>'SELECT t.id,CONCAT(p.Name," / ",t.Name) Label FROM tblpathwaytracks t JOIN tblpathways p ON p.id=t.PathwayId WHERE t.Status=1 AND p.Status=1',
      'combinations'=>'SELECT c.id,CONCAT(y.AcademicYear," / ",p.Name," / ",t.Name," / ",c.Name) Label FROM tblschoolcombinations c JOIN tblpathwaytracks t ON t.id=c.TrackId JOIN tblpathways p ON p.id=t.PathwayId JOIN tblacademicyears y ON y.id=c.AcademicYearId WHERE c.Status=1 AND t.Status=1 AND p.Status=1',
      'students'=>'SELECT StudentId id,CONCAT(StudentName," (",RollId,")") Label FROM tblstudents WHERE Status=1 ORDER BY StudentName',
      'types'=>'SELECT id,Name Label FROM tblassessmenttypes WHERE Status=1',
      'levels-performance'=>'SELECT id,Name Label FROM tblperformancelevels WHERE Status=1 ORDER BY SortOrder',
      'competencies'=>'SELECT c.id,CONCAT(s.SubjectName," / ",c.Title) Label FROM tblcompetencies c JOIN tblsubjects s ON s.id=c.SubjectId WHERE c.Status=1',
      'outcomes'=>'SELECT o.id,CONCAT(c.Title," / ",o.Title) Label FROM tbllearningoutcomes o JOIN tblcompetencies c ON c.id=o.CompetencyId WHERE o.Status=1',
      'rooms'=>'SELECT id,RoomName Label FROM tblrooms WHERE Status=1',
      'exams'=>'SELECT e.id,CONCAT(y.AcademicYear," / ",e.ExamName) Label FROM tblexams e JOIN tblacademicyears y ON y.id=e.AcademicYearId'
    ];
    if(!isset($queries[$kind]))throw new LogicException('Unknown option list');return cbe_rows($db,$queries[$kind]);
}
function cbe_save_config($db,$entity,$p) {
    $spec=cbe_configs()[$entity]??null;if(!$spec)throw new DomainException('Invalid configuration.');[$table,,$fields]=$spec;$values=[];
    foreach($fields as $key=>$type) {
        if($type==='bool') {$v=(string)($p[$key]??'');if(!in_array($v,['0','1'],true))throw new DomainException('Select active/inactive.');$values[$key]=(int)$v;}
        elseif($type==='number')$values[$key]=(int)cbe_number($p[$key]??'',0,999);
        elseif($type==='text'||$type==='textarea')$values[$key]=cbe_text($p,$key,$type==='text'?100:5000,$type==='text');
        else { $kind=rtrim($type,'?');$v=(int)($p[$key]??0);if(!$v && str_ends_with($type,'?')){$values[$key]=null;continue;}if(!in_array($v,array_column(cbe_options($db,$kind),'id')))throw new DomainException('Select a valid '.$key.'.');$values[$key]=$v; }
    }
    $id=(int)($p['id']??0);$old=$id?cbe_one($db,"SELECT * FROM $table WHERE id=? FOR UPDATE",[$id]):null;
    if($id&&!$old)throw new DomainException('Record not found.');
    if($id)academic_query($db,"UPDATE $table SET ".implode(',',array_map(fn($k)=>$k.'=?',array_keys($values))).' WHERE id=?',[...array_values($values),$id]);
    else {academic_query($db,"INSERT INTO $table (".implode(',',array_keys($values)).') VALUES('.implode(',',array_fill(0,count($values),'?')).')',array_values($values));$id=(int)$db->lastInsertId();}
    cbe_audit($db,'academic_config_saved',$table,$id,$old,$values);return $id;
}
// The caller owns the transaction, including the audit record.
function cbe_save_class($db,$p) {
    if(!$db->inTransaction())throw new LogicException('Class changes require a transaction.');
    $gradeId=filter_var($p['GradeId']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!$gradeId)throw new DomainException('Select an active configured grade.');
    // Serialize saves for a grade so duplicate stream checks also cover concurrent requests.
    $grade=cbe_one($db,'SELECT * FROM tblgrades WHERE id=? AND Status=1 FOR UPDATE',[$gradeId]);
    if(!$grade)throw new DomainException('Select an active configured grade.');
    $id=filter_var($p['id']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
    if($id===false)throw new DomainException('Class not found.');
    $old=$id?cbe_one($db,'SELECT * FROM tblclasses WHERE id=? FOR UPDATE',[$id]):null;
    if($id&&!$old)throw new DomainException('Class not found.');
    $section=cbe_text($p,'Section',5);
    $name=array_key_exists('ClassName',$p)?cbe_text($p,'ClassName',80):($old['ClassName']??$grade['Name']);
    // Include unmapped legacy classes to avoid creating a second stream for the same grade.
    if(academic_query($db,'SELECT id FROM tblclasses WHERE (GradeId=? OR (GradeId IS NULL AND ClassNameNumeric=?)) AND Section=? AND id<>? FOR UPDATE',[$gradeId,$grade['GradeNumber'],$section,$id])->fetchColumn())throw new DomainException('This grade and stream already exist.');
    $values=[$gradeId,$name,$grade['GradeNumber'],$section];
    if($id)academic_query($db,'UPDATE tblclasses SET GradeId=?,ClassName=?,ClassNameNumeric=?,Section=? WHERE id=?',[...$values,$id]);
    else {
        academic_query($db,'INSERT INTO tblclasses(GradeId,ClassName,ClassNameNumeric,Section) VALUES(?,?,?,?)',$values);
        $id=(int)$db->lastInsertId();
    }
    cbe_audit($db,'class_saved','tblclasses',$id,$old,['GradeId'=>$gradeId,'ClassName'=>$name,'ClassNameNumeric'=>$grade['GradeNumber'],'Section'=>$section]);
    return $id;
}
function cbe_save_combination($db,$p) {
    $year=(int)($p['AcademicYearId']??0);academic_period($db,$year);$track=(int)($p['TrackId']??0);
    if(!in_array($track,array_column(cbe_options($db,'tracks'),'id')))throw new DomainException('Select an active track and pathway.');
    $subjects=cbe_ids($p['Subjects']??[]);if(!$subjects)throw new DomainException('Select subjects offered by this school.');
    foreach($subjects as $s)if(!academic_query($db,'SELECT s.id FROM tblsubjects s WHERE s.id=? AND s.Status=1 AND (EXISTS(SELECT 1 FROM tblsubjectgrades g WHERE g.SubjectId=s.id) OR EXISTS(SELECT 1 FROM tblsubjectcombination sc WHERE sc.SubjectId=s.id AND sc.status=1))',[$s])->fetchColumn())throw new DomainException('Each combination subject must be active and offered by the school.');
    $id=(int)($p['id']??0);$old=$id?cbe_one($db,'SELECT * FROM tblschoolcombinations WHERE id=? FOR UPDATE',[$id]):null;
    if($id&&!$old)throw new DomainException('Combination not found.');
    if($id&&academic_query($db,'SELECT id FROM tblstudentpathways WHERE CombinationId=? LIMIT 1',[$id])->fetchColumn())throw new DomainException('This combination has allocation history. Create a new combination to change its subjects or track.');
    $v=[$track,$year,cbe_text($p,'Code',50,false),cbe_text($p,'Name',150),(int)cbe_number($p['Status']??1,0,1)];
    if($id)academic_query($db,'UPDATE tblschoolcombinations SET TrackId=?,AcademicYearId=?,Code=?,Name=?,Status=? WHERE id=?',[...$v,$id]);
    else {academic_query($db,'INSERT INTO tblschoolcombinations(TrackId,AcademicYearId,Code,Name,Status) VALUES(?,?,?,?,?)',$v);$id=(int)$db->lastInsertId();}
    academic_query($db,'DELETE FROM tblschoolcombinationsubjects WHERE CombinationId=?',[$id]);foreach($subjects as $s)academic_query($db,'INSERT INTO tblschoolcombinationsubjects VALUES(?,?)',[$id,$s]);
    cbe_audit($db,'school_combination_saved','tblschoolcombinations',$id,$old,['values'=>$v,'subjects'=>$subjects]);return $id;
}
function cbe_allocate_pathway($db,$p) {
    $student=(int)($p['StudentId']??0);$year=(int)($p['AcademicYearId']??0);$combination=(int)($p['CombinationId']??0);academic_period($db,$year);
    $s=cbe_one($db,'SELECT s.*,l.SeniorSchool FROM tblstudents s JOIN tblclasses c ON c.id=s.ClassId JOIN tblgrades g ON g.id=c.GradeId JOIN tblschoollevels l ON l.id=g.SchoolLevelId WHERE s.StudentId=? AND s.Status=1 FOR UPDATE',[$student]);
    if(!$s||!$s['SeniorSchool'])throw new DomainException('Pathway allocation requires an active Senior School learner.');
    if(!academic_query($db,'SELECT c.id FROM tblschoolcombinations c JOIN tblpathwaytracks t ON t.id=c.TrackId JOIN tblpathways p ON p.id=t.PathwayId WHERE c.id=? AND c.AcademicYearId=? AND c.Status=1 AND t.Status=1 AND p.Status=1',[$combination,$year])->fetchColumn())throw new DomainException('Select an active school combination for this academic year.');
    $subjects=academic_query($db,'SELECT SubjectId FROM tblschoolcombinationsubjects WHERE CombinationId=?',[$combination])->fetchAll(PDO::FETCH_COLUMN);if(!$subjects)throw new DomainException('The combination has no subjects.');
    foreach($subjects as $subject)academic_offering($db,$s['ClassId'],$subject);
    $old=cbe_one($db,'SELECT * FROM tblstudentpathways WHERE StudentId=? AND AcademicYearId=? AND Status=1 FOR UPDATE',[$student,$year]);
    if($old && empty($p['ConfirmReplacement']))throw new DomainException('Confirm replacement of the current pathway allocation; its history will be retained.');
    academic_query($db,'UPDATE tblstudentpathways SET Status=0,EndedAt=CURRENT_TIMESTAMP WHERE StudentId=? AND AcademicYearId=? AND Status=1',[$student,$year]);
    academic_query($db,'INSERT INTO tblstudentpathways(StudentId,ClassId,CombinationId,AcademicYearId,PlacementSource,ReferenceNotes) VALUES(?,?,?,?,?,?)',[$student,$s['ClassId'],$combination,$year,cbe_text($p,'PlacementSource',150),cbe_text($p,'ReferenceNotes',5000,false)]);$id=$db->lastInsertId();
    // Preserve independently selected/common subjects; replace only the old combination's contribution.
    if($old)academic_query($db,'UPDATE tblstudentsubjects ss JOIN tblschoolcombinationsubjects cs ON cs.SubjectId=ss.SubjectId SET ss.Status=0 WHERE ss.StudentId=? AND ss.AcademicYearId=? AND cs.CombinationId=?',[$student,$year,$old['CombinationId']]);
    foreach($subjects as $subject)academic_query($db,'INSERT INTO tblstudentsubjects(StudentId,SubjectId,ClassId,AcademicYearId) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE Status=1',[$student,$subject,$s['ClassId'],$year]);
    cbe_audit($db,'student_pathway_allocated','tblstudentpathways',$id,$old,['student'=>$student,'combination'=>$combination,'year'=>$year]);
}
function cbe_guidance($db,$p) {
    $student=(int)($p['StudentId']??0);$year=(int)($p['AcademicYearId']??0);academic_period($db,$year);
    if(!academic_query($db,'SELECT s.StudentId FROM tblstudents s JOIN tblclasses c ON c.id=s.ClassId JOIN tblgrades g ON g.id=c.GradeId WHERE s.StudentId=? AND s.Status=1 AND g.GuidanceEligible=1',[$student])->fetchColumn())throw new DomainException('The learner grade must be configured for pathway guidance.');
    $proposed=(int)($p['ProposedPathwayId']??0)?:null;$track=(int)($p['ChosenTrackId']??0)?:null;$combo=(int)($p['PreferredCombinationId']??0)?:null;
    if($track&&!academic_query($db,'SELECT id FROM tblpathwaytracks WHERE id=? AND Status=1',[$track])->fetchColumn())throw new DomainException('Invalid chosen track.');
    if($combo&&!academic_query($db,'SELECT id FROM tblschoolcombinations WHERE id=? AND TrackId=? AND AcademicYearId=? AND Status=1',[$combo,$track,$year])->fetchColumn())throw new DomainException('The preferred combination must match the chosen track and year.');
    academic_query($db,'INSERT INTO tblpathwayguidance(StudentId,AcademicYearId,Interests,ProposedPathwayId,ChosenTrackId,PreferredCombinationId,ExternalAssessmentReference,Notes,RecordedBy) VALUES(?,?,?,?,?,?,?,?,?)',[$student,$year,cbe_text($p,'Interests',5000,false),$proposed,$track,$combo,cbe_text($p,'ExternalAssessmentReference',5000,false),cbe_text($p,'Notes',5000,false),cbe_actor()]);
    cbe_audit($db,'pathway_guidance_recorded','tblpathwayguidance',$db->lastInsertId(),null,$p);
}
