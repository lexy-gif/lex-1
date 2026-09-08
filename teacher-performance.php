<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/teacher-auth.php');
require_class_teacher();
$classId = teacher_class_id();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Performance Analysis | SRMS</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <link rel="stylesheet" href="css/custom.css" media="screen">
</head>
<body class="top-navbar-fixed">
<div class="main-wrapper">
<?php include('includes/teacher-topbar.php');?>
<div class="content-wrapper"><div class="content-container">
<?php include('includes/teacher-leftbar.php');?>
<div class="main-page"><div class="container-fluid">
<div class="row page-title-div"><div class="col-md-6"><h2 class="title">Performance Analysis</h2></div></div>
<section class="section">
<div class="row">
    <div class="col-md-6"><div class="panel"><div class="panel-heading"><h5>Subject Averages</h5></div><div class="panel-body">
        <table class="table table-bordered">
            <thead><tr><th>Subject</th><th>Average</th></tr></thead><tbody>
            <?php
            $sql = "SELECT sub.SubjectName, ROUND(AVG(r.marks),2) averageMarks
                    FROM tblsubjectcombination sc
                    JOIN tblsubjects sub ON sub.id = sc.SubjectId
                    LEFT JOIN tblresult r ON r.SubjectId = sub.id AND r.ClassId = sc.ClassId
                    WHERE sc.ClassId = :classid AND sc.status = 1
                    GROUP BY sub.SubjectName ORDER BY averageMarks DESC";
            $query = $dbh->prepare($sql);
            $query->execute(array(':classid' => $classId));
            foreach($query->fetchAll(PDO::FETCH_OBJ) as $row) {
                echo '<tr><td>' . htmlentities($row->SubjectName) . '</td><td>' . htmlentities($row->averageMarks ?: 0) . '</td></tr>';
            }
            ?>
            </tbody>
        </table>
    </div></div></div>
    <div class="col-md-6"><div class="panel"><div class="panel-heading"><h5>Students Requiring Support</h5></div><div class="panel-body">
        <table class="table table-bordered">
            <thead><tr><th>Student</th><th>Average</th><th>Signal</th></tr></thead><tbody>
            <?php
            $sql = "SELECT s.StudentName, ROUND(AVG(r.marks),2) averageMarks
                    FROM tblstudents s
                    LEFT JOIN tblresult r ON r.StudentId = s.StudentId
                    WHERE s.ClassId = :classid
                    GROUP BY s.StudentId, s.StudentName
                    HAVING averageMarks IS NULL OR averageMarks < 50
                    ORDER BY averageMarks ASC";
            $query = $dbh->prepare($sql);
            $query->execute(array(':classid' => $classId));
            foreach($query->fetchAll(PDO::FETCH_OBJ) as $row) {
                $signal = $row->averageMarks === null ? 'Missing assessments' : 'Academic support';
                echo '<tr><td>' . htmlentities($row->StudentName) . '</td><td>' . htmlentities($row->averageMarks ?: 0) . '</td><td>' . htmlentities($signal) . '</td></tr>';
            }
            ?>
            </tbody>
        </table>
    </div></div></div>
</div>
</section>
</div></div></div></div></div>
<script src="js/jquery/jquery-2.2.4.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
