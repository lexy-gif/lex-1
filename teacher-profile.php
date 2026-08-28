<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/teacher-auth.php');
require_teacher();
$sql = "SELECT u.FullName, u.Username, u.Email, u.StaffNumber, u.PhoneNumber, u.Department, u.Role, u.EmailStatus, u.EmailVerifiedAt, u.Status, c.ClassName, c.Section
        FROM tblusers u
        LEFT JOIN tblclasses c ON c.id = u.ClassId
        WHERE u.id = :teacherid AND u.Role IN ('class_teacher','subject_teacher','head_of_department','exams_officer','deputy_dean')
        LIMIT 1";
$query = $dbh->prepare($sql);
$query->execute(array(':teacherid' => teacher_id()));
$teacher = $query->fetch(PDO::FETCH_OBJ);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile | SRMS</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" href="css/main.css" media="screen">
</head>
<body class="top-navbar-fixed">
<div class="main-wrapper">
<?php include('includes/teacher-topbar.php');?>
<div class="content-wrapper"><div class="content-container">
<?php include('includes/teacher-leftbar.php');?>
<div class="main-page"><div class="container-fluid">
<div class="row page-title-div"><div class="col-md-6"><h2 class="title">Profile</h2></div></div>
<section class="section">
<div class="panel"><div class="panel-heading"><h5>Teacher Profile</h5></div><div class="panel-body">
    <p><strong>Name:</strong> <?php echo htmlentities($teacher->FullName); ?></p>
    <p><strong>Staff Number:</strong> <?php echo htmlentities($teacher->StaffNumber); ?></p>
    <p><strong>Username:</strong> <?php echo htmlentities($teacher->Username); ?></p>
    <p><strong>Email:</strong> <?php echo htmlentities($teacher->Email); ?></p>
    <p><strong>Email Status:</strong> <?php echo htmlentities($teacher->EmailStatus); ?><?php echo $teacher->EmailVerifiedAt ? ' on ' . htmlentities($teacher->EmailVerifiedAt) : ''; ?></p>
    <p><strong>Phone:</strong> <?php echo htmlentities($teacher->PhoneNumber); ?></p>
    <p><strong>Role:</strong> <?php echo htmlentities(str_replace('_', ' ', $teacher->Role)); ?></p>
    <p><strong>Department:</strong> <?php echo htmlentities($teacher->Department); ?></p>
    <p><strong>Assigned Class:</strong> <?php echo $teacher->ClassName ? htmlentities($teacher->ClassName . ' Section-' . $teacher->Section) : 'Not assigned'; ?></p>
    <p><strong>Status:</strong> <?php echo $teacher->Status ? 'Active' : 'Inactive'; ?></p>
</div></div>
</section>
</div></div></div></div></div>
<script src="js/jquery/jquery-2.2.4.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
