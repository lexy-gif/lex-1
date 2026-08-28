<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/csrf.php');
include('includes/audit.php');
include('includes/dean-auth.php');
require_dean();

if(isset($_POST['save_period'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    $academicYear = trim($_POST['academicyear']);
    $termName = trim($_POST['termname']);
    $startDate = $_POST['startdate'] ?: null;
    $endDate = $_POST['enddate'] ?: null;
    $activate = isset($_POST['activate']) ? 1 : 0;

    if($academicYear === '' || $termName === '') {
        $error = "Academic year and term are required.";
    } else {
        if($activate) {
            $dbh->exec("UPDATE tblacademicyears SET IsActive = 0");
            $dbh->exec("UPDATE tblterms SET IsActive = 0");
        }

        $yearSql = "INSERT INTO tblacademicyears(AcademicYear, StartDate, EndDate, IsActive)
                    VALUES(:academicyear, :startdate, :enddate, :active)
                    ON DUPLICATE KEY UPDATE StartDate = VALUES(StartDate), EndDate = VALUES(EndDate), IsActive = VALUES(IsActive)";
        $yearQuery = $dbh->prepare($yearSql);
        $yearQuery->execute(array(':academicyear' => $academicYear, ':startdate' => $startDate, ':enddate' => $endDate, ':active' => $activate));

        $yearSelect = $dbh->prepare("SELECT id FROM tblacademicyears WHERE AcademicYear = :academicyear LIMIT 1");
        $yearSelect->execute(array(':academicyear' => $academicYear));
        $year = $yearSelect->fetch(PDO::FETCH_OBJ);

        $termSql = "INSERT INTO tblterms(AcademicYearId, TermName, StartDate, EndDate, IsActive)
                    VALUES(:yearid, :termname, :startdate, :enddate, :active)
                    ON DUPLICATE KEY UPDATE StartDate = VALUES(StartDate), EndDate = VALUES(EndDate), IsActive = VALUES(IsActive)";
        $termQuery = $dbh->prepare($termSql);
        $termQuery->execute(array(':yearid' => $year->id, ':termname' => $termName, ':startdate' => $startDate, ':enddate' => $endDate, ':active' => $activate));
        audit_log($dbh, 'academic_period_saved', 'tblacademicyears', $year->id, $academicYear . ' - ' . $termName);
        $msg = "Academic period saved.";
    }
}
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
</head>
<body class="top-navbar-fixed">
<div class="main-wrapper">
<?php include('includes/topbar.php');?>
<div class="content-wrapper"><div class="content-container">
<?php include('includes/leftbar.php');?>
<div class="main-page"><div class="container-fluid">
<div class="row page-title-div"><div class="col-md-8"><h2 class="title">Academic Years & Terms</h2></div></div>
<section class="section"><div class="row">
    <div class="col-md-4"><div class="panel"><div class="panel-heading"><h5>Create / Activate Term</h5></div><div class="panel-body">
        <?php if($msg){?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php } ?>
        <?php if($error){?><div class="alert alert-danger"><?php echo htmlentities($error); ?></div><?php } ?>
        <form method="post">
            <?php csrf_field(); ?>
            <div class="form-group"><label>Academic Year</label><input type="text" name="academicyear" class="form-control" placeholder="2026" required></div>
            <div class="form-group"><label>Term</label><input type="text" name="termname" class="form-control" placeholder="Term 1" required></div>
            <div class="form-group"><label>Start Date</label><input type="date" name="startdate" class="form-control"></div>
            <div class="form-group"><label>End Date</label><input type="date" name="enddate" class="form-control"></div>
            <div class="checkbox"><label><input type="checkbox" name="activate" value="1"> Set as active academic term</label></div>
            <button type="submit" name="save_period" class="btn btn-primary">Save Period</button>
        </form>
    </div></div></div>
    <div class="col-md-8"><div class="panel"><div class="panel-heading"><h5>Academic Periods</h5></div><div class="panel-body">
        <table id="example" class="display table table-striped table-bordered">
            <thead><tr><th>Year</th><th>Term</th><th>Start</th><th>End</th><th>Status</th></tr></thead><tbody>
            <?php
            $sql = "SELECT ay.AcademicYear, t.TermName, t.StartDate, t.EndDate, t.IsActive FROM tblterms t JOIN tblacademicyears ay ON ay.id = t.AcademicYearId ORDER BY ay.AcademicYear DESC, t.id DESC";
            $query = $dbh->prepare($sql);
            $query->execute();
            foreach($query->fetchAll(PDO::FETCH_OBJ) as $period) {
                echo '<tr><td>'.htmlentities($period->AcademicYear).'</td><td>'.htmlentities($period->TermName).'</td><td>'.htmlentities($period->StartDate).'</td><td>'.htmlentities($period->EndDate).'</td><td>'.($period->IsActive ? 'Active' : 'Archived/Upcoming').'</td></tr>';
            }
            ?>
            </tbody>
        </table>
    </div></div></div>
</div></section>
</div></div></div></div></div>
<script src="js/jquery/jquery-2.2.4.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/DataTables/datatables.min.js"></script>
<script src="js/main.js"></script>
<script>$(function($){ $('#example').DataTable(); });</script>
</body>
</html>
