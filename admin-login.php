<?php
require_once 'includes/bootstrap.php';

require_once 'includes/config.php';
require_once 'includes/csrf.php';
require_once 'includes/audit.php';
require_once 'includes/dean-account.php';
require_once 'includes/security.php';
if(!empty($_SESSION['alogin'])){
$_SESSION['alogin']='';
}
if(isset($_POST['login']))
{
csrf_require_valid($_POST['csrf_token'] ?? '');
$uname=is_string($_POST['username']??null)?trim($_POST['username']):'';
$password=is_string($_POST['password']??null)?$_POST['password']:'';
$source=is_string($_POST['account_source']??null)?$_POST['account_source']:dean_account_table($dbh);
$loginKey=security_login_key('staff:'.$source,$uname);
$loginAllowed=security_login_allowed($dbh,$loginKey);
$result=staff_login_account($dbh, $uname, $source);
$passwordMatches = false;

if($result && $loginAllowed) {
    $passwordMatches = password_verify($password, $result->Password);

    if(!$passwordMatches && hash_equals($result->Password, md5($password))) {
        $passwordMatches = true;
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $q=$dbh->prepare("UPDATE `$source` SET Password=? WHERE id=?");
        $q->execute([$newHash,$result->id]);
        $result->Password = $newHash;
    }
}

if($loginAllowed)security_login_result($dbh,$loginKey,$passwordMatches);
if($passwordMatches)
{
security_login_session(['alogin'=>$result->UserName,'staff_account_table'=>$result->AccountTable,'dean_password_version'=>hash('sha256',$result->Password)]);
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
    <title>Academic and Administration Login | SRMS</title>

    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" href="css/animate-css/animate.min.css" media="screen">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <link rel="stylesheet" href="css/custom.css">
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
                                        <h4><i class="fa fa-lock"></i> Academic and Administration Login</h4>
                                    </div>
                                </div>
                                <div class="panel-body p-20">
                                    <form class="form-horizontal" method="post">
                                        <?php csrf_field(); ?>
                                        <div class="form-group">
                                            <label for="account-source" class="col-sm-2 control-label">Account</label>
                                            <div class="col-sm-10"><select id="account-source" name="account_source" class="form-control">
                                                <option value="<?= htmlspecialchars(dean_account_table($dbh),ENT_QUOTES,'UTF-8') ?>">Academic / staff account</option>
                                                <?php if(dean_account_table($dbh)!=='admin') { ?><option value="admin">Legacy administrator account</option><?php } ?>
                                            </select></div>
                                        </div>
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
                                <small>Access follows the roles and permissions assigned to your account.</small><br>
                                <a href="/staff/login/">Staff login</a>
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
    <script src="js/jquery/jquery-3.7.1.min.js"></script>
    <script src="js/bootstrap/bootstrap.min.js"></script>
    <script src="js/pace/pace.min.js"></script>
    <script src="js/lobipanel/lobipanel.min.js"></script>
    <script src="js/iscroll/iscroll.js"></script>
    <script src="js/main.js"></script>
</body>

</html>
