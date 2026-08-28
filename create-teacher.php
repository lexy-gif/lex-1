<?php
session_start();
include('includes/dean-auth.php');
require_dean();
header("Location: manage-teachers.php#create-teacher");
exit;
?>
