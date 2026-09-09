<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/dean-auth.php');
require_dean();

$teacherRoles = array(
    'class_teacher' => 'Class Teacher',
    'subject_teacher' => 'Subject Teacher',
    'head_of_department' => 'Head of Department',
    'exams_officer' => 'Exams Officer',
    'deputy_dean' => 'Deputy Dean'
);
$roleSql = "'" . implode("','", array_keys($teacherRoles)) . "'";
$teacherId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$teacherQuery = $dbh->prepare("SELECT u.*, c.ClassName, c.Section
                               FROM tblusers u
                               LEFT JOIN tblclasses c ON c.id = u.ClassId
                               WHERE u.id = :teacherid AND u.Role IN ($roleSql)
                               LIMIT 1");
$teacherQuery->execute(array(':teacherid' => $teacherId));
$teacher = $teacherQuery->fetch(PDO::FETCH_OBJ);
if(!$teacher) {
    header("Location: manage-teachers.php");
    exit;
}

$lessons = $dbh->prepare("SELECT t.DayOfWeek, t.StartTime, t.EndTime, c.ClassName, c.Section, s.SubjectName, r.RoomName
                          FROM tblclasstimetableentries t
                          JOIN tblclasses c ON c.id = t.ClassId
                          JOIN tblsubjects s ON s.id = t.SubjectId
                          LEFT JOIN tblrooms r ON r.id = t.RoomId
                          WHERE t.TeacherId = :teacherid AND t.Status <> 'cancelled'
                          ORDER BY FIELD(t.DayOfWeek,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), t.StartTime");
$lessons->execute(array(':teacherid' => $teacherId));
$lessonRows = $lessons->fetchAll(PDO::FETCH_OBJ);

$examDuties = $dbh->prepare("SELECT e.ExamDate, e.StartTime, e.EndTime, c.ClassName, c.Section, s.SubjectName, r.RoomName
                             FROM tblexamtimetableentries e
                             JOIN tblclasses c ON c.id = e.ClassId
                             JOIN tblsubjects s ON s.id = e.SubjectId
                             LEFT JOIN tblrooms r ON r.id = e.RoomId
                             WHERE (e.InvigilatorId = :teacherid OR EXISTS(SELECT 1 FROM tblexaminvigilators i WHERE i.SessionId=e.id AND i.TeacherId=:memberid)) AND e.Status NOT IN ('cancelled','archived')
                             ORDER BY e.ExamDate, e.StartTime");
$examDuties->execute(array(':teacherid' => $teacherId, ':memberid' => $teacherId));
$examRows = $examDuties->fetchAll(PDO::FETCH_OBJ);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teacher Profile | SRMS</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <link rel="stylesheet" href="css/custom.css">
</head>
<body class="top-navbar-fixed">
<div class="main-wrapper">
<?php include('includes/topbar.php');?>
<div class="content-wrapper"><div class="content-container">
<?php include('includes/leftbar.php');?>
<div class="main-page"><div class="container-fluid">
<div class="row page-title-div">
    <div class="col-md-8"><h2 class="title">Teacher Profile</h2></div>
    <div class="col-md-4 text-right"><a href="edit-teacher.php?id=<?php echo htmlentities($teacher->id); ?>" class="btn btn-primary">Edit Teacher</a></div>
</div>
<section class="section">
<?php require_once 'includes/academic-teacher-summary.php'; if(academic_ready($dbh)) { ?><div class="panel panel-body"><h4>Academic relationships — active academic year</h4><?php academic_teacher_summary($dbh,(int)$teacher->id,null,true); ?><a href="dean-teacher-relationships.php?teacher=<?= (int)$teacher->id ?>">Change assignments / view history and other years</a></div><?php } ?>
<div class="row">
    <div class="col-md-6"><div class="panel"><div class="panel-heading"><h5>Account Details</h5></div><div class="panel-body">
        <p><strong>Name:</strong> <?php echo htmlentities($teacher->FullName); ?></p>
        <p><strong>Staff Number:</strong> <?php echo htmlentities($teacher->StaffNumber); ?></p>
        <p><strong>Email:</strong> <?php echo htmlentities($teacher->Email); ?></p>
        <p><strong>Phone:</strong> <?php echo htmlentities($teacher->PhoneNumber); ?></p>
        <p><strong>Username:</strong> <?php echo htmlentities($teacher->Username); ?></p>
        <p><strong>Department:</strong> <?php echo htmlentities($teacher->Department); ?></p>
        <p><strong>Account category:</strong> <?php echo htmlentities($teacherRoles[$teacher->Role] ?? $teacher->Role); ?></p>
        <p><strong>Status:</strong> <?php echo $teacher->Status ? 'ACTIVE' : 'INACTIVE'; ?></p>
        <p><strong>Joined:</strong> <?php echo htmlentities($teacher->CreationDate); ?></p>
        <p><strong>Last Login:</strong> <?php echo $teacher->LastLoginAt ? htmlentities($teacher->LastLoginAt) : 'Never Logged In'; ?></p>
    </div></div></div>
    <div class="col-md-6"><div class="panel"><div class="panel-heading"><h5>Assignment Summary</h5></div><div class="panel-body">
        <p><strong>Timetable Lessons:</strong> <?php echo count($lessonRows); ?></p>
        <p><strong>Exam Duties:</strong> <?php echo count($examRows); ?></p>
        <p><a href="dean-teacher-relationships.php?teacher=<?php echo htmlentities($teacher->id); ?>" class="btn btn-info">View Assignments</a></p>
    </div></div></div>
</div>
<div class="panel"><div class="panel-heading"><h5>Current Timetable Lessons</h5></div><div class="panel-body">
<?php if($lessonRows) { ?><table class="table table-striped table-bordered"><thead><tr><th>Day</th><th>Time</th><th>Class</th><th>Subject</th><th>Room</th></tr></thead><tbody><?php foreach($lessonRows as $lesson) { ?><tr><td><?php echo htmlentities($lesson->DayOfWeek); ?></td><td><?php echo htmlentities($lesson->StartTime . ' - ' . $lesson->EndTime); ?></td><td><?php echo htmlentities($lesson->ClassName . ' Section-' . $lesson->Section); ?></td><td><?php echo htmlentities($lesson->SubjectName); ?></td><td><?php echo htmlentities($lesson->RoomName); ?></td></tr><?php } ?></tbody></table><?php } else { ?><p class="text-muted">No timetable lessons assigned.</p><?php } ?>
</div></div>
</section>
</div></div></div></div></div>
<script src="js/jquery/jquery-2.2.4.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
