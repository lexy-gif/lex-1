<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/dean-auth.php');
require_dean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Audit Logs | SRMS</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" type="text/css" href="js/DataTables/datatables.min.css">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <link rel="stylesheet" href="css/custom.css" media="screen">
</head>
<body class="top-navbar-fixed">
<div class="main-wrapper">
<?php include('includes/topbar.php');?>
<div class="content-wrapper"><div class="content-container">
<?php include('includes/leftbar.php');?>
<div class="main-page"><div class="container-fluid">
<div class="row page-title-div"><div class="col-md-8"><h2 class="title">Audit Logs</h2></div></div>
<section class="section"><div class="panel"><div class="panel-heading"><h5>Academic Activity Trail</h5></div><div class="panel-body">
<table id="example" class="display table table-striped table-bordered">
<thead><tr><th>#</th><th>Actor</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th><th>Date</th></tr></thead><tbody>
<?php
$sql = "SELECT Actor, Action, EntityType, EntityId, Details, IpAddress, CreationDate FROM tblauditlog ORDER BY CreationDate DESC LIMIT 300";
$query = $dbh->prepare($sql);
$query->execute();
$cnt = 1;
foreach($query->fetchAll(PDO::FETCH_OBJ) as $log) {
    echo '<tr><td>'.htmlentities($cnt).'</td><td>'.htmlentities($log->Actor).'</td><td>'.htmlentities($log->Action).'</td><td>'.htmlentities($log->EntityType.' #'.$log->EntityId).'</td><td>'.htmlentities($log->Details).'</td><td>'.htmlentities($log->IpAddress).'</td><td>'.htmlentities($log->CreationDate).'</td></tr>';
    $cnt++;
}
?>
</tbody></table>
</div></div></section>
</div></div></div></div></div>
<script src="js/jquery/jquery-2.2.4.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/DataTables/datatables.min.js"></script>
<script src="js/main.js"></script>
<script>$(function($){ $('#example').DataTable(); });</script>
</body>
</html>
