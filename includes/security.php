<?php
function security_password($value) {
    if (!is_string($value) || strlen($value)<12 || strlen($value)>72) {
        throw new DomainException('Use a password of 12 to 72 characters.');
    }
    return password_hash($value, PASSWORD_DEFAULT);
}
function security_login_key($role, $username) {
    return hash('sha256', $role.'|'.strtolower($username).'|'.($_SERVER['REMOTE_ADDR']??'cli'));
}
function security_login_allowed($db, $key) {
    $q=$db->prepare('SELECT LockedUntil FROM tblloginattempts WHERE AttemptKey=?'); $q->execute([$key]);
    $until=$q->fetchColumn(); return !$until || strtotime($until)<=time();
}
function security_login_result($db, $key, $success) {
    if ($success) { $db->prepare('DELETE FROM tblloginattempts WHERE AttemptKey=?')->execute([$key]); return; }
    $db->prepare('INSERT INTO tblloginattempts(AttemptKey,Failures,WindowStarted) VALUES(?,1,NOW())
        ON DUPLICATE KEY UPDATE Failures=IF(WindowStarted<DATE_SUB(NOW(),INTERVAL 15 MINUTE),1,Failures+1),
        WindowStarted=IF(WindowStarted<DATE_SUB(NOW(),INTERVAL 15 MINUTE),NOW(),WindowStarted),
        LockedUntil=IF(Failures>=5,DATE_ADD(NOW(),INTERVAL 15 MINUTE),NULL)')->execute([$key]);
    $db->exec('DELETE FROM tblloginattempts WHERE WindowStarted<DATE_SUB(NOW(),INTERVAL 1 DAY)');
}
function security_login_session($identity) {
    $_SESSION=[]; session_regenerate_id(true);
    $_SESSION=$identity+['last_activity'=>time(),'authenticated_at'=>time()];
}
