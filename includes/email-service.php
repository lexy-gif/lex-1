<?php
include_once(__DIR__ . '/notification-service.php');

function email_service_enabled()
{
    return getenv('MAIL_ENABLED') === 'true';
}

function email_service_process_queue($dbh, $limit = 20)
{
    $query = $dbh->prepare("SELECT d.id, d.Destination, n.Title, n.Message
                            FROM tblnotificationdeliveries d
                            LEFT JOIN tblteachernotifications n ON n.id = d.NotificationId
                            WHERE d.Channel = 'EMAIL' AND d.Status IN ('PENDING','RETRYING')
                            ORDER BY d.CreationDate ASC
                            LIMIT " . (int)$limit);
    $query->execute();

    foreach($query->fetchAll(PDO::FETCH_OBJ) as $delivery) {
        if(!email_service_enabled()) {
            $update = $dbh->prepare("UPDATE tblnotificationdeliveries
                                     SET Status = 'SKIPPED', ErrorMessage = 'MAIL_ENABLED is not true.'
                                     WHERE id = :id");
            $update->execute(array(':id' => $delivery->id));
            continue;
        }

        $headers = "From: " . notification_mail_from_name() . " <" . notification_mail_from() . ">\r\n";
        $sent = @mail($delivery->Destination, $delivery->Title, $delivery->Message, $headers);

        if($sent) {
            $update = $dbh->prepare("UPDATE tblnotificationdeliveries SET Status = 'SENT', SentAt = NOW() WHERE id = :id");
            $update->execute(array(':id' => $delivery->id));
        } else {
            $update = $dbh->prepare("UPDATE tblnotificationdeliveries
                                     SET Status = 'FAILED', FailedAt = NOW(), RetryCount = RetryCount + 1, ErrorMessage = 'PHP mail() returned false.'
                                     WHERE id = :id");
            $update->execute(array(':id' => $delivery->id));
        }
    }
}
?>
