<?php
require_once 'includes/bootstrap.php';
require 'includes/config.php';
require_once 'includes/teacher-auth.php';
require_once 'includes/senior-ui.php';
require_teacher();
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Allow: GET'); exit; }
$ready = senior_ready($dbh);
try {
    $f = senior_filters($_GET);
    $f['year'] = $f['year'] ?: academic_year($dbh);
    if ($f['year']) academic_period($dbh, $f['year']);
    $learners = $ready ? senior_learners($dbh, $f, teacher_id()) : [];
    if ($ready && $f['student'] && !$learners) { http_response_code(403); exit('This learner is not part of your teaching assignments for the selected year.'); }
} catch (DomainException $e) { http_response_code(400); exit(academic_h($e->getMessage())); }
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Senior School | SRMS</title>
<link rel="stylesheet" href="css/bootstrap.min.css"><link rel="stylesheet" href="css/font-awesome.min.css"><link rel="stylesheet" href="css/main.css"><link rel="stylesheet" href="js/DataTables/datatables.min.css"><link rel="stylesheet" href="css/custom.css">
</head><body class="top-navbar-fixed"><div class="main-wrapper">
<?php include 'includes/teacher-topbar.php'; ?>
<div class="content-wrapper"><div class="content-container"><?php include 'includes/teacher-leftbar.php'; ?>
<main class="main-page senior-page"><div class="container-fluid">
<div class="page-title-div"><h2 class="title">Senior School</h2><p>Teaching assignments and learner subjects for Grades 10–12.</p></div>
<?php if (!$ready) { ?>
<div class="alert alert-warning">Senior School setup is not yet available. Contact the system administrator.</div>
<?php } else {
    senior_filter_form($dbh, $f, 'assignments');
    echo '<div class="panel panel-body"><h3>My Subject Assignments</h3>';
    senior_table(senior_report($dbh, 'teachers', $f, teacher_id()));
    echo '</div><div class="panel panel-body"><h3>My Learners</h3>';
    $rows = array_map(fn($s) => ['_id'=>$s['StudentId'], 'Admission'=>$s['RollId'], 'Learner'=>$s['StudentName'], 'Grade'=>$s['GradeNumber'], 'Class'=>$s['Class'], 'Pathway'=>$s['Pathway']??'Unassigned', 'Track'=>$s['Track']??'', 'Combination'=>$s['Combination']??'', 'Status'=>$s['StudentStatus']?'Active':'Inactive'], $learners);
    senior_table($rows, function($row) { echo '<a class="btn btn-xs btn-default" href="'.academic_h(senior_url('teacher-senior.php', ['student'=>$row['_id']])).'">View Subjects</a>'; });
    echo '</div>';
    if ($f['student'] && count($learners) === 1) {
        echo '<div class="panel panel-body"><h3>'.academic_h($learners[0]['StudentName']).': Subjects</h3>';
        senior_table(senior_learner_subjects($dbh, $f['student'], $f['year'], $learners[0]['ClassId'], teacher_id()));
        echo '</div>';
    }
} ?>
</div></main></div></div></div>
<script src="js/jquery/jquery-3.7.1.min.js"></script><script src="js/bootstrap/bootstrap.min.js"></script><script src="js/DataTables/datatables.min.js"></script><script src="js/main.js"></script><script src="js/senior-school.js"></script>
</body></html>
