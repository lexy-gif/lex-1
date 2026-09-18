<?php
require_once 'includes/config.php';require_once 'includes/dean-auth.php';require_dean();
require_once 'includes/portal-layout.php';require_once 'includes/staff-administration.php';
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_require_valid($_POST['csrf_token']??'');
    try {staff_change_access($dbh,$_POST);header('Location: staff-permissions.php?saved=1');exit;}
    catch(DomainException $e){$error=$e->getMessage();}
}
portal_start('Staff permissions','dean');portal_alert($error);if(isset($_GET['saved']))portal_alert('Authorisation saved.','success');
echo '<p>Roles are additive. Dean alone grants academic coordination. Delegations start disabled and require a recorded authorisation. Assign technical administration and academic approval separately when appropriate.</p>';
$accounts=[];
foreach(['tbldean','admin'] as $source) {
    $exists=academic_query($dbh,'SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$source])->fetchColumn();
    if($exists)foreach(cbe_rows($dbh,"SELECT id,UserName FROM `$source`") as $a)$accounts[]=[$source,$a];
}
foreach($accounts as [$source,$a]) { ?>
<section class="panel panel-body"><h3><?= academic_h($a['UserName']) ?></h3>
<p>Roles: <?= academic_h(implode(', ',academic_query($dbh,'SELECT RoleName FROM tblstaffroles WHERE AccountTable=? AND AccountId=?',[$source,$a['id']])->fetchAll(PDO::FETCH_COLUMN))) ?></p>
<p>Separate permissions: <?= academic_h(implode(', ',academic_query($dbh,'SELECT PermissionName FROM tblstaffpermissions WHERE AccountTable=? AND AccountId=?',[$source,$a['id']])->fetchAll(PDO::FETCH_COLUMN))) ?></p>
<?php foreach(['role'=>array_combine(array_keys(staff_role_permissions()),array_keys(staff_role_permissions())),'permission'=>array_intersect_key(staff_permissions(),array_flip(staff_delegatable_permissions()))] as $kind=>$choices){ ?>
<form method="post" class="form-inline"><?php csrf_field(); ?><input type="hidden" name="AccountTable" value="<?= academic_h($source) ?>"><input type="hidden" name="AccountId" value="<?= (int)$a['id'] ?>"><input type="hidden" name="Kind" value="<?= $kind ?>">
<label><?= ucfirst($kind) ?> <select name="Name" class="form-control"><?php foreach($choices as $key=>$label){ ?><option value="<?= academic_h($key) ?>"><?= academic_h($label) ?></option><?php } ?></select></label>
<label>Decision <select name="Grant" class="form-control"><option value="1">Grant</option><option value="0">Revoke</option></select></label>
<label>Authorisation reason <input name="Reason" class="form-control" required maxlength="1000"></label><button class="btn btn-primary">Save</button></form><?php } ?></section><?php }
echo '<h3>Permission audit history</h3>';require_once 'includes/cbe-ui.php';cbe_table(cbe_rows($dbh,'SELECT * FROM tblstaffpermissionaudit ORDER BY id DESC LIMIT 100'));portal_end('dean');
