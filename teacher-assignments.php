<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/dean-auth.php');
require_dean();
require_once 'includes/academic-teacher-summary.php';

$teacherRoles = array('class_teacher','subject_teacher','head_of_department','exams_officer','deputy_dean');
$roleSql = "'" . implode("','", $teacherRoles) . "'";
$teacherId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($teacherId <= 0) {
    $sql = "SELECT u.id, u.FullName, u.StaffNumber, u.Department, u.Role, u.Status, c.ClassName, c.Section,
            (SELECT COUNT(*) FROM tblclasstimetableentries t WHERE t.TeacherId = u.id AND t.Status <> 'cancelled') AS LessonCount,
            (SELECT COUNT(*) FROM tblexamtimetableentries e WHERE (e.InvigilatorId=u.id OR EXISTS(SELECT 1 FROM tblexaminvigilators i WHERE i.SessionId=e.id AND i.TeacherId=u.id)) AND e.Status NOT IN ('cancelled','archived')) AS ExamDutyCount
            FROM tblusers u
            LEFT JOIN tblclassteacherassignments ca ON ca.TeacherId=u.id AND ca.Status=1 AND ca.AcademicYearId=(SELECT id FROM tblacademicyears WHERE IsActive=1 ORDER BY id DESC LIMIT 1)
            LEFT JOIN tblclasses c ON c.id=ca.ClassId
            WHERE u.Role IN ($roleSql)
            ORDER BY u.FullName";
    $query = $dbh->prepare($sql);
    $query->execute();
    $teachers = $query->fetchAll(PDO::FETCH_OBJ);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teacher Assignments | SRMS</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen"><link rel="stylesheet" href="css/font-awesome.min.css" media="screen"><link rel="stylesheet" href="css/main.css" media="screen"><link rel="stylesheet" href="css/custom.css"><link rel="stylesheet" href="css/custom.css">
</head>
<body class="top-navbar-fixed">
<div class="main-wrapper">
<?php include('includes/topbar.php');?>
<div class="content-wrapper"><div class="content-container">
<?php include('includes/leftbar.php');?>
<div class="main-page"><div class="container-fluid">
<div class="row page-title-div"><div class="col-md-8"><h2 class="title">Teacher Assignments</h2></div></div>
<section class="section"><p><a class="btn btn-primary" href="dean-teacher-relationships.php?teacher=<?php echo (int)$teacherId; ?>">Manage Academic Assignments and Responsibilities</a></p>
<div class="panel"><div class="panel-heading"><h5>Assignment Summary</h5></div><div class="panel-body">
<table class="table table-striped table-bordered">
    <thead><tr><th>Teacher</th><th>Staff Number</th><th>Department</th><th>Academic Roles and Responsibilities</th><th>Class Teacher Of</th><th>Lessons/Week</th><th>Exam Duties</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach($teachers as $teacher) { ?>
        <tr>
            <td><?php echo htmlentities($teacher->FullName); ?></td>
            <td><?php echo htmlentities($teacher->StaffNumber); ?></td>
            <td><?php echo htmlentities($teacher->Department); ?></td>
            <td><?php academic_teacher_summary($dbh,(int)$teacher->id); ?></td>
            <td><?php echo $teacher->ClassName ? htmlentities($teacher->ClassName . ' Section-' . $teacher->Section) : '-'; ?></td>
            <td><?php echo htmlentities($teacher->LessonCount); ?></td>
            <td><?php echo htmlentities($teacher->ExamDutyCount); ?></td>
            <td><?php echo $teacher->Status ? 'ACTIVE' : 'INACTIVE'; ?></td>
            <td><a href="teacher-assignments.php?id=<?php echo htmlentities($teacher->id); ?>" class="btn btn-xs btn-primary">View Details</a></td>
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
<?php
    exit;
}

$teacherQuery = $dbh->prepare("SELECT u.*, c.ClassName, c.Section
                               FROM tblusers u
                               LEFT JOIN tblclassteacherassignments ca ON ca.TeacherId=u.id AND ca.Status=1 AND ca.AcademicYearId=(SELECT id FROM tblacademicyears WHERE IsActive=1 ORDER BY id DESC LIMIT 1)
            LEFT JOIN tblclasses c ON c.id=ca.ClassId
                               WHERE u.id = :teacherid AND u.Role IN ($roleSql)
                               LIMIT 1");
$teacherQuery->execute(array(':teacherid' => $teacherId));
$teacher = $teacherQuery->fetch(PDO::FETCH_OBJ);
if(!$teacher) {
    header("Location: manage-teachers.php");
    exit;
}

$lessons = $dbh->prepare("SELECT t.*, ay.AcademicYear, tr.TermName, c.ClassName, c.Section, s.SubjectName, r.RoomName
                          FROM tblclasstimetableentries t
                          JOIN tblacademicyears ay ON ay.id = t.AcademicYearId
                          JOIN tblterms tr ON tr.id = t.TermId
                          JOIN tblclasses c ON c.id = t.ClassId
                          JOIN tblsubjects s ON s.id = t.SubjectId
                          LEFT JOIN tblrooms r ON r.id = t.RoomId
                          WHERE t.TeacherId = :teacherid AND t.Status <> 'cancelled'
                          ORDER BY ay.AcademicYear DESC, tr.TermName, FIELD(t.DayOfWeek,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), t.StartTime");
$lessons->execute(array(':teacherid' => $teacherId));
$lessonRows = $lessons->fetchAll(PDO::FETCH_OBJ);

$examDuties = $dbh->prepare("SELECT e.*, ex.ExamName, c.ClassName, c.Section, s.SubjectName, r.RoomName
                             FROM tblexamtimetableentries e
                             JOIN tblexams ex ON ex.id = e.ExamId
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
    <title>Teacher Assignments | SRMS</title>
    
    
    
    
</head>
<body class="top-navbar-fixed">
<div class="main-wrapper">
<?php include('includes/topbar.php');?>
<div class="content-wrapper"><div class="content-container">
<?php include('includes/leftbar.php');?>
<div class="main-page"><div class="container-fluid">
<div class="row page-title-div">
    <div class="col-md-8"><h2 class="title">Teacher Assignments</h2><p><?php echo htmlentities($teacher->FullName); ?></p></div>
    <div class="col-md-4 text-right"><a href="view-teacher.php?id=<?php echo htmlentities($teacher->id); ?>" class="btn btn-default">View Profile</a></div>
</div>
<section class="section"><p><a class="btn btn-primary" href="dean-teacher-relationships.php?teacher=<?php echo (int)$teacherId; ?>">Manage Academic Assignments and Responsibilities</a></p>
<div class="panel"><div class="panel-heading"><h5>Class Teacher Assignment</h5></div><div class="panel-body">
    <p><?php echo $teacher->ClassName ? htmlentities($teacher->ClassName . ' Section-' . $teacher->Section) : 'No class-teacher assignment.'; ?></p>
</div></div>
<div class="panel"><div class="panel-heading"><h5>Timetable Lessons</h5></div><div class="panel-body">
<?php if($lessonRows) { ?><table class="table table-striped table-bordered"><thead><tr><th>Academic Period</th><th>Day</th><th>Time</th><th>Class</th><th>Subject</th><th>Room</th><th>Status</th></tr></thead><tbody><?php foreach($lessonRows as $lesson) { ?><tr><td><?php echo htmlentities($lesson->AcademicYear . ' - ' . $lesson->TermName); ?></td><td><?php echo htmlentities($lesson->DayOfWeek); ?></td><td><?php echo htmlentities($lesson->StartTime . ' - ' . $lesson->EndTime); ?></td><td><?php echo htmlentities($lesson->ClassName . ' Section-' . $lesson->Section); ?></td><td><?php echo htmlentities($lesson->SubjectName); ?></td><td><?php echo htmlentities($lesson->RoomName); ?></td><td><?php echo htmlentities(str_replace('_', ' ', $lesson->Status)); ?></td></tr><?php } ?></tbody></table><?php } else { ?><p class="text-muted">No timetable lessons assigned.</p><?php } ?>
</div></div>
<div class="panel"><div class="panel-heading"><h5>Examination Duties</h5></div><div class="panel-body">
<?php if($examRows) { ?><table class="table table-striped table-bordered"><thead><tr><th>Exam</th><th>Date</th><th>Time</th><th>Class</th><th>Subject</th><th>Room</th><th>Status</th></tr></thead><tbody><?php foreach($examRows as $exam) { ?><tr><td><?php echo htmlentities($exam->ExamName); ?></td><td><?php echo htmlentities($exam->ExamDate); ?></td><td><?php echo htmlentities($exam->StartTime . ' - ' . $exam->EndTime); ?></td><td><?php echo htmlentities($exam->ClassName . ' Section-' . $exam->Section); ?></td><td><?php echo htmlentities($exam->SubjectName); ?></td><td><?php echo htmlentities($exam->RoomName); ?></td><td><?php echo htmlentities(str_replace('_', ' ', $exam->Status)); ?></td></tr><?php } ?></tbody></table><?php } else { ?><p class="text-muted">No examination duties assigned.</p><?php } ?>
</div></div>
</section>
</div></div></div></div></div>
<script src="js/jquery/jquery-2.2.4.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
