<?php
require_once __DIR__.'/cbe-academics.php';
require_once __DIR__.'/security.php';
function require_parent($allowPasswordChange=false) {
    global $dbh;
    $id=(int)($_SESSION['parent_user_id']??0);
    $parent=$id?cbe_one($dbh,"SELECT * FROM tblusers WHERE id=? AND Role='parent' AND Status=1",[$id]):null;
    if (!$parent || (int)$parent['SessionVersion']!==(int)($_SESSION['parent_session_version']??0)) {
        unset($_SESSION['parent_user_id'],$_SESSION['parent_session_version']);
        header('Location: parent-login.php'); exit;
    }
    if ($parent['MustChangePassword'] && !$allowPasswordChange) { header('Location: parent-profile.php'); exit; }
    header('Cache-Control: no-store');
    return $parent;
}
function parent_child($db, $parent, $student) {
    $row=cbe_one($db,'SELECT s.*,c.ClassName,c.Section,c.ClassNameNumeric GradeNumber,ps.Relationship
        FROM tblparentstudents ps JOIN tblstudents s ON s.StudentId=ps.StudentId AND s.Status=1
        JOIN tblclasses c ON c.id=s.ClassId
        WHERE ps.ParentId=? AND ps.StudentId=? AND ps.Status=1 AND c.ClassNameNumeric IN (10,11,12)',[$parent,$student]);
    if (!$row) { http_response_code(404); exit('Student record unavailable.'); }
    return $row;
}
function parent_children($db, $parent) {
    return cbe_rows($db,'SELECT s.StudentId,s.StudentName,s.RollId,c.ClassName,c.Section
        FROM tblparentstudents ps JOIN tblstudents s ON s.StudentId=ps.StudentId AND s.Status=1
        JOIN tblclasses c ON c.id=s.ClassId WHERE ps.ParentId=? AND ps.Status=1
        AND c.ClassNameNumeric IN (10,11,12) ORDER BY s.StudentName LIMIT 100',[$parent]);
}
