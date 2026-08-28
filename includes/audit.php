<?php
function audit_log($dbh, $action, $entityType = null, $entityId = null, $details = null)
{
    $actor = $_SESSION['alogin'] ?? $_SESSION['teacher_username'] ?? 'system';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    try {
        $sql = "INSERT INTO tblauditlog(Actor, Action, EntityType, EntityId, Details, IpAddress)
                VALUES(:actor, :action, :entitytype, :entityid, :details, :ipaddress)";
        $query = $dbh->prepare($sql);
        $query->bindParam(':actor', $actor, PDO::PARAM_STR);
        $query->bindParam(':action', $action, PDO::PARAM_STR);
        $query->bindParam(':entitytype', $entityType, PDO::PARAM_STR);
        $query->bindParam(':entityid', $entityId, PDO::PARAM_STR);
        $query->bindParam(':details', $details, PDO::PARAM_STR);
        $query->bindParam(':ipaddress', $ipAddress, PDO::PARAM_STR);
        $query->execute();
    } catch (Exception $e) {
        error_log('Audit log failed: ' . $e->getMessage());
    }
}
?>
