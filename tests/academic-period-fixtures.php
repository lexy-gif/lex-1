<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../includes/config.php';
require __DIR__.'/../includes/academic-periods.php';
require __DIR__.'/../includes/cbe-context.php';
require __DIR__.'/../includes/timetable.php';
require __DIR__.'/../includes/teacher-auth.php';
if(!preg_match('/^srms_period_test_[a-f0-9]{16}$/D',DB_NAME))throw new RuntimeException('Period tests require an isolated database.');
$_SESSION=['alogin'=>'period-validation'];
$mode=$argv[1]??'';
if($mode==='setup') {
    $dbh->beginTransaction();
    try {
        academic_query($dbh,'INSERT INTO tblacademicsettings(id) VALUES(1)');
        academic_query($dbh,'INSERT INTO tblschemamigrations(Name) VALUES(?)',['relational-teacher-management-v1']);
        $periods=[];$classes=[];
        foreach(['2027-test','2028-test'] as $i=>$name) {
            foreach(['Term 1','Term 2'] as $term)$periods[$i][]=academic_period_save($dbh,['academicyear'=>$name,'termname'=>$term,'startdate'=>($i+2027).'-01-10','enddate'=>($i+2027).'-04-10']);
            academic_query($dbh,'UPDATE tblacademicyears SET StartDate=?,EndDate=? WHERE id=?',[($i+2027).'-01-01',($i+2027).'-12-31',$periods[$i][0]['AcademicYearId']]);
            academic_query($dbh,'INSERT INTO tblclasses(ClassName,Section) VALUES(?,?)',[$name,'TEST']);$classes[]=(int)$dbh->lastInsertId();
        }
        academic_query($dbh,"INSERT INTO tblusers(FullName,Username,PasswordHash,Role,Status) VALUES('Period teacher','period_teacher',?,'class_teacher',1)",[password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);$teacher=(int)$dbh->lastInsertId();
        foreach($classes as $i=>$class)academic_assign($dbh,'class',$teacher,$class,null,$periods[$i][0]['AcademicYearId']);
        academic_period_activate($dbh,$periods[0][0]['AcademicYearId'],$periods[0][0]['TermId']);
        $dbh->commit();echo json_encode(compact('periods','classes','teacher'));
    }catch(Throwable $e){if($dbh->inTransaction())$dbh->rollBack();throw $e;}
} elseif($mode==='state') {
    $_SESSION['teacher_user_id']=(int)$dbh->query("SELECT id FROM tblusers WHERE Username='period_teacher'")->fetchColumn();
    echo json_encode(['years'=>$dbh->query('SELECT * FROM tblacademicyears ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),'terms'=>$dbh->query('SELECT * FROM tblterms ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),'exams'=>$dbh->query('SELECT * FROM tblexams ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),'audits'=>$dbh->query('SELECT * FROM tblauditlog ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),'context'=>cbe_period_context($dbh,[]),'timetable'=>timetable_active_period($dbh),'teacherClass'=>teacher_class_id()]);
} else throw new RuntimeException('Unknown fixture action.');
