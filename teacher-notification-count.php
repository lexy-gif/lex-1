<?php
session_start();
header('Content-Type: application/json');
include('includes/config.php');
include('includes/teacher-auth.php');
include('includes/notification-service.php');

if(empty($_SESSION['teacher_user_id'])) {
    echo json_encode(array('count' => 0));
    exit;
}

echo json_encode(array('count' => notification_unread_count($dbh, teacher_id())));
?>
