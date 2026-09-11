<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../includes/config.php';
$migration = 'senior-school-v1';
$q = $dbh->prepare('SELECT 1 FROM tblschemamigrations WHERE Name=?');
$q->execute([$migration]);
if ($q->fetchColumn()) { echo "Senior School migration already applied.\n"; exit; }
$q->execute(['cbe-academic-module-v1']);
if (!$q->fetchColumn()) throw new RuntimeException('Apply migrate-cbe-academics.php first.');
if (!$dbh->query("SELECT GET_LOCK('srms_senior_migration',30)")->fetchColumn()) throw new RuntimeException('Another migration is running.');

function senior_migration_column($db, $table, $column, $definition) {
    $q = $db->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $q->execute([$table, $column]);
    if (!$q->fetchColumn()) $db->exec("ALTER TABLE $table ADD $column $definition");
}
function senior_migration_query($db, $sql, $values=[]) {
    $q=$db->prepare($sql); $q->execute($values); return $q;
}
try {
    // A second invocation may have waited while the first migration completed.
    $q->execute([$migration]);
    if ($q->fetchColumn()) { echo "Senior School migration already applied.\n"; return; }
    senior_migration_column($dbh,'tblsubjects','SeniorType',"ENUM('core','elective') NULL");
    senior_migration_column($dbh,'tblsubjects','CoreRole',"ENUM('common','language','mathematics') NULL");
    senior_migration_column($dbh,'tblpathways','MathSubjectId','INT NULL REFERENCES tblsubjects(id)');
    senior_migration_column($dbh,'tblpathways','ElectiveCount','INT NULL');
    senior_migration_column($dbh,'tblacademicsettings','SeniorElectiveCount','INT NOT NULL DEFAULT 3');
    senior_migration_column($dbh,'tblacademicsettings','SeniorLanguageSubjectId','INT NULL REFERENCES tblsubjects(id)');
    senior_migration_column($dbh,'tblacademicsettings','SeniorMathSubjectId','INT NULL REFERENCES tblsubjects(id)');
    foreach (['tblstudents','tblstudentpathways'] as $table) {
        senior_migration_column($dbh,$table,'SeniorLanguageSubjectId','INT NULL REFERENCES tblsubjects(id)');
        senior_migration_column($dbh,$table,'SeniorMathSubjectId','INT NULL REFERENCES tblsubjects(id)');
    }
    senior_migration_column($dbh,'tblstudentpathways','PathwayId','INT NULL REFERENCES tblpathways(id)');
    senior_migration_column($dbh,'tblstudentpathways','TrackId','INT NULL REFERENCES tblpathwaytracks(id)');
    senior_migration_column($dbh,'tblstudentpathways','GradeId','INT NULL REFERENCES tblgrades(id)');
    $dbh->exec('ALTER TABLE tblstudentpathways MODIFY CombinationId INT NULL');
    senior_migration_column($dbh,'tblstudentsubjects','AssignmentSource',"ENUM('manual','core','elective') NOT NULL DEFAULT 'manual'");
    foreach (['tblclasstimetableentries','tblexamtimetableentries'] as $table)
        senior_migration_column($dbh,$table,'PathwayId','INT NULL REFERENCES tblpathways(id)');
    $dbh->exec(file_get_contents(__DIR__.'/../database-updates/senior-school.sql'));
    $defaults=json_decode(file_get_contents(__DIR__.'/../database-updates/senior-school-defaults.json'),true,512,JSON_THROW_ON_ERROR);
    $dbh->beginTransaction();
    // Reuse the existing Arts pathway ID, preserving every reference to it.
    senior_migration_query($dbh,"UPDATE tblpathways SET Name='Arts and Sports Science' WHERE Name='Arts & Sports' AND NOT EXISTS (SELECT 1 FROM (SELECT Name FROM tblpathways) existing WHERE existing.Name='Arts and Sports Science')");
    $subjects=[];
    $seed=function($name,$code,$type,$role=null) use ($dbh,$defaults,&$subjects) {
        $names=[$name]; if(isset($defaults['aliases'][$name]))$names[]=$defaults['aliases'][$name];
        $rows=senior_migration_query($dbh,'SELECT id FROM tblsubjects WHERE SubjectName IN ('.implode(',',array_fill(0,count($names),'?')).') ORDER BY id',$names)->fetchAll(PDO::FETCH_COLUMN);
        if(count($rows)>1) throw new RuntimeException('Resolve ambiguous existing subjects before migrating: '.$name);
        $id=$rows[0]??null;
        if(!$id) {
            senior_migration_query($dbh,'INSERT INTO tblsubjects(SubjectName,SubjectCode,SeniorType,CoreRole) VALUES(?,?,?,?)',[$name,$code,$type,$role]);
            $id=(int)$dbh->lastInsertId();
        } else senior_migration_query($dbh,'UPDATE tblsubjects SET SeniorType=COALESCE(SeniorType,?),CoreRole=COALESCE(CoreRole,?) WHERE id=?',[$type,$role,$id]);
        senior_migration_query($dbh,'INSERT IGNORE INTO tblsubjectgrades(SubjectId,GradeId) SELECT ?,id FROM tblgrades WHERE GradeNumber BETWEEN 10 AND 12',[$id]);
        return $subjects[$name]=(int)$id;
    };
    foreach($defaults['core'] as $s)$seed($s['name'],$s['code'],'core',$s['role']);
    foreach($defaults['electives'] as $path=>$names) {
        senior_migration_query($dbh,'INSERT IGNORE INTO tblpathways(Name) VALUES(?)',[$path]);
        $pathway=(int)senior_migration_query($dbh,'SELECT id FROM tblpathways WHERE Name=?',[$path])->fetchColumn();
        foreach($defaults['tracks'][$path] as $track)senior_migration_query($dbh,'INSERT IGNORE INTO tblpathwaytracks(PathwayId,Name) VALUES(?,?)',[$pathway,$track]);
        senior_migration_query($dbh,'UPDATE tblpathways SET MathSubjectId=COALESCE(MathSubjectId,?) WHERE id=?',[$subjects[$path==='STEM'?'Core Mathematics':'Essential Mathematics'],$pathway]);
        foreach($names as $name) {
            $id=$seed($name,'SR-'.strtoupper(substr(hash('sha256',$name),0,8)),'elective');
            senior_migration_query($dbh,'INSERT IGNORE INTO tblpathwaysubjects(PathwayId,SubjectId) VALUES(?,?)',[$pathway,$id]);
        }
    }
    senior_migration_query($dbh,'UPDATE tblacademicsettings SET SeniorLanguageSubjectId=COALESCE(SeniorLanguageSubjectId,?),SeniorMathSubjectId=COALESCE(SeniorMathSubjectId,?) WHERE id=1',[$subjects['Kiswahili'],$subjects['Core Mathematics']]);
    // Snapshot existing allocations. No learner registration or result is replaced.
    $dbh->exec('UPDATE tblstudentpathways a JOIN tblschoolcombinations co ON co.id=a.CombinationId JOIN tblpathwaytracks t ON t.id=co.TrackId JOIN tblclasses c ON c.id=a.ClassId SET a.PathwayId=COALESCE(a.PathwayId,t.PathwayId),a.TrackId=COALESCE(a.TrackId,t.id),a.GradeId=COALESCE(a.GradeId,c.GradeId)');
    // Retain the latest placement's class for each earlier year in historical views.
    // All allocation rows remain intact, including earlier replacements that year.
    $dbh->exec("INSERT IGNORE INTO tblstudentenrollments(StudentId,AcademicYearId,ClassId,GradeId,RecordedBy)
        SELECT a.StudentId,a.AcademicYearId,a.ClassId,a.GradeId,'senior-school-migration'
        FROM tblstudentpathways a WHERE a.GradeId IS NOT NULL AND NOT EXISTS (
            SELECT 1 FROM tblstudentpathways newer WHERE newer.StudentId=a.StudentId AND newer.AcademicYearId=a.AcademicYearId
            AND (newer.Status>a.Status OR (newer.Status=a.Status AND newer.id>a.id)))");
    $dbh->exec("INSERT IGNORE INTO tblpathwayallocationsubjects(AllocationId,SubjectId,SubjectType) SELECT a.id,cs.SubjectId,COALESCE(s.SeniorType,'elective') FROM tblstudentpathways a JOIN tblschoolcombinationsubjects cs ON cs.CombinationId=a.CombinationId JOIN tblsubjects s ON s.id=cs.SubjectId");
    senior_migration_query($dbh,'INSERT INTO tblschemamigrations(Name) VALUES(?)',[$migration]);
    $dbh->commit();
    echo "Senior School schema and configurable defaults installed. Existing learners and results retained.\n";
} catch(Throwable $e) { if($dbh->inTransaction())$dbh->rollBack(); throw $e; }
finally { $dbh->query("SELECT RELEASE_LOCK('srms_senior_migration')"); }
