<?php
require_once 'includes/bootstrap.php';
$error=$msg='';

require_once 'includes/config.php';
require_once 'includes/csrf.php';
require_once 'includes/audit.php';
require_once 'includes/dean-auth.php';
require_dean();

if(isset($_POST['save_room'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    $roomName = trim($_POST['roomname']);
    $capacity = $_POST['capacity'] ?: null;
    if($roomName !== '') {
        $sql = "INSERT INTO tblrooms(RoomName, Capacity) VALUES(:roomname, :capacity)
                ON DUPLICATE KEY UPDATE Capacity = VALUES(Capacity), Status = 1";
        $query = $dbh->prepare($sql);
        $query->execute(array(':roomname' => $roomName, ':capacity' => $capacity));
        audit_log($dbh, 'timetable_room_saved', 'tblrooms', $roomName, 'Room capacity ' . $capacity);
        $msg = "Room saved successfully.";
    }
}

if(isset($_POST['save_period'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    $sql = "INSERT INTO tbltimetableperiods(PeriodName, DayOfWeek, StartTime, EndTime, PeriodType)
            VALUES(:periodname, :dayofweek, :starttime, :endtime, :periodtype)
            ON DUPLICATE KEY UPDATE PeriodName = VALUES(PeriodName), PeriodType = VALUES(PeriodType), Status = 1";
    $query = $dbh->prepare($sql);
    $query->execute(array(
        ':periodname' => trim($_POST['periodname']),
        ':dayofweek' => $_POST['dayofweek'],
        ':starttime' => $_POST['starttime'],
        ':endtime' => $_POST['endtime'],
        ':periodtype' => $_POST['periodtype']
    ));
    audit_log($dbh, 'timetable_period_saved', 'tbltimetableperiods', $_POST['dayofweek'], $_POST['periodname']);
    $msg = "Timetable period saved successfully.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Timetable Setup | SRMS</title>
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
<div class="row page-title-div"><div class="col-md-8"><h2 class="title">Timetable Setup</h2></div></div>
<section class="section">
<?php if($msg){?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php } ?>
<div class="row">
    <div class="col-md-4"><div class="panel"><div class="panel-heading"><h5>Rooms</h5></div><div class="panel-body">
        <form method="post">
            <?php csrf_field(); ?>
            <div class="form-group"><label>Room Name</label><input type="text" name="roomname" class="form-control" required></div>
            <div class="form-group"><label>Capacity</label><input type="number" name="capacity" class="form-control"></div>
            <button type="submit" name="save_room" class="btn btn-primary">Save Room</button>
        </form>
    </div></div></div>
    <div class="col-md-8"><div class="panel"><div class="panel-heading"><h5>School Periods</h5></div><div class="panel-body">
        <form method="post" class="form-inline form-bottom-md">
            <?php csrf_field(); ?>
            <input type="text" name="periodname" class="form-control" placeholder="Period 1" required>
            <select name="dayofweek" class="form-control">
                <?php foreach(array('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') as $day) { echo '<option value="'.$day.'">'.$day.'</option>'; } ?>
            </select>
            <input type="time" name="starttime" class="form-control" required>
            <input type="time" name="endtime" class="form-control" required>
            <select name="periodtype" class="form-control">
                <?php foreach(array('lesson','break','lunch','assembly','games','club','activity') as $type) { echo '<option value="'.$type.'">'.ucfirst($type).'</option>'; } ?>
            </select>
            <button type="submit" name="save_period" class="btn btn-primary">Save Period</button>
        </form>
        <table id="example" class="display table table-striped table-bordered">
            <thead><tr><th>Day</th><th>Period</th><th>Start</th><th>End</th><th>Type</th></tr></thead><tbody>
            <?php
            $query = $dbh->prepare("SELECT DayOfWeek, PeriodName, StartTime, EndTime, PeriodType FROM tbltimetableperiods WHERE Status = 1 ORDER BY FIELD(DayOfWeek,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), StartTime");
            $query->execute();
            foreach($query->fetchAll(PDO::FETCH_OBJ) as $period) {
                echo '<tr><td>'.htmlentities($period->DayOfWeek).'</td><td>'.htmlentities($period->PeriodName).'</td><td>'.htmlentities($period->StartTime).'</td><td>'.htmlentities($period->EndTime).'</td><td>'.htmlentities(ucfirst($period->PeriodType)).'</td></tr>';
            }
            ?>
            </tbody>
        </table>
    </div></div></div>
</div>
</section>
</div></div></div></div></div>
<script src="js/jquery/jquery-3.7.1.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/DataTables/datatables.min.js"></script>
<script src="js/main.js"></script>
<script>$(function($){ $('#example').DataTable(); });</script>
</body>
</html>
