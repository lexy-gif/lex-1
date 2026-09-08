<?php
session_start();
require_once __DIR__.'/config.php';require_once __DIR__.'/csrf.php';require_once __DIR__.'/dean-auth.php';require_once __DIR__.'/teacher-auth.php';
require_once __DIR__.'/cbe-learning.php';require_once __DIR__.'/cbe-reports.php';require_once __DIR__.'/cbe-ui.php';
require_once __DIR__.'/cbe-timetable.php';
require_once __DIR__.'/cbe-context.php';
if($cbeTeacherPortal)require_teacher();else require_dean();
$cbeTeacherId=$cbeTeacherPortal?teacher_id():0;
$base=$cbeTeacherPortal?'teacher-academics.php':'dean-academics.php';
$areas=['dashboard'=>'Academic Dashboard','structure'=>'Academic Structure','subjects'=>'Learning Areas / Subjects','pathways'=>'Pathways and School Combinations','allocation'=>'Learner Pathways and Guidance','workload'=>'Teacher Workload','assessments'=>'Assessments','coverage'=>'Curriculum Coverage','interventions'=>'Academic Interventions','analytics'=>'Performance Analytics','reports'=>'Academic Reports','class'=>'Class Academic View','permissions'=>'Department Permissions','exams'=>'Examination Entry Control','timetable'=>'Academic Timetables','department'=>'Department Academic View'];
$area=$_GET['area']??($cbeTeacherPortal?'assessments':'dashboard');
if(!isset($areas[$area])||($cbeTeacherPortal&&!in_array($area,['assessments','coverage','interventions','workload','timetable','department'],true))){http_response_code(403);exit('This academic area is not available to your account.');}
try { [$year,$term]=cbe_period_context($dbh,$_GET); }
catch(DomainException $e) {http_response_code(400);exit(academic_h($e->getMessage()));}
$class=(int)($_GET['class']??0);$subject=(int)($_GET['subject']??0);$student=(int)($_GET['student']??0);$teacher=$cbeTeacherId?:(int)($_GET['teacher']??0);$edit=(int)($_GET['id']??0);
$_GET['year']=$year;$_GET['term']=$term;
$entity=$_GET['entity']??'levels';$error='';$defaults=['AcademicYearId'=>$year,'TermId'=>$term,'ClassId'=>$class?:null,'SubjectId'=>$subject?:null,'TeacherId'=>$teacher?:null,'StudentId'=>$student?:null];
$selectedAssessment=null;
if($area==='assessments'&&$edit&&$entity==='assessment') {
    try {$selectedAssessment=cbe_assessment_access($dbh,$edit,$cbeTeacherId);}
    catch(DomainException $e) {http_response_code($cbeTeacherPortal?403:404);exit(academic_h($e->getMessage()));}
}
if($_SERVER['REQUEST_METHOD']==='POST') {
 csrf_require_valid($_POST['csrf_token']??'');
 try {
    $action=$_POST['action']??'';
    if($cbeTeacherPortal&&!in_array($action,['assessment','score','coverage'],true))throw new DomainException('Only the Dean can perform this change.');
    $dbh->beginTransaction();
    switch($action) {
      case 'config':cbe_save_config($dbh,$_POST['entity']??'',$_POST);break;
      case 'combination':cbe_save_combination($dbh,$_POST);break;
      case 'allocation':cbe_allocate_pathway($dbh,$_POST);break;
      case 'guidance':cbe_guidance($dbh,$_POST);break;
      case 'assessment':$savedAssessment=cbe_save_assessment($dbh,$_POST,$cbeTeacherId);break;
      case 'score':cbe_score($dbh,$_POST,$cbeTeacherId);break;
      case 'coverage':cbe_coverage($dbh,$_POST,$cbeTeacherId);break;
      case 'intervention':cbe_intervention($dbh,$_POST);break;
      case 'lesson':$savedSchedule=cbe_schedule_save($dbh,$_POST,false);break;
      case 'exam_session':$savedSchedule=cbe_schedule_save($dbh,$_POST,true);break;
      case 'class':cbe_save_class($dbh,$_POST);break;
      case 'subject':
        $id=(int)($_POST['id']??0);$old=$id?cbe_one($dbh,'SELECT * FROM tblsubjects WHERE id=? FOR UPDATE',[$id]):null;if($id&&!$old)throw new DomainException('Subject not found.');
        $dept=(int)($_POST['DepartmentId']??0)?:null;$status=(int)cbe_number($_POST['Status']??'',0,1);$grades=cbe_ids($_POST['Grades']??[]);
        foreach($grades as $g)if(!in_array($g,array_column(cbe_options($dbh,'grades'),'id')))throw new DomainException('Invalid grade.');
        if($dept&&!in_array($dept,array_column(cbe_options($dbh,'departments'),'id')))throw new DomainException('Invalid department.');
        $v=[cbe_text($_POST,'SubjectName',100),cbe_text($_POST,'SubjectCode',100,false),$dept,$status];
        if($id)academic_query($dbh,'UPDATE tblsubjects SET SubjectName=?,SubjectCode=?,DepartmentId=?,Status=? WHERE id=?',[...$v,$id]);else{academic_query($dbh,'INSERT INTO tblsubjects(SubjectName,SubjectCode,DepartmentId,Status) VALUES(?,?,?,?)',$v);$id=$dbh->lastInsertId();}
        academic_query($dbh,'DELETE FROM tblsubjectgrades WHERE SubjectId=?',[$id]);foreach($grades as $g)academic_query($dbh,'INSERT INTO tblsubjectgrades VALUES(?,?)',[$id,$g]);cbe_audit($dbh,'learning_area_saved','tblsubjects',$id,$old,$_POST);break;
      case 'settings':
        $low=cbe_number($_POST['WorkloadLow']??'',0,200);$high=cbe_number($_POST['WorkloadHigh']??'',0,200);if($low>$high)throw new DomainException('Low workload limit must not exceed high limit.');$threshold=($_POST['SupportThreshold']??'')===''?null:cbe_number($_POST['SupportThreshold']);
        $old=cbe_one($dbh,'SELECT * FROM tblacademicsettings WHERE id=1 FOR UPDATE');academic_query($dbh,'UPDATE tblacademicsettings SET WorkloadLow=?,WorkloadHigh=?,SupportThreshold=? WHERE id=1',[$low,$high,$threshold]);cbe_audit($dbh,'academic_limits_saved','tblacademicsettings',1,$old,$_POST);break;
      case 'permission':
        $t=(int)($_POST['TeacherId']??0);academic_teacher($dbh,$t);$d=(int)($_POST['DepartmentId']??0);$allow=(int)cbe_number($_POST['CanReadAcademic']??'',0,1);
        if(!in_array($d,array_column(cbe_options($dbh,'departments'),'id')))throw new DomainException('Select a valid department.');
        $old=cbe_one($dbh,'SELECT * FROM tbldepartmentpermissions WHERE TeacherId=? AND DepartmentId=?',[$t,$d]);academic_query($dbh,'INSERT INTO tbldepartmentpermissions VALUES(?,?,?) ON DUPLICATE KEY UPDATE CanReadAcademic=VALUES(CanReadAcademic)',[$t,$d,$allow]);cbe_audit($dbh,'department_permission_saved','tbldepartmentpermissions',$t,$old,$_POST);break;
      case 'exam_lock':
        $id=(int)($_POST['ExamId']??0);$old=cbe_one($dbh,'SELECT * FROM tblexams WHERE id=? FOR UPDATE',[$id]);if(!$old)throw new DomainException('Exam not found.');$locked=(int)cbe_number($_POST['EntryLocked']??'',0,1);
        academic_query($dbh,"UPDATE tblexams SET EntryLocked=?,Status=CASE WHEN ?=0 THEN 'marks_entry' ELSE Status END WHERE id=?",[$locked,$locked,$id]);cbe_audit($dbh,'exam_entry_lock_changed','tblexams',$id,$old,['EntryLocked'=>$locked]);break;
      case 'carry':
        $from=(int)($_POST['FromYear']??0);$to=(int)($_POST['ToYear']??0);academic_period($dbh,$from);academic_period($dbh,$to);if($from===$to||empty($_POST['Confirm']))throw new DomainException('Choose different years and confirm carry-forward.');
        // Carry only whole-year academic roles; term assignments need deliberate term mapping.
        foreach(cbe_rows($dbh,'SELECT * FROM tblsubjectteacherassignments WHERE AcademicYearId=? AND TermId IS NULL AND Status=1',[$from]) as $a)academic_assign($dbh,'subject',(int)$a['TeacherId'],(int)$a['ClassId'],(int)$a['SubjectId'],$to);
        foreach(cbe_rows($dbh,'SELECT * FROM tblclassteacherassignments WHERE AcademicYearId=? AND Status=1',[$from]) as $a)academic_assign($dbh,'class',(int)$a['TeacherId'],(int)$a['ClassId'],0,$to);
        cbe_audit($dbh,'academic_assignments_carried_forward','tblacademicyears',$to,['year'=>$from],['year'=>$to]);break;
      default:throw new DomainException('Unknown academic action.');
    }
    $dbh->commit();$query=$_GET;unset($query['id']);
    if(in_array($action,['assessment','score'],true)) {
        $query['area']='assessments';$query['entity']='assessment';
        $query['id']=$action==='assessment'?$savedAssessment:(int)$_POST['AssessmentId'];
        $saved=cbe_assessment_access($dbh,$query['id'],$cbeTeacherId);
        $query['year']=$saved['AcademicYearId'];$query['term']=$saved['TermId'];
    }
    if(in_array($action,['lesson','exam_session'],true)) {
        $query['area']='timetable';$query['kind']=$action==='lesson'?'lesson':'exam';$query['id']=$savedSchedule;
        $saved=cbe_one($dbh,'SELECT * FROM '.($action==='lesson'?'tblclasstimetableentries':'tblexamtimetableentries').' WHERE id=?',[$savedSchedule]);
        $period=$action==='lesson'?$saved:cbe_one($dbh,'SELECT AcademicYearId,TermId FROM tblexams WHERE id=?',[$saved['ExamId']]);
        $query['year']=$period['AcademicYearId'];$query['term']=$period['TermId'];
    }
    header('Location: '.cbe_view_url($base,$query).'&saved=1');exit;
 }catch(Throwable $e){if($dbh->inTransaction())$dbh->rollBack();$error=$e instanceof DomainException?$e->getMessage():'Could not save. Check duplicate records and related academic data.';error_log($e->getMessage());}
}
$reportKinds=['performance'=>'Student / Subject / Class Performance','legacy'=>'Examination Analysis','workload'=>'Teacher Workload','assignments'=>'Teacher Assignments','pathways'=>'Pathway Allocation','combinations'=>'School Combinations','assessments'=>'Assessment Completion','coverage'=>'Curriculum Coverage','interventions'=>'Learner Support','unallocated'=>'Students Without Subjects'];
$report=$_GET['report']??'performance';
if(!isset($reportKinds[$report])&&!isset($_GET['export']))$report='performance';
if(isset($_GET['export'])) {
 if($cbeTeacherPortal||!isset($reportKinds[$report])){http_response_code(403);exit('Export not permitted.');}
 $rows=cbe_report($dbh,$report,$year,$term,$teacher,$class,$subject,$student);header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="academic-'.$report.'.csv"');$out=fopen('php://output','w');
 if($rows){fputcsv($out,array_keys($rows[0]));foreach($rows as $r)fputcsv($out,array_map(fn($v)=>preg_match('/^[=+@\-\t\r]/',(string)$v)?"'".$v:$v,$r));}fclose($out);exit;
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= academic_h($areas[$area]) ?> | SRMS</title><link rel="stylesheet" href="css/bootstrap.min.css"><link rel="stylesheet" href="css/font-awesome.min.css"><link rel="stylesheet" href="css/main.css"><link rel="stylesheet" href="css/custom.css"><link rel="stylesheet" href="css/cbe-academics.css"><link rel="stylesheet" href="js/DataTables/datatables.min.css"></head><body class="top-navbar-fixed"><div class="main-wrapper"><?php include __DIR__.($cbeTeacherPortal?'/teacher-topbar.php':'/topbar.php'); ?><div class="content-wrapper"><div class="content-container"><?php include __DIR__.($cbeTeacherPortal?'/teacher-leftbar.php':'/leftbar.php'); ?><div class="main-page"><div class="container-fluid"><div class="cbe-heading"><div><h2><?= academic_h($areas[$area]) ?></h2><p>School academic administration</p></div><button class="btn btn-default cbe-no-print" onclick="window.print()">Print View</button></div>
<?php if($error){?><div class="alert alert-danger"><?= academic_h($error) ?></div><?php }if(isset($_GET['saved'])&&$_SERVER['REQUEST_METHOD']==='GET'){?><div class="alert alert-success">Academic changes saved.</div><?php } ?>
<nav class="cbe-nav" aria-label="Academic workspace"><?php foreach($areas as $key=>$label)if((!$cbeTeacherPortal&&$key!=='department')||($cbeTeacherPortal&&in_array($key,['assessments','coverage','interventions','workload','timetable','department'],true)))echo '<a class="btn btn-'.($key===$area?'primary':'default').'" '.($key===$area?'aria-current="page" ':'').'href="'.academic_h(cbe_view_url($base,['area'=>$key,'year'=>$year,'term'=>$term])).'">'.academic_h($label).'</a>'; ?></nav>
<form method="get" class="panel panel-body cbe-filters"><input type="hidden" name="area" value="<?= academic_h($area) ?>">
<?php
if($area==='reports')echo '<input type="hidden" name="report" value="'.academic_h($report).'">';
$filterTypes=['year'=>'years','term'=>'terms','class'=>'classes','subject'=>'subjects','teacher'=>'teachers','student'=>'students'];
foreach(cbe_filter_names($area,$cbeTeacherPortal,$report) as $filter) {
    $choices=$filter==='term'?cbe_rows($dbh,'SELECT id,TermName Label FROM tblterms WHERE AcademicYearId=? ORDER BY id',[$year]):cbe_ui_options($dbh,$filterTypes[$filter]);
    if($filter!=='year')array_unshift($choices,['id'=>0,'Label'=>$filter==='term'?'All terms':'All']);
    cbe_field($dbh,$filter,$filterTypes[$filter],$$filter,$choices);
}
?><button class="btn btn-primary">Apply Filters</button></form>
<?php
if($area==='structure') {
 echo '<p><a href="dean-academic-periods.php">Academic Years and Terms</a> · <a href="manage-classes.php">Existing Classes</a></p>';
 foreach(['levels','grades','departments'] as $key){$spec=cbe_configs()[$key];$val=$edit&&$entity===$key?cbe_one($dbh,'SELECT * FROM '.$spec[0].' WHERE id=?',[$edit]):[];cbe_form($dbh,'Create / Edit '.$spec[1],'config',$spec[2],$val?:[],['entity'=>$key]);cbe_table(cbe_rows($dbh,'SELECT * FROM '.$spec[0]),$base.'?area=structure&entity='.$key.'&id=');}
 $val=$edit&&$entity==='class'?cbe_one($dbh,'SELECT * FROM tblclasses WHERE id=?',[$edit]):[];cbe_form($dbh,'Create / Edit Grade and Stream','class',['GradeId'=>'grades','Section'=>'text'],$val?:[]);
 cbe_table(cbe_rows($dbh,'SELECT c.id,g.Name Grade,l.Name Level,c.ClassName LegacyName,c.Section Stream FROM tblclasses c LEFT JOIN tblgrades g ON g.id=c.GradeId LEFT JOIN tblschoollevels l ON l.id=g.SchoolLevelId ORDER BY c.ClassNameNumeric,c.Section'),$base.'?area=structure&entity=class&id=');
 cbe_form($dbh,'Carry Whole-Year Teacher Assignments Forward','carry',['FromYear'=>'years','ToYear'=>'years','Confirm'=>'bool']);
 echo '<p class="help-block">Carry-forward copies whole-year subject and class teacher assignments only. Conflicts or inactive teachers cancel the entire copy. Term assignments and learner allocations require deliberate selection in the new year.</p>';
} elseif($area==='subjects') {
 $val=$edit?cbe_one($dbh,'SELECT * FROM tblsubjects WHERE id=?',[$edit]):[];if($val)$val['Grades']=academic_query($dbh,'SELECT GradeId FROM tblsubjectgrades WHERE SubjectId=?',[$edit])->fetchAll(PDO::FETCH_COLUMN);
 cbe_form($dbh,'Create / Edit Learning Area','subject',['SubjectName'=>'text','SubjectCode'=>'text?','DepartmentId'=>'departments?','Status'=>'bool','Grades'=>'grades[]'],$val?:[]);
 echo '<p><a href="add-subjectcombination.php">Offer a subject in a class</a> · <a href="student-subjects.php">Individual learner subjects</a> · <a href="dean-teacher-relationships.php">Teaching assignments</a></p>';
 cbe_table(cbe_rows($dbh,'SELECT s.id,s.SubjectName,s.SubjectCode,d.DepartmentName Department,s.Status FROM tblsubjects s LEFT JOIN tbldepartments d ON d.id=s.DepartmentId ORDER BY s.SubjectName'),$base.'?area=subjects&id=');
} elseif($area==='pathways') {
 foreach(['pathways','tracks'] as $key){$spec=cbe_configs()[$key];$val=$edit&&$entity===$key?cbe_one($dbh,'SELECT * FROM '.$spec[0].' WHERE id=?',[$edit]):[];cbe_form($dbh,'Create / Edit '.$spec[1],'config',$spec[2],$val?:[],['entity'=>$key]);cbe_table(cbe_rows($dbh,'SELECT * FROM '.$spec[0]),$base.'?area=pathways&entity='.$key.'&id=');}
 $val=$edit&&$entity==='combination'?cbe_one($dbh,'SELECT * FROM tblschoolcombinations WHERE id=?',[$edit]):$defaults;if(!empty($val['id']))$val['Subjects']=academic_query($dbh,'SELECT SubjectId FROM tblschoolcombinationsubjects WHERE CombinationId=?',[$edit])->fetchAll(PDO::FETCH_COLUMN);
 cbe_form($dbh,'Create / Edit School Subject Combination','combination',['TrackId'=>'tracks','AcademicYearId'=>'years','Code'=>'text?','Name'=>'text','Subjects'=>'subjects[]','Status'=>'bool'],$val?:$defaults);
 cbe_table(cbe_rows($dbh,'SELECT c.id,c.Name,c.Code,t.Name Track,p.Name Pathway,c.Status FROM tblschoolcombinations c JOIN tblpathwaytracks t ON t.id=c.TrackId JOIN tblpathways p ON p.id=t.PathwayId WHERE c.AcademicYearId=?',[$year]),$base.'?area=pathways&entity=combination&year='.$year.'&id=');cbe_table(cbe_report($dbh,'combinations',$year));
} elseif($area==='allocation') {
 echo '<p>Record staff-reviewed school allocations or externally supplied placement information. SRMS does not perform official government placement. A combination must be offered in the learner’s current class.</p>';
 cbe_form($dbh,'Allocate / Change Learner Pathway','allocation',['StudentId'=>'students','AcademicYearId'=>'years','CombinationId'=>'combinations','PlacementSource'=>'text','ReferenceNotes'=>'textarea','ConfirmReplacement'=>'bool'],$defaults);
 cbe_form($dbh,'Record Career / Pathway Guidance','guidance',['StudentId'=>'students','AcademicYearId'=>'years','Interests'=>'textarea','ProposedPathwayId'=>'pathways?','ChosenTrackId'=>'tracks?','PreferredCombinationId'=>'combinations?','ExternalAssessmentReference'=>'textarea','Notes'=>'textarea'],$defaults);
 cbe_table(cbe_report($dbh,'pathways',$year,0,0,$class,0,$student));
 echo '<h4>Guidance history</h4>';cbe_table(cbe_rows($dbh,'SELECT s.StudentName Student,p.Name ProposedPathway,t.Name ChosenTrack,c.Name PreferredCombination,g.Interests,g.ExternalAssessmentReference,g.Notes,g.RecordedBy,g.CreationDate FROM tblpathwayguidance g JOIN tblstudents s ON s.StudentId=g.StudentId LEFT JOIN tblpathways p ON p.id=g.ProposedPathwayId LEFT JOIN tblpathwaytracks t ON t.id=g.ChosenTrackId LEFT JOIN tblschoolcombinations c ON c.id=g.PreferredCombinationId WHERE g.AcademicYearId=? AND (?=0 OR g.StudentId=?) AND (?=0 OR s.ClassId=?) ORDER BY g.id DESC',[$year,$student,$student,$class,$class]));
 if($student){echo '<h4>Learner subjects and assigned teachers</h4>';cbe_table(cbe_rows($dbh,'SELECT sub.SubjectName Subject,CONCAT(c.ClassName," ",c.Section) Class,u.FullName Teacher,COALESCE(t.TermName,"Whole year") Term FROM tblstudentsubjects ss JOIN tblsubjects sub ON sub.id=ss.SubjectId JOIN tblclasses c ON c.id=ss.ClassId LEFT JOIN tblsubjectteacherassignments a ON a.ClassId=ss.ClassId AND a.SubjectId=ss.SubjectId AND a.AcademicYearId=ss.AcademicYearId AND a.Status=1 LEFT JOIN tblusers u ON u.id=a.TeacherId LEFT JOIN tblterms t ON t.id=a.TermId WHERE ss.StudentId=? AND ss.AcademicYearId=? AND ss.Status=1',[$student,$year]));}
} elseif($area==='workload') {
 echo '<p>Lesson counts include current draft/published timetable entries and exclude cancelled/archived entries. Choose one term for weekly workload.</p>';
 if(!$cbeTeacherPortal)cbe_form($dbh,'School Workload and Learner Support Limits','settings',['WorkloadLow'=>'number','WorkloadHigh'=>'number','SupportThreshold'=>'number?'],cbe_one($dbh,'SELECT * FROM tblacademicsettings WHERE id=1'));
 $rows=cbe_report($dbh,'workload',$year,$term,$teacher);cbe_table($rows);cbe_chart($term?'Weekly lesson distribution':'Lesson entries across all terms',$rows,'Teacher','Lessons');
} elseif($area==='assessments') {
 if(!$cbeTeacherPortal)foreach(['types','levels-performance','competencies','outcomes'] as $key){$spec=cbe_configs()[$key];$val=$edit&&$entity===$key?cbe_one($dbh,'SELECT * FROM '.$spec[0].' WHERE id=?',[$edit]):[];cbe_form($dbh,'Configure '.$spec[1],'config',$spec[2],$val?:[],['entity'=>$key]);echo '<details><summary>Existing '.academic_h($spec[1]).'</summary>';cbe_table(cbe_rows($dbh,'SELECT * FROM '.$spec[0]),$base.'?area=assessments&entity='.$key.'&id=');echo '</details>';}
 $val=$selectedAssessment?:$defaults;if(!empty($val['id']))$val['Outcomes']=academic_query($dbh,'SELECT OutcomeId FROM tblassessmentoutcomes WHERE AssessmentId=?',[$edit])->fetchAll(PDO::FETCH_COLUMN);
 $fields=['Title'=>'text','AssessmentTypeId'=>'types','AcademicYearId'=>'years','TermId'=>'terms','ClassId'=>'classes','SubjectId'=>'subjects'];if(!$cbeTeacherPortal)$fields['TeacherId']='teachers';$fields+=['MaximumScore'=>'number?','AssessmentDate'=>'date','Outcomes'=>'outcomes[]','Status'=>'enum:'.($cbeTeacherPortal?'draft|open|submitted':'draft|open|submitted|locked|published|archived')];
 cbe_form($dbh,'Create / Edit Assessment','assessment',$fields,$val?:$defaults);
 cbe_table(cbe_report($dbh,'assessments',$year,$term,$teacher,$class,$subject),$base.'?area=assessments&entity=assessment&year='.$year.'&id=');
 if($edit&&$entity==='assessment') {
   echo '<h3>Record learner assessment evidence</h3><p>Blank maximum score supports qualitative evidence and performance levels. Entry is permitted only while the assessment is open.</p>';
   $a=$selectedAssessment;$learners=academic_students($dbh,$a['ClassId'],$a['SubjectId'],$a['AcademicYearId']);
   if($a['Status']==='open'&&$learners) {
     $evidenceValues=($_POST['action']??'')==='score'?$_POST:[];
     $outcomeChoices=cbe_rows($dbh,'SELECT o.id,o.Title Label FROM tblassessmentoutcomes ao JOIN tbllearningoutcomes o ON o.id=ao.OutcomeId WHERE ao.AssessmentId=? ORDER BY o.Title',[$edit]);
     echo '<form method="post" class="panel panel-body">';csrf_field();echo '<input type="hidden" name="action" value="score"><input type="hidden" name="AssessmentId" value="'.$edit.'"><label>Learner registered for this subject</label><select class="form-control academic-search" name="StudentId" required>';foreach($learners as $s)echo '<option value="'.(int)$s['StudentId'].'" '.((int)($evidenceValues['StudentId']??0)===(int)$s['StudentId']?'selected':'').'>'.academic_h($s['StudentName'].' ('.$s['RollId'].')').'</option>';echo '</select>';
     if($a['MaximumScore']!==null)cbe_field($dbh,'Score','number?',$evidenceValues['Score']??null);
     cbe_field($dbh,'PerformanceLevelId','levels-performance?',$evidenceValues['PerformanceLevelId']??null);cbe_field($dbh,'OutcomeId','outcomes?',$evidenceValues['OutcomeId']??null,$outcomeChoices);cbe_field($dbh,'Evidence','textarea',$evidenceValues['Evidence']??null);echo '<button class="btn btn-primary">Save Evidence</button></form>';
   } else echo '<p class="alert alert-info">'.($a['Status']!=='open'?'Evidence entry is closed for this assessment.':'Register learners for this class and subject to enter evidence.').'</p>';
   cbe_table(cbe_rows($dbh,'SELECT s.StudentName Student,r.Score,l.Name PerformanceLevel,r.Evidence,r.RecordedBy,r.UpdationDate FROM tblassessmentresults r JOIN tblstudents s ON s.StudentId=r.StudentId LEFT JOIN tblperformancelevels l ON l.id=r.PerformanceLevelId WHERE r.AssessmentId=?',[$edit]));
   cbe_table(cbe_rows($dbh,'SELECT s.StudentName Student,o.Title Outcome,l.Name PerformanceLevel,r.Evidence FROM tbloutcomeobservations r JOIN tblstudents s ON s.StudentId=r.StudentId JOIN tbllearningoutcomes o ON o.id=r.OutcomeId JOIN tblperformancelevels l ON l.id=r.PerformanceLevelId WHERE r.AssessmentId=?',[$edit]));
 }
} elseif($area==='coverage') {
 $fields=['AcademicYearId'=>'years','TermId'=>'terms','ClassId'=>'classes','SubjectId'=>'subjects'];if(!$cbeTeacherPortal)$fields['TeacherId']='teachers';$fields+=['ContentReference'=>'text','ExpectedProgress'=>'number','ActualProgress'=>'number','ReportDate'=>'date','Notes'=>'textarea'];
 cbe_form($dbh,'Record Curriculum Progress','coverage',$fields,$defaults);echo '<p>Record school/teacher-defined content references and progress. Each update retains the previous observation. Search ON TRACK, BEHIND or COMPLETED in the table.</p>';cbe_table(cbe_report($dbh,'coverage',$year,$term,$teacher,$class,$subject));
 echo '<details><summary>Progress history</summary>';cbe_table(cbe_rows($dbh,'SELECT a.ContentReference,a.ExpectedProgress,a.ActualProgress,a.ReportDate,a.Notes,u.FullName Teacher,s.SubjectName Subject FROM tblcurriculumcoverage a JOIN tblusers u ON u.id=a.TeacherId JOIN tblsubjects s ON s.id=a.SubjectId WHERE a.AcademicYearId=? AND (?=0 OR a.TeacherId=?) AND (?=0 OR a.ClassId=?) AND (?=0 OR a.SubjectId=?) AND (?=0 OR a.TermId=?) ORDER BY a.id DESC',[$year,$teacher,$teacher,$class,$class,$subject,$subject,$term,$term]));echo '</details>';
} elseif($area==='interventions') {
 if(!$cbeTeacherPortal){$val=$edit?cbe_one($dbh,'SELECT * FROM tblacademicinterventions WHERE id=?',[$edit]):$defaults;cbe_form($dbh,'Create / Review Academic Intervention','intervention',['StudentId'=>'students','SubjectId'=>'subjects','TeacherId'=>'teachers','AcademicYearId'=>'years','TermId'=>'terms','Reason'=>'textarea','ActionPlan'=>'textarea','ReviewDate'=>'date','Status'=>'enum:active|review|completed|cancelled','ReviewNotes'=>'textarea'],$val?:$defaults);}
 cbe_table(cbe_report($dbh,'interventions',$year,$term,$teacher,$class,$subject,$student),$cbeTeacherPortal?null:$base.'?area=interventions&year='.$year.'&id=');
 if(!$cbeTeacherPortal){echo '<details><summary>Intervention change history</summary>';cbe_table(cbe_rows($dbh,"SELECT Actor,Details,CreationDate FROM tblauditlog WHERE EntityType='tblacademicinterventions' ORDER BY id DESC LIMIT 200"));echo '</details>';}
} elseif($area==='permissions') {
 cbe_form($dbh,'Grant / Revoke Department Academic Read Access','permission',['TeacherId'=>'teachers','DepartmentId'=>'departments','CanReadAcademic'=>'bool']);cbe_table(cbe_rows($dbh,'SELECT u.FullName Teacher,d.DepartmentName Department,p.CanReadAcademic FROM tbldepartmentpermissions p JOIN tblusers u ON u.id=p.TeacherId JOIN tbldepartments d ON d.id=p.DepartmentId'));
} elseif($area==='department') {
 cbe_table(cbe_rows($dbh,'SELECT d.DepartmentName Department,s.SubjectName Subject,a.Title Assessment,COUNT(r.id) Observations,ROUND(AVG(100*r.Score/NULLIF(a.MaximumScore,0)),2) MeanPercentage FROM tbldepartmentpermissions p JOIN tbldepartments d ON d.id=p.DepartmentId JOIN tblsubjects s ON s.DepartmentId=d.id LEFT JOIN tblassessments a ON a.SubjectId=s.id AND a.AcademicYearId=? AND (?=0 OR a.TermId=?) AND (?=0 OR a.ClassId=?) LEFT JOIN tblassessmentresults r ON r.AssessmentId=a.id WHERE p.TeacherId=? AND p.CanReadAcademic=1 AND (?=0 OR s.id=?) GROUP BY d.id,d.DepartmentName,s.id,s.SubjectName,a.id,a.Title',[$year,$term,$term,$class,$class,$cbeTeacherId,$subject,$subject]));
} elseif($area==='exams') {
 echo '<p><a class="btn btn-default" href="manage-exams.php">Create Examination</a> <a class="btn btn-default" href="dean-exam-timetable.php">Exam Timetable / Invigilation</a> <a class="btn btn-default" href="dean-result-approvals.php">Submission and Approval</a></p>';
 cbe_form($dbh,'Lock / Unlock Examination Result Entry','exam_lock',['ExamId'=>'exams','EntryLocked'=>'bool']);echo '<p>Active / Yes locks entry. Inactive / No unlocks and opens marks entry. Opening dates and deadlines still apply.</p>';
 cbe_table(cbe_rows($dbh,'SELECT e.id,e.ExamName,e.StartDate,e.EndDate,e.MarksOpenDate,e.MarksDeadline,e.EntryLocked,e.Status,COUNT(r.id) RecordedMarks FROM tblexams e LEFT JOIN tblresult r ON r.ExamId=e.id WHERE e.AcademicYearId=? GROUP BY e.id,e.ExamName,e.StartDate,e.EndDate,e.MarksOpenDate,e.MarksDeadline,e.EntryLocked,e.Status',[$year]));
} elseif($area==='class') {
 if(!$class)echo '<p>Select a class above to open its academic profile.</p>';else {
   echo '<h3>Class Teacher</h3>';cbe_table(cbe_rows($dbh,'SELECT u.FullName Teacher,u.Email FROM tblclassteacherassignments a JOIN tblusers u ON u.id=a.TeacherId WHERE a.ClassId=? AND a.AcademicYearId=? AND a.Status=1',[$class,$year]));
   echo '<h3>Active Students</h3>';cbe_table(academic_students($dbh,$class,0,$year));
   echo '<h3>Subject Teachers</h3>';cbe_table(cbe_report($dbh,'assignments',$year,$term,0,$class));
   echo '<h3>Assessments</h3>';cbe_table(cbe_report($dbh,'assessments',$year,$term,0,$class));
   echo '<h3>Performance</h3>';cbe_table(cbe_report($dbh,'performance',$year,$term,0,$class));
   echo '<h3>Curriculum Coverage</h3>';cbe_table(cbe_report($dbh,'coverage',$year,$term,0,$class));
   echo '<h3>Attendance</h3>';cbe_table(cbe_rows($dbh,'SELECT a.AttendanceDate,a.Status,COUNT(*) Learners FROM tblattendance a JOIN tblacademicyears y ON y.id=? WHERE a.ClassId=? AND (y.StartDate IS NULL OR a.AttendanceDate>=y.StartDate) AND (y.EndDate IS NULL OR a.AttendanceDate<=y.EndDate) GROUP BY a.AttendanceDate,a.Status ORDER BY a.AttendanceDate DESC',[$year,$class]));
   echo '<p><a href="dean-teacher-relationships.php?class='.$class.'&year='.$year.'">Modify assignments</a> · <a href="'.$base.'?area=timetable&class='.$class.'&year='.$year.'&term='.$term.'">View timetable</a></p>';
 }
} elseif($area==='analytics'||$area==='reports') {
 if($area==='reports'){echo '<form class="panel panel-body" method="get"><input type="hidden" name="area" value="reports">';foreach(['year','term','class','subject','teacher','student'] as $filter)echo '<input type="hidden" name="'.$filter.'" value="'.(int)$$filter.'">';echo '<label>Report</label><select name="report" class="form-control">';foreach($reportKinds as $k=>$label)echo '<option value="'.$k.'" '.($report===$k?'selected':'').'>'.academic_h($label).'</option>';echo '</select><button class="btn btn-primary">Open Report</button></form>';if(!isset($reportKinds[$report]))$report='performance';echo '<p><a class="btn btn-default" href="'.academic_h($base.'?'.http_build_query(array_merge($_GET,['report'=>$report,'export'=>1]))).'">Download CSV</a></p>';cbe_table(cbe_report($dbh,$report,$year,$term,$teacher,$class,$subject,$student));}
 else {$rows=cbe_report($dbh,'performance',$year,$term,$teacher,$class,$subject,$student);cbe_chart('Assessment performance by grade (%)',$rows,'Grade','Percentage');cbe_chart('Subject performance (%)',$rows,'Subject','Percentage');cbe_chart('Term comparisons (%)',cbe_report($dbh,'performance',$year,0,$teacher,$class,$subject,$student),'Term','Percentage');cbe_chart('Assessment trends (%)',$rows,'Date','Percentage');cbe_table($rows);$limits=cbe_one($dbh,'SELECT SupportThreshold FROM tblacademicsettings WHERE id=1');echo '<h4>Learners requiring review</h4>';if($limits['SupportThreshold']===null)echo '<p>Configure the school support threshold under Teacher Workload.</p>';else cbe_table(array_values(array_filter($rows,fn($r)=>$r['Percentage']!==null&&$r['Percentage']<$limits['SupportThreshold'])));}
} elseif($area==='timetable') {include __DIR__.'/cbe-timetable-view.php';}
else {include __DIR__.'/cbe-dashboard.php';}
?>
</div></div></div></div></div><script src="js/jquery/jquery-2.2.4.min.js"></script><script src="js/bootstrap/bootstrap.min.js"></script><script src="js/DataTables/datatables.min.js"></script><script src="js/main.js"></script><script src="js/academic-assignments.js"></script><script>$(function(){ $('.cbe-filters [name=year]').on('change',function(){ var term=$(this.form).find('[name=term]');term.val('0');this.form.submit(); }); $('.cbe-table').each(function(){ $(this).DataTable({pageLength:15,order:[]}); }); });</script></body></html>
