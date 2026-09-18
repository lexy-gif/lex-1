<?php
function require_dean()
{
    require_once __DIR__.'/config.php';
    if (empty($_SESSION['alogin'])) {
        header("Location: admin-login.php");
        exit;
    }
    if (PHP_SAPI !== 'cli') staff_require_route();
}

function dean_name()
{
    return $_SESSION['alogin'] ?? 'Dean of Studies';
}
?>
