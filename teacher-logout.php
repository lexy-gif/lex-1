<?php
require_once 'includes/bootstrap.php';
require_once 'includes/csrf.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit;}
csrf_require_valid($_POST['csrf_token']??'');
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();
header("Location: teacher-login.php");
exit;
?>
