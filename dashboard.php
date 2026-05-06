<?php
session_start();
error_reporting(0);
include('includes/config.php');
if(strlen($_SESSION['alogin'])=="") {   
    header("Location: index.php"); 
} else {
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Result Management System | Dashboard</title>

    <!-- CSS FILES -->
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" href="css/animate-css/animate.min.css" media="screen">
    <link rel="stylesheet" href="css/lobipanel/lobipanel.min.css" media="screen">
    <link rel="stylesheet" href="css/toastr/toastr.min.css" media="screen">
    <link rel="stylesheet" href="css/icheck/skins/line/blue.css">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <script src="js/modernizr/modernizr.min.js"></script>

    <style>
    /* Full background image */
    body {
        background-image: url('images/school system background.jpg');
        background-size: cover;
        background-attachment: fixed;
        background-position: center;
        position: relative;
    }

    /* Dark overlay for readability */
    body::before {
        content: "";
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
        z-index: -1;
    }

    /* Dashboard content readability */
    .main-wrapper,
    .content-wrapper,
    .content-container {
        color: #fff !important;
    }

    .dashboard-stat {
        background: rgba(0, 0, 0, 0.6) !important;
        border: 1px solid rgba(255, 255, 255, 0.15);
        transition: transform 0.3s ease, background 0.3s ease;
    }

    .dashboard-stat:hover {
        transform: scale(1.05);
        background: rgba(0, 0, 0, 0.8) !important;
    }

    /* Info Cards Section */
    .info-section {
        margin-top: 40px;
    }

    .info-card {
        background: rgba(255, 255, 255, 0.15);
        border-radius: 15px;
        padding: 25px;
        color: #fff;
        backdrop-filter: blur(6px);
        text-align: center;
        transition: all 0.3s ease;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }

    .info-card:hover {
        background: rgba(255, 255, 255, 0.25);
        transform: translateY(-5px);
    }

    .info-card i {
        font-size: 40px;
        color: #00b4ff;
        margin-bottom: 15px;
    }

    .info-card h5 {
        font-weight: bold;
        margin-bottom: 10px;
    }

    footer {
        background-color: rgba(0, 0, 0, 0.85);
        color: #fff;
        padding: 15px 0;
        text-align: center;
        margin-top: 50px;
    }
    </style>
</head>

<body class="top-navbar-fixed">
    <div class="main-wrapper">
        <?php include('includes/topbar.php');?>
        <div class="content-wrapper">
            <div class="content-container">

                <?php include('includes/leftbar.php');?>

                <div class="main-page">
                    <div class="container-fluid">
                        <div class="row page-title-div">
                            <div class="col-sm-6">
                                <h2 class="title text-white">Dashboard</h2>
                            </div>
                        </div>
                    </div>

                    <section class="section">
                        <div class="container-fluid">
                            <div class="row">

                                <!-- Registered Users -->
                                <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                                    <a class="dashboard-stat bg-primary" href="manage-students.php">
                                        <?php 
                                        $sql1 ="SELECT StudentId from tblstudents";
                                        $query1 = $dbh -> prepare($sql1);
                                        $query1->execute();
                                        $totalstudents=$query1->rowCount();
                                        ?>
                                        <span class="number counter"><?php echo htmlentities($totalstudents);?></span>
                                        <span class="name">Registered Users</span>
                                        <span class="bg-icon"><i class="fa fa-users"></i></span>
                                    </a>
                                </div>

                                <!-- Subjects -->
                                <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12">
                                    <a class="dashboard-stat bg-danger" href="manage-subjects.php">
                                        <?php 
                                        $sql ="SELECT id from tblsubjects";
                                        $query = $dbh -> prepare($sql);
                                        $query->execute();
                                        $totalsubjects=$query->rowCount();
                                        ?>
                                        <span class="number counter"><?php echo htmlentities($totalsubjects);?></span>
                                        <span class="name">Subjects Listed</span>
                                        <span class="bg-icon"><i class="fa fa-ticket"></i></span>
                                    </a>
                                </div>

                                <!-- Classes -->
                                <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12" style="margin-top:1%;">
                                    <a class="dashboard-stat bg-warning" href="manage-classes.php">
                                        <?php 
                                        $sql2 ="SELECT id from tblclasses";
                                        $query2 = $dbh -> prepare($sql2);
                                        $query2->execute();
                                        $totalclasses=$query2->rowCount();
                                        ?>
                                        <span class="number counter"><?php echo htmlentities($totalclasses);?></span>
                                        <span class="name">Total Classes</span>
                                        <span class="bg-icon"><i class="fa fa-bank"></i></span>
                                    </a>
                                </div>

                                <!-- Results -->
                                <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12" style="margin-top:1%;">
                                    <a class="dashboard-stat bg-success" href="manage-results.php">
                                        <?php 
                                        $sql3="SELECT distinct StudentId from tblresult";
                                        $query3 = $dbh -> prepare($sql3);
                                        $query3->execute();
                                        $totalresults=$query3->rowCount();
                                        ?>
                                        <span class="number counter"><?php echo htmlentities($totalresults);?></span>
                                        <span class="name">Results Declared</span>
                                        <span class="bg-icon"><i class="fa fa-file-text"></i></span>
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
                                    <p>Stay updated with the latest exam notifications and system announcements directly
                                        from the admin portal.</p>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="info-card">
                                    <i class="fa fa-line-chart"></i>
                                    <h5>Performance Insights</h5>
                                    <p>Track student progress, subject averages, and overall performance trends for each
                                        academic term.</p>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="info-card">
                                    <i class="fa fa-cogs"></i>
                                    <h5>System Management</h5>
                                    <p>Manage classes, results, and user data efficiently through the SRMS dashboard
                                        with ease and control.</p>
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
        <p>&copy; <span id="year"></span> Student Result Management System. All Rights Reserved.</p>
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
        toastr["success"]("Welcome to Student Result Management System Dashboard!");
    });
    </script>
</body>

</html>

<?php } ?>