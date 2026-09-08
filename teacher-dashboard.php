<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/teacher-auth.php');
require_class_teacher();

$classId = teacher_class_id();
$classQuery = $dbh->prepare("SELECT ClassName, Section FROM tblclasses WHERE id = :classid");
$classQuery->bindParam(':classid', $classId, PDO::PARAM_STR);
$classQuery->execute();
$class = $classQuery->fetch(PDO::FETCH_OBJ);

$students = $dbh->prepare("SELECT COUNT(*) FROM tblstudents WHERE ClassId = :classid");
$students->execute(array(':classid' => $classId));
$totalStudents = $students->fetchColumn();

$subjects = $dbh->prepare("SELECT COUNT(*) FROM tblsubjectcombination WHERE ClassId = :classid AND status = 1");
$subjects->execute(array(':classid' => $classId));
$totalSubjects = $subjects->fetchColumn();

$submitted = $dbh->prepare("SELECT COUNT(DISTINCT CONCAT(StudentId, '-', COALESCE(ExamId, 0))) FROM tblresult WHERE ClassId = :classid");
$submitted->execute(array(':classid' => $classId));
$submittedResults = $submitted->fetchColumn();

$avg = $dbh->prepare("SELECT ROUND(AVG(marks), 2) FROM tblresult WHERE ClassId = :classid");
$avg->execute(array(':classid' => $classId));
$classAverage = $avg->fetchColumn();

$attendance = $dbh->prepare("SELECT ROUND(100 * SUM(Status IN ('present','late')) / COUNT(*), 2) FROM tblattendance WHERE ClassId = :classid");
$attendance->execute(array(':classid' => $classId));
$attendancePercent = $attendance->fetchColumn();

$pendingResults = max(0, ((int)$totalStudents) - ((int)$submittedResults));
$nextLessonQuery = $dbh->prepare("SELECT e.DayOfWeek, e.StartTime, e.EndTime, s.SubjectName, u.FullName
                                  FROM tblclasstimetableentries e
                                  JOIN tblsubjects s ON s.id = e.SubjectId
                                  LEFT JOIN tblusers u ON u.id = e.TeacherId
                                  WHERE e.ClassId = :classid AND e.Status = 'published'
                                  ORDER BY FIELD(e.DayOfWeek,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), e.StartTime
                                  LIMIT 1");
$nextLessonQuery->execute(array(':classid' => $classId));
$nextLesson = $nextLessonQuery->fetch(PDO::FETCH_OBJ);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Class Teacher Dashboard | SRMS</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <link rel="stylesheet" href="css/custom.css" media="screen">
    <script src="js/modernizr/modernizr.min.js"></script>
</head>
<body class="top-navbar-fixed">
<div class="main-wrapper">
    <?php include('includes/teacher-topbar.php');?>
    <div class="content-wrapper">
        <div class="content-container">
            <?php include('includes/teacher-leftbar.php');?>
            <div class="main-page">
                <div class="container-fluid">
                    <div class="row page-title-div">
                        <div class="col-md-8">
                            <h2 class="title">Class Teacher Dashboard</h2>
                            <p class="text-muted">Assigned Class: <?php echo $class ? htmlentities($class->ClassName . ' Section-' . $class->Section) : 'Not assigned'; ?></p>
                        </div>
                    </div>
                    <section class="section">
                        <div class="row">
                            <div class="col-md-3 col-sm-6"><a class="dashboard-stat bg-primary" href="teacher-students.php"><span class="number"><?php echo htmlentities($totalStudents); ?></span><span class="name">Total Students</span><span class="bg-icon"><i class="fa fa-users"></i></span></a></div>
                            <div class="col-md-3 col-sm-6"><a class="dashboard-stat bg-success" href="teacher-results.php"><span class="number"><?php echo htmlentities($classAverage ?: '0'); ?>%</span><span class="name">Class Average</span><span class="bg-icon"><i class="fa fa-line-chart"></i></span></a></div>
                            <div class="col-md-3 col-sm-6"><a class="dashboard-stat bg-warning" href="teacher-results.php"><span class="number"><?php echo htmlentities($submittedResults); ?></span><span class="name">Results Submitted</span><span class="bg-icon"><i class="fa fa-check-square-o"></i></span></a></div>
                            <div class="col-md-3 col-sm-6"><a class="dashboard-stat bg-danger" href="teacher-attendance.php"><span class="number"><?php echo htmlentities($attendancePercent ?: '0'); ?>%</span><span class="name">Attendance</span><span class="bg-icon"><i class="fa fa-calendar-check-o"></i></span></a></div>
                        </div>
                        <div class="row action-top-md">
                            <div class="col-md-4">
                                <div class="panel"><div class="panel-heading"><h5>Class Summary</h5></div><div class="panel-body">
                                    <p><strong>Subjects:</strong> <?php echo htmlentities($totalSubjects); ?></p>
                                    <p><strong>Pending Results:</strong> <?php echo htmlentities($pendingResults); ?></p>
                                    <p><strong>Review Status:</strong> Open for review</p>
                                </div></div>
                            </div>
                            <div class="col-md-8">
                                <div class="panel"><div class="panel-heading"><h5>Quick Actions</h5></div><div class="panel-body">
                                    <a class="btn btn-primary" href="teacher-students.php"><i class="fa fa-users"></i> View Students</a>
                                    <a class="btn btn-info" href="teacher-accounts.php"><i class="fa fa-user-plus"></i> Manage Accounts</a>
                                    <a class="btn btn-success" href="teacher-results.php"><i class="fa fa-check-square-o"></i> View Results</a>
                                    <a class="btn btn-warning" href="teacher-attendance.php"><i class="fa fa-calendar"></i> Attendance</a>
                                    <a class="btn btn-info" href="teacher-timetable.php"><i class="fa fa-table"></i> Timetable</a>
                                    <a class="btn btn-default" href="teacher-report-cards.php"><i class="fa fa-file-text"></i> Report Cards</a>
                                </div></div>
                            </div>
                        </div>
                        <div class="panel">
                            <div class="panel-heading"><h5>My Class Timetable</h5></div>
                            <div class="panel-body">
                                <?php if($nextLesson) { ?>
                                <p><strong>Next Scheduled Lesson:</strong> <?php echo htmlentities($nextLesson->DayOfWeek . ' ' . $nextLesson->StartTime . ' - ' . $nextLesson->EndTime); ?></p>
                                <p><strong>Subject:</strong> <?php echo htmlentities($nextLesson->SubjectName); ?></p>
                                <p><strong>Teacher:</strong> <?php echo htmlentities($nextLesson->FullName ?: 'Not assigned'); ?></p>
                                <?php } else { ?>
                                <p class="text-muted">No published timetable yet.</p>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="panel">
                            <div class="panel-heading"><h5>Recent Notifications</h5></div>
                            <div class="panel-body">
                                <?php
                                $noticeSql = "SELECT Title, Message, CreationDate FROM tblteachernotifications WHERE (TeacherId = :teacherid OR ClassId = :classid) ORDER BY CreationDate DESC LIMIT 5";
                                $noticeQuery = $dbh->prepare($noticeSql);
                                $noticeQuery->execute(array(':teacherid' => teacher_id(), ':classid' => $classId));
                                $notes = $noticeQuery->fetchAll(PDO::FETCH_OBJ);
                                if($notes) {
                                    foreach($notes as $note) {
                                        echo '<p><strong>' . htmlentities($note->Title) . '</strong> - ' . htmlentities($note->Message) . ' <small>' . htmlentities($note->CreationDate) . '</small></p>';
                                    }
                                } else {
                                    echo '<p class="text-muted">No notifications yet.</p>';
                                }
                                ?>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="js/jquery/jquery-2.2.4.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
