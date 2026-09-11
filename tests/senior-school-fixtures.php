<?php
if (PHP_SAPI !== 'cli') exit;
require __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/senior-school.php';
require_once __DIR__.'/../includes/academic-periods.php';
if (!preg_match('/^srms_senior_test_[a-f0-9]{16}$/D', DB_NAME)) throw new RuntimeException('Senior School tests require an isolated database.');
$_SESSION = ['alogin'=>'senior-validation'];
$mode = $argv[1] ?? '';
if ($mode === 'base') {
    $dbh->beginTransaction();
    academic_query($dbh, 'INSERT INTO tblacademicsettings(id) VALUES(1)');
    foreach (['relational-teacher-management-v1','cbe-academic-module-v1'] as $name) academic_query($dbh, 'INSERT INTO tblschemamigrations(Name) VALUES(?)', [$name]);
    academic_query($dbh, "INSERT INTO tblschoollevels(Name,SeniorSchool) VALUES('Senior test school',1)");
    $level = (int)$dbh->lastInsertId();
    foreach ([9,10,11,12] as $grade) academic_query($dbh, 'INSERT INTO tblgrades(SchoolLevelId,Name,GradeNumber) VALUES(?,?,?)', [$level,'Grade '.$grade,$grade]);
    foreach (['2026','2027','2028'] as $name) {
        $period = academic_period_save($dbh, ['academicyear'=>$name,'termname'=>'Term 1','startdate'=>$name.'-01-01','enddate'=>$name.'-04-30']);
        academic_query($dbh, 'UPDATE tblacademicyears SET StartDate=?,EndDate=? WHERE id=?', [$name.'-01-01',$name.'-12-31',$period['AcademicYearId']]);
        if ($name === '2027') academic_period_activate($dbh, $period['AcademicYearId'], $period['TermId']);
    }
    // The migration must reuse this legacy subject and preserve its name and code.
    academic_query($dbh, "INSERT INTO tblsubjects(SubjectName,SubjectCode) VALUES('Computer','LEGACY-COMP')");
    $legacySubject = (int)$dbh->lastInsertId();
    $legacyYear = (int)academic_query($dbh, "SELECT id FROM tblacademicyears WHERE AcademicYear='2026'")->fetchColumn();
    $legacyGrade = (int)academic_query($dbh, 'SELECT id FROM tblgrades WHERE GradeNumber=10')->fetchColumn();
    academic_query($dbh, "INSERT INTO tblclasses(ClassName,GradeId,Section) VALUES('Legacy class',?,'HIST')", [$legacyGrade]);
    $legacyClass = (int)$dbh->lastInsertId();
    academic_query($dbh, "INSERT INTO tblstudents(StudentName,RollId,ClassId,Status) VALUES('Historical learner','HIST-1',?,1)", [$legacyClass]);
    $legacyStudent = (int)$dbh->lastInsertId();
    academic_query($dbh, "INSERT INTO tblpathways(Name) VALUES('STEM')");
    $legacyPath = (int)$dbh->lastInsertId();
    academic_query($dbh, "INSERT INTO tblpathwaytracks(PathwayId,Name) VALUES(?,'Pure Sciences')", [$legacyPath]);
    $legacyTrack = (int)$dbh->lastInsertId();
    academic_query($dbh, "INSERT INTO tblschoolcombinations(TrackId,AcademicYearId,Name) VALUES(?,?,'Legacy combination')", [$legacyTrack,$legacyYear]);
    $legacyCombination = (int)$dbh->lastInsertId();
    academic_query($dbh, 'INSERT INTO tblschoolcombinationsubjects(CombinationId,SubjectId) VALUES(?,?)', [$legacyCombination,$legacySubject]);
    academic_query($dbh, "INSERT INTO tblstudentpathways(StudentId,ClassId,CombinationId,AcademicYearId,PlacementSource) VALUES(?,?,?,?,'Legacy')", [$legacyStudent,$legacyClass,$legacyCombination,$legacyYear]);
    $legacyAllocation = (int)$dbh->lastInsertId();
    $dbh->commit();
    echo json_encode(compact('legacySubject','legacyStudent','legacyAllocation','legacyClass','legacyYear'));
} elseif ($mode === 'setup') {
    $dbh->beginTransaction();
    $years = academic_query($dbh, "SELECT id FROM tblacademicyears WHERE AcademicYear IN ('2027','2028') ORDER BY AcademicYear")->fetchAll(PDO::FETCH_COLUMN);
    $term = (int)academic_query($dbh, 'SELECT id FROM tblterms WHERE AcademicYearId=?', [$years[0]])->fetchColumn();
    $classes = [];
    foreach ([10,10,11,9] as $i=>$grade) {
        $gradeId = academic_query($dbh, 'SELECT id FROM tblgrades WHERE GradeNumber=?', [$grade])->fetchColumn();
        academic_query($dbh, 'INSERT INTO tblclasses(ClassName,ClassNameNumeric,GradeId,Section) VALUES(?,?,?,?)', ['Grade '.$grade,$grade,$gradeId,$i===1?'B':'A']);
        $classes[] = (int)$dbh->lastInsertId();
    }
    $students = [];
    foreach ([0,0,1,0,3] as $i=>$class) {
        academic_query($dbh, 'INSERT INTO tblstudents(StudentName,RollId,ClassId,Gender,Status) VALUES(?,?,?,?,?)', ['Senior learner '.$i,'SENIOR-'.$i,$classes[$class],$i%2?'Female':'Male',$i===3?0:1]);
        $students[] = (int)$dbh->lastInsertId();
        if ($i!==3 && $i!==4) senior_sync_core($dbh, $students[$i], $years[0]);
    }
    $subjects = [];
    foreach (cbe_rows($dbh, 'SELECT id,SubjectName FROM tblsubjects') as $row) $subjects[$row['SubjectName']] = (int)$row['id'];
    foreach ([$classes[0],$classes[1]] as $class) foreach (['Biology','Chemistry','Physics','Agriculture'] as $name) senior_ensure_offering($dbh, $class, $subjects[$name]);
    $teachers = [];
    foreach (['class','subject','unassigned'] as $name) {
        academic_query($dbh, "INSERT INTO tblusers(FullName,Username,PasswordHash,Role,Status) VALUES(?,?,?,'subject_teacher',1)", ['Senior '.$name.' teacher','senior_'.$name,password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);
        $teachers[] = (int)$dbh->lastInsertId();
        foreach (['SYSTEM','SUBJECT_ASSIGNMENT'] as $category) academic_query($dbh, 'INSERT INTO tblnotificationpreferences(UserId,Category,InAppEnabled,EmailEnabled) VALUES(?,?,1,0)', [end($teachers),$category]);
    }
    academic_assign($dbh, 'class', $teachers[0], $classes[0], 0, $years[0]);
    academic_assign($dbh, 'subject', $teachers[1], $classes[0], $subjects['Biology'], $years[0]);
    academic_query($dbh, "INSERT INTO tblusers(FullName,Username,PasswordHash,Role,StudentId,ClassId,Status) VALUES('Senior student','senior_student',?,'student',?,?,1)", [password_hash('test-student-only',PASSWORD_DEFAULT),$students[0],$classes[0]]);
    $account = (int)$dbh->lastInsertId();
    academic_query($dbh, "INSERT INTO tblnotificationpreferences(UserId,Category,InAppEnabled,EmailEnabled) VALUES(?,'SYSTEM',1,0)", [$account]);
    $pathway = (int)academic_query($dbh, "SELECT id FROM tblpathways WHERE Name='STEM'")->fetchColumn();
    $track = (int)academic_query($dbh, "SELECT id FROM tblpathwaytracks WHERE PathwayId=? AND Name='Pure Sciences'", [$pathway])->fetchColumn();
    academic_query($dbh, "INSERT INTO tblexams(AcademicYearId,TermId,ClassId,ExamName,Status) VALUES(?,?,?,'Senior history exam','marks_entry')", [$years[0],$term,$classes[0]]);
    $exam = (int)$dbh->lastInsertId();
    academic_query($dbh, 'INSERT INTO tblresult(StudentId,ClassId,ExamId,SubjectId,marks) VALUES(?,?,?,?,80)', [$students[0],$classes[0],$exam,$subjects['English']]);
    $dbh->commit();
    echo json_encode(compact('years','term','classes','students','subjects','teachers','account','pathway','track','exam'));
} elseif ($mode === 'state') {
    $state = [];
    foreach (['tblstudentpathways','tblpathwayallocationsubjects','tblstudentsubjects','tblstudentenrollments','tblstudents','tblresult','tblschoolcombinations','tblschoolcombinationsubjects','tblteachernotifications','tblnotificationdeliveries','tblsubjectteacherassignments'] as $table) $state[$table] = cbe_rows($dbh, 'SELECT * FROM '.$table.' ORDER BY 1,2');
    echo json_encode($state);
} else throw new RuntimeException('Unknown fixture action.');
