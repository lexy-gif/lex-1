<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/dean-auth.php');
require_dean();

$status = $_GET['status'] ?? 'all';
$allowed = array('all','PENDING','SENT','FAILED','RETRYING','SKIPPED');
if(!in_array($status, $allowed)) {
    $status = 'all';
}

$where = "";
$params = array();
if($status !== 'all') {
    $where = "WHERE d.Status = :status";
    $params[':status'] = $status;
}

$sql = "SELECT d.*, u.FullName, u.Email, n.Title, n.Category
        FROM tblnotificationdeliveries d
        JOIN tblusers u ON u.id = d.UserId
        LEFT JOIN tblteachernotifications n ON n.id = d.NotificationId
        $where
        ORDER BY d.CreationDate DESC
        LIMIT 200";
$query = $dbh->prepare($sql);
$query->execute($params);
$deliveries = $query->fetchAll(PDO::FETCH_OBJ);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notification Deliveries | SRMS</title>
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
<div class="row page-title-div"><div class="col-md-8"><h2 class="title">Notification Deliveries</h2></div></div>
<section class="section">
<div class="panel"><div class="panel-heading"><h5>Email Queue and Delivery Status</h5></div><div class="panel-body">
    <p class="text-muted">This page shows notification delivery records. It does not expose mail credentials.</p>
    <p>
        <?php foreach($allowed as $item) {
            $label = $item === 'all' ? 'All' : ucwords(strtolower($item));
            $class = $status === $item ? 'btn btn-primary btn-sm' : 'btn btn-default btn-sm';
            echo '<a class="' . $class . ' mr-5" href="dean-notification-deliveries.php?status=' . urlencode($item) . '">' . htmlentities($label) . '</a>';
        } ?>
    </p>
    <table id="example" class="display table table-striped table-bordered">
        <thead><tr><th>#</th><th>Teacher</th><th>Channel</th><th>Destination</th><th>Notification</th><th>Category</th><th>Status</th><th>Created</th><th>Error</th></tr></thead>
        <tbody>
        <?php $cnt = 1; foreach($deliveries as $delivery) { ?>
            <tr>
                <td><?php echo htmlentities($cnt); ?></td>
                <td><?php echo htmlentities($delivery->FullName); ?></td>
                <td><?php echo htmlentities($delivery->Channel); ?></td>
                <td><?php echo htmlentities($delivery->Destination ?: $delivery->Email); ?></td>
                <td><?php echo htmlentities($delivery->Title); ?></td>
                <td><?php echo htmlentities($delivery->Category); ?></td>
                <td><?php echo htmlentities($delivery->Status); ?></td>
                <td><?php echo htmlentities($delivery->CreationDate); ?></td>
                <td><?php echo htmlentities($delivery->ErrorMessage); ?></td>
            </tr>
        <?php $cnt++; } ?>
        </tbody>
    </table>
</div></div>
</section>
</div></div></div></div></div>
<script src="js/jquery/jquery-2.2.4.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/DataTables/datatables.min.js"></script>
<script src="js/main.js"></script>
<script>$(function($){ $('#example').DataTable(); });</script>
</body>
</html>
