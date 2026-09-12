<?php
require_once 'includes/bootstrap.php'; require_once 'includes/csrf.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); header('Allow: POST'); exit; }
csrf_require_valid($_POST['csrf_token']??'');
$_SESSION=[];
$cookie=session_get_cookie_params(); setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>$cookie['path'],'secure'=>$cookie['secure'],'httponly'=>true,'samesite'=>'Lax']);
session_destroy(); header('Location: parent-login.php'); exit;
