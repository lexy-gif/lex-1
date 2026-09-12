<?php
require_once 'includes/bootstrap.php';

require_once 'includes/config.php';
require_once 'includes/teacher-auth.php';
require_class_teacher();
$classId = teacher_class_id();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Class Reports | SRMS</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <link rel="stylesheet" href="css/custom.css">
</head>
<body class="top-navbar-fixed">
<div class="main-wrapper">
<?php include('includes/teacher-topbar.php');?>
<div class="content-wrapper"><div class="content-container">
<?php include('includes/teacher-leftbar.php');?>
<div class="main-page"><div class="container-fluid">
<div class="row page-title-div"><div class="col-md-6"><h2 class="title">Class Reports</h2></div></div>
<section class="section">
<div class="row">
    <div class="col-md-4"><div class="panel"><div class="panel-heading"><h5>Attendance Report</h5></div><div class="panel-body">
        <?php
        $attendance = $dbh->prepare("SELECT Status, COUNT(*) total FROM tblattendance WHERE ClassId = :classid GROUP BY Status");
        $attendance->execute(array(':classid' => $classId));
        foreach($attendance->fetchAll(PDO::FETCH_OBJ) as $row) {
            echo '<p>' . htmlentities(ucfirst($row->Status)) . ': <strong>' . htmlentities($row->total) . '</strong></p>';
        }
        ?>
    </div></div></div>
    <div class="col-md-4"><div class="panel"><div class="panel-heading"><h5>Missing Results Report</h5></div><div class="panel-body">
        <?php
        $missing = $dbh->prepare("SELECT COUNT(*) FROM tblstudents s WHERE s.ClassId = :classid AND NOT EXISTS (SELECT 1 FROM tblresult r WHERE r.StudentId = s.StudentId)");
        $missing->execute(array(':classid' => $classId));
        echo '<p>Students without results: <strong>' . htmlentities($missing->fetchColumn()) . '</strong></p>';
        ?>
    </div></div></div>
    <div class="col-md-4"><div class="panel"><div class="panel-heading"><h5>Account Report</h5></div><div class="panel-body">
        <?php
        $accounts = $dbh->prepare("SELECT Status, COUNT(*) total FROM tblusers WHERE ClassId = :classid AND Role = 'student' GROUP BY Status");
        $accounts->execute(array(':classid' => $classId));
        foreach($accounts->fetchAll(PDO::FETCH_OBJ) as $row) {
            echo '<p>' . ($row->Status ? 'Active' : 'Inactive') . ' accounts: <strong>' . htmlentities($row->total) . '</strong></p>';
        }
        ?>
    </div></div></div>
</div>
<div class="panel"><div class="panel-heading"><h5>Class Performance Report</h5></div><div class="panel-body">
    <table class="table table-bordered table-striped">
        <thead><tr><th>Student</th><th>Total Marks</th><th>Average</th></tr></thead><tbody>
        <?php
        $sql = "SELECT s.StudentName, SUM(r.marks) totalMarks, ROUND(AVG(r.marks),2) averageMarks
                FROM tblstudents s
                LEFT JOIN tblresult r ON r.StudentId = s.StudentId
                WHERE s.ClassId = :classid
                GROUP BY s.StudentId, s.StudentName
                ORDER BY totalMarks DESC";
        $query = $dbh->prepare($sql);
        $query->execute(array(':classid' => $classId));
        foreach($query->fetchAll(PDO::FETCH_OBJ) as $row) {
            echo '<tr><td>' . htmlentities($row->StudentName) . '</td><td>' . htmlentities($row->totalMarks ?: 0) . '</td><td>' . htmlentities($row->averageMarks ?: 0) . '</td></tr>';
        }
        ?>
        </tbody>
    </table>
    <button type="button" onclick="window.print();" class="btn btn-default"><i class="fa fa-print"></i> Print Report</button>
</div></div>
</section>
</div></div></div></div></div>
<script src="js/jquery/jquery-3.7.1.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
