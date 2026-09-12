<?php
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/config.php';require_once __DIR__.'/csrf.php';require_once __DIR__.'/dean-auth.php';
require_once __DIR__.'/senior-ui.php';require_dean();
$areas=['pathways'=>'Senior School Pathways','subjects'=>'Manage Senior School Subjects','combinations'=>'Subject Combinations','assignments'=>'Assign Learners to Pathways','teachers'=>'Senior School Teacher Assignments','promotions'=>'Senior School Promotion','reports'=>'Senior School Reports'];
if(!isset($areas[$seniorArea])){http_response_code(404);exit;}
$title=$areas[$seniorArea];$base='dean-senior-'.$seniorArea.'.php';$ready=senior_ready($dbh);$error='';$conflict=null;
try {$f=senior_filters($_GET);$f['year']=$f['year']?:academic_year($dbh);if($f['year'])academic_period($dbh,$f['year']);$edit=senior_id($_GET['id']??0,'record',true);}
catch(DomainException $e){http_response_code(400);exit(academic_h($e->getMessage()));}
if($ready && $_SERVER['REQUEST_METHOD']==='POST') {
    csrf_require_valid($_POST['csrf_token']??'');
    try {
        $dbh->beginTransaction();$action=$_POST['action']??'';
        switch($action) {
            case 'pathway':senior_save_pathway($dbh,$_POST);break;
            case 'track':senior_save_track($dbh,$_POST);break;
            case 'subject':senior_save_subject($dbh,$_POST);break;
            case 'mapping':senior_map_subjects($dbh,$_POST);break;
            case 'settings':senior_save_settings($dbh,$_POST);break;
            case 'combination':senior_save_combination($dbh,$_POST);break;
            case 'assign':senior_assign_bulk($dbh,$_POST);break;
            case 'teacher':senior_assign_teacher($dbh,$_POST);break;
            case 'promote':senior_promote($dbh,$_POST);break;
            case 'toggle':case 'delete':senior_toggle($dbh,$_POST['entity']??'',senior_id($_POST['id']??null,'record'),$_POST['Status']??0,$action==='delete');break;
            default:throw new DomainException('Unknown action.');
        }
        $dbh->commit();header('Location: '.senior_url($base,['saved'=>1]));exit;
    } catch(Throwable $e) {
        if($dbh->inTransaction())$dbh->rollBack();
        $error=$e instanceof DomainException?$e->getMessage():'Unable to save. Check the selected records and try again.';
        if($e instanceof AcademicConflict)$conflict=$e->assignmentKey;
        error_log($e->getMessage());
    }
}
$report=$_GET['report']??'pathways';if(!in_array($report,['pathways','tracks','combinations','enrollment','teachers'],true)){http_response_code(400);exit('Unknown report.');}
if($ready && isset($_GET['export']))senior_csv(senior_report($dbh,$report,$f),'senior-school-'.$report);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= academic_h($title) ?> | SRMS</title>
<link rel="stylesheet" href="css/bootstrap.min.css"><link rel="stylesheet" href="css/font-awesome.min.css"><link rel="stylesheet" href="css/main.css">
<link rel="stylesheet" href="js/DataTables/datatables.min.css"><link rel="stylesheet" href="css/custom.css">
</head><body class="top-navbar-fixed"><div class="main-wrapper"><?php include __DIR__.'/topbar.php'; ?><div class="content-wrapper"><div class="content-container"><?php include __DIR__.'/leftbar.php'; ?>
<main class="main-page senior-page"><div class="container-fluid"><div class="page-title-div"><h2 class="title"><?= academic_h($title) ?></h2><p>Curriculum, learner placements and academic history for Grades 10–12.</p></div>
<ol class="breadcrumb"><li><a href="dashboard.php">Dean Dashboard</a></li><li class="active"><?= academic_h($title) ?></li></ol>
<nav class="senior-nav cbe-no-print" aria-label="Senior School sections"><?php foreach($areas as $key=>$label){ ?><a class="btn btn-sm <?= $key===$seniorArea?'btn-primary':'btn-default' ?>" href="<?= academic_h(senior_url('dean-senior-'.$key.'.php',['student'=>null])) ?>"><?= academic_h($label) ?></a><?php } ?></nav>
<?php if(!$ready){ ?><div class="alert alert-warning">Senior School setup is not yet available. Contact the system administrator.</div><?php } else {
if($error){ ?><div class="alert alert-danger" role="alert"><?= academic_h($error) ?></div><?php }
if(isset($_GET['saved'])){ ?><div class="alert alert-success" role="status">Changes saved successfully.</div><?php }
senior_filter_form($dbh,$f,$seniorArea);
$entity=$_GET['entity']??'';
$status=['Status','bool'];$yearField=['Academic year','years',senior_choices($dbh,'years')];
$pathField=['Pathway','pathways',cbe_options($dbh,'pathways')];$trackField=['Track','tracks',cbe_options($dbh,'tracks')];
$electiveField=['Elective subjects','subjects[]',senior_choices($dbh,'electives')];
$mathField=['Mathematics (blank uses school/pathway default)','subjects?',senior_choices($dbh,'mathematics')];
$languageField=['Language (blank uses school default)','subjects?',senior_choices($dbh,'language')];
require __DIR__.'/senior-views.php';
} ?>
</div></main></div></div></div>
<div class="modal fade" id="senior-confirm" tabindex="-1" role="dialog" aria-labelledby="senior-confirm-title"><div class="modal-dialog" role="document"><div class="modal-content"><div class="modal-header"><h3 class="modal-title" id="senior-confirm-title">Confirm change</h3></div><div class="modal-body"><p class="senior-confirm-message"></p></div><div class="modal-footer"><button class="btn btn-default" data-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary senior-confirm-accept">Confirm</button></div></div></div></div>
<script src="js/jquery/jquery-3.7.1.min.js"></script><script src="js/bootstrap/bootstrap.min.js"></script><script src="js/DataTables/datatables.min.js"></script><script src="js/main.js"></script><script src="js/senior-school.js"></script>
</body></html>
