<?php
require_once 'includes/config.php';require_once 'includes/dean-auth.php';require_dean();
require_once 'includes/csrf.php';require_once 'includes/portal-layout.php';require_once 'includes/cbe-ui.php';require_once 'includes/academic-planning.php';
$error='';[$year,$term]=cbe_period_context($dbh,$_GET);$id=(int)($_GET['id']??0);$kind=$_GET['kind']??'plan';
if($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_require_valid($_POST['csrf_token']??'');
    try{$dbh->beginTransaction();if(($_POST['action']??'')==='remind')planning_remind($dbh,'plan',(int)($_POST['id']??0));else planning_save($dbh,$_POST);$dbh->commit();header('Location: dean-planning.php?'.http_build_query(['year'=>$year,'term'=>$term,'saved'=>1]));exit;}
    catch(Throwable $e){if($dbh->inTransaction())$dbh->rollBack();$error=$e instanceof DomainException?$e->getMessage():'Could not save academic planning.';error_log($e->getMessage());}
}
portal_start('Calendar, targets and follow-ups','dean');portal_alert($error);if(isset($_GET['saved']))portal_alert('Academic planning saved.','success');
echo '<p><a href="dean-academic-periods.php">Manage academic years and terms</a> · <a href="dean-academics.php?area=coverage">Review curriculum coverage</a></p>';
echo '<form method="get" class="panel panel-body">';cbe_field($dbh,'year','years',$year);cbe_field($dbh,'term','terms?',$term);echo '<button class="btn btn-default">Apply period</button></form>';
$defaults=['AcademicYearId'=>$year,'TermId'=>$term,'Status'=>1];
$event=$kind==='calendar'&&$id?cbe_one($dbh,'SELECT * FROM tblacademiccalendar WHERE id=?',[$id]):$defaults;
if($event&&!in_array($event['EventType']??'teaching',['teaching','assessment','academic_meeting','reporting','submission_deadline'],true))$event=$defaults;
cbe_form($dbh,'Academic calendar event','calendar',['AcademicYearId'=>'years','TermId'=>'terms','EventTitle'=>'text','EventType'=>'enum:teaching|assessment|academic_meeting|reporting|submission_deadline','StartDate'=>'date','EndDate'=>'date','Description'=>'textarea','Status'=>'bool'],$event?:$defaults);
cbe_table(cbe_rows($dbh,"SELECT id,EventTitle,EventType,StartDate,EndDate,Description,Status FROM tblacademiccalendar WHERE AcademicYearId=? AND (?=0 OR TermId=?) AND EventType IN ('teaching','assessment','academic_meeting','reporting','submission_deadline') ORDER BY StartDate",[$year,$term,$term]),'dean-planning.php?kind=calendar&id=');
$plan=$kind==='plan'&&$id?cbe_one($dbh,'SELECT * FROM tblacademicplans WHERE id=?',[$id]):array_merge($defaults,['Status'=>'open']);
cbe_form($dbh,'Academic target or follow-up','plan',['AcademicYearId'=>'years','TermId'=>'terms','Kind'=>'enum:target|coverage_follow_up|missed_lesson|learning_activity|reporting','Title'=>'text','TeacherId'=>'teachers','DueDate'=>'date','Status'=>'enum:open|in_progress|completed|cancelled','Details'=>'textarea','Outcome'=>'textarea'],$plan?:$defaults);
cbe_table(cbe_rows($dbh,'SELECT p.id,p.Kind,p.Title,u.FullName Owner,p.DueDate,p.Status,p.Details,p.Outcome FROM tblacademicplans p JOIN tblusers u ON u.id=p.TeacherId WHERE p.AcademicYearId=? AND (?=0 OR p.TermId=?) ORDER BY p.DueDate',[$year,$term,$term]),'dean-planning.php?kind=plan&id=');
foreach(cbe_rows($dbh,"SELECT id,Title FROM tblacademicplans WHERE AcademicYearId=? AND (?=0 OR TermId=?) AND Status IN ('open','in_progress') AND DueDate<CURRENT_DATE",[$year,$term,$term]) as $row) {
    echo '<form method="post">';csrf_field();echo '<input type="hidden" name="action" value="remind"><input type="hidden" name="id" value="'.(int)$row['id'].'"><button class="btn btn-default">Remind owner: '.academic_h($row['Title']).'</button></form>';
}
portal_end('dean');
