<?php
function require_class_teacher()
{
    if (empty($_SESSION['teacher_user_id']) || ($_SESSION['teacher_role'] ?? '') !== 'class_teacher') {
        header("Location: teacher-login.php");
        exit;
    }
    teacher_require_active_account();
}

function require_teacher()
{
    if (empty($_SESSION['teacher_user_id']) || !in_array(($_SESSION['teacher_role'] ?? ''), array('class_teacher','subject_teacher','head_of_department','exams_officer','deputy_dean'))) {
        header("Location: teacher-login.php");
        exit;
    }
    teacher_require_active_account();
}

function teacher_require_active_account()
{
    global $dbh;
    if(!isset($dbh) || empty($_SESSION['teacher_user_id'])) {
        return;
    }
    $query = $dbh->prepare("SELECT Status FROM tblusers WHERE id = :teacherid LIMIT 1");
    $query->execute(array(':teacherid' => (int)$_SESSION['teacher_user_id']));
    $status = $query->fetchColumn();
    if((int)$status !== 1) {
        session_unset();
        session_destroy();
        header("Location: teacher-login.php?deactivated=1");
        exit;
    }
}

function teacher_id()
{
    return (int)($_SESSION['teacher_user_id'] ?? 0);
}

function teacher_class_id()
{
    return (int)($_SESSION['teacher_class_id'] ?? 0);
}

function teacher_name()
{
    return $_SESSION['teacher_name'] ?? 'Class Teacher';
}

function teacher_role_label()
{
    return ucwords(str_replace('_', ' ', $_SESSION['teacher_role'] ?? 'teacher'));
}
?>
