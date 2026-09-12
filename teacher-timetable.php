<?php
require_once 'includes/bootstrap.php';
$error=$msg='';

require_once 'includes/config.php';
require_once 'includes/csrf.php';
require_once 'includes/audit.php';
require_once 'includes/teacher-auth.php';
include('includes/timetable.php');
require_class_teacher();

$classId = teacher_class_id();
$activePeriod = timetable_active_period($dbh);
$periodParams = [':classid'=>$classId, ':yearid'=>$activePeriod->AcademicYearId??0, ':termid'=>$activePeriod->TermId??0];

if(isset($_POST['notify_teachers'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    $sql = "SELECT e.TeacherId, e.DayOfWeek, e.StartTime, e.EndTime, c.ClassName, c.Section, s.SubjectName, r.RoomName
            FROM tblclasstimetableentries e
            JOIN tblclasses c ON c.id = e.ClassId
            JOIN tblsubjects s ON s.id = e.SubjectId
            LEFT JOIN tblrooms r ON r.id = e.RoomId
            WHERE e.ClassId = :classid
              AND e.Status = 'published' AND e.AcademicYearId = :yearid AND e.TermId = :termid
              AND e.TeacherId IS NOT NULL
            ORDER BY e.TeacherId, FIELD(e.DayOfWeek,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), e.StartTime";
    $query = $dbh->prepare($sql);
    $query->execute($periodParams);
    $sent = 0;
    foreach($query->fetchAll(PDO::FETCH_OBJ) as $lesson) {
        $message = $lesson->SubjectName . " with " . $lesson->ClassName . " Section-" . $lesson->Section . " on " . $lesson->DayOfWeek . " from " . $lesson->StartTime . " to " . $lesson->EndTime . ". Room: " . ($lesson->RoomName ?: 'Not assigned');
        timetable_notify_user($dbh, $lesson->TeacherId, $classId, 'Class Timetable Reminder', $message);
        $sent++;
    }
    audit_log($dbh, 'class_teacher_notified_subject_teachers', 'tblclasses', $classId, 'Notifications generated: ' . $sent);
    $msg = "Subject teacher notifications generated.";
}

$classQuery = $dbh->prepare("SELECT ClassName, Section FROM tblclasses WHERE id = :classid");
$classQuery->execute(array(':classid' => $classId));
$class = $classQuery->fetch(PDO::FETCH_OBJ);
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
    <link rel="stylesheet" href="css/custom.css">
</head>
<body class="top-navbar-fixed">
<div class="main-wrapper">
<?php include('includes/teacher-topbar.php');?>
<div class="content-wrapper"><div class="content-container">
<?php include('includes/teacher-leftbar.php');?>
<div class="main-page"><div class="container-fluid">
<div class="row page-title-div"><div class="col-md-8"><h2 class="title">Timetable</h2><p class="text-muted"><?php echo $class ? htmlentities($class->ClassName . ' Section-' . $class->Section) : 'Assigned class'; ?></p></div></div>
<section class="section">
<?php if($msg){?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php } ?>
<div class="panel"><div class="panel-heading"><h5>Published Class Timetable</h5></div><div class="panel-body">
<form method="post" class="form-bottom-md">
    <?php csrf_field(); ?>
    <button type="submit" name="notify_teachers" class="btn btn-primary"><i class="fa fa-bell"></i> Notify Subject Teachers</button>
</form>
<table id="example" class="display table table-striped table-bordered">
<thead><tr><th>Day</th><th>Time</th><th>Subject</th><th>Teacher</th><th>Room</th></tr></thead><tbody>
<?php
$sql = "SELECT e.DayOfWeek, e.StartTime, e.EndTime, s.SubjectName, u.FullName, r.RoomName
        FROM tblclasstimetableentries e
        JOIN tblsubjects s ON s.id = e.SubjectId
        LEFT JOIN tblusers u ON u.id = e.TeacherId
        LEFT JOIN tblrooms r ON r.id = e.RoomId
        WHERE e.ClassId = :classid AND e.Status = 'published' AND e.AcademicYearId = :yearid AND e.TermId = :termid
        ORDER BY FIELD(e.DayOfWeek,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), e.StartTime";
$query = $dbh->prepare($sql);
$query->execute($periodParams);
foreach($query->fetchAll(PDO::FETCH_OBJ) as $lesson) {
    echo '<tr><td>'.htmlentities($lesson->DayOfWeek).'</td><td>'.htmlentities($lesson->StartTime.' - '.$lesson->EndTime).'</td><td>'.htmlentities($lesson->SubjectName).'</td><td>'.htmlentities($lesson->FullName).'</td><td>'.htmlentities($lesson->RoomName).'</td></tr>';
}
?>
</tbody></table>
</div></div>
<div class="panel"><div class="panel-heading"><h5>Published Examination Timetable</h5></div><div class="panel-body">
<table id="examtable" class="display table table-striped table-bordered">
<thead><tr><th>Exam</th><th>Date</th><th>Time</th><th>Subject</th><th>Room</th><th>Invigilators</th></tr></thead><tbody>
<?php
$sql = "SELECT ex.ExamName, et.ExamDate, et.StartTime, et.EndTime, s.SubjectName, r.RoomName, (SELECT GROUP_CONCAT(u.FullName ORDER BY u.FullName SEPARATOR \", \") FROM tblusers u WHERE u.id=et.InvigilatorId OR EXISTS(SELECT 1 FROM tblexaminvigilators i WHERE i.SessionId=et.id AND i.TeacherId=u.id)) AS FullName
        FROM tblexamtimetableentries et
        JOIN tblexams ex ON ex.id = et.ExamId
        JOIN tblsubjects s ON s.id = et.SubjectId
        LEFT JOIN tblrooms r ON r.id = et.RoomId
        WHERE et.ClassId = :classid AND et.Status = 'published' AND ex.AcademicYearId = :yearid AND ex.TermId = :termid
        ORDER BY et.ExamDate, et.StartTime";
$query = $dbh->prepare($sql);
$query->execute($periodParams);
foreach($query->fetchAll(PDO::FETCH_OBJ) as $exam) {
    echo '<tr><td>'.htmlentities($exam->ExamName).'</td><td>'.htmlentities($exam->ExamDate).'</td><td>'.htmlentities($exam->StartTime.' - '.$exam->EndTime).'</td><td>'.htmlentities($exam->SubjectName).'</td><td>'.htmlentities($exam->RoomName).'</td><td>'.htmlentities($exam->FullName).'</td></tr>';
}
?>
</tbody></table>
</div></div>
</section>
</div></div></div></div></div>
<script src="js/jquery/jquery-3.7.1.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/DataTables/datatables.min.js"></script>
<script src="js/main.js"></script>
<script>$(function($){ $('#example').DataTable(); $('#examtable').DataTable(); });</script>
</body>
</html>
