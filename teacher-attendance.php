<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/csrf.php');
include('includes/audit.php');
include('includes/teacher-auth.php');
require_class_teacher();

$classId = teacher_class_id();
$attendanceDate = $_POST['attendance_date'] ?? $_GET['date'] ?? date('Y-m-d');

if(isset($_POST['save_attendance'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    $attendanceDate = $_POST['attendance_date'];
    foreach($_POST['attendance'] as $studentId => $status) {
        $check = $dbh->prepare("SELECT StudentId FROM tblstudents WHERE StudentId = :studentid AND ClassId = :classid");
        $check->execute(array(':studentid' => $studentId, ':classid' => $classId));
        if($check->rowCount() === 0) {
            continue;
        }
        $remarks = $_POST['remarks'][$studentId] ?? '';
        $sql = "INSERT INTO tblattendance(StudentId, ClassId, AttendanceDate, Status, Remarks, RecordedBy)
                VALUES(:studentid, :classid, :attdate, :status, :remarks, :recordedby)
                ON DUPLICATE KEY UPDATE Status = VALUES(Status), Remarks = VALUES(Remarks), RecordedBy = VALUES(RecordedBy)";
        $query = $dbh->prepare($sql);
        $query->execute(array(':studentid' => $studentId, ':classid' => $classId, ':attdate' => $attendanceDate, ':status' => $status, ':remarks' => $remarks, ':recordedby' => teacher_id()));
    }
    audit_log($dbh, 'attendance_saved', 'tblclasses', $classId, 'Attendance date ' . $attendanceDate);
    $msg = "Attendance saved successfully.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attendance | SRMS</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <link rel="stylesheet" href="css/custom.css">
</head>
<body class="top-navbar-fixed">
<div class="main-wrapper">
<?php include('includes/teacher-topbar.php');?>
<div class="content-wrapper"><div class="content-container">
<?php include('includes/teacher-leftbar.php');?>
<div class="main-page"><div class="container-fluid">
<div class="row page-title-div"><div class="col-md-6"><h2 class="title">Attendance</h2></div></div>
<section class="section">
<?php if($msg){?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php } ?>
<div class="panel"><div class="panel-heading"><h5>Record Attendance</h5></div><div class="panel-body">
    <form method="post">
        <?php csrf_field(); ?>
        <div class="form-group">
            <label>Date</label>
            <input type="date" name="attendance_date" value="<?php echo htmlentities($attendanceDate); ?>" class="form-control input-date-md" required>
        </div>
        <table class="table table-striped table-bordered">
            <thead><tr><th>#</th><th>Student</th><th>Admission No.</th><th>Status</th><th>Remarks</th></tr></thead>
            <tbody>
            <?php
            $sql = "SELECT s.StudentId, s.StudentName, s.RollId, a.Status, a.Remarks
                    FROM tblstudents s
                    LEFT JOIN tblattendance a ON a.StudentId = s.StudentId AND a.AttendanceDate = :attdate
                    WHERE s.ClassId = :classid
                    ORDER BY s.StudentName";
            $query = $dbh->prepare($sql);
            $query->execute(array(':attdate' => $attendanceDate, ':classid' => $classId));
            $cnt = 1;
            foreach($query->fetchAll(PDO::FETCH_OBJ) as $student) { $status = $student->Status ?: 'present'; ?>
                <tr>
                    <td><?php echo htmlentities($cnt); ?></td>
                    <td><?php echo htmlentities($student->StudentName); ?></td>
                    <td><?php echo htmlentities($student->RollId); ?></td>
                    <td>
                        <select name="attendance[<?php echo htmlentities($student->StudentId); ?>]" class="form-control">
                            <option value="present" <?php echo $status === 'present' ? 'selected' : ''; ?>>Present</option>
                            <option value="absent" <?php echo $status === 'absent' ? 'selected' : ''; ?>>Absent</option>
                            <option value="late" <?php echo $status === 'late' ? 'selected' : ''; ?>>Late</option>
                            <option value="excused" <?php echo $status === 'excused' ? 'selected' : ''; ?>>Excused</option>
                        </select>
                    </td>
                    <td><input type="text" name="remarks[<?php echo htmlentities($student->StudentId); ?>]" value="<?php echo htmlentities($student->Remarks); ?>" class="form-control"></td>
                </tr>
            <?php $cnt++; } ?>
            </tbody>
        </table>
        <button type="submit" name="save_attendance" class="btn btn-primary">Save Attendance</button>
    </form>
</div></div>
</section>
</div></div></div></div></div>
<script src="js/jquery/jquery-2.2.4.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
