<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/csrf.php');
include('includes/audit.php');
include('includes/teacher-auth.php');
require_class_teacher();

$classId = teacher_class_id();

if(isset($_POST['review_action'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    $examId = $_POST['examid'];
    $action = $_POST['action'];
    $reason = trim($_POST['correction_reason']);
    $status = $action === 'approve' ? 'approved' : 'correction_requested';

    $sql = "INSERT INTO tblresultreviews(ClassId, ExamId, ReviewedBy, Status, CorrectionReason)
            VALUES(:classid, :examid, :teacherid, :status, :reason)
            ON DUPLICATE KEY UPDATE ReviewedBy = VALUES(ReviewedBy), Status = VALUES(Status), CorrectionReason = VALUES(CorrectionReason)";
    $query = $dbh->prepare($sql);
    $query->execute(array(':classid' => $classId, ':examid' => $examId, ':teacherid' => teacher_id(), ':status' => $status, ':reason' => $reason));
    audit_log($dbh, 'class_result_review_' . $status, 'tblexams', $examId, $reason);
    $msg = $status === 'approved' ? 'Class results approved for publication.' : 'Correction request recorded.';
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
    <title>Results Review | SRMS</title>
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
<div class="row page-title-div"><div class="col-md-6"><h2 class="title">Results Review</h2></div></div>
<section class="section">
<?php if($msg){?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php } ?>
<div class="panel"><div class="panel-heading"><h5>Select Exam</h5></div><div class="panel-body">
    <form method="get" class="form-inline">
        <select name="examid" class="form-control" required>
            <?php foreach($exams as $exam) { ?>
            <option value="<?php echo htmlentities($exam->id); ?>" <?php echo $selectedExamId == $exam->id ? 'selected' : ''; ?>><?php echo htmlentities($exam->ExamName); ?></option>
            <?php } ?>
        </select>
        <button class="btn btn-primary" type="submit">View</button>
    </form>
</div></div>
<div class="row">
    <div class="col-md-8"><div class="panel"><div class="panel-heading"><h5>Student Performance</h5></div><div class="panel-body">
        <table id="example" class="display table table-striped table-bordered">
            <thead><tr><th>#</th><th>Student</th><th>Admission No.</th><th>Total</th><th>Average</th><th>Subjects Submitted</th></tr></thead>
            <tbody>
            <?php
            $sql = "SELECT s.StudentName, s.RollId, SUM(r.marks) AS totalMarks, ROUND(AVG(r.marks), 2) AS averageMarks, COUNT(r.SubjectId) AS submittedSubjects
                    FROM tblstudents s
                    LEFT JOIN tblresult r ON r.StudentId = s.StudentId AND r.ExamId = :examid
                    WHERE s.ClassId = :classid
                    GROUP BY s.StudentId, s.StudentName, s.RollId
                    ORDER BY totalMarks DESC";
            $query = $dbh->prepare($sql);
            $query->execute(array(':examid' => $selectedExamId, ':classid' => $classId));
            $cnt = 1;
            foreach($query->fetchAll(PDO::FETCH_OBJ) as $row) { ?>
                <tr><td><?php echo htmlentities($cnt); ?></td><td><?php echo htmlentities($row->StudentName); ?></td><td><?php echo htmlentities($row->RollId); ?></td><td><?php echo htmlentities($row->totalMarks ?: 0); ?></td><td><?php echo htmlentities($row->averageMarks ?: 0); ?></td><td><?php echo htmlentities($row->submittedSubjects); ?></td></tr>
            <?php $cnt++; } ?>
            </tbody>
        </table>
    </div></div></div>
    <div class="col-md-4"><div class="panel"><div class="panel-heading"><h5>Subject Submission Status</h5></div><div class="panel-body">
        <?php
        $subjectSql = "SELECT sub.SubjectName, COUNT(r.id) AS marksCount
                       FROM tblsubjectcombination sc
                       JOIN tblsubjects sub ON sub.id = sc.SubjectId
                       LEFT JOIN tblresult r ON r.SubjectId = sub.id AND r.ClassId = sc.ClassId AND r.ExamId = :examid
                       WHERE sc.ClassId = :classid AND sc.status = 1
                       GROUP BY sub.id, sub.SubjectName
                       ORDER BY sub.SubjectName";
        $subjectQuery = $dbh->prepare($subjectSql);
        $subjectQuery->execute(array(':examid' => $selectedExamId, ':classid' => $classId));
        foreach($subjectQuery->fetchAll(PDO::FETCH_OBJ) as $subject) {
            $label = $subject->marksCount > 0 ? 'Submitted' : 'Pending';
            $class = $subject->marksCount > 0 ? 'label-success' : 'label-warning';
            echo '<p>' . htmlentities($subject->SubjectName) . ' <span class="label ' . $class . '">' . $label . '</span></p>';
        }
        ?>
        <hr>
        <form method="post">
            <?php csrf_field(); ?>
            <input type="hidden" name="examid" value="<?php echo htmlentities($selectedExamId); ?>">
            <div class="form-group">
                <label>Correction Reason</label>
                <textarea name="correction_reason" class="form-control" rows="3"></textarea>
            </div>
            <button type="submit" name="action" value="approve" class="btn btn-success">Approve</button>
            <button type="submit" name="action" value="correction" class="btn btn-warning">Request Correction</button>
            <input type="hidden" name="review_action" value="1">
        </form>
    </div></div></div>
</div>
</section>
</div></div></div></div></div>
<script src="js/jquery/jquery-2.2.4.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/DataTables/datatables.min.js"></script>
<script src="js/main.js"></script>
<script>$(function($){ $('#example').DataTable(); });</script>
</body>
</html>
