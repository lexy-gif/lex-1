<?php
require_once __DIR__.'/academic-assignments.php';
function require_class_teacher()
{
    require_teacher();
    if (!teacher_class_id()) {
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
    $query = $dbh->prepare("SELECT Status,Role,SessionVersion,MustChangePassword FROM tblusers WHERE id = :teacherid LIMIT 1");
    $query->execute(array(':teacherid' => (int)$_SESSION['teacher_user_id']));
    $account = $query->fetch(PDO::FETCH_ASSOC);
    if(!$account || ((int)($_SESSION['teacher_session_version']??0)!==(int)$account['SessionVersion'] && PHP_SAPI!=='cli') || (int)$account['Status'] !== 1 || !in_array($account['Role'], ['class_teacher','subject_teacher','head_of_department','exams_officer','deputy_dean'],true)) {
        session_unset();
        session_destroy();
        header("Location: teacher-login.php?deactivated=1");
        exit;
    }
    $_SESSION['teacher_role'] = $account['Role'];
    if(!empty($account['MustChangePassword']) && isset($_SESSION['teacher_session_version']) && basename($_SERVER['SCRIPT_NAME']??'')!=='teacher-change-password.php') { header('Location: teacher-change-password.php'); exit; }
}

function teacher_id()
{
    return (int)($_SESSION['teacher_user_id'] ?? 0);
}

function teacher_class_id()
{
    global $dbh;
    if (isset($dbh) && academic_ready($dbh)) {
        return (int)academic_query($dbh,'SELECT a.ClassId FROM tblclassteacherassignments a JOIN tblusers u ON u.id=a.TeacherId AND u.Status=1 JOIN tblacademicyears y ON y.id=a.AcademicYearId AND y.IsActive=1 WHERE a.TeacherId=? AND a.Status=1 LIMIT 1',[teacher_id()])->fetchColumn();
    }
    return (int)($_SESSION['teacher_class_id'] ?? 0);
}

function teacher_name()
{
    return $_SESSION['teacher_name'] ?? 'Class Teacher';
}

function teacher_role_label()
{
    global $dbh;
    if (isset($dbh) && academic_ready($dbh)) {
        $labels=[];
        if(teacher_class_id()) $labels[]='Class Teacher';
        if(academic_query($dbh,'SELECT a.id FROM tblsubjectteacherassignments a JOIN tblacademicyears y ON y.id=a.AcademicYearId AND y.IsActive=1 WHERE a.TeacherId=? AND a.Status=1 AND (a.TermId IS NULL OR EXISTS (SELECT 1 FROM tblterms t WHERE t.id=a.TermId AND t.IsActive=1)) LIMIT 1',[teacher_id()])->fetchColumn()) $labels[]='Subject Teacher';
        return $labels?implode(' / ',$labels):'Teacher';
    }
    return ucwords(str_replace('_', ' ', $_SESSION['teacher_role'] ?? 'teacher'));
}
?>
