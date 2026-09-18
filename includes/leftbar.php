<?php
require_once __DIR__.'/permissions.php';
$navigation=[
 'Academic overview'=>['dashboard.php','dashboard'],
 'Calendar and terms'=>['dean-academic-periods.php','calendar'],
 'Curriculum, pathways and subjects'=>['dean-senior-pathways.php','book'],
 'Classes and streams'=>['manage-classes.php','university'],
 'Learner academic records'=>['manage-students.php','graduation-cap'],
 'Learner subject enrolments'=>['student-subjects.php','book'],
 'Teaching allocations'=>['dean-teacher-relationships.php','users'],
 'Timetables'=>['dean-class-timetable.php','table'],
 'Curriculum coverage'=>['dean-academics.php?area=coverage','tasks'],
 'Assessments'=>['dean-academics.php?area=assessments','pencil'],
 'Results review and publication'=>['dean-result-approvals.php','check-square-o'],
 'Learner support'=>['dean-academics.php?area=interventions','heart'],
 'Academic reports'=>['dean-academics.php?area=reports','bar-chart'],
 'Academic notices'=>['manage-notices.php','bullhorn'],
 'Notifications'=>['dean-notification-deliveries.php','bell'],
 'Guardian SMS deliveries'=>['dean-sms-deliveries.php','envelope'],
 'Teacher accounts'=>['manage-teachers.php','user'],
 'Guardian accounts'=>['manage-parents.php','users'],
 'Department access'=>['dean-academics.php?area=permissions','key'],
 'Performance scales'=>['dean-grading.php','list'],
 'Learner promotions'=>['dean-senior-promotions.php','level-up'],
 'Legacy mark administration'=>['add-result.php','pencil'],
 'System audit logs'=>['dean-audit-logs.php','history'],
 'Staff permissions'=>['staff-permissions.php','lock'],
 'Change password'=>['change-password.php','key']
];
?>
<div class="left-sidebar bg-black-300 box-shadow" id="srms-sidebar" role="navigation" aria-label="Staff navigation"><div class="sidebar-content"><div class="user-info closed"><h6 class="title"><?= htmlspecialchars($_SESSION['alogin']??'Staff',ENT_QUOTES,'UTF-8') ?></h6><small><?= htmlspecialchars(implode(', ', $GLOBALS['staff_access']['roles']??[]),ENT_QUOTES,'UTF-8') ?></small></div><div class="sidebar-nav"><ul class="side-nav color-gray">
<?php foreach($navigation as $label=>[$url,$icon]) { $path=parse_url($url,PHP_URL_PATH);parse_str(parse_url($url,PHP_URL_QUERY)??'',$query);$permission=isset($query['area'])?staff_area_permission($query['area']):(staff_routes()[$path]??null);if(!staff_can($permission))continue; ?>
<li><a href="<?= htmlspecialchars($url,ENT_QUOTES,'UTF-8') ?>"><i class="fa fa-<?= $icon ?>"></i> <span><?= htmlspecialchars($label,ENT_QUOTES,'UTF-8') ?></span></a></li>
<?php } ?></ul></div></div></div>
