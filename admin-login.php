<?php
session_start();
error_reporting(0);
include('includes/config.php');
if($_SESSION['alogin']!=''){
$_SESSION['alogin']='';
}
if(isset($_POST['login']))
{
$uname=$_POST['username'];
$password=md5($_POST['password']);
$sql ="SELECT UserName,Password FROM admin WHERE UserName=:uname and Password=:password";
$query= $dbh -> prepare($sql);
$query-> bindParam(':uname', $uname, PDO::PARAM_STR);
$query-> bindParam(':password', $password, PDO::PARAM_STR);
$query-> execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);
if($query->rowCount() > 0)
{
$_SESSION['alogin']=$_POST['username'];
echo "<script type='text/javascript'> document.location = 'dashboard.php'; </script>";
} else{
    echo "<script>alert('Invalid Details');</script>";
}
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Class Teacher Login | SRMS</title>

    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" href="css/animate-css/animate.min.css" media="screen">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <script src="js/modernizr/modernizr.min.js"></script>

    <style>
    body {
        background-image: url('images/school system background.jpg');
        /* ✅ Use your existing background image */
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .login-container {
        background-color: rgba(255, 255, 255, 0.93);
        padding: 40px;
        border-radius: 10px;
        box-shadow: 0px 4px 20px rgba(0, 0, 0, 0.3);
        margin-top: 70px;
    }

    h1 {
        color: #fff;
        font-weight: 700;
        text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
        margin-top: 30px;
    }

    .panel-title h4 {
        font-weight: 600;
        color: #333;
    }

    footer {
        margin-top: 60px;
        background: rgba(0, 0, 0, 0.7);
        color: #ccc;
        padding: 15px 0;
        text-align: center;
    }

    footer a {
        color: #00bfff;
        text-decoration: none;
    }

    footer a:hover {
        text-decoration: underline;
    }

    .btn-success {
        background-color: #28a745;
        border: none;
    }

    .btn-success:hover {
        background-color: #218838;
    }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <div class="container">
            <h1 align="center">Student Result Management System</h1>
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8 col-sm-10">
                    <div class="login-container">
                        <section class="section">
                            <div class="panel">
                                <div class="panel-heading">
                                    <div class="panel-title text-center">
                                        <h4><i class="fa fa-lock"></i> Class Teacher Login</h4>
                                    </div>
                                </div>
                                <div class="panel-body p-20">
                                    <form class="form-horizontal" method="post">
                                        <div class="form-group">
                                            <label for="inputEmail3" class="col-sm-2 control-label">Username</label>
                                            <div class="col-sm-10">
                                                <input type="text" name="username" class="form-control" id="inputEmail3"
                                                    placeholder="Enter Username" required>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="inputPassword3" class="col-sm-2 control-label">Password</label>
                                            <div class="col-sm-10">
                                                <input type="password" name="password" class="form-control"
                                                    id="inputPassword3" placeholder="Enter Password" required>
                                            </div>
                                        </div>

                                        <div class="form-group mt-20">
                                            <div class="col-sm-offset-2 col-sm-10">
                                                <button type="submit" name="login"
                                                    class="btn btn-success btn-labeled pull-right">
                                                    Sign in
                                                    <span class="btn-label btn-label-right"><i
                                                            class="fa fa-check"></i></span>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <p class="text-muted text-center mt-3">
                                <small>Secure Access Portal -Class Teacher Only</small>
                            </p>
                        </section>
                    </div>
                </div>
            </div>
        </div>

        <footer>
            <p>© <?php echo date("Y"); ?> <strong>Student Result Management System (SRMS)</strong></p>
        </footer>
    </div>

    <!-- ========== JS FILES ========== -->
    <script src="js/jquery/jquery-2.2.4.min.js"></script>
    <script src="js/bootstrap/bootstrap.min.js"></script>
    <script src="js/pace/pace.min.js"></script>
    <script src="js/lobipanel/lobipanel.min.js"></script>
    <script src="js/iscroll/iscroll.js"></script>
    <script src="js/main.js"></script>
</body>

</html>