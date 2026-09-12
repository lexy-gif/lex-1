<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../includes/config.php';
$name = 'parent-results-v1';
$query = $dbh->prepare('SELECT 1 FROM tblschemamigrations WHERE Name=?');
$query->execute([$name]);
if ($query->fetchColumn()) { echo "Parent results migration already applied.\n"; exit; }
$query->execute(['senior-school-v1']);
if (!$query->fetchColumn()) throw new RuntimeException('Apply the Senior School migration first.');
if (!$dbh->query("SELECT GET_LOCK('srms_parent_migration',30)")->fetchColumn()) throw new RuntimeException('Another migration is running.');
function parent_migration_column($db, $table, $column, $definition) {
    $q=$db->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $q->execute([$table,$column]);
    if (!$q->fetchColumn()) $db->exec("ALTER TABLE $table ADD $column $definition");
}
try {
    $query->execute([$name]);
    if ($query->fetchColumn()) { echo "Parent results migration already applied.\n"; return; }
    parent_migration_column($dbh,'tblusers','MustChangePassword','TINYINT NOT NULL DEFAULT 0');
    parent_migration_column($dbh,'tblusers','SessionVersion','INT NOT NULL DEFAULT 1');
    parent_migration_column($dbh,'tblexams','MaximumMarks','DECIMAL(8,2) NOT NULL DEFAULT 100');
    $dbh->exec('ALTER TABLE tblresult MODIFY marks DECIMAL(8,2) NOT NULL');
    $dbh->exec(file_get_contents(__DIR__.'/../database-updates/parent-results.sql'));
    $dbh->beginTransaction();
    // Do not infer guardianship from a shared phone or convert student accounts.
    $dbh->exec("INSERT IGNORE INTO tblparentstudents(ParentId,StudentId,CreatedBy)
        SELECT id,StudentId,'migration:existing-parent-link' FROM tblusers WHERE Role='parent' AND StudentId IS NOT NULL");
    $dbh->exec('UPDATE tblgrades SET Status=0 WHERE GradeNumber NOT IN (10,11,12)');
    $dbh->exec('UPDATE tblschoollevels SET Status=0 WHERE SeniorSchool<>1');
    $query=$dbh->prepare('INSERT INTO tblschemamigrations(Name) VALUES(?)'); $query->execute([$name]);
    $dbh->commit();
    echo "Guardian links and publication workflow installed; historical records retained.\n";
} catch (Throwable $e) { if ($dbh->inTransaction()) $dbh->rollBack(); throw $e; }
finally { $dbh->query("SELECT RELEASE_LOCK('srms_parent_migration')"); }
