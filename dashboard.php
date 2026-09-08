<?php
session_start();
error_reporting(0);
include('includes/config.php');
if(strlen($_SESSION['alogin'])=="") {   
    header("Location: index.php"); 
} else {
$totalStudents = $dbh->query("SELECT COUNT(*) FROM tblstudents")->fetchColumn();
$totalSubjects = $dbh->query("SELECT COUNT(*) FROM tblsubjects")->fetchColumn();
$totalClasses = $dbh->query("SELECT COUNT(*) FROM tblclasses")->fetchColumn();
$teacherRoleSql = "'class_teacher','subject_teacher','head_of_department','exams_officer','deputy_dean'";
$totalTeachers = $dbh->query("SELECT COUNT(*) FROM tblusers WHERE Role IN ($teacherRoleSql)")->fetchColumn();
$activeTeachers = $dbh->query("SELECT COUNT(*) FROM tblusers WHERE Role IN ($teacherRoleSql) AND Status = 1")->fetchColumn();
$inactiveTeachers = $dbh->query("SELECT COUNT(*) FROM tblusers WHERE Role IN ($teacherRoleSql) AND Status = 0")->fetchColumn();
$activeExamCount = $dbh->query("SELECT COUNT(*) FROM tblexams WHERE Status IN ('marks_entry','submitted','under_review','approved')")->fetchColumn();
$resultsSubmitted = $dbh->query("SELECT COUNT(DISTINCT CONCAT(StudentId, '-', COALESCE(ExamId, 0))) FROM tblresult")->fetchColumn();
$classesAwaitingApproval = $dbh->query("SELECT COUNT(*) FROM tblresultreviews WHERE Status = 'approved'")->fetchColumn();
$schoolMean = $dbh->query("SELECT ROUND(AVG(marks), 2) FROM tblresult")->fetchColumn();
$classesScheduled = $dbh->query("SELECT COUNT(DISTINCT ClassId) FROM tblclasstimetableentries WHERE Status = 'published'")->fetchColumn();
$timetableConflicts = 0;
$examTimetableStatusQuery = $dbh->query("SELECT Status FROM tblexamtimetableentries ORDER BY id DESC LIMIT 1");
$examTimetableStatus = $examTimetableStatusQuery ? $examTimetableStatusQuery->fetchColumn() : null;
$activePeriodQuery = $dbh->prepare("SELECT ay.AcademicYear, t.TermName FROM tblterms t JOIN tblacademicyears ay ON ay.id = t.AcademicYearId WHERE t.IsActive = 1 LIMIT 1");
$activePeriodQuery->execute();
$activePeriod = $activePeriodQuery->fetch(PDO::FETCH_OBJ);
$pendingResults = max(0, ((int)$totalStudents) - ((int)$resultsSubmitted));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dean of Studies Dashboard | SRMS</title>

    <!-- CSS FILES -->
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" href="css/animate-css/animate.min.css" media="screen">
    <link rel="stylesheet" href="css/lobipanel/lobipanel.min.css" media="screen">
    <link rel="stylesheet" href="css/toastr/toastr.min.css" media="screen">
    <link rel="stylesheet" href="css/icheck/skins/line/blue.css">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <link rel="stylesheet" href="css/custom.css" media="screen">
    <script src="js/modernizr/modernizr.min.js"></script>
</head>

<body class="top-navbar-fixed dashboard-page">
    <div class="main-wrapper">
        <?php include('includes/topbar.php');?>
        <div class="content-wrapper">
            <div class="content-container">

                <?php include('includes/leftbar.php');?>

                <div class="main-page">
                    <div class="container-fluid">
                        <div class="row page-title-div">
                            <div class="col-sm-6">
                                <h2 class="title text-white">Dean of Studies Dashboard</h2>
                                <p class="text-white">Academic Year: <?php echo $activePeriod ? htmlentities($activePeriod->AcademicYear) : 'Not set'; ?> | Term: <?php echo $activePeriod ? htmlentities($activePeriod->TermName) : 'Not set'; ?></p>
                            </div>
                            <div class="col-sm-6 text-right">
                                <a href="dean-academics.php" class="btn btn-primary action-top-md">
                                    <i class="fa fa-graduation-cap"></i> Open Academic Workspace
                                </a>
                                <a href="manage-teachers.php" class="btn btn-primary action-top-md">
                                    <i class="fa fa-user-circle"></i> Manage Teachers
                                </a>
                            </div>
                        </div>
                    </div>

                    <section class="section"><?php include 'includes/academic-overview.php'; ?>
                        <div class="container-fluid">
                            <div class="row">

                                <!-- Registered Users -->
                                <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                                    <a class="dashboard-stat bg-primary" href="manage-students.php">
                                        <span class="number counter"><?php echo htmlentities($totalStudents);?></span>
                                        <span class="name">Total Students</span>
                                        <span class="bg-icon"><i class="fa fa-users"></i></span>
                                    </a>
                                </div>

                                <!-- Subjects -->
                                <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                                    <a class="dashboard-stat bg-danger" href="manage-teachers.php">
                                        <span class="number counter"><?php echo htmlentities($totalTeachers);?></span>
                                        <span class="name">Manage Teachers</span>
                                        <span class="bg-icon"><i class="fa fa-user-circle"></i></span>
                                    </a>
                                </div>

                                <!-- Classes -->
                                <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 dashboard-stat-spaced">
                                    <a class="dashboard-stat bg-warning" href="manage-classes.php">
                                        <span class="number counter"><?php echo htmlentities($totalClasses);?></span>
                                        <span class="name">Total Classes</span>
                                        <span class="bg-icon"><i class="fa fa-bank"></i></span>
                                    </a>
                                </div>

                                <!-- Results -->
                                <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 dashboard-stat-spaced">
                                    <a class="dashboard-stat bg-success" href="manage-results.php">
                                        <span class="number counter"><?php echo htmlentities($totalSubjects);?></span>
                                        <span class="name">Total Subjects</span>
                                        <span class="bg-icon"><i class="fa fa-file-text"></i></span>
                                    </a>
                                </div>

                                <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 dashboard-stat-spaced">
                                    <a class="dashboard-stat bg-primary" href="manage-exams.php">
                                        <span class="number counter"><?php echo htmlentities($activeExamCount);?></span>
                                        <span class="name">Active Examinations</span>
                                        <span class="bg-icon"><i class="fa fa-calendar"></i></span>
                                    </a>
                                </div>

                                <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 dashboard-stat-spaced">
                                    <a class="dashboard-stat bg-success" href="manage-results.php">
                                        <span class="number counter"><?php echo htmlentities($resultsSubmitted);?></span>
                                        <span class="name">Results Submitted</span>
                                        <span class="bg-icon"><i class="fa fa-check"></i></span>
                                    </a>
                                </div>

                                <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 dashboard-stat-spaced">
                                    <a class="dashboard-stat bg-warning" href="dean-result-approvals.php">
                                        <span class="number counter"><?php echo htmlentities($classesAwaitingApproval);?></span>
                                        <span class="name">Classes Awaiting Approval</span>
                                        <span class="bg-icon"><i class="fa fa-check-square-o"></i></span>
                                    </a>
                                </div>

                                <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 dashboard-stat-spaced">
                                    <a class="dashboard-stat bg-danger" href="dean-result-approvals.php">
                                        <span class="number counter"><?php echo htmlentities($schoolMean ?: 0);?></span>
                                        <span class="name">School Mean Performance</span>
                                        <span class="bg-icon"><i class="fa fa-line-chart"></i></span>
                                    </a>
                                </div>

                                <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 dashboard-stat-spaced">
                                    <a class="dashboard-stat bg-primary" href="dean-class-timetable.php">
                                        <span class="number counter"><?php echo htmlentities($classesScheduled);?> / <?php echo htmlentities($totalClasses);?></span>
                                        <span class="name">Classes Scheduled</span>
                                        <span class="bg-icon"><i class="fa fa-table"></i></span>
                                    </a>
                                </div>

                                <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 dashboard-stat-spaced">
                                    <a class="dashboard-stat bg-warning" href="dean-exam-timetable.php">
                                        <span class="number"><?php echo htmlentities($examTimetableStatus ? ucfirst(str_replace('_', ' ', $examTimetableStatus)) : 'Draft');?></span>
                                        <span class="name">Exam Timetable</span>
                                        <span class="bg-icon"><i class="fa fa-calendar-check-o"></i></span>
                                    </a>
                                </div>

                                <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 dashboard-stat-spaced">
                                    <a class="dashboard-stat bg-success" href="manage-teachers.php">
                                        <span class="number counter"><?php echo htmlentities($activeTeachers);?></span>
                                        <span class="name">Active Teachers</span>
                                        <span class="bg-icon"><i class="fa fa-user-circle"></i></span>
                                    </a>
                                </div>

                                <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 dashboard-stat-spaced">
                                    <a class="dashboard-stat bg-danger" href="manage-teachers.php">
                                        <span class="number counter"><?php echo htmlentities($inactiveTeachers);?></span>
                                        <span class="name">Inactive Teachers</span>
                                        <span class="bg-icon"><i class="fa fa-user-times"></i></span>
                                    </a>
                                </div>

                            </div>
                        </div>
                    </section>

                    <!-- Extra Info Cards Section -->
                    <section class="info-section container mt-5">
                        <div class="row g-4 justify-content-center">

                            <div class="col-md-4">
                                <div class="info-card">
                                    <i class="fa fa-bullhorn"></i>
                                    <h5>Recent Announcements</h5>
                                    <p>Stay updated with exam notifications, academic deadlines, and school-wide result workflow activity.</p>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="info-card">
                                    <i class="fa fa-line-chart"></i>
                                    <h5>Performance Insights</h5>
                                    <p>Track school mean, class averages, subject performance, and term-based academic progress.</p>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="info-card">
                                    <i class="fa fa-cogs"></i>
                                    <h5>Academic Administration</h5>
                                    <p>Manage academic years, grades, teachers, examinations, approvals, publication, and audit activity.</p>
                                </div>
                            </div>

                        </div>
                    </section>

                </div><!-- /.main-page -->
            </div><!-- /.content-container -->
        </div><!-- /.content-wrapper -->
    </div><!-- /.main-wrapper -->

    <!-- Footer -->
    <footer>
        <p>&copy; <span id="year"></span> Student Result Management System | Dean of Studies. All Rights Reserved.</p>
    </footer>

    <script>
    document.getElementById("year").textContent = new Date().getFullYear();
    </script>

    <!-- JS FILES -->
    <script src="js/jquery/jquery-2.2.4.min.js"></script>
    <script src="js/bootstrap/bootstrap.min.js"></script>
    <script src="js/lobipanel/lobipanel.min.js"></script>
    <script src="js/toastr/toastr.min.js"></script>
    <script src="js/waypoint/waypoints.min.js"></script>
    <script src="js/counterUp/jquery.counterup.min.js"></script>
    <script src="js/main.js"></script>

    <script>
    $(function() {
        $('.counter').counterUp({
            delay: 10,
            time: 1000
        });
        toastr["success"]("Welcome to the Dean of Studies Dashboard!");
    });
    </script>
</body>

</html>

<?php } ?>
