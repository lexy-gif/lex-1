<?php
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
require __DIR__.'/../includes/config.php';
$name='cbe-academic-module-v1';
$q=$dbh->prepare('SELECT 1 FROM tblschemamigrations WHERE Name=?');$q->execute([$name]);
if($q->fetchColumn()) {echo "Already applied.\n";exit;}
$dbh->exec(file_get_contents(__DIR__.'/../database-updates/cbe-academic-module.sql'));
function cbe_add_column($db,$table,$column,$definition) {
    $q=$db->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$q->execute([$table,$column]);
    if(!$q->fetchColumn()) $db->exec("ALTER TABLE $table ADD $column $definition");
}
cbe_add_column($dbh,'tblclasses','GradeId','INT NULL REFERENCES tblgrades(id)');
cbe_add_column($dbh,'tblsubjects','Status','TINYINT NOT NULL DEFAULT 1');
cbe_add_column($dbh,'tblsubjects','DepartmentId','INT NULL REFERENCES tbldepartments(id)');
cbe_add_column($dbh,'tblexams','EntryLocked','TINYINT NOT NULL DEFAULT 0');
$dbh->beginTransaction();
try {
    for($n=10;$n<=12;$n++) {
        $level='Senior School';
        $q=$dbh->prepare('INSERT IGNORE INTO tblgrades(SchoolLevelId,Name,GradeNumber,PathwayEntry,GuidanceEligible) SELECT id,?,?,?,? FROM tblschoollevels WHERE Name=?');
        $q->execute(['Grade '.$n,$n,$n===10?1:0,$n>=9?1:0,$level]);
    }
    $dbh->exec('UPDATE tblclasses c JOIN tblgrades g ON g.GradeNumber=c.ClassNameNumeric SET c.GradeId=g.id WHERE c.GradeId IS NULL');
    $dbh->exec('INSERT IGNORE INTO tblsubjectgrades(SubjectId,GradeId) SELECT DISTINCT sc.SubjectId,c.GradeId FROM tblsubjectcombination sc JOIN tblclasses c ON c.id=sc.ClassId WHERE c.GradeId IS NOT NULL');
    $dbh->exec('INSERT IGNORE INTO tblexaminvigilators(SessionId,TeacherId) SELECT id,InvigilatorId FROM tblexamtimetableentries WHERE InvigilatorId IS NOT NULL');
    $dbh->prepare('INSERT INTO tblschemamigrations(Name) VALUES(?)')->execute([$name]);
    $dbh->commit();echo "CBE academic schema applied; existing identities and results preserved.\n";
}catch(Throwable $e){$dbh->rollBack();throw $e;}
