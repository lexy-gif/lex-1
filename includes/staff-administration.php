<?php
require_once __DIR__.'/permissions.php';
function staff_change_access($db,$p) {
    staff_assert('security.manage');
    $table=$p['AccountTable']??'';$id=filter_var($p['AccountId']??0,FILTER_VALIDATE_INT);
    if(!in_array($table,['tbldean','admin'],true)||!$id)throw new DomainException('Select an existing staff identity.');
    $kind=$p['Kind']??'';$name=$p['Name']??'';$grant=($p['Grant']??'')==='1';$reason=trim($p['Reason']??'');
    if($reason===''||strlen($reason)>1000)throw new DomainException('Record an authorisation reason of up to 1000 characters.');
    $catalogue=$kind==='role'?array_keys(staff_role_permissions()):($kind==='permission'?staff_delegatable_permissions():[]);
    if(!in_array($name,$catalogue,true))throw new DomainException('Unknown role or delegatable permission.');
    $db->beginTransaction();
    try {
        $db->query("SELECT AccountId FROM tblstaffroles WHERE RoleName='administrator' FOR UPDATE")->fetchAll();
        $q=$db->prepare("SELECT id FROM `$table` WHERE id=? FOR UPDATE");$q->execute([$id]);if(!$q->fetchColumn())throw new DomainException('Staff account no longer exists.');
        $target=$kind==='role'?'tblstaffroles':'tblstaffpermissions';$column=$kind==='role'?'RoleName':'PermissionName';
        if(!$grant&&$kind==='role'&&$name==='administrator') {
            $q=$db->prepare("SELECT COUNT(*) FROM tblstaffroles WHERE RoleName='administrator' AND NOT(AccountTable=? AND AccountId=?)");$q->execute([$table,$id]);
            if(!(int)$q->fetchColumn())throw new DomainException('Keep at least one authorised administrator.');
        }
        $actor=staff_identity($db)['name'];
        if($grant) {
            $sql=$kind==='role'?"INSERT INTO $target(AccountTable,AccountId,$column,GrantedBy) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE GrantedBy=VALUES(GrantedBy)":"INSERT INTO $target(AccountTable,AccountId,$column,GrantedBy,Reason) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE GrantedBy=VALUES(GrantedBy),Reason=VALUES(Reason)";
            $params=[$table,$id,$name,$actor];if($kind==='permission')$params[]=$reason;$db->prepare($sql)->execute($params);
        } else $db->prepare("DELETE FROM $target WHERE AccountTable=? AND AccountId=? AND $column=?")->execute([$table,$id,$name]);
        $db->prepare('INSERT INTO tblstaffpermissionaudit(Actor,AccountTable,AccountId,ChangeType,ChangeName,Reason) VALUES(?,?,?,?,?,?)')->execute([$actor,$table,$id,$kind.($grant?'_granted':'_revoked'),$name,$reason]);
        $db->commit();unset($GLOBALS['staff_access']);
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
