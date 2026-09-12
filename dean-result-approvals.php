<?php
require_once 'includes/bootstrap.php';
$error=$msg='';

require_once 'includes/config.php';
require_once 'includes/csrf.php';
require_once 'includes/audit.php';
require_once 'includes/dean-auth.php';
require_dean();

require_once 'includes/result-workflow.php';
$error=$msg='';
if(isset($_POST['decision'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    try {
        $dbh->beginTransaction();
        workflow_decide($dbh,result_id($_POST['classid']??null),result_id($_POST['examid']??null),$_POST['decision']??'',cbe_text($_POST,'reason',5000,false));
        $dbh->commit(); $msg='Result decision saved.';
    } catch(Throwable $e) {
        if($dbh->inTransaction())$dbh->rollBack();error_log($e->getMessage());
        $error=$e instanceof DomainException?$e->getMessage():'Could not save the decision.';
    }
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
<section class="section"><?php if($error){?><div class="alert alert-danger"><?= academic_h($error) ?></div><?php } ?>
<?php if($msg){?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php } ?>
<div class="panel"><div class="panel-heading"><h5>Class Result Approval Queue</h5></div><div class="panel-body">
<table id="example" class="display table table-striped table-bordered">
<thead><tr><th>#</th><th>Exam</th><th>Class</th><th>Submitted Students</th><th>Class Teacher Review</th><th>Dean Status</th><th>Decision</th></tr></thead>
<tbody>
<?php
$sql = "SELECT e.id AS ExamId, e.ExamName, e.Status AS ExamStatus, ay.AcademicYear, t.TermName,
               c.id AS ClassId, c.ClassName, c.Section,
               COUNT(DISTINCT CASE WHEN EXISTS(SELECT 1 FROM tblresultsubmissions rs WHERE rs.ClassId=r.ClassId AND rs.SubjectId=r.SubjectId AND rs.ExamId=r.ExamId AND rs.Status='submitted') THEN r.StudentId END) AS SubmittedStudents,
               rr.Status AS ReviewStatus,
               da.Status AS DeanStatus
        FROM tblexams e
        JOIN tblacademicyears ay ON ay.id = e.AcademicYearId
        JOIN tblterms t ON t.id = e.TermId
        JOIN tblclasses c ON (e.ClassId = c.id OR e.ClassId IS NULL) AND c.ClassNameNumeric IN (10,11,12)
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
<script src="js/jquery/jquery-3.7.1.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/DataTables/datatables.min.js"></script>
<script src="js/main.js"></script>
<script>$(function($){ $('#example').DataTable(); });</script>
</body>
</html>
