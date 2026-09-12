<?php
require_once 'includes/bootstrap.php';
$error=$msg=''; require_once 'includes/config.php';
require_once 'includes/parent-auth.php'; require_once 'includes/csrf.php';
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_require_valid($_POST['csrf_token']??'');
    $username=is_string($_POST['username']??null)?trim($_POST['username']):'';
    $password=is_string($_POST['password']??null)?$_POST['password']:'';
    $key=security_login_key('parent',$username);
    $account=cbe_one($dbh,"SELECT id,PasswordHash,Status,SessionVersion FROM tblusers WHERE Username=? AND Role='parent'",[$username]);
    $allowed=security_login_allowed($dbh,$key);
    $valid=$allowed&&$account&&(int)$account['Status']===1&&password_verify($password,$account['PasswordHash']);
    if ($allowed)security_login_result($dbh,$key,$valid);
    if ($valid) {
        security_login_session(['parent_user_id'=>(int)$account['id'],'parent_session_version'=>(int)$account['SessionVersion']]);
        audit_log($dbh,'parent_login','tblusers',$account['id'],'Guardian logged in');
        header('Location: parent-dashboard.php'); exit;
    }
    $error='Invalid login details or too many attempts. Please try again later.';
}
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Parent / Guardian Login | SRMS</title><link rel="stylesheet" href="css/bootstrap.min.css"><link rel="stylesheet" href="css/main.css"><link rel="stylesheet" href="css/custom.css"><link rel="stylesheet" href="css/parent-portal.css"></head><body class="auth-page"><main class="container parent-main"><div class="row"><div class="col-md-6 col-md-offset-3"><section class="parent-card"><h1>Parent / Guardian Login</h1><p>Access your children's published results and school reports.</p><?php if($error){ ?><div class="alert alert-danger" role="alert"><?= academic_h($error) ?></div><?php } ?><form method="post"><?php csrf_field(); ?><div class="form-group"><label for="username">Username</label><input id="username" name="username" class="form-control" required maxlength="100" autocomplete="username"></div><div class="form-group"><label for="password">Password</label><input id="password" type="password" name="password" class="form-control" required maxlength="72" autocomplete="current-password"></div><button class="btn btn-primary">Sign in</button> <a class="btn btn-default" href="index.php">Home</a></form><hr><p>For a new account or password reset, contact the school. The school verifies guardianship before linking a child.</p></section></div></div></main></body></html>
