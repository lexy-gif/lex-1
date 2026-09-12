<?php
require_once 'includes/bootstrap.php'; require_once 'includes/config.php'; require_once 'includes/parent-auth.php'; require_once 'includes/sms.php'; require_once 'includes/portal-layout.php';
$parent=require_parent(true); $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_require_valid($_POST['csrf_token']??'');
    try {
        if (!is_string($_POST['CurrentPassword']??null)||!password_verify($_POST['CurrentPassword'],$parent['PasswordHash']))throw new DomainException('Enter your current password.');
        $email=cbe_text($_POST,'Email',150,false); if($email&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new DomainException('Enter a valid email address.');
        $phone=cbe_text($_POST,'Phone',30,false); $normalized=normalize_phone_number($phone); if($phone&&!$normalized)throw new DomainException('Enter a valid Kenyan mobile number.');
        $password=$_POST['NewPassword']??'';
        if($parent['MustChangePassword']&&$password==='')throw new DomainException('Choose your own password to finish account setup.');
        $hash=$parent['PasswordHash'];
        if($password!=='') {
            if($password!==($_POST['ConfirmPassword']??null))throw new DomainException('New passwords do not match.');
            if(password_verify($password,$hash))throw new DomainException('Choose a different password.');
            $hash=security_password($password);
        }
        $dbh->beginTransaction();
        $updated=academic_query($dbh,"UPDATE tblusers SET Email=?,ParentPhone=?,PasswordHash=?,MustChangePassword=0,SessionVersion=SessionVersion+1 WHERE id=? AND Role='parent' AND Status=1 AND SessionVersion=?",[$email?:null,$normalized?:null,$hash,$parent['id'],$parent['SessionVersion']]);
        if(!$updated->rowCount())throw new DomainException('Your account changed. Please sign in again.');
        audit_log($dbh,'parent_profile_updated','tblusers',$parent['id'],'Guardian updated contact details or password'); $dbh->commit();
        security_login_session(['parent_user_id'=>(int)$parent['id'],'parent_session_version'=>(int)$parent['SessionVersion']+1]);
        header('Location: parent-profile.php?saved=1');exit;
    }catch(Throwable $e){if($dbh->inTransaction())$dbh->rollBack();error_log($e->getMessage());$error=$e instanceof DomainException?$e->getMessage():'Could not update your account. Check the email address or contact the school.';}
}
portal_start('My account');portal_alert($error);if(isset($_GET['saved']))portal_alert('Account updated.','success');if($parent['MustChangePassword'])portal_alert('Change your temporary password before opening results.','info');
?><section class="parent-card portal-form"><p><?= academic_h($parent['FullName'].' — '.$parent['Username']) ?></p><form method="post"><?php csrf_field();portal_input('Email','Email',$parent['Email']??'','email',false);portal_input('Kenyan mobile phone','Phone',$parent['ParentPhone']??'','tel',false);portal_input('Current password','CurrentPassword','','password',true,'autocomplete="current-password"');portal_input('New password','NewPassword','','password',(bool)$parent['MustChangePassword'],'minlength="12" maxlength="72" autocomplete="new-password"');portal_input('Confirm new password','ConfirmPassword','','password',(bool)$parent['MustChangePassword'],'minlength="12" maxlength="72" autocomplete="new-password"');?><button class="btn btn-primary">Save account</button></form></section><?php portal_end(); ?>
