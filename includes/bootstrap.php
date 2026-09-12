<?php
if (defined('SRMS_BOOTSTRAPPED')) return;
define('SRMS_BOOTSTRAPPED',true);
date_default_timezone_set(getenv('APP_TIMEZONE')?:'Africa/Nairobi');
error_reporting(E_ALL);
ini_set('display_errors','0'); ini_set('log_errors','1');
set_exception_handler(function(Throwable $e) {
    error_log((string)$e);
    if (PHP_SAPI==='cli') { fwrite(STDERR,"Operation failed; check the error log.\n"); exit(1); }
    while (ob_get_level()) ob_end_clean();
    http_response_code(500); echo 'Unable to complete this request. Please try again or contact the school.';
});
if (PHP_SAPI!=='cli') {
    ob_start();
    header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin'); header('Cache-Control: no-store');
    if (session_status()!==PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
        session_set_cookie_params(['httponly'=>true,'secure'=>filter_var(getenv('SESSION_COOKIE_SECURE')?:'false',FILTER_VALIDATE_BOOLEAN),'samesite'=>'Lax','path'=>'/']);
        session_start();
    }
    $idle=max(300,(int)(getenv('SESSION_IDLE_SECONDS')?:1800));
    if ((!empty($_SESSION['last_activity']) && time()-$_SESSION['last_activity']>$idle)
        || (!empty($_SESSION['authenticated_at']) && time()-$_SESSION['authenticated_at']>43200)) {
        $_SESSION=[]; session_regenerate_id(true);
    }
    $_SESSION['last_activity']=time();
}
