<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/csrf.php');
include('includes/audit.php');
include('includes/dean-account.php');
if(!empty($_SESSION['alogin'])){
$_SESSION['alogin']='';
}
if(isset($_POST['login']))
{
csrf_require_valid($_POST['csrf_token'] ?? '');
$uname=$_POST['username'];
$password=$_POST['password'];
$result=dean_find_by_username($dbh, $uname);
$passwordMatches = false;

if($result) {
    $passwordMatches = password_verify($password, $result->Password);

    if(!$passwordMatches && hash_equals($result->Password, md5($password))) {
        $passwordMatches = true;
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        dean_update_password($dbh, $uname, $newHash);
    }
}

if($passwordMatches)
{
session_regenerate_id(true);
$_SESSION['alogin']=$result->UserName;
audit_log($dbh, 'dean_login', 'dean', $result->UserName, 'Dean of Studies logged in');
header("Location: dashboard.php");
exit;
} else{
    audit_log($dbh, 'dean_login_failed', 'dean', null, 'Failed Dean login for username: ' . $uname);
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
    <title>Dean of Studies Login | SRMS</title>

    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" href="css/animate-css/animate.min.css" media="screen">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <link rel="stylesheet" href="css/custom.css" media="screen">
    <script src="js/modernizr/modernizr.min.js"></script>
</head>

<body class="auth-page auth-page--segoe admin-login-page">
    <div class="main-wrapper">
        <div class="container">
            <h1 align="center">Student Result Management System</h1>
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8 col-sm-10">
                    <div class="login-container auth-card">
                        <section class="section">
                            <div class="panel">
                                <div class="panel-heading">
                                    <div class="panel-title text-center">
                                        <h4><i class="fa fa-lock"></i> Dean of Studies Login</h4>
                                    </div>
                                </div>
                                <div class="panel-body p-20">
                                    <form class="form-horizontal" method="post">
                                        <?php csrf_field(); ?>
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
                                <small>Secure Access Portal - Dean of Studies Only</small>
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
