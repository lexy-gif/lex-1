<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/csrf.php');
include('includes/audit.php');
include('includes/dean-auth.php');
include('includes/timetable.php');
require_dean();

if(isset($_POST['save_exam_session'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    $examId = $_POST['examid'];
    $classId = $_POST['classid'];
    $subjectId = $_POST['subjectid'];
    $roomId = $_POST['roomid'] ?: null;
    $invigilatorId = $_POST['invigilatorid'] ?: null;
    $examDate = $_POST['examdate'];
    $startTime = $_POST['starttime'];
    $endTime = $_POST['endtime'];
    $status = $_POST['status'];
    $reason = trim($_POST['reason']);

    $conflicts = timetable_exam_conflicts($dbh, $examId, $classId, $roomId, $invigilatorId, $examDate, $startTime, $endTime);
    if($conflicts) {
        $error = implode(" ", $conflicts);
    } else {
        $sql = "INSERT INTO tblexamtimetableentries(ExamId, ClassId, SubjectId, RoomId, InvigilatorId, ExamDate, StartTime, EndTime, Status, ChangeReason, CreatedBy)
                VALUES(:examid, :classid, :subjectid, :roomid, :invigilatorid, :examdate, :starttime, :endtime, :status, :reason, :createdby)";
        $query = $dbh->prepare($sql);
        $query->execute(array(':examid' => $examId, ':classid' => $classId, ':subjectid' => $subjectId, ':roomid' => $roomId, ':invigilatorid' => $invigilatorId, ':examdate' => $examDate, ':starttime' => $startTime, ':endtime' => $endTime, ':status' => $status, ':reason' => $reason, ':createdby' => dean_name()));
        $entryId = $dbh->lastInsertId();
        timetable_record_version($dbh, 'exam', $entryId, 'exam_session_created', null, json_encode($_POST), $reason, dean_name());
        audit_log($dbh, 'exam_timetable_session_created', 'tblexamtimetableentries', $entryId, 'Exam ID ' . $examId);
        $msg = "Exam timetable session saved.";
    }
}

if(isset($_POST['publish_exam'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    $examId = $_POST['examid'];
    $publish = $dbh->prepare("UPDATE tblexamtimetableentries SET Status = 'published' WHERE ExamId = :examid AND Status <> 'cancelled'");
    $publish->execute(array(':examid' => $examId));

    $notifySql = "SELECT DISTINCT e.ClassId, u.id AS ClassTeacherId
                  FROM tblexamtimetableentries e
                  LEFT JOIN tblusers u ON u.ClassId = e.ClassId AND u.Role = 'class_teacher' AND u.Status = 1
                  WHERE e.ExamId = :examid";
    $notify = $dbh->prepare($notifySql);
    $notify->execute(array(':examid' => $examId));
    foreach($notify->fetchAll(PDO::FETCH_OBJ) as $row) {
        if($row->ClassTeacherId) {
            timetable_notify_user($dbh, $row->ClassTeacherId, $row->ClassId, 'Exam Timetable Published', 'The examination timetable for your class has been published.');
        }
    }

    $teachers = $dbh->prepare("SELECT DISTINCT InvigilatorId FROM tblexamtimetableentries WHERE ExamId = :examid AND InvigilatorId IS NOT NULL");
    $teachers->execute(array(':examid' => $examId));
    foreach($teachers->fetchAll(PDO::FETCH_OBJ) as $teacher) {
        timetable_notify_user($dbh, $teacher->InvigilatorId, null, 'Invigilation Timetable Published', 'An exam timetable session has been assigned to you.');
    }

    audit_log($dbh, 'exam_timetable_published', 'tblexams', $examId, 'Published by Dean of Studies');
    $msg = "Exam timetable published and notifications generated.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Exam Timetable | SRMS</title>
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
<div class="row page-title-div"><div class="col-md-8"><h2 class="title">Examination Timetable</h2></div></div>
<section class="section">
<?php if($msg){?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php } ?>
<?php if($error){?><div class="alert alert-danger"><?php echo htmlentities($error); ?></div><?php } ?>
<div class="row">
<div class="col-md-4"><div class="panel"><div class="panel-heading"><h5>Create Exam Session</h5></div><div class="panel-body">
<form method="post">
<?php csrf_field(); ?>
<div class="form-group"><label>Exam</label><select name="examid" class="form-control" required><option value="">Select Exam</option><?php $q=$dbh->query("SELECT id,ExamName FROM tblexams ORDER BY id DESC"); foreach($q->fetchAll(PDO::FETCH_OBJ) as $e){echo '<option value="'.htmlentities($e->id).'">'.htmlentities($e->ExamName).'</option>';} ?></select></div>
<div class="form-group"><label>Class</label><select name="classid" class="form-control" required><option value="">Select Class</option><?php $q=$dbh->query("SELECT id,ClassName,Section FROM tblclasses ORDER BY ClassNameNumeric,Section"); foreach($q->fetchAll(PDO::FETCH_OBJ) as $c){echo '<option value="'.htmlentities($c->id).'">'.htmlentities($c->ClassName.' Section-'.$c->Section).'</option>';} ?></select></div>
<div class="form-group"><label>Subject</label><select name="subjectid" class="form-control" required><option value="">Select Subject</option><?php $q=$dbh->query("SELECT id,SubjectName FROM tblsubjects ORDER BY SubjectName"); foreach($q->fetchAll(PDO::FETCH_OBJ) as $s){echo '<option value="'.htmlentities($s->id).'">'.htmlentities($s->SubjectName).'</option>';} ?></select></div>
<div class="form-group"><label>Room</label><select name="roomid" class="form-control"><option value="">No room</option><?php $q=$dbh->query("SELECT id,RoomName FROM tblrooms WHERE Status=1 ORDER BY RoomName"); foreach($q->fetchAll(PDO::FETCH_OBJ) as $r){echo '<option value="'.htmlentities($r->id).'">'.htmlentities($r->RoomName).'</option>';} ?></select></div>
<div class="form-group"><label>Invigilator</label><select name="invigilatorid" class="form-control"><option value="">No invigilator</option><?php $q=$dbh->query("SELECT id,FullName FROM tblusers WHERE Role IN ('class_teacher','subject_teacher','head_of_department','exams_officer','deputy_dean') AND Status=1 ORDER BY FullName"); foreach($q->fetchAll(PDO::FETCH_OBJ) as $t){echo '<option value="'.htmlentities($t->id).'">'.htmlentities($t->FullName).'</option>';} ?></select></div>
<div class="form-group"><label>Date</label><input type="date" name="examdate" class="form-control" required></div>
<div class="form-group"><label>Start</label><input type="time" name="starttime" class="form-control" required></div>
<div class="form-group"><label>End</label><input type="time" name="endtime" class="form-control" required></div>
<div class="form-group"><label>Status</label><select name="status" class="form-control"><option value="draft">Draft</option><option value="ready_for_review">Ready For Review</option><option value="approved">Approved</option></select></div>
<div class="form-group"><label>Reason / Note</label><input type="text" name="reason" class="form-control"></div>
<button type="submit" name="save_exam_session" class="btn btn-primary">Save Session</button>
</form>
</div></div></div>
<div class="col-md-8"><div class="panel"><div class="panel-heading"><h5>Exam Sessions</h5></div><div class="panel-body">
<table id="example" class="display table table-striped table-bordered">
<thead><tr><th>Exam</th><th>Date</th><th>Time</th><th>Class</th><th>Subject</th><th>Room</th><th>Invigilator</th><th>Status</th></tr></thead><tbody>
<?php
$sql="SELECT e.ExamName, et.ExamDate, et.StartTime, et.EndTime, c.ClassName,c.Section,s.SubjectName,r.RoomName,u.FullName,et.Status FROM tblexamtimetableentries et JOIN tblexams e ON e.id=et.ExamId JOIN tblclasses c ON c.id=et.ClassId JOIN tblsubjects s ON s.id=et.SubjectId LEFT JOIN tblrooms r ON r.id=et.RoomId LEFT JOIN tblusers u ON u.id=et.InvigilatorId ORDER BY et.ExamDate, et.StartTime";
$q=$dbh->prepare($sql); $q->execute(); foreach($q->fetchAll(PDO::FETCH_OBJ) as $e){echo '<tr><td>'.htmlentities($e->ExamName).'</td><td>'.htmlentities($e->ExamDate).'</td><td>'.htmlentities($e->StartTime.' - '.$e->EndTime).'</td><td>'.htmlentities($e->ClassName.' Section-'.$e->Section).'</td><td>'.htmlentities($e->SubjectName).'</td><td>'.htmlentities($e->RoomName).'</td><td>'.htmlentities($e->FullName).'</td><td>'.htmlentities(str_replace('_',' ',$e->Status)).'</td></tr>';}
?>
</tbody></table>
<form method="post" class="form-inline">
<?php csrf_field(); ?>
<select name="examid" class="form-control" required><option value="">Publish Exam Timetable</option><?php $q=$dbh->query("SELECT id,ExamName FROM tblexams ORDER BY id DESC"); foreach($q->fetchAll(PDO::FETCH_OBJ) as $e){echo '<option value="'.htmlentities($e->id).'">'.htmlentities($e->ExamName).'</option>';} ?></select>
<button type="submit" name="publish_exam" class="btn btn-success">Publish Exam Timetable</button>
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
