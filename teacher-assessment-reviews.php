<?php
require_once 'includes/config.php';require_once 'includes/teacher-auth.php';require_teacher();
require_once 'includes/cbe-learning.php';require_once 'includes/portal-layout.php';require_once 'includes/cbe-ui.php';
$teacher=teacher_id();$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_require_valid($_POST['csrf_token']??'');
    try{$dbh->beginTransaction();cbe_review_assessment($dbh,(int)($_POST['AssessmentId']??0),$_POST['Decision']??'',cbe_text($_POST,'Reason',5000,false),$teacher);$dbh->commit();header('Location: teacher-assessment-reviews.php?saved=1');exit;}
    catch(Throwable $e){if($dbh->inTransaction())$dbh->rollBack();$error=$e instanceof DomainException?$e->getMessage():'Could not save the review.';error_log($e->getMessage());}
}
portal_start('Class and department assessment reviews','teacher');portal_alert($error);if(isset($_GET['saved']))portal_alert('Review saved.','success');
$rows=cbe_rows($dbh,"SELECT a.id,a.Title,s.SubjectName,c.ClassName,c.Section,a.Status FROM tblassessments a JOIN tblsubjects s ON s.id=a.SubjectId JOIN tblclasses c ON c.id=a.ClassId WHERE a.Status IN ('submitted','reviewed') AND (EXISTS(SELECT 1 FROM tblclassteacherassignments ct WHERE ct.TeacherId=? AND ct.ClassId=a.ClassId AND ct.AcademicYearId=a.AcademicYearId AND ct.Status=1) OR EXISTS(SELECT 1 FROM tbldepartmentpermissions dp WHERE dp.TeacherId=? AND dp.DepartmentId=s.DepartmentId AND dp.CanReviewAcademic=1)) ORDER BY a.AssessmentDate",[$teacher,$teacher]);
foreach($rows as $row) {
    echo '<h2>'.academic_h($row['Title'].' · '.$row['SubjectName'].' · '.$row['ClassName'].' '.$row['Section']).'</h2>';
    cbe_table(cbe_rows($dbh,'SELECT s.StudentName,r.Score,r.Evidence,l.Name PerformanceLevel FROM tblassessmentresults r JOIN tblstudents s ON s.StudentId=r.StudentId LEFT JOIN tblperformancelevels l ON l.id=r.PerformanceLevelId WHERE r.AssessmentId=?',[$row['id']]));
    cbe_form($dbh,'Review submission','assessment_review',['Decision'=>'enum:reviewed|correction','Reason'=>'textarea'],[],['AssessmentId'=>$row['id']]);
}
if(!$rows)echo '<p>No submitted assessments are awaiting your review.</p>';
portal_end('teacher');
