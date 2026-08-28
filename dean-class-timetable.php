<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/csrf.php');
include('includes/audit.php');
include('includes/dean-auth.php');
include('includes/timetable.php');
require_dean();

$activePeriod = timetable_active_period($dbh);

if(isset($_POST['save_lesson'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    if(!$activePeriod) {
        $error = "Set an active academic term before creating a timetable.";
    } else {
        $classId = $_POST['classid'];
        $subjectId = $_POST['subjectid'];
        $teacherId = $_POST['teacherid'] ?: null;
        $roomId = $_POST['roomid'] ?: null;
        $day = $_POST['dayofweek'];
        $startTime = $_POST['starttime'];
        $endTime = $_POST['endtime'];
        $status = $_POST['status'];
        $reason = trim($_POST['reason']);

        $conflicts = timetable_lesson_conflicts($dbh, $activePeriod->AcademicYearId, $activePeriod->TermId, $classId, $teacherId, $roomId, $day, $startTime, $endTime);
        if($conflicts) {
            $error = implode(" ", $conflicts);
        } else {
            $sql = "INSERT INTO tblclasstimetableentries(AcademicYearId, TermId, ClassId, SubjectId, TeacherId, RoomId, DayOfWeek, StartTime, EndTime, Status, ChangeReason, CreatedBy)
                    VALUES(:yearid, :termid, :classid, :subjectid, :teacherid, :roomid, :dayofweek, :starttime, :endtime, :status, :reason, :createdby)";
            $query = $dbh->prepare($sql);
            $query->execute(array(':yearid' => $activePeriod->AcademicYearId, ':termid' => $activePeriod->TermId, ':classid' => $classId, ':subjectid' => $subjectId, ':teacherid' => $teacherId, ':roomid' => $roomId, ':dayofweek' => $day, ':starttime' => $startTime, ':endtime' => $endTime, ':status' => $status, ':reason' => $reason, ':createdby' => dean_name()));
            $entryId = $dbh->lastInsertId();
            timetable_record_version($dbh, 'class', $entryId, 'lesson_created', null, json_encode($_POST), $reason, dean_name());
            audit_log($dbh, 'lesson_created', 'tblclasstimetableentries', $entryId, 'Class ID ' . $classId . ', Subject ID ' . $subjectId);
            $msg = "Lesson saved successfully.";
        }
    }
}

if(isset($_POST['publish_class'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    $classId = $_POST['classid'];
    $publish = $dbh->prepare("UPDATE tblclasstimetableentries SET Status = 'published' WHERE ClassId = :classid AND AcademicYearId = :yearid AND TermId = :termid AND Status <> 'cancelled'");
    $publish->execute(array(':classid' => $classId, ':yearid' => $activePeriod->AcademicYearId, ':termid' => $activePeriod->TermId));
    $teachers = $dbh->prepare("SELECT DISTINCT TeacherId FROM tblclasstimetableentries WHERE ClassId = :classid AND TeacherId IS NOT NULL");
    $teachers->execute(array(':classid' => $classId));
    foreach($teachers->fetchAll(PDO::FETCH_OBJ) as $teacher) {
        timetable_notify_user($dbh, $teacher->TeacherId, $classId, 'Timetable Published', 'A class timetable affecting you has been published by the Dean of Studies.');
    }
    $classTeacher = $dbh->prepare("SELECT id FROM tblusers WHERE Role = 'class_teacher' AND ClassId = :classid AND Status = 1");
    $classTeacher->execute(array(':classid' => $classId));
    foreach($classTeacher->fetchAll(PDO::FETCH_OBJ) as $teacher) {
        timetable_notify_user($dbh, $teacher->id, $classId, 'Class Timetable Published', 'Your assigned class timetable has been published.');
    }
    audit_log($dbh, 'class_timetable_published', 'tblclasses', $classId, 'Published by Dean of Studies');
    $msg = "Class timetable published and notifications generated.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Class Timetable | SRMS</title>
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
<div class="row page-title-div"><div class="col-md-8"><h2 class="title">Class Timetable</h2><p class="text-muted"><?php echo $activePeriod ? htmlentities($activePeriod->AcademicYear . ' - ' . $activePeriod->TermName) : 'No active term'; ?></p></div></div>
<section class="section">
<?php if($msg){?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php } ?>
<?php if($error){?><div class="alert alert-danger"><?php echo htmlentities($error); ?></div><?php } ?>
<div class="row">
<div class="col-md-4"><div class="panel"><div class="panel-heading"><h5>Create Lesson</h5></div><div class="panel-body">
<form method="post">
<?php csrf_field(); ?>
<div class="form-group"><label>Class</label><select name="classid" class="form-control" required><option value="">Select Class</option><?php $q=$dbh->query("SELECT id,ClassName,Section FROM tblclasses ORDER BY ClassNameNumeric,Section"); foreach($q->fetchAll(PDO::FETCH_OBJ) as $c){echo '<option value="'.htmlentities($c->id).'">'.htmlentities($c->ClassName.' Section-'.$c->Section).'</option>';} ?></select></div>
<div class="form-group"><label>Subject</label><select name="subjectid" class="form-control" required><option value="">Select Subject</option><?php $q=$dbh->query("SELECT id,SubjectName FROM tblsubjects ORDER BY SubjectName"); foreach($q->fetchAll(PDO::FETCH_OBJ) as $s){echo '<option value="'.htmlentities($s->id).'">'.htmlentities($s->SubjectName).'</option>';} ?></select></div>
<div class="form-group"><label>Teacher</label><select name="teacherid" class="form-control"><option value="">No teacher</option><?php $q=$dbh->query("SELECT id,FullName,Role FROM tblusers WHERE Role IN ('class_teacher','subject_teacher','head_of_department','exams_officer','deputy_dean') AND Status=1 ORDER BY FullName"); foreach($q->fetchAll(PDO::FETCH_OBJ) as $t){echo '<option value="'.htmlentities($t->id).'">'.htmlentities($t->FullName.' - '.str_replace('_',' ',$t->Role)).'</option>';} ?></select></div>
<div class="form-group"><label>Room</label><select name="roomid" class="form-control"><option value="">No room</option><?php $q=$dbh->query("SELECT id,RoomName FROM tblrooms WHERE Status=1 ORDER BY RoomName"); foreach($q->fetchAll(PDO::FETCH_OBJ) as $r){echo '<option value="'.htmlentities($r->id).'">'.htmlentities($r->RoomName).'</option>';} ?></select></div>
<div class="form-group"><label>Day</label><select name="dayofweek" class="form-control"><?php foreach(array('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') as $day){echo '<option value="'.$day.'">'.$day.'</option>';} ?></select></div>
<div class="form-group"><label>Start</label><input type="time" name="starttime" class="form-control" required></div>
<div class="form-group"><label>End</label><input type="time" name="endtime" class="form-control" required></div>
<div class="form-group"><label>Status</label><select name="status" class="form-control"><option value="draft">Draft</option><option value="ready_for_review">Ready For Review</option><option value="approved">Approved</option></select></div>
<div class="form-group"><label>Reason / Note</label><input type="text" name="reason" class="form-control"></div>
<button type="submit" name="save_lesson" class="btn btn-primary">Save Lesson</button>
</form>
</div></div></div>
<div class="col-md-8"><div class="panel"><div class="panel-heading"><h5>Weekly Lessons</h5></div><div class="panel-body">
<table id="example" class="display table table-striped table-bordered">
<thead><tr><th>Day</th><th>Time</th><th>Class</th><th>Subject</th><th>Teacher</th><th>Room</th><th>Status</th></tr></thead><tbody>
<?php
$sql="SELECT e.*, c.ClassName,c.Section,s.SubjectName,u.FullName,r.RoomName FROM tblclasstimetableentries e JOIN tblclasses c ON c.id=e.ClassId JOIN tblsubjects s ON s.id=e.SubjectId LEFT JOIN tblusers u ON u.id=e.TeacherId LEFT JOIN tblrooms r ON r.id=e.RoomId ORDER BY FIELD(e.DayOfWeek,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), e.StartTime";
$q=$dbh->prepare($sql); $q->execute(); foreach($q->fetchAll(PDO::FETCH_OBJ) as $e){echo '<tr><td>'.htmlentities($e->DayOfWeek).'</td><td>'.htmlentities($e->StartTime.' - '.$e->EndTime).'</td><td>'.htmlentities($e->ClassName.' Section-'.$e->Section).'</td><td>'.htmlentities($e->SubjectName).'</td><td>'.htmlentities($e->FullName).'</td><td>'.htmlentities($e->RoomName).'</td><td>'.htmlentities(str_replace('_',' ',$e->Status)).'</td></tr>';}
?>
</tbody></table>
<form method="post" class="form-inline">
<?php csrf_field(); ?>
<select name="classid" class="form-control" required><option value="">Publish Class</option><?php $q=$dbh->query("SELECT id,ClassName,Section FROM tblclasses ORDER BY ClassNameNumeric,Section"); foreach($q->fetchAll(PDO::FETCH_OBJ) as $c){echo '<option value="'.htmlentities($c->id).'">'.htmlentities($c->ClassName.' Section-'.$c->Section).'</option>';} ?></select>
<button type="submit" name="publish_class" class="btn btn-success">Publish Timetable</button>
</form>
</div></div></div>
</div>
</section>
</div></div></div></div></div>
<script src="js/jquery/jquery-2.2.4.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/DataTables/datatables.min.js"></script>
<script src="js/main.js"></script>
<script>$(function($){ $('#example').DataTable(); });</script>
</body>
</html>
