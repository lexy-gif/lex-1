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
    <title>Assigned Students | SRMS</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" type="text/css" href="js/DataTables/datatables.min.css">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <link rel="stylesheet" href="css/custom.css">
</head>
<body class="top-navbar-fixed">
<div class="main-wrapper">
<?php include('includes/teacher-topbar.php');?>
<div class="content-wrapper"><div class="content-container">
<?php include('includes/teacher-leftbar.php');?>
<div class="main-page"><div class="container-fluid">
    <div class="row page-title-div"><div class="col-md-6"><h2 class="title">Students</h2></div></div>
    <section class="section"><div class="panel"><div class="panel-heading"><h5>Assigned Class Students</h5></div><div class="panel-body">
        <table id="example" class="display table table-striped table-bordered">
            <thead><tr><th>#</th><th>Name</th><th>Admission No.</th><th>Email</th><th>Linked Guardians</th><th>Status</th></tr></thead>
            <tbody>
            <?php
            $sql = "SELECT s.StudentName,s.RollId,s.StudentEmail,s.Status,(SELECT GROUP_CONCAT(CONCAT(u.FullName,': ',COALESCE(u.ParentPhone,'No phone recorded')) SEPARATOR '; ') FROM tblparentstudents ps JOIN tblusers u ON u.id=ps.ParentId AND u.Role='parent' AND u.Status=1 WHERE ps.StudentId=s.StudentId AND ps.Status=1) ParentPhone FROM tblstudents s WHERE s.ClassId=:classid AND s.Status=1 ORDER BY s.StudentName";
            $query = $dbh->prepare($sql);
            $query->execute(array(':classid' => $classId));
            $cnt = 1;
            foreach($query->fetchAll(PDO::FETCH_OBJ) as $student) { ?>
                <tr>
                    <td><?php echo htmlentities($cnt); ?></td>
                    <td><?php echo htmlentities($student->StudentName); ?></td>
                    <td><?php echo htmlentities($student->RollId); ?></td>
                    <td><?php echo htmlentities($student->StudentEmail); ?></td>
                    <td><?php echo htmlentities($student->ParentPhone??'No linked guardian'); ?></td>
                    <td><?php echo $student->Status ? 'Active' : 'Blocked'; ?></td>
                </tr>
            <?php $cnt++; } ?>
            </tbody>
        </table>
    </div></div></section>
</div></div></div></div></div>
<script src="js/jquery/jquery-3.7.1.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/DataTables/datatables.min.js"></script>
<script src="js/main.js"></script>
<script>$(function($){ $('#example').DataTable(); });</script>
</body>
</html>
