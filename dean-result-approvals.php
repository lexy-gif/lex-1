<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/csrf.php');
include('includes/audit.php');
include('includes/dean-auth.php');
require_dean();

if(isset($_POST['decision'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    $classId = $_POST['classid'];
    $examId = $_POST['examid'];
    $decision = $_POST['decision'];
    $reason = trim($_POST['reason']);
    $status = $decision === 'publish' ? 'published' : ($decision === 'approve' ? 'approved' : 'rejected');

    $sql = "INSERT INTO tbldeanapprovals(ClassId, ExamId, ApprovedBy, Status, DecisionReason)
            VALUES(:classid, :examid, :approvedby, :status, :reason)
            ON DUPLICATE KEY UPDATE ApprovedBy = VALUES(ApprovedBy), Status = VALUES(Status), DecisionReason = VALUES(DecisionReason)";
    $query = $dbh->prepare($sql);
    $query->execute(array(':classid' => $classId, ':examid' => $examId, ':approvedby' => dean_name(), ':status' => $status, ':reason' => $reason));

    if($decision === 'publish') {
        $examUpdate = $dbh->prepare("UPDATE tblexams SET Status = 'published' WHERE id = :examid");
    } elseif($decision === 'approve') {
        $examUpdate = $dbh->prepare("UPDATE tblexams SET Status = 'approved' WHERE id = :examid");
    } else {
        $examUpdate = $dbh->prepare("UPDATE tblexams SET Status = 'under_review' WHERE id = :examid");
    }
    $examUpdate->execute(array(':examid' => $examId));

    audit_log($dbh, 'dean_result_' . $status, 'tblexams', $examId, 'Class ID ' . $classId . '. ' . $reason);
    $msg = "Dean result decision saved.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Result Approval | SRMS</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" type="text/css" href="js/DataTables/datatables.min.css">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <link rel="stylesheet" href="css/custom.css">
</head>
<body class="top-navbar-fixed">
<div class="main-wrapper">
<?php include('includes/topbar.php');?>
<div class="content-wrapper"><div class="content-container">
<?php include('includes/leftbar.php');?>
<div class="main-page"><div class="container-fluid">
<div class="row page-title-div"><div class="col-md-8"><h2 class="title">Approve / Publish Results</h2></div></div>
<section class="section">
<?php if($msg){?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php } ?>
<div class="panel"><div class="panel-heading"><h5>Class Result Approval Queue</h5></div><div class="panel-body">
<table id="example" class="display table table-striped table-bordered">
<thead><tr><th>#</th><th>Exam</th><th>Class</th><th>Submitted Students</th><th>Class Teacher Review</th><th>Dean Status</th><th>Decision</th></tr></thead>
<tbody>
<?php
$sql = "SELECT e.id AS ExamId, e.ExamName, e.Status AS ExamStatus, ay.AcademicYear, t.TermName,
               c.id AS ClassId, c.ClassName, c.Section,
               COUNT(DISTINCT r.StudentId) AS SubmittedStudents,
               rr.Status AS ReviewStatus,
               da.Status AS DeanStatus
        FROM tblexams e
        JOIN tblacademicyears ay ON ay.id = e.AcademicYearId
        JOIN tblterms t ON t.id = e.TermId
        JOIN tblclasses c ON e.ClassId = c.id OR e.ClassId IS NULL
        LEFT JOIN tblresult r ON r.ExamId = e.id AND r.ClassId = c.id
        LEFT JOIN tblresultreviews rr ON rr.ExamId = e.id AND rr.ClassId = c.id
        LEFT JOIN tbldeanapprovals da ON da.ExamId = e.id AND da.ClassId = c.id
        GROUP BY e.id, e.ExamName, e.Status, ay.AcademicYear, t.TermName, c.id, c.ClassName, c.Section, rr.Status, da.Status
        ORDER BY e.id DESC, c.ClassNameNumeric, c.Section";
$query = $dbh->prepare($sql);
$query->execute();
$cnt = 1;
foreach($query->fetchAll(PDO::FETCH_OBJ) as $row) { ?>
<tr>
    <td><?php echo htmlentities($cnt); ?></td>
    <td><?php echo htmlentities($row->AcademicYear . ' - ' . $row->TermName . ' - ' . $row->ExamName); ?></td>
    <td><?php echo htmlentities($row->ClassName . ' Section-' . $row->Section); ?></td>
    <td><?php echo htmlentities($row->SubmittedStudents); ?></td>
    <td><?php echo htmlentities($row->ReviewStatus ?: 'Pending'); ?></td>
    <td><?php echo htmlentities($row->DeanStatus ?: $row->ExamStatus); ?></td>
    <td>
        <form method="post">
            <?php csrf_field(); ?>
            <input type="hidden" name="classid" value="<?php echo htmlentities($row->ClassId); ?>">
            <input type="hidden" name="examid" value="<?php echo htmlentities($row->ExamId); ?>">
            <input type="text" name="reason" class="form-control input-sm" placeholder="Reason if rejecting or returning">
            <button type="submit" name="decision" value="approve" class="btn btn-xs btn-success action-top-sm">Approve</button>
            <button type="submit" name="decision" value="publish" class="btn btn-xs btn-primary action-top-sm">Publish</button>
            <button type="submit" name="decision" value="reject" class="btn btn-xs btn-warning action-top-sm">Return</button>
        </form>
    </td>
</tr>
<?php $cnt++; } ?>
</tbody></table>
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
