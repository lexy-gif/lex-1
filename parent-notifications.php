<?php
require_once 'includes/bootstrap.php';require_once 'includes/config.php';require_once 'includes/parent-auth.php';require_once 'includes/portal-layout.php';
$parent=require_parent();
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_require_valid($_POST['csrf_token']??'');
    $id=(int)($_POST['id']??0);$all=($_POST['action']??'')==='all';
    academic_query($dbh,'UPDATE tblparentnotifications SET ReadAt=COALESCE(ReadAt,NOW()) WHERE ParentId=? AND (?=1 OR id=?)',[$parent['id'],$all?1:0,$id]);
    header('Location: parent-notifications.php');exit;
}
$page=portal_page($_GET);$rows=cbe_rows($dbh,'SELECT n.* FROM tblparentnotifications n JOIN tblparentstudents ps ON ps.ParentId=n.ParentId AND ps.StudentId=n.StudentId AND ps.Status=1 JOIN tblstudents s ON s.StudentId=n.StudentId AND s.Status=1 WHERE n.ParentId=? ORDER BY n.id DESC LIMIT 26 OFFSET '.(($page-1)*25),[$parent['id']]);
portal_start('Notifications');?><form method="post"><?php csrf_field();?><button name="action" value="all" class="btn btn-default">Mark all as read</button></form><?php foreach(array_slice($rows,0,25) as $n){?><article class="parent-card"><h2><?= academic_h($n['Title']) ?><?= $n['ReadAt']?'':' · New' ?></h2><small><?= academic_h($n['CreationDate']) ?></small><p><?= academic_h($n['Message']) ?></p><a class="btn btn-primary" href="<?= academic_h($n['ActionUrl']) ?>">Open results</a><form method="post" class="portal-inline"><?php csrf_field();?><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button class="btn btn-default">Mark as read</button></form></article><?php }if(!$rows)echo '<p>No notifications yet.</p>';portal_pagination($page,count($rows)>25);portal_end(); ?>
