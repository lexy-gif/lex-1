<?php
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/config.php';
require_once __DIR__.'/csrf.php';
require_once __DIR__.'/dean-auth.php';
require_once __DIR__.'/cbe-ui.php';
require_once __DIR__.'/cbe-timetable.php';
require_dean();
$base=$examTimetable?'dean-exam-timetable.php':'dean-class-timetable.php';
$title=$examTimetable?'Examination Timetable':'Class Timetable';
$table=$examTimetable?'tblexamtimetableentries':'tblclasstimetableentries';
$action=$examTimetable?'exam_session':'lesson';
$publishAction=$examTimetable?'publish_exam':'publish_class';
$error='';$msg=isset($_GET['saved'])?'Timetable entry saved.':(isset($_GET['published'])?'Timetable published and notifications generated.':'');
try {[$year,$term]=cbe_period_context($dbh,$_GET);}
catch(DomainException $e) {http_response_code(400);exit(academic_h($e->getMessage()));}
if($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_require_valid($_POST['csrf_token']??'');
    // Accept forms opened before the scheduling integration as well as the current fields.
    $aliases=['classid'=>'ClassId','subjectid'=>'SubjectId','teacherid'=>'TeacherId','roomid'=>'RoomId','dayofweek'=>'DayOfWeek','starttime'=>'StartTime','endtime'=>'EndTime','status'=>'Status','reason'=>'ChangeReason','examid'=>'ExamId','examdate'=>'ExamDate'];
    foreach($aliases as $old=>$new)if(isset($_POST[$old])&&!isset($_POST[$new]))$_POST[$new]=$_POST[$old];
    if(!isset($_POST['Invigilators'])&&isset($_POST['invigilatorid']))$_POST['Invigilators']=$_POST['invigilatorid']?[$_POST['invigilatorid']]:[];
    $_POST['action']=$_POST['action']??(isset($_POST[$publishAction])?$publishAction:$action);
    $_POST['AcademicYearId']=$_POST['AcademicYearId']??$year;
    $_POST['TermId']=$_POST['TermId']??$term;
    try {
        $dbh->beginTransaction();
        if($_POST['action']===$action) {
            $savedId=cbe_schedule_save($dbh,$_POST,$examTimetable);
            $row=cbe_one($dbh,"SELECT * FROM $table WHERE id=?",[$savedId]);
            $period=$examTimetable?cbe_one($dbh,'SELECT AcademicYearId,TermId FROM tblexams WHERE id=?',[$row['ExamId']]):$row;
            $query=['year'=>$period['AcademicYearId'],'term'=>$period['TermId'],'id'=>$savedId,'saved'=>1];
        } elseif($_POST['action']===$publishAction) {
            $scope=(int)($_POST[$examTimetable?'ExamId':'ClassId']??0);
            cbe_schedule_publish($dbh,$scope,$year,$term,$examTimetable);
            $period=$examTimetable?cbe_one($dbh,'SELECT AcademicYearId,TermId FROM tblexams WHERE id=?',[$scope]):['AcademicYearId'=>$year,'TermId'=>$term];
            $query=['year'=>$period['AcademicYearId'],'term'=>$period['TermId'],'published'=>1];
        } else throw new DomainException('Unknown timetable action.');
        $dbh->commit();header('Location: '.$base.'?'.http_build_query($query));exit;
    } catch(Throwable $e) {
        if($dbh->inTransaction())$dbh->rollBack();
        $error=$e instanceof DomainException?$e->getMessage():'Could not save the timetable. Check the academic setup and try again.';
        error_log($e->getMessage());
    }
}
$edit=(int)($_GET['id']??0);
$values=$edit?cbe_one($dbh,"SELECT * FROM $table WHERE id=?",[$edit]):['AcademicYearId'=>$year,'TermId'=>$term,'Status'=>'draft'];
if($edit&&!$values){http_response_code(404);exit('Timetable entry not found.');}
if($examTimetable&&$edit)$values['Invigilators']=timetable_exam_invigilators($dbh,$edit);
$fields=$examTimetable?['ExamId'=>'exams']:['AcademicYearId'=>'years','TermId'=>'terms'];
if(senior_ready($dbh))$fields['GradeId']='grades?';
$fields['ClassId']='classes';
if(senior_ready($dbh))$fields['PathwayId']='pathways?';
$fields['SubjectId']='subjects';
if(!$examTimetable)$fields['TeacherId']='teachers';
$fields['RoomId']='rooms?';
if($examTimetable)$fields+=['Invigilators'=>'teachers[]','ExamDate'=>'date'];
else $fields['DayOfWeek']='enum:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday';
$fields+=['StartTime'=>'text','EndTime'=>'text','Status'=>'enum:draft|ready_for_review|approved|published|updated|cancelled|archived','ChangeReason'=>'textarea'];
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= academic_h($title) ?> | SRMS</title>
<link rel="stylesheet" href="css/bootstrap.min.css"><link rel="stylesheet" href="css/font-awesome.min.css">
<link rel="stylesheet" href="js/DataTables/datatables.min.css"><link rel="stylesheet" href="css/main.css"><link rel="stylesheet" href="css/custom.css">
</head><body class="top-navbar-fixed"><div class="main-wrapper">
<?php include __DIR__.'/topbar.php'; ?>
<div class="content-wrapper"><div class="content-container"><?php include __DIR__.'/leftbar.php'; ?>
<div class="main-page"><div class="container-fluid"><div class="row page-title-div"><div class="col-md-12"><h2 class="title"><?= academic_h($title) ?></h2></div></div>
<section class="section">
<?php if($error){ ?><div class="alert alert-danger" role="alert"><?= academic_h($error) ?></div><?php } ?>
<?php if($msg){ ?><div class="alert alert-success" role="alert"><?= academic_h($msg) ?></div><?php } ?>
<p><a href="dean-timetable-setup.php">Rooms and school periods</a> &middot; <a href="dean-academics.php?area=timetable&amp;year=<?= (int)$year ?>&amp;term=<?= (int)$term ?>">Academic timetable workspace</a></p>
<form method="get" class="panel panel-body"><div class="row"><div class="col-md-5">
<?php cbe_field($dbh,'year','years',$year); ?></div><div class="col-md-5">
<?php cbe_field($dbh,'term','terms',$term,[['id'=>0,'Label'=>'All terms'],...cbe_rows($dbh,'SELECT id,TermName Label FROM tblterms WHERE AcademicYearId=? ORDER BY id',[$year])]); ?>
</div><div class="col-md-2"><button class="btn btn-default">Show Period</button></div></div></form>
<?php cbe_form($dbh,$examTimetable?'Create / Edit Exam Session':'Create / Edit Lesson',$action,$fields,$values); ?>
<p class="text-muted">Enter times as HH:MM. Only assigned teachers can receive lessons. Exam conflicts include every invigilator across exams on the same date. Publishing checks the schedule again.</p>
<div class="panel panel-body">
<?php
if($examTimetable) {
    $rows=cbe_rows($dbh,'SELECT e.id,x.ExamName Exam,e.ExamDate Date,e.StartTime Start,e.EndTime End,CONCAT(c.ClassName," ",c.Section) Class,s.SubjectName Subject,r.RoomName Room,(SELECT GROUP_CONCAT(u.FullName ORDER BY u.FullName SEPARATOR ", ") FROM tblusers u WHERE u.id=e.InvigilatorId OR EXISTS(SELECT 1 FROM tblexaminvigilators i WHERE i.SessionId=e.id AND i.TeacherId=u.id)) Invigilators,e.Status FROM tblexamtimetableentries e JOIN tblexams x ON x.id=e.ExamId JOIN tblclasses c ON c.id=e.ClassId JOIN tblsubjects s ON s.id=e.SubjectId LEFT JOIN tblrooms r ON r.id=e.RoomId WHERE x.AcademicYearId=? AND (?=0 OR x.TermId=?) ORDER BY e.ExamDate,e.StartTime',[$year,$term,$term]);
} else {
    $rows=cbe_rows($dbh,'SELECT e.id,e.DayOfWeek Day,e.StartTime Start,e.EndTime End,CONCAT(c.ClassName," ",c.Section) Class,s.SubjectName Subject,u.FullName Teacher,r.RoomName Room,e.Status FROM tblclasstimetableentries e JOIN tblclasses c ON c.id=e.ClassId JOIN tblsubjects s ON s.id=e.SubjectId LEFT JOIN tblusers u ON u.id=e.TeacherId LEFT JOIN tblrooms r ON r.id=e.RoomId WHERE e.AcademicYearId=? AND (?=0 OR e.TermId=?) ORDER BY FIELD(e.DayOfWeek,"Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"),e.StartTime',[$year,$term,$term]);
}
cbe_table($rows,$base.'?year='.$year.'&term='.$term.'&id=');
?></div>
<form method="post" class="panel panel-body">
<?php csrf_field(); ?>
<input type="hidden" name="action" value="<?= $publishAction ?>">
<?php cbe_field($dbh,$examTimetable?'ExamId':'ClassId',$examTimetable?'exams':'classes',$_POST[$examTimetable?'ExamId':'ClassId']??null); ?>
<p>Publish all pending entries <?= $examTimetable?'for this examination':'for this class in the selected year and term' ?>. Cancelled and archived entries remain closed.</p>
<button class="btn btn-success">Publish Timetable</button>
</form>
</section></div></div></div></div></div>
<script src="js/jquery/jquery-3.7.1.min.js"></script><script src="js/bootstrap/bootstrap.min.js"></script><script src="js/DataTables/datatables.min.js"></script><script src="js/main.js"></script>
<script>$(function(){ $('.cbe-table').DataTable(); $('select[name="year"]').on('change',function(){this.form.elements.term.disabled=true;this.form.submit();}); });</script>
<?php if(senior_ready($dbh)){ ?><script src="js/senior-school.js"></script><?php } ?>
</body></html>
