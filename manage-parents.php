<?php
require_once 'includes/bootstrap.php';
require_once 'includes/config.php';
require_once 'includes/dean-auth.php'; require_dean();
require_once 'includes/parent-management.php';
require_once 'includes/portal-layout.php';
$error=''; $id=max(0,(int)($_GET['id']??0));
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_require_valid($_POST['csrf_token']??'');
    try { $dbh->beginTransaction(); $id=parent_manage($dbh,$_POST); $dbh->commit(); header('Location: manage-parents.php?id='.$id.'&saved=1'); exit; }
    catch(Throwable $e) { if($dbh->inTransaction())$dbh->rollBack(); error_log($e->getMessage()); $error=$e instanceof DomainException?$e->getMessage():'Could not save. Check whether the username or email already exists.'; }
}
$account=$id?cbe_one($dbh,"SELECT id,FullName,Username,Email,ParentPhone,Status FROM tblusers WHERE id=? AND Role='parent'",[$id]):[];
if ($id&&!$account) { http_response_code(404); exit('Guardian not found.'); }
$values=$error?$_POST:$account; $page=portal_page($_GET); $search=cbe_text($_GET,'q',100,false);
portal_start('Parents and Guardians','dean'); portal_alert($error); if(isset($_GET['saved']))portal_alert('Guardian account updated.','success');
?><div class="parent-grid"><section class="parent-card"><h2><?= $id?'Edit guardian':'Create guardian' ?></h2><form method="post" class="portal-form"><?php csrf_field(); ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $id ?>">
<?php foreach(['FullName'=>'Full name','Username'=>'Username','Email'=>'Email','ParentPhone'=>'Mobile phone'] as $key=>$label) portal_input($label,$key,$values[$key]??'',$key==='Email'?'email':'text',!in_array($key,['Email','ParentPhone']), 'maxlength="150"');
if(!$id)portal_input('Temporary password','Password','','password',true,'minlength="12" maxlength="72" autocomplete="new-password"'); ?>
<div class="form-group"><label for="Status">Status</label><select id="Status" name="Status" class="form-control"><option value="1">Active</option><option value="0" <?= isset($values['Status'])&&(int)$values['Status']===0?'selected':'' ?>>Inactive</option></select></div><button class="btn btn-primary">Save guardian</button> <a class="btn btn-default" href="manage-parents.php">New guardian</a></form></section>
<?php if($id){ ?><section class="parent-card"><h2>Linked children</h2><p>Verify guardianship with the school before granting access. One account can link to siblings.</p>
<?php foreach(cbe_rows($dbh,'SELECT ps.*,s.StudentName,s.RollId FROM tblparentstudents ps JOIN tblstudents s ON s.StudentId=ps.StudentId WHERE ps.ParentId=? AND ps.Status=1 ORDER BY s.StudentName LIMIT 100',[$id]) as $child){ ?><p><?= academic_h($child['StudentName'].' — '.$child['RollId'].' ('.$child['Relationship'].')') ?></p><form method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="unlink"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="StudentId" value="<?= (int)$child['StudentId'] ?>"><button class="btn btn-warning btn-sm">Revoke access</button></form><?php } ?>
<hr><form method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="link"><input type="hidden" name="id" value="<?= $id ?>"><?php portal_input('Child admission number','Admission'); portal_input('Relationship','Relationship','Guardian'); ?><p><label><input type="checkbox" name="NotifyResults" value="1" checked> Receive result publication SMS</label></p><button class="btn btn-primary">Link child</button></form>
<hr><form method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= $id ?>"><?php portal_input('New temporary password','Password','','password',true,'minlength="12" maxlength="72" autocomplete="new-password"'); ?><button class="btn btn-warning">Reset password and end sessions</button></form></section><?php } ?></div>
<section class="parent-card"><h2>Guardian directory</h2><form method="get" class="form-inline"><label for="q">Search</label> <input id="q" name="q" class="form-control" value="<?= academic_h($search) ?>" maxlength="100" placeholder="Name, username or phone"> <button class="btn btn-default">Search</button></form>
<?php $rows=cbe_rows($dbh,"SELECT u.id,u.FullName,u.Username,u.ParentPhone,u.Status,(SELECT COUNT(*) FROM tblparentstudents ps WHERE ps.ParentId=u.id AND ps.Status=1) Children FROM tblusers u WHERE u.Role='parent' AND (u.FullName LIKE ? OR u.Username LIKE ? OR u.ParentPhone LIKE ?) ORDER BY u.FullName,u.id LIMIT 26 OFFSET ".(($page-1)*25),array_fill(0,3,'%'.$search.'%')); $more=count($rows)>25; ?>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Name</th><th>Username</th><th>Phone</th><th>Children</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach(array_slice($rows,0,25) as $row){ ?><tr><td><?= academic_h($row['FullName']) ?></td><td><?= academic_h($row['Username']) ?></td><td><?= academic_h($row['ParentPhone']) ?></td><td><?= (int)$row['Children'] ?></td><td><?= $row['Status']?'Active':'Inactive' ?></td><td><a href="?id=<?= (int)$row['id'] ?>">Manage</a></td></tr><?php } if(!$rows)echo '<tr><td colspan="6">No guardians found.</td></tr>'; ?></tbody></table></div><?php portal_pagination($page,$more,['q'=>$search]); ?></section><?php portal_end('dean'); ?>
