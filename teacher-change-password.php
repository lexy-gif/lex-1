<?php
require_once 'includes/bootstrap.php';require_once 'includes/config.php';require_once 'includes/teacher-auth.php';require_teacher();require_once 'includes/security.php';require_once 'includes/portal-layout.php';
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_require_valid($_POST['csrf_token']??'');
    try{
        $account=cbe_one($dbh,'SELECT PasswordHash,SessionVersion FROM tblusers WHERE id=? AND Status=1',[teacher_id()]);
        if(!$account||!is_string($_POST['current']??null)||!password_verify($_POST['current'],$account['PasswordHash']))throw new DomainException('Enter your current password.');
        if(($_POST['password']??null)!==($_POST['confirm']??null))throw new DomainException('New passwords do not match.');
        $hash=security_password($_POST['password']??null);
        if(password_verify($_POST['password'],$account['PasswordHash']))throw new DomainException('Choose a different password.');
        $q=academic_query($dbh,'UPDATE tblusers SET PasswordHash=?,MustChangePassword=0,SessionVersion=SessionVersion+1 WHERE id=? AND Status=1 AND SessionVersion=?',[$hash,teacher_id(),$account['SessionVersion']]);
        if(!$q->rowCount())throw new DomainException('Your account changed. Sign in again.');
        $_SESSION['teacher_session_version']=(int)$account['SessionVersion']+1;session_regenerate_id(true);
        audit_log($dbh,'teacher_password_changed','tblusers',teacher_id(),'Teacher changed password');header('Location: teacher-academic-assignments.php');exit;
    }catch(Throwable $e){error_log($e->getMessage());$error=$e instanceof DomainException?$e->getMessage():'Could not update password.';}
}
portal_start('Change password','teacher');portal_alert($error);?><section class="parent-card portal-form"><form method="post"><?php csrf_field();portal_input('Current password','current','','password',true,'autocomplete="current-password"');portal_input('New password','password','','password',true,'minlength="12" maxlength="72" autocomplete="new-password"');portal_input('Confirm new password','confirm','','password',true,'minlength="12" maxlength="72" autocomplete="new-password"');?><button class="btn btn-primary">Change password</button></form></section><?php portal_end('teacher'); ?>
