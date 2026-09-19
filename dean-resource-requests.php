<?php
require_once 'includes/config.php';require_once 'includes/dean-auth.php';require_dean();
if(!staff_can('resources.request')&&!staff_can('resources.approve'))staff_forbid();
require_once 'includes/csrf.php';require_once 'includes/portal-layout.php';require_once 'includes/cbe-ui.php';require_once 'includes/academic-planning.php';
$error='';[$year,$term]=cbe_period_context($dbh,$_GET);
if($_SERVER['REQUEST_METHOD']==='POST') {
    staff_require(($_POST['action']??'')==='resource_decision'?'resources.approve':'resources.request');csrf_require_valid($_POST['csrf_token']??'');
    try{$dbh->beginTransaction();planning_resource($dbh,$_POST);$dbh->commit();header('Location: dean-resource-requests.php?saved=1');exit;}
    catch(Throwable $e){if($dbh->inTransaction())$dbh->rollBack();$error=$e instanceof DomainException?$e->getMessage():'Could not save resource request.';error_log($e->getMessage());}
}
portal_start('Teaching resource requests','dean');portal_alert($error);if(isset($_GET['saved']))portal_alert('Resource request saved.','success');
echo '<p>Describe teaching shortages and request management review. An approved request records academic need; purchasing and financial authorisation remain with school management.</p>';
cbe_form($dbh,'Submit a teaching resource request','resource',['AcademicYearId'=>'years','TermId'=>'terms','Title'=>'text','Justification'=>'textarea','Quantity'=>'number','RequiredDate'=>'date'],['AcademicYearId'=>$year,'TermId'=>$term]);
cbe_table(cbe_rows($dbh,'SELECT * FROM tblacademicresourcerequests ORDER BY id DESC'));
if(staff_can('resources.approve'))foreach(cbe_rows($dbh,"SELECT * FROM tblacademicresourcerequests WHERE Status='submitted' ORDER BY RequiredDate") as $row)cbe_form($dbh,'Review: '.$row['Title'],'resource_decision',['Status'=>'enum:approved|returned','DecisionReason'=>'textarea'],$row);
portal_end('dean');
