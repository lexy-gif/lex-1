<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/csrf.php');
include('includes/audit.php');
require_once 'includes/teacher-auth.php';

if(isset($_POST['login'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT id, FullName, Username, PasswordHash, Role, ClassId, Status
            FROM tblusers
            WHERE Username = :username AND Role IN ('class_teacher','subject_teacher','head_of_department','exams_officer','deputy_dean')
            LIMIT 1";
    $query = $dbh->prepare($sql);
    $query->bindParam(':username', $username, PDO::PARAM_STR);
    $query->execute();
    $teacher = $query->fetch(PDO::FETCH_OBJ);

    if($teacher && (int)$teacher->Status === 1 && password_verify($password, $teacher->PasswordHash)) {
        session_regenerate_id(true);
        $_SESSION['teacher_user_id'] = $teacher->id;
        $_SESSION['teacher_username'] = $teacher->Username;
        $_SESSION['teacher_name'] = $teacher->FullName;
        $_SESSION['teacher_role'] = $teacher->Role;
        $_SESSION['teacher_class_id'] = $teacher->ClassId;
        $loginUpdate = $dbh->prepare("UPDATE tblusers SET LastLoginAt = NOW() WHERE id = :teacherid");
        $loginUpdate->execute(array(':teacherid' => $teacher->id));
        audit_log($dbh, 'teacher_login', 'tblusers', $teacher->id, 'Teacher logged in');
        header("Location: " . (teacher_class_id() ? 'teacher-dashboard.php' : 'teacher-academic-assignments.php'));
        exit;
    } else {
        audit_log($dbh, 'teacher_login_failed', 'tblusers', null, 'Failed login for username: ' . $username);
        $error = "Invalid teacher login details.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teacher Login | SRMS</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <link rel="stylesheet" href="css/custom.css" media="screen">
    <script src="js/modernizr/modernizr.min.js"></script>
</head>
<body class="auth-page teacher-login-page">
    <div class="container">
        <h1 class="text-center">Student Result Management System</h1>
        <div class="row">
            <div class="col-md-6 col-md-offset-3">
                <div class="login-container">
                    <div class="panel">
                        <div class="panel-heading">
                            <div class="panel-title text-center">
                                <h4><i class="fa fa-lock"></i> Teacher Login</h4>
                            </div>
                        </div>
                        <div class="panel-body p-20">
                            <?php if(isset($_GET['deactivated'])){?>
                            <div class="alert alert-warning">Your account has been deactivated. Please contact the school administration.</div>
                            <?php } ?>
                            <?php if($error){?>
                            <div class="alert alert-danger"><?php echo htmlentities($error); ?></div>
                            <?php } ?>
                            <form class="form-horizontal" method="post">
                                <?php csrf_field(); ?>
                                <div class="form-group">
                                    <label class="col-sm-3 control-label">Username</label>
                                    <div class="col-sm-9">
                                        <input type="text" name="username" class="form-control" required autocomplete="off">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-3 control-label">Password</label>
                                    <div class="col-sm-9">
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="col-sm-offset-3 col-sm-9">
                                        <button type="submit" name="login" class="btn btn-success pull-right">Sign in</button>
                                        <a href="index.php" class="btn btn-default">Back Home</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <p class="text-muted text-center"><small>Access is limited to assigned class data.</small></p>
                </div>
            </div>
        </div>
    </div>
    <script src="js/jquery/jquery-2.2.4.min.js"></script>
    <script src="js/bootstrap/bootstrap.min.js"></script>
</body>
</html>
