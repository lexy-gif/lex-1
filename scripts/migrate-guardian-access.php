<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/config.php';
$name='guardian-access-v1';
$q=$dbh->prepare('SELECT 1 FROM tblschemamigrations WHERE Name=?');
$q->execute(['parent-results-v1']);
if(!$q->fetchColumn())throw new RuntimeException('Apply migrate-parent-results.php first.');
if(!$dbh->query("SELECT GET_LOCK('srms_guardian_access_migration',30)")->fetchColumn())throw new RuntimeException('Another migration is running.');
try {
    $q->execute([$name]);
    if($q->fetchColumn()){echo "Guardian access migration already applied.\n";return;}
    $dbh->beginTransaction();
    $dbh->exec(file_get_contents(__DIR__.'/../database-updates/guardian-access.sql'));
    $dbh->commit();
    // DDL commits independently. A rerun can safely finish after interruption.
    $constraint=$dbh->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='tblusers' AND CONSTRAINT_NAME='chk_student_accounts_disabled'")->fetchColumn();
    if(!$constraint)$dbh->exec("ALTER TABLE tblusers ADD CONSTRAINT chk_student_accounts_disabled CHECK (Role<>'student' OR Status=0)");
    $dbh->prepare('INSERT INTO tblschemamigrations(Name) VALUES(?)')->execute([$name]);
    echo "Student accounts disabled; verified guardian links, learner records and historical user references retained.\n";
} catch(Throwable $e){if($dbh->inTransaction())$dbh->rollBack();throw $e;}
finally{$dbh->query("SELECT RELEASE_LOCK('srms_guardian_access_migration')");}
