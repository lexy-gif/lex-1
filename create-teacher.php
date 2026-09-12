<?php
require_once 'includes/bootstrap.php';
require_once 'includes/dean-auth.php';
require_dean();
header("Location: manage-teachers.php#create-teacher");
exit;
?>
