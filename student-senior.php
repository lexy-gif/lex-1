<?php
session_start();
require 'includes/config.php';
require_once 'includes/csrf.php';
require_once 'includes/senior-ui.php';
header('Cache-Control: no-store');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    if (isset($_POST['logout'])) {
        unset($_SESSION['student_user_id']);
        session_regenerate_id(true);
        header('Location: student-senior.php'); exit;
    }
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $account = cbe_one($dbh, "SELECT u.id,u.PasswordHash FROM tblusers u JOIN tblstudents s ON s.StudentId=u.StudentId WHERE u.Username=? AND u.Role='student' AND u.Status=1 AND s.Status=1", [$username]);
    if ($account && password_verify($password, $account['PasswordHash'])) {
        session_regenerate_id(true);
        $_SESSION['student_user_id'] = (int)$account['id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        academic_query($dbh, 'UPDATE tblusers SET LastLoginAt=NOW() WHERE id=?', [$account['id']]);
        header('Location: student-senior.php'); exit;
    }
    $error = 'Invalid student login details.';
}
// Resolve learner identity from the active account on every request, never from the URL.
$student = empty($_SESSION['student_user_id']) ? null : cbe_one($dbh, "SELECT s.StudentId,s.StudentName,s.RollId FROM tblusers u JOIN tblstudents s ON s.StudentId=u.StudentId WHERE u.id=? AND u.Role='student' AND u.Status=1 AND s.Status=1", [(int)$_SESSION['student_user_id']]);
if (!$student) unset($_SESSION['student_user_id']);
$ready = senior_ready($dbh);
$learner = null;
if ($student && $ready) {
    try {
        $f = senior_filters(['year'=>$_GET['year']??0, 'student'=>$student['StudentId']]);
        $f['year'] = $f['year'] ?: academic_year($dbh);
        academic_period($dbh, $f['year']);
        $learner = senior_learners($dbh, $f)[0] ?? null;
    } catch (DomainException $e) { http_response_code(400); exit(academic_h($e->getMessage())); }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Senior School Subjects | SRMS</title>
<link rel="stylesheet" href="css/bootstrap.min.css"><link rel="stylesheet" href="css/font-awesome.min.css"><link rel="stylesheet" href="css/main.css"><link rel="stylesheet" href="css/custom.css">
</head><body class="<?= $student ? 'senior-student-page' : 'auth-page' ?>"><div class="container">
<h1>My Senior School Subjects</h1>
<?php if (!$student) { ?>
<div class="row"><div class="col-md-6"><div class="panel panel-body">
<h2>Student Sign In</h2><p>Use the student account provided by your class teacher.</p>
<?php if ($error) { ?><div class="alert alert-danger" role="alert"><?= academic_h($error) ?></div><?php } ?>
<form method="post"><?php csrf_field(); ?>
<div class="form-group"><label for="student-username">Username</label><input id="student-username" name="username" class="form-control" autocomplete="username" required value="<?= academic_h($username??'') ?>"></div>
<div class="form-group"><label for="student-password">Password</label><input id="student-password" name="password" type="password" class="form-control" autocomplete="current-password" required></div>
<button class="btn btn-primary" type="submit">Sign In</button> <a class="btn btn-default" href="index.php">Home</a>
</form></div></div></div>
<?php } else { ?>
<div class="senior-toolbar"><a class="btn btn-default" href="index.php">Home</a><a class="btn btn-default" href="find-result.php">Find Results</a><form method="post"><?php csrf_field(); ?><button class="btn btn-default" type="submit" name="logout">Sign Out</button></form></div>
<h2><?= academic_h($student['StudentName']) ?></h2><p>Admission number: <?= academic_h($student['RollId']) ?></p>
<?php if (!$ready) { ?><div class="alert alert-warning">Senior School setup is not yet available. Contact your class teacher.</div><?php } else { ?>
<form method="get" class="panel panel-body senior-filters"><?php senior_field($dbh, 'year', ['Academic year','years',senior_choices($dbh,'years')], $f['year']); ?><button class="btn btn-default">View Year</button></form>
<?php if (!$learner) { ?><p>No Senior School enrollment is available for this academic year.</p><?php } else {
    echo '<div class="panel panel-body"><h3>My Placement</h3>';
    senior_table([['Grade'=>$learner['GradeNumber'], 'Class'=>$learner['Class'], 'Pathway'=>$learner['Pathway']??'Unassigned', 'Track'=>$learner['Track']??'', 'Combination'=>$learner['Combination']??($learner['AllocationId']?'Individual selection':'')]]);
    echo '<h3>My Subjects</h3>';
    $subjects = senior_learner_subjects($dbh, $student['StudentId'], $f['year'], $learner['ClassId']);
    senior_table(array_map(function($row) { unset($row['id']); return $row; }, $subjects));
    echo '</div>';
} } } ?>
</div></body></html>
