<?php
// CLI only: additive, repeatable migration. Never run from a web request.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/config.php';
$dbh->exec("CREATE TABLE IF NOT EXISTS tblschemamigrations (Name VARCHAR(150) PRIMARY KEY, AppliedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$name = 'relational-teacher-management-v1';
$check = $dbh->prepare('SELECT 1 FROM tblschemamigrations WHERE Name=?');
$check->execute([$name]);
if ($check->fetchColumn()) { echo "Already applied.\n"; exit; }
// Stop before any assignment changes when existing primary teachers overlap.
$conflicts = $dbh->query("SELECT a.id FROM tblsubjectteacherassignments a JOIN tblsubjectteacherassignments b ON a.id<b.id AND a.ClassId=b.ClassId AND a.SubjectId=b.SubjectId AND a.AcademicYearId=b.AcademicYearId AND (a.TermId=b.TermId OR a.TermId IS NULL OR b.TermId IS NULL) WHERE a.Status=1 AND b.Status=1")->fetchAll();
$classConflicts = $dbh->query("SELECT ClassId FROM tblusers WHERE Role='class_teacher' AND ClassId IS NOT NULL GROUP BY ClassId HAVING COUNT(*)>1")->fetchAll();
if ($conflicts || $classConflicts) { fwrite(STDERR, "Resolve existing overlapping subject/class teachers before migration; no assignments changed.\n"); exit(1); }
$years = $dbh->query('SELECT id FROM tblacademicyears WHERE IsActive=1')->fetchAll(PDO::FETCH_COLUMN);
if (count($years)!==1) { fwrite(STDERR, "Select exactly one active academic year before migration.\n"); exit(1); }
$dbh->exec(file_get_contents(__DIR__ . '/../database-updates/relational-teacher-management.sql'));
$columns = $dbh->query('SHOW COLUMNS FROM tblsubjectteacherassignments')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('EndedAt', $columns)) $dbh->exec('ALTER TABLE tblsubjectteacherassignments ADD EndedAt TIMESTAMP NULL');
// Historical assignments must survive; reject deletion of referenced records.
// SET NULL on TermId is also incompatible with its stored generated period key.
$oldForeignKey = $dbh->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_sta_teacher'")->fetchColumn();
if ($oldForeignKey) $dbh->exec('ALTER TABLE tblsubjectteacherassignments DROP FOREIGN KEY fk_sta_teacher, DROP FOREIGN KEY fk_sta_class, DROP FOREIGN KEY fk_sta_subject, DROP FOREIGN KEY fk_sta_year, DROP FOREIGN KEY fk_sta_term, ADD CONSTRAINT fk_academic_sta_teacher FOREIGN KEY (TeacherId) REFERENCES tblusers(id), ADD CONSTRAINT fk_academic_sta_class FOREIGN KEY (ClassId) REFERENCES tblclasses(id), ADD CONSTRAINT fk_academic_sta_subject FOREIGN KEY (SubjectId) REFERENCES tblsubjects(id), ADD CONSTRAINT fk_academic_sta_year FOREIGN KEY (AcademicYearId) REFERENCES tblacademicyears(id), ADD CONSTRAINT fk_academic_sta_term FOREIGN KEY (TermId) REFERENCES tblterms(id)');
if (!in_array('ActiveSubjectId', $columns)) $dbh->exec('ALTER TABLE tblsubjectteacherassignments ADD ActiveSubjectId INT GENERATED ALWAYS AS (CASE WHEN Status=1 THEN SubjectId ELSE NULL END) STORED, ADD PeriodTermId INT GENERATED ALWAYS AS (COALESCE(TermId,0)) STORED');
$indexes = $dbh->query('SHOW INDEX FROM tblsubjectteacherassignments')->fetchAll(PDO::FETCH_ASSOC);
$names = array_column($indexes, 'Key_name');
if (!in_array('idx_assignment_teacher', $names)) $dbh->exec('ALTER TABLE tblsubjectteacherassignments ADD INDEX idx_assignment_teacher (TeacherId)');
if (in_array('uk_subject_teacher_assignment', $names)) $dbh->exec('ALTER TABLE tblsubjectteacherassignments DROP INDEX uk_subject_teacher_assignment');
if (!in_array('uk_primary_subject_period', $names)) $dbh->exec('ALTER TABLE tblsubjectteacherassignments ADD UNIQUE KEY uk_primary_subject_period (ClassId,ActiveSubjectId,AcademicYearId,PeriodTermId)');
$dbh->beginTransaction();
try {
    $q=$dbh->prepare("INSERT INTO tblclassteacherassignments (TeacherId,ClassId,AcademicYearId) SELECT u.id,u.ClassId,? FROM tblusers u WHERE u.Role='class_teacher' AND u.ClassId IS NOT NULL AND NOT EXISTS (SELECT 1 FROM tblclassteacherassignments a WHERE a.TeacherId=u.id AND a.ClassId=u.ClassId AND a.AcademicYearId=?)");
    $q->execute([$years[0],$years[0]]);
    // Class subject offerings do not prove individual enrolment. Do not guess it.
    $dbh->prepare('INSERT INTO tblschemamigrations(Name) VALUES(?)')->execute([$name]);
    $dbh->commit();
    echo "Teacher relationships migrated. Individual student subjects await Dean registration.\n";
} catch (Throwable $e) { $dbh->rollBack(); throw $e; }
