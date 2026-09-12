<?php
require_once 'includes/bootstrap.php';
$error=$msg='';
require_once 'includes/config.php';
require_once 'includes/csrf.php';
require_once 'includes/dean-auth.php';
require_once 'includes/academic-periods.php';
require_dean();
$error='';$msg=isset($_GET['activated'])?'Academic period activated.':(isset($_GET['saved'])?'Academic period saved.':'');
$form=[];
if(isset($_GET['termid'])) {
    $form=academic_query($dbh,'SELECT ay.AcademicYear academicyear,t.TermName termname,t.StartDate startdate,t.EndDate enddate FROM tblterms t JOIN tblacademicyears ay ON ay.id=t.AcademicYearId WHERE t.id=?',[(int)$_GET['termid']])->fetch(PDO::FETCH_ASSOC);
    if(!$form){http_response_code(404);exit('Academic term not found.');}
}
if($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_require_valid($_POST['csrf_token']??'');
    $form=$_POST;
    try {
        $dbh->beginTransaction();
        if(isset($_POST['save_period'])) {
            $period=academic_period_save($dbh,$_POST);
            $redirect='termid='.$period['TermId'].'&saved=1';
        } elseif(isset($_POST['activate_period'])) {
            academic_period_activate($dbh,$_POST['yearid']??null,$_POST['termid']??null);
            $redirect='termid='.(int)$_POST['termid'].'&activated=1';
        } else throw new DomainException('Unknown academic period action.');
        $dbh->commit();header('Location: dean-academic-periods.php?'.$redirect);exit;
    } catch(Throwable $e) {
        if($dbh->inTransaction())$dbh->rollBack();
        $error=$e instanceof DomainException?$e->getMessage():'Academic period could not be saved. Please try again.';
        error_log($e->getMessage());
    }
}
$formValue=static fn($key)=>is_scalar($form[$key]??null)?(string)$form[$key]:'';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Academic Years & Terms | SRMS</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" type="text/css" href="js/DataTables/datatables.min.css">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <link rel="stylesheet" href="css/custom.css">
</head>
<body class="top-navbar-fixed">
<div class="main-wrapper">
<?php include('includes/topbar.php');?>
<div class="content-wrapper"><div class="content-container">
<?php include('includes/leftbar.php');?>
<div class="main-page"><div class="container-fluid">
<div class="row page-title-div"><div class="col-md-8"><h2 class="title">Academic Years & Terms</h2></div></div>
<section class="section"><div class="row">
    <div class="col-md-4"><div class="panel"><div class="panel-heading"><h5>Create / Update Term</h5></div><div class="panel-body">
        <?php if($msg){?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php } ?>
        <?php if($error){?><div class="alert alert-danger"><?php echo htmlentities($error); ?></div><?php } ?>
        <form method="post">
            <?php csrf_field(); ?>
            <p>Saving updates this term's dates. Select activation to switch the school's active year and term.</p>
            <div class="form-group"><label>Academic Year</label><input type="text" name="academicyear" class="form-control" placeholder="2026" maxlength="20" value="<?= academic_h($formValue('academicyear')) ?>" required></div>
            <div class="form-group"><label>Term</label><input type="text" name="termname" class="form-control" placeholder="Term 1" maxlength="50" value="<?= academic_h($formValue('termname')) ?>" required></div>
            <div class="form-group"><label>Term Start Date</label><input type="date" name="startdate" class="form-control" value="<?= academic_h($formValue('startdate')) ?>"></div>
            <div class="form-group"><label>Term End Date</label><input type="date" name="enddate" class="form-control" value="<?= academic_h($formValue('enddate')) ?>"></div>
            <div class="checkbox"><label><input type="checkbox" name="activate" value="1" <?= $formValue('activate')==='1'?'checked':'' ?>> Set as active academic term</label></div>
            <button type="submit" name="save_period" class="btn btn-primary">Save Period</button>
        </form>
    </div></div></div>
    <div class="col-md-8"><div class="panel"><div class="panel-heading"><h5>Academic Periods</h5></div><div class="panel-body">
        <table id="example" class="display table table-striped table-bordered">
            <thead><tr><th>Year</th><th>Term</th><th>Start</th><th>End</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            <?php
            $sql = "SELECT ay.id YearId,ay.AcademicYear,ay.IsActive YearActive,t.id TermId,t.TermName,t.StartDate,t.EndDate,t.IsActive FROM tblterms t JOIN tblacademicyears ay ON ay.id=t.AcademicYearId ORDER BY ay.AcademicYear DESC,t.id DESC";
            $query = $dbh->prepare($sql);
            $query->execute();
            foreach($query->fetchAll(PDO::FETCH_OBJ) as $period) {
                $isActive=$period->IsActive&&$period->YearActive;
                echo '<tr><td>'.academic_h($period->AcademicYear).'</td><td>'.academic_h($period->TermName).'</td><td>'.academic_h($period->StartDate).'</td><td>'.academic_h($period->EndDate).'</td><td>'.($isActive?'Active':'Inactive').'</td><td><a class="btn btn-info btn-xs" href="dean-academic-periods.php?termid='.(int)$period->TermId.'">Edit dates</a>';
                if(!$isActive) {
                    echo '<form method="post">';csrf_field();
                    echo '<input type="hidden" name="yearid" value="'.(int)$period->YearId.'"><input type="hidden" name="termid" value="'.(int)$period->TermId.'"><button class="btn btn-success btn-xs" name="activate_period">Activate</button></form>';
                }
                echo '</td></tr>';
            }
            ?>
            </tbody>
        </table>
    </div></div></div>
</div></section>
</div></div></div></div></div>
<script src="js/jquery/jquery-3.7.1.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/DataTables/datatables.min.js"></script>
<script src="js/main.js"></script>
<script>$(function($){ $('#example').DataTable(); });</script>
</body>
</html>
