<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/csrf.php');
include('includes/audit.php');
include('includes/teacher-auth.php');
require_class_teacher();

$classId = teacher_class_id();
$teacherId = teacher_id();

if(isset($_POST['save_comment'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    $studentId = $_POST['studentid'];
    $examId = $_POST['examid'] ?: null;
    $comment = trim($_POST['comment']);
    $status = $_POST['status'];

    $studentCheck = $dbh->prepare("SELECT StudentId FROM tblstudents WHERE StudentId = :studentid AND ClassId = :classid");
    $studentCheck->execute(array(':studentid' => $studentId, ':classid' => $classId));
    if($studentCheck->rowCount() > 0 && $comment !== '') {
        $sql = "INSERT INTO tblteachercomments(StudentId, ClassId, ExamId, TeacherId, CommentText, Status)
                VALUES(:studentid, :classid, :examid, :teacherid, :comment, :status)
                ON DUPLICATE KEY UPDATE CommentText = VALUES(CommentText), Status = VALUES(Status)";
        $query = $dbh->prepare($sql);
        $query->bindParam(':studentid', $studentId, PDO::PARAM_STR);
        $query->bindParam(':classid', $classId, PDO::PARAM_STR);
        if($examId === null) {
            $query->bindValue(':examid', null, PDO::PARAM_NULL);
        } else {
            $query->bindParam(':examid', $examId, PDO::PARAM_STR);
        }
        $query->bindParam(':teacherid', $teacherId, PDO::PARAM_STR);
        $query->bindParam(':comment', $comment, PDO::PARAM_STR);
        $query->bindParam(':status', $status, PDO::PARAM_STR);
        $query->execute();
        audit_log($dbh, 'teacher_comment_saved', 'tblstudents', $studentId, 'Exam ID ' . ($examId ?: 'legacy'));
        $msg = "Teacher comment saved.";
    } else {
        $error = "Please select a valid student and enter a comment.";
    }
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
                    <select name="status" class="form-control input-sm" style="margin-top:4px;">
                        <option value="draft">Draft</option>
                        <option value="submitted">Submit</option>
                    </select>
                    <button type="submit" name="save_comment" class="btn btn-xs btn-primary" style="margin-top:4px;">Save</button>
                    <button type="button" onclick="window.print();" class="btn btn-xs btn-default" style="margin-top:4px;">Print</button>
                </form>
            </td>
        </tr>
        <?php $cnt++; } ?>
        </tbody>
    </table>
</div></div>
</section>
</div></div></div></div></div>
<script src="js/jquery/jquery-2.2.4.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/DataTables/datatables.min.js"></script>
<script src="js/main.js"></script>
<script>$(function($){ $('#example').DataTable(); });</script>
</body>
</html>
