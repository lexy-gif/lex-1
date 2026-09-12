<?php
require_once 'includes/bootstrap.php';
header('Content-Type: application/json');
require_once 'includes/config.php';
require_once 'includes/teacher-auth.php';
require_once 'includes/notification-service.php';

if(empty($_SESSION['teacher_user_id'])) {
    http_response_code(401);
    echo json_encode(array('count' => 0));
    exit;
}
require_teacher();

echo json_encode(array('count' => notification_unread_count($dbh, teacher_id())));
?>
