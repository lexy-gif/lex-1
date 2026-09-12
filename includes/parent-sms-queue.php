<?php
require_once __DIR__.'/cbe-academics.php';require_once __DIR__.'/sms.php';
function parent_sms_process($db,$limit=25,$sender=null) {
    if (!$sender && !filter_var(getenv('AFRICASTALKING_SMS_ENABLED')?:'false',FILTER_VALIDATE_BOOLEAN)) return 0;
    $sender=$sender?:'send_africastalking_sms'; $processed=0;
    for($i=0;$i<max(1,min(100,$limit));$i++) {
        $db->beginTransaction();
        try {
            $row=cbe_one($db,"SELECT * FROM tblparentsms WHERE Status='pending' ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED");
            if(!$row){$db->commit();break;}
            $guardian=cbe_one($db,"SELECT u.ParentPhone FROM tblusers u JOIN tblparentstudents ps ON ps.ParentId=u.id AND ps.StudentId=? AND ps.Status=1 AND ps.NotifyResults=1 JOIN tblstudents s ON s.StudentId=ps.StudentId AND s.Status=1 WHERE u.id=? AND u.Role='parent' AND u.Status=1",[$row['StudentId'],$row['ParentId']]);
            if(!$guardian || normalize_phone_number($guardian['ParentPhone']??'')!==$row['Destination']) {
                academic_query($db,"UPDATE tblparentsms SET Status='skipped',ErrorMessage='Guardian access, notification preference or contact changed.' WHERE id=?",[$row['id']]);$db->commit();continue;
            }
            academic_query($db,"UPDATE tblparentsms SET Status='processing',Attempts=Attempts+1,AttemptedAt=NOW() WHERE id=?",[$row['id']]);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        // Claim is committed before I/O. An interrupted attempt is never automatically resent.
        try{$result=$sender($row['Destination'],$row['Message']);}
        catch(Throwable $e){error_log($e->getMessage());$result=['success'=>false,'uncertain'=>true,'message'=>'Provider outcome unknown.'];}
        $status=!empty($result['success'])?'accepted':(!empty($result['skipped'])?'skipped':(!empty($result['uncertain'])?'uncertain':'failed'));
        academic_query($db,'UPDATE tblparentsms SET Status=?,ProviderReference=?,ProviderResponse=?,ErrorMessage=? WHERE id=?',
            [$status,$result['reference']??null,json_encode($result['response']??null),$status==='accepted'?null:substr((string)($result['message']??'Provider rejected message.'),0,500),$row['id']]);
        $processed++;
    }
    return $processed;
}
