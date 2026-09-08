<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/dean-auth.php');
require_dean();

$roleSql = "'class_teacher','subject_teacher','head_of_department','exams_officer','deputy_dean'";
$sql = "SELECT COALESCE(NULLIF(Department,''), 'Unassigned') AS DepartmentName,
        COUNT(*) AS TotalTeachers,
        SUM(CASE WHEN Status = 1 THEN 1 ELSE 0 END) AS ActiveTeachers,
        SUM(CASE WHEN Status = 0 THEN 1 ELSE 0 END) AS InactiveTeachers,
        SUM(CASE WHEN Role = 'head_of_department' THEN 1 ELSE 0 END) AS HeadsOfDepartment
        FROM tblusers
        WHERE Role IN ($roleSql)
        GROUP BY COALESCE(NULLIF(Department,''), 'Unassigned')
        ORDER BY DepartmentName";
$query = $dbh->prepare($sql);
$query->execute();
$departments = $query->fetchAll(PDO::FETCH_OBJ);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teacher Departments | SRMS</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <link rel="stylesheet" href="css/custom.css" media="screen">
</head>
<body class="top-navbar-fixed">
<div class="main-wrapper">
<?php include('includes/topbar.php');?>
<div class="content-wrapper"><div class="content-container">
<?php include('includes/leftbar.php');?>
<div class="main-page"><div class="container-fluid">
<div class="row page-title-div">
    <div class="col-md-8"><h2 class="title">Teacher Departments</h2></div>
    <div class="col-md-4 text-right"><a href="manage-teachers.php" class="btn btn-default">Manage Teachers</a></div>
</div>
<section class="section">
<div class="panel"><div class="panel-heading"><h5>Department Summary</h5></div><div class="panel-body">
    <table class="table table-striped table-bordered">
        <thead><tr><th>Department</th><th>Total Teachers</th><th>Active</th><th>Inactive</th><th>Heads of Department</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach($departments as $department) { ?>
            <tr>
                <td><?php echo htmlentities($department->DepartmentName); ?></td>
                <td><?php echo htmlentities($department->TotalTeachers); ?></td>
                <td><?php echo htmlentities($department->ActiveTeachers); ?></td>
                <td><?php echo htmlentities($department->InactiveTeachers); ?></td>
                <td><?php echo htmlentities($department->HeadsOfDepartment); ?></td>
                <td><a href="manage-teachers.php?department=<?php echo urlencode($department->DepartmentName === 'Unassigned' ? '' : $department->DepartmentName); ?>" class="btn btn-xs btn-primary">View Teachers</a></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div></div>
</section>
</div></div></div></div></div>
<script src="js/jquery/jquery-2.2.4.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
