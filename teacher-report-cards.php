<?php
require_once 'includes/bootstrap.php';
$error=$msg='';

require_once 'includes/config.php';
require_once 'includes/csrf.php';
require_once 'includes/audit.php';
require_once 'includes/teacher-auth.php';
require_class_teacher();

$classId = teacher_class_id();
$teacherId = teacher_id();

require_once 'includes/result-workflow.php';
$msg=$error='';
if(isset($_POST['save_comment'])) {
    csrf_require_valid($_POST['csrf_token']??'');
    try {
        $dbh->beginTransaction();$studentId=result_id($_POST['studentid']??null);$examId=result_id($_POST['examid']??null);
        $e=workflow_exam($dbh,$classId,$examId);
        if(academic_query($dbh,'SELECT id FROM tblresultpublications WHERE ClassId=? AND ExamId=?',[$classId,$examId])->fetchColumn())throw new DomainException('Published report comments are locked.');
        if(!academic_query($dbh,'SELECT StudentId FROM tblstudents WHERE StudentId=? AND ClassId=? AND Status=1',[$studentId,$classId])->fetchColumn())throw new DomainException('Select an active learner in your class.');
        if(!academic_query($dbh,'SELECT id FROM tblclassteacherassignments WHERE TeacherId=? AND ClassId=? AND AcademicYearId=? AND Status=1',[$teacherId,$classId,$e['AcademicYearId']])->fetchColumn())throw new DomainException('A class assignment in the examination year is required.');
        $status=$_POST['status']??'';if(!in_array($status,['draft','submitted'],true))throw new DomainException('Save a draft or submit the comment for publication.');
        $comment=cbe_text($_POST,'comment',5000);
        academic_query($dbh,'INSERT INTO tblteachercomments(StudentId,ClassId,ExamId,TeacherId,CommentText,Status) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE TeacherId=VALUES(TeacherId),CommentText=VALUES(CommentText),Status=VALUES(Status)',[$studentId,$classId,$examId,$teacherId,$comment,$status]);
        audit_log($dbh,'teacher_comment_saved','tblstudents',$studentId,'Exam '.$examId);$dbh->commit();$msg='Teacher comment saved.';
    }catch(Throwable $e){if($dbh->inTransaction())$dbh->rollBack();error_log($e->getMessage());$error=$e instanceof DomainException?$e->getMessage():'Could not save the comment.';}
}

$examQuery = $dbh->prepare("SELECT id, ExamName FROM tblexams WHERE ClassId = :classid OR ClassId IS NULL ORDER BY id DESC");
$examQuery->execute(array(':classid' => $classId));
$exams = $examQuery->fetchAll(PDO::FETCH_OBJ);
$selectedExamId = isset($_GET['examid']) ? intval($_GET['examid']) : ($exams[0]->id ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report Cards | SRMS</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" type="text/css" href="js/DataTables/datatables.min.css">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <link rel="stylesheet" href="css/custom.css">
</head>
<body class="top-navbar-fixed">
<div class="main-wrapper">
<?php include('includes/teacher-topbar.php');?>
<div class="content-wrapper"><div class="content-container">
<?php include('includes/teacher-leftbar.php');?>
<div class="main-page"><div class="container-fluid">
<div class="row page-title-div"><div class="col-md-6"><h2 class="title">Report Cards</h2></div></div>
<section class="section">
<?php if($msg){?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php } ?>
<?php if($error){?><div class="alert alert-danger"><?php echo htmlentities($error); ?></div><?php } ?>
<div class="panel"><div class="panel-heading"><h5>Exam</h5></div><div class="panel-body">
    <form method="get" class="form-inline">
        <select name="examid" class="form-control">
            <?php foreach($exams as $exam) { ?><option value="<?php echo htmlentities($exam->id); ?>" <?php echo $selectedExamId == $exam->id ? 'selected' : ''; ?>><?php echo htmlentities($exam->ExamName); ?></option><?php } ?>
        </select>
        <button type="submit" class="btn btn-primary">View</button>
    </form>
</div></div>
<div class="panel"><div class="panel-heading"><h5>Student Report Cards</h5></div><div class="panel-body">
    <table id="example" class="display table table-striped table-bordered">
        <thead><tr><th>#</th><th>Student</th><th>Admission No.</th><th>Total</th><th>Average</th><th>Attendance %</th><th>Comment</th><th>Action</th></tr></thead>
        <tbody>
        <?php
        $sql = "SELECT s.StudentId, s.StudentName, s.RollId, SUM(r.marks) totalMarks, ROUND(AVG(r.marks),2) averageMarks,
                       ROUND(100 * SUM(a.Status IN ('present','late')) / NULLIF(COUNT(a.id),0), 2) attendancePercent,
                       tc.CommentText
                FROM tblstudents s
                LEFT JOIN tblresult r ON r.StudentId = s.StudentId AND r.ExamId = :examid
                LEFT JOIN tblattendance a ON a.StudentId = s.StudentId
                LEFT JOIN tblteachercomments tc ON tc.StudentId = s.StudentId AND tc.ExamId = :examid2 AND tc.TeacherId = :teacherid
                WHERE s.ClassId = :classid
                GROUP BY s.StudentId, s.StudentName, s.RollId, tc.CommentText
                ORDER BY s.StudentName";
        $query = $dbh->prepare($sql);
        $query->execute(array(':examid' => $selectedExamId, ':examid2' => $selectedExamId, ':teacherid' => $teacherId, ':classid' => $classId));
        $cnt = 1;
        foreach($query->fetchAll(PDO::FETCH_OBJ) as $student) { ?>
        <tr>
            <td><?php echo htmlentities($cnt); ?></td>
            <td><?php echo htmlentities($student->StudentName); ?></td>
            <td><?php echo htmlentities($student->RollId); ?></td>
            <td><?php echo htmlentities($student->totalMarks ?: 0); ?></td>
            <td><?php echo htmlentities($student->averageMarks ?: 0); ?></td>
            <td><?php echo htmlentities($student->attendancePercent ?: 0); ?></td>
            <td><?php echo htmlentities($student->CommentText ?: 'No comment'); ?></td>
            <td>
                <form method="post">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="studentid" value="<?php echo htmlentities($student->StudentId); ?>">
                    <input type="hidden" name="examid" value="<?php echo htmlentities($selectedExamId); ?>">
                    <textarea name="comment" class="form-control" rows="2" placeholder="Teacher comment" required><?php echo htmlentities($student->CommentText); ?></textarea>
                    <select name="status" class="form-control input-sm action-top-sm">
                        <option value="draft">Draft</option>
                        <option value="submitted">Submit</option>
                    </select>
                    <button type="submit" name="save_comment" class="btn btn-xs btn-primary action-top-sm">Save</button>
                    <button type="button" onclick="window.print();" class="btn btn-xs btn-default action-top-sm">Print</button>
                </form>
            </td>
        </tr>
        <?php $cnt++; } ?>
        </tbody>
    </table>
</div></div>
</section>
</div></div></div></div></div>
<script src="js/jquery/jquery-3.7.1.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/DataTables/datatables.min.js"></script>
<script src="js/main.js"></script>
<script>$(function($){ $('#example').DataTable(); });</script>
</body>
</html>
