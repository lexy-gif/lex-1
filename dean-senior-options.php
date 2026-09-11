<?php
session_start();require __DIR__.'/includes/config.php';require __DIR__.'/includes/dean-auth.php';require_dean();
require __DIR__.'/includes/senior-school.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try {
    if(!senior_ready($dbh))throw new DomainException('Senior School setup is unavailable.');
    $year=senior_id($_GET['year']??academic_year($dbh),'academic year');
    $class=senior_id($_GET['class']??0,'class',true);$path=senior_id($_GET['pathway']??0,'pathway',true);$track=senior_id($_GET['track']??0,'track',true);
    $subject=senior_id($_GET['subject']??0,'subject',true);$term=senior_id($_GET['term']??0,'term',true);$exam=senior_id($_GET['exam']??0,'exam',true);
    $gradeFilter=senior_id($_GET['grade']??0,'grade',true);
    if($exam){$e=cbe_one($dbh,'SELECT * FROM tblexams WHERE id=?',[$exam]);if(!$e)throw new DomainException('Exam not found.');$year=$e['AcademicYearId'];$term=$e['TermId'];}
    academic_period($dbh,$year,$term);
    $grade=$class?(senior_class($dbh,$class)['GradeId']??0):0;
    $terms=cbe_rows($dbh,'SELECT id,TermName Label FROM tblterms WHERE AcademicYearId=? ORDER BY id',[$year]);
    $classes=cbe_rows($dbh,'SELECT c.id,CONCAT(c.ClassName," ",c.Section) Label FROM tblclasses c WHERE (?=0 OR c.GradeId=?) ORDER BY c.ClassNameNumeric,c.Section',[$gradeFilter??0,$gradeFilter??0]);
    $mode=$_GET['mode']??'assign';
    $tracks=cbe_rows($dbh,'SELECT t.id,t.Name Label FROM tblpathwaytracks t JOIN tblpathways p ON p.id=t.PathwayId WHERE p.id=? AND p.Status=1 AND t.Status=1 ORDER BY t.Name',[$path??0]);
    $combinations=cbe_rows($dbh,'SELECT co.id,co.Name Label FROM tblschoolcombinations co JOIN tblpathwaytracks t ON t.id=co.TrackId JOIN tblpathways p ON p.id=t.PathwayId WHERE co.AcademicYearId=? AND co.TrackId=? AND t.PathwayId=? AND co.Status=1 AND t.Status=1 AND p.Status=1 ORDER BY co.Name',[$year,$track??0,$path??0]);
    $schedule=in_array($mode,['lesson','exam_session','teacher'],true);
    $subjects=$schedule?senior_timetable_subjects($dbh,$class,$year,$path):($mode==='mapping'?cbe_rows($dbh,"SELECT id,SubjectName Label FROM tblsubjects WHERE SeniorType='elective' AND Status=1 ORDER BY SubjectName"):senior_electives($dbh,$path??0,$track??0,$grade));
    $mapped=$mode==='mapping'?academic_query($dbh,'SELECT SubjectId FROM tblpathwaysubjects WHERE PathwayId=? AND TrackScope=?',[$path??0,$track??0])->fetchAll(PDO::FETCH_COLUMN):[];
    $count=academic_query($dbh,'SELECT COALESCE((SELECT ElectiveCount FROM tblpathways WHERE id=?),SeniorElectiveCount) FROM tblacademicsettings WHERE id=1',[$path??0])->fetchColumn();
    echo json_encode(['tracks'=>$tracks,'combinations'=>$combinations,'subjects'=>$subjects,'classes'=>$classes,'terms'=>$terms,'teachers'=>$class&&$subject?senior_teaching_options($dbh,$class,$subject,$year,$term??0):[],'mapped'=>$mapped,'elective_count'=>(int)$count],JSON_THROW_ON_ERROR);
} catch(DomainException $e){http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);}
catch(Throwable $e){error_log($e->getMessage());http_response_code(500);echo json_encode(['error'=>'Unable to load the selected curriculum.']);}
