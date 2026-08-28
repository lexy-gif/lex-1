<?php
function require_dean()
{
    if (empty($_SESSION['alogin'])) {
        header("Location: admin-login.php");
        exit;
    }
}

function dean_name()
{
    return $_SESSION['alogin'] ?? 'Dean of Studies';
}
?>
