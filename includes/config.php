<?php
require_once __DIR__.'/bootstrap.php';
foreach (['DB_HOST','DB_USER','DB_PASS','DB_NAME'] as $key) {
    $value=getenv($key);
    if ($value===false || $value==='') throw new RuntimeException('Missing required environment variable: '.$key);
    define($key,$value);
}
// Establish database connection.
try
{
$dbh = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, array(
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4'",
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
));
}
catch (PDOException $e)
{
throw $e;
}
// Every legacy and current Dean route loads this file before reading the session.
if (PHP_SAPI !== 'cli' && !empty($_SESSION['alogin'])) {
    require_once __DIR__.'/dean-account.php';
    $sessionDean = dean_find_by_username($dbh, $_SESSION['alogin']);
    if (!$sessionDean || !hash_equals(hash('sha256', $sessionDean->Password), (string)($_SESSION['dean_password_version'] ?? ''))) {
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: admin-login.php', true, 303);
        exit;
    }
}
?>
