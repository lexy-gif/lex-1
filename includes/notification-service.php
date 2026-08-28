<?php
function notification_normalize_email($email)
{
    $email = trim((string)$email);
    return $email === '' ? null : strtolower($email);
}

function notification_mail_from()
{
    return getenv('MAIL_FROM_ADDRESS') ?: 'noreply@school-domain.example';
}

function notification_mail_from_name()
{
    return getenv('MAIL_FROM_NAME') ?: 'School SRMS';
}

function notification_queue_email($dbh, $notificationId, $userId, $destination)
{
    if(!$destination) {
        $sql = "INSERT INTO tblnotificationdeliveries(NotificationId, UserId, Channel, Destination, Status, ErrorMessage)
                VALUES(:notificationid, :userid, 'EMAIL', NULL, 'SKIPPED', 'Teacher email address is not available.')";
        $query = $dbh->prepare($sql);
        $query->execute(array(':notificationid' => $notificationId, ':userid' => $userId));
        return;
    }

    $sql = "INSERT INTO tblnotificationdeliveries(NotificationId, UserId, Channel, Destination, Status)
            VALUES(:notificationid, :userid, 'EMAIL', :destination, 'PENDING')";
    $query = $dbh->prepare($sql);
    $query->execute(array(':notificationid' => $notificationId, ':userid' => $userId, ':destination' => $destination));
}

function notification_create($dbh, $userId, $title, $message, $options = array())
{
    $category = $options['category'] ?? 'SYSTEM';
    $type = $options['type'] ?? $category;
    $classId = $options['class_id'] ?? null;
    $subjectId = $options['subject_id'] ?? null;
    $relatedType = $options['related_entity_type'] ?? null;
    $relatedId = $options['related_entity_id'] ?? null;
    $actionUrl = $options['action_url'] ?? null;
    $sendEmail = array_key_exists('send_email', $options) ? (bool)$options['send_email'] : true;

    $pref = $dbh->prepare("SELECT InAppEnabled, EmailEnabled FROM tblnotificationpreferences WHERE UserId = :userid AND Category = :category LIMIT 1");
    $pref->execute(array(':userid' => $userId, ':category' => $category));
    $preference = $pref->fetch(PDO::FETCH_OBJ);
    $inAppEnabled = !$preference || (int)$preference->InAppEnabled === 1;
    $emailEnabled = (!$preference || (int)$preference->EmailEnabled === 1) && $sendEmail;

    if(!$inAppEnabled && !$emailEnabled) {
        return null;
    }

    $notificationId = null;
    if($inAppEnabled) {
        $sql = "INSERT INTO tblteachernotifications(TeacherId, ClassId, Type, Category, SubjectId, RelatedEntityType, RelatedEntityId, ActionUrl, Title, Message)
                VALUES(:teacherid, :classid, :type, :category, :subjectid, :relatedtype, :relatedid, :actionurl, :title, :message)";
        $query = $dbh->prepare($sql);
        $query->execute(array(
            ':teacherid' => $userId,
            ':classid' => $classId,
            ':type' => $type,
            ':category' => $category,
            ':subjectid' => $subjectId,
            ':relatedtype' => $relatedType,
            ':relatedid' => $relatedId,
            ':actionurl' => $actionUrl,
            ':title' => $title,
            ':message' => $message
        ));
        $notificationId = $dbh->lastInsertId();
    }

    if($emailEnabled) {
        $userQuery = $dbh->prepare("SELECT Email FROM tblusers WHERE id = :userid LIMIT 1");
        $userQuery->execute(array(':userid' => $userId));
        $destination = notification_normalize_email($userQuery->fetchColumn());
        notification_queue_email($dbh, $notificationId, $userId, $destination);
    }

    return $notificationId;
}

function notification_unread_count($dbh, $userId)
{
    $query = $dbh->prepare("SELECT COUNT(*) FROM tblteachernotifications WHERE TeacherId = :teacherid AND ReadAt IS NULL");
    $query->execute(array(':teacherid' => $userId));
    return (int)$query->fetchColumn();
}

function notification_recent($dbh, $userId, $limit = 5)
{
    $query = $dbh->prepare("SELECT id, Title, Message, Category, ActionUrl, ReadAt, CreationDate
                            FROM tblteachernotifications
                            WHERE TeacherId = :teacherid
                            ORDER BY CreationDate DESC
                            LIMIT " . (int)$limit);
    $query->execute(array(':teacherid' => $userId));
    return $query->fetchAll(PDO::FETCH_OBJ);
}

function notification_mark_read($dbh, $userId, $notificationId)
{
    $query = $dbh->prepare("UPDATE tblteachernotifications
                            SET IsRead = 1, ReadAt = COALESCE(ReadAt, NOW())
                            WHERE id = :id AND TeacherId = :teacherid");
    $query->execute(array(':id' => $notificationId, ':teacherid' => $userId));
    return $query->rowCount() > 0;
}

function notification_mark_all_read($dbh, $userId)
{
    $query = $dbh->prepare("UPDATE tblteachernotifications
                            SET IsRead = 1, ReadAt = COALESCE(ReadAt, NOW())
                            WHERE TeacherId = :teacherid AND ReadAt IS NULL");
    $query->execute(array(':teacherid' => $userId));
}
?>
