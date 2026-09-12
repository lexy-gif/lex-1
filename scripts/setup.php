<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/config.php';
require __DIR__.'/../includes/security.php';
require __DIR__.'/../includes/dean-account.php';
$table=dean_account_table($dbh);
if($dbh->query("SELECT COUNT(*) FROM `$table`")->fetchColumn()) { echo "A Dean account already exists. Setup made no changes.\n";exit; }
$username=getenv('SETUP_DEAN_USERNAME')?:'';$password=getenv('SETUP_DEAN_PASSWORD')?:'';
if(!preg_match('/^[a-zA-Z0-9_.@-]{3,100}$/D',$username))throw new DomainException('Set SETUP_DEAN_USERNAME to a valid username.');
$hash=security_password($password);
$year=getenv('SETUP_ACADEMIC_YEAR')?:date('Y');if(!preg_match('/^20[0-9]{2}$/D',$year))throw new DomainException('Set a four-digit academic year.');
$dbh->beginTransaction();
try{
    $q=$dbh->prepare('INSERT IGNORE INTO tblacademicyears(AcademicYear,IsActive) VALUES(?,1)');$q->execute([$year]);
    $q=$dbh->prepare("INSERT IGNORE INTO tblterms(AcademicYearId,TermName,IsActive) SELECT id,'Term 1',1 FROM tblacademicyears WHERE AcademicYear=?");$q->execute([$year]);
    $dbh->commit();
}catch(Throwable $e){if($dbh->inTransaction())$dbh->rollBack();throw $e;}
// Each existing migration has its own idempotence marker and transaction.
foreach(['migrate-teacher-management.php','migrate-cbe-academics.php','migrate-senior-school.php','migrate-parent-results.php','migrate-senior-integrity.php'] as $file){
    $process=proc_open([PHP_BINARY,__DIR__.'/'.$file],[0=>STDIN,1=>STDOUT,2=>STDERR],$pipes);
    if(!is_resource($process)||proc_close($process)!==0)throw new RuntimeException('Setup migration failed: '.$file);
}
$dbh->beginTransaction();
try{
    $dbh->exec("INSERT INTO tblclasses(ClassName,ClassNameNumeric,GradeId,Section) SELECT g.Name,g.GradeNumber,g.id,'A' FROM tblgrades g WHERE g.GradeNumber IN (10,11,12) AND NOT EXISTS(SELECT 1 FROM tblclasses c WHERE c.GradeId=g.id AND c.Section='A')");
    $q=$dbh->prepare("INSERT INTO `$table`(UserName,Password) VALUES(?,?)");$q->execute([$username,$hash]);
    $dbh->commit();
    echo "Senior School installed. Configure assessment descriptors and verify curriculum defaults in the Dean workspace.\n";
}catch(Throwable $e){if($dbh->inTransaction())$dbh->rollBack();throw $e;}
