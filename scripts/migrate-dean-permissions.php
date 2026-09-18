<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/dean-account.php';
$options=getopt('',['administrator:','table:']);
$name=$options['administrator']??'';$table=$options['table']??dean_account_table($dbh);
if(!is_string($name)||$name==='')throw new DomainException('Specify --administrator=EXISTING_USERNAME. No credentials will be created.');
$admin=dean_find_by_username($dbh,$name,$table);
if(!$admin)throw new DomainException('The explicitly selected existing administrator was not found. No changes made.');
$already=false;
try{$already=(bool)$dbh->query("SELECT 1 FROM tblschemamigrations WHERE Name='dean-permissions-v1'")->fetchColumn();}catch(PDOException $e){if(($e->errorInfo[1]??null)!==1146)throw $e;}
if($already){echo "Dean permissions migration already applied. Manage subsequent roles through Staff Permissions.\n";exit;}
$dbh->exec(file_get_contents(__DIR__.'/../database-updates/dean-permissions.sql'));
$dbh->beginTransaction();
try {
    foreach(['tbldean','admin'] as $source) {
        $exists=$dbh->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$exists->execute([$source]);if(!$exists->fetchColumn())continue;
        foreach($dbh->query("SELECT id,UserName FROM `$source`") as $row) {
            $role=$source==='admin'?'administrator':'dean';
            $q=$dbh->prepare('INSERT IGNORE INTO tblstaffroles(AccountTable,AccountId,RoleName,GrantedBy) VALUES(?,?,?,?)');$q->execute([$source,$row['id'],$role,'migration']);
            $dbh->prepare('INSERT INTO tblstaffpermissionaudit(Actor,AccountTable,AccountId,ChangeType,ChangeName,Reason) VALUES(?,?,?,?,?,?)')->execute(['migration',$source,$row['id'],'role_granted',$role,$source==='admin'?'Preserve legacy administrator identity':'Restrict legacy Dean to academic allowlist']);
        }
    }
    $dbh->prepare("INSERT IGNORE INTO tblstaffroles(AccountTable,AccountId,RoleName,GrantedBy) VALUES(?,?,'administrator','migration')")->execute([$table,$admin->id]);
    $dbh->prepare("INSERT INTO tblstaffpermissionaudit(Actor,AccountTable,AccountId,ChangeType,ChangeName,Reason) VALUES(?,? ,?,'role_granted','administrator',?)")->execute(['migration',$table,$admin->id,'Existing administrator explicitly selected by the operator; username '.$name]);
    $dbh->exec("INSERT INTO tblschemamigrations(Name) VALUES('dean-permissions-v1')");
    $dbh->commit();echo "Dean allowlist installed. Existing administrator preserved: ".$name.". No default delegations granted.\n";
} catch(Throwable $e){if($dbh->inTransaction())$dbh->rollBack();throw $e;}
