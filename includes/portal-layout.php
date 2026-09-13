<?php
require_once __DIR__.'/cbe-academics.php';
require_once __DIR__.'/csrf.php';
function portal_start($title,$role='parent') {
    global $dbh;
    ?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= academic_h($title) ?> | SRMS</title><link rel="stylesheet" href="css/bootstrap.min.css"><link rel="stylesheet" href="css/font-awesome.min.css"><link rel="stylesheet" href="css/main.css"><link rel="stylesheet" href="css/custom.css"><link rel="stylesheet" href="css/parent-portal.css"></head><body class="<?= $role==='parent'?'parent-portal':'top-navbar-fixed' ?>"><div class="main-wrapper">
    <?php if ($role!=='parent') { include __DIR__.($role==='teacher'?'/teacher-topbar.php':'/topbar.php'); echo '<div class="content-wrapper"><div class="content-container">'; include __DIR__.($role==='teacher'?'/teacher-leftbar.php':'/leftbar.php'); echo '<main class="main-page"><div class="container-fluid">'; }
    else { ?><nav class="parent-nav" aria-label="Parent portal"><a href="index.php" class="parent-brand"><?= academic_h(getenv('SCHOOL_NAME')?:'Senior School SRMS') ?></a><div><a href="parent-dashboard.php">My children</a><a href="parent-notifications.php">Notifications</a><a href="parent-profile.php">My account</a><form method="post" action="parent-logout.php"><?php csrf_field(); ?><button class="btn btn-default">Sign out</button></form></div></nav><main class="container parent-main"><?php }
    echo '<h1 class="title">'.academic_h($title).'</h1>';
}
function portal_end($role='parent') {
    echo $role==='parent'?'</main>':'</div></main></div></div>';
    echo '</div><script src="js/jquery/jquery-3.7.1.min.js"></script><script src="js/bootstrap/bootstrap.min.js"></script>';
    if ($role!=='parent') echo '<script src="js/main.js"></script>';
    echo '</body></html>';
}
function portal_child_navigation($parent, $student=0) {
    global $dbh;
    $routes=['parent-dashboard.php'=>'Overview','parent-child.php'=>'Subjects and profile','parent-report.php'=>'Reports and feedback','parent-assessments.php'=>'Assessments','parent-attendance.php'=>'Attendance','parent-timetable.php'=>'Timetables'];
    $page=basename($_SERVER['SCRIPT_NAME']??'');
    $action=isset($routes[$page])?$page:'parent-child.php';
    $children=parent_children($dbh,$parent);
    if (!$children) return;
    ?><div class="parent-child-navigation"><form method="get" action="<?= academic_h($action) ?>" class="parent-selector"><label for="portal-student">Student</label><select id="portal-student" name="student" class="form-control" required><?php if(!$student){ ?><option value="">Select a student</option><?php } foreach($children as $child){ ?><option value="<?= (int)$child['StudentId'] ?>" <?= (int)$child['StudentId']===$student?'selected':'' ?>><?= academic_h($child['StudentName'].' ('.$child['RollId'].')') ?></option><?php } ?></select><button class="btn btn-primary">View student</button></form>
    <?php if($student){ ?><nav class="report-actions" aria-label="Student information"><?php foreach($routes as $route=>$label){ ?><a class="btn <?= $page===$route?'btn-primary':'btn-default' ?>" href="<?= academic_h($route.'?student='.$student) ?>" <?= $page===$route?'aria-current="page"':'' ?>><?= academic_h($label) ?></a> <?php } ?></nav><?php } ?></div><?php
}
function portal_input($label,$name,$value='',$type='text',$required=true,$extra='') {
    echo '<div class="form-group"><label for="'.academic_h($name).'">'.academic_h($label).'</label><input id="'.academic_h($name).'" class="form-control" type="'.academic_h($type).'" name="'.academic_h($name).'" value="'.academic_h($value).'" '.($required?'required ':'').$extra.'></div>';
}
function portal_alert($message,$kind='danger') { if ($message!=='') echo '<div class="alert alert-'.$kind.'" role="alert">'.academic_h($message).'</div>'; }
function portal_page($query) { return max(1,min(100000,(int)($query['page']??1))); }
function portal_pagination($page,$hasNext,$query=[]) {
    echo '<nav class="portal-pagination" aria-label="Pagination">';
    if ($page>1) echo '<a class="btn btn-default" href="?'.academic_h(http_build_query(array_merge($query,['page'=>$page-1]))).'">Previous</a> ';
    echo '<span>Page '.$page.'</span> ';
    if ($hasNext) echo '<a class="btn btn-default" href="?'.academic_h(http_build_query(array_merge($query,['page'=>$page+1]))).'">Next</a>';
    echo '</nav>';
}
