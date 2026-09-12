<?php
// CLI-only identities for regression suites that exercise forms without login.
if (PHP_SAPI !== 'cli') exit;
require_once __DIR__.'/../includes/config.php';
function test_dean_session() {
    global $dbh;
    $name = 'fixture_'.session_id();
    if (!preg_match('/^fixture_[a-z0-9]{20,50}$/D', $name)) throw new RuntimeException('Invalid test session.');
    $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
    $dbh->prepare('INSERT INTO tbldean(UserName,Password) VALUES(?,?)')->execute([$name,$hash]);
    $_SESSION['dean_fixture'] = ['id'=>(int)$dbh->lastInsertId(),'name'=>$name,'hash'=>$hash];
    $_SESSION['alogin'] = $name;
    $_SESSION['dean_password_version'] = hash('sha256',$hash);
    $_SESSION['authenticated_at'] = time();
}
function test_session_cleanup() {
    global $dbh;
    $f = $_SESSION['dean_fixture'] ?? null;
    if (!$f || !preg_match('/^fixture_[a-z0-9]{20,50}$/D', $f['name'])) return;
    $dbh->prepare('DELETE FROM tbltimetableversions WHERE CreatedBy=?')->execute([$f['name']]);
    $dbh->prepare('DELETE FROM tblauditlog WHERE Actor=?')->execute([$f['name']]);
    $dbh->prepare('DELETE FROM tbldean WHERE id=? AND UserName=? AND Password=?')->execute([$f['id'],$f['name'],$f['hash']]);
}
