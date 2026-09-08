<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/csrf.php');
include('includes/audit.php');
include('includes/teacher-auth.php');
require_class_teacher();

$classId = teacher_class_id();

if(isset($_POST['create_account'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    $studentId = $_POST['studentid'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $email = $email === '' ? null : $email;
    $password = $_POST['password'];
    $status = $_POST['status'];

    $studentCheck = $dbh->prepare("SELECT StudentName, RollId, ParentPhone FROM tblstudents WHERE StudentId = :studentid AND ClassId = :classid LIMIT 1");
    $studentCheck->execute(array(':studentid' => $studentId, ':classid' => $classId));
    $student = $studentCheck->fetch(PDO::FETCH_OBJ);

    if(!$student) {
        $error = "Selected student is not in your assigned class.";
    } else {
        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'student';
            $createdBy = 'class_teacher:' . teacher_id();
            $sql = "INSERT INTO tblusers(FullName, Username, Email, PasswordHash, Role, ClassId, StudentId, ParentPhone, Status, CreatedBy)
                    VALUES(:fullname, :username, :email, :passwordhash, :role, :classid, :studentid, :parentphone, :status, :createdby)";
            $query = $dbh->prepare($sql);
            $query->bindParam(':fullname', $student->StudentName, PDO::PARAM_STR);
            $query->bindParam(':username', $username, PDO::PARAM_STR);
            if($email === null) {
                $query->bindValue(':email', null, PDO::PARAM_NULL);
            } else {
                $query->bindParam(':email', $email, PDO::PARAM_STR);
            }
            $query->bindParam(':passwordhash', $passwordHash, PDO::PARAM_STR);
            $query->bindParam(':role', $role, PDO::PARAM_STR);
            $query->bindParam(':classid', $classId, PDO::PARAM_STR);
            $query->bindParam(':studentid', $studentId, PDO::PARAM_STR);
            $query->bindParam(':parentphone', $student->ParentPhone, PDO::PARAM_STR);
            $query->bindParam(':status', $status, PDO::PARAM_STR);
            $query->bindParam(':createdby', $createdBy, PDO::PARAM_STR);
            $query->execute();
            audit_log($dbh, 'student_account_created', 'tblusers', $dbh->lastInsertId(), 'Created student account for student ID ' . $studentId);
            $msg = "Student account created successfully.";
        } catch(Exception $e) {
            $error = "Could not create account. Username, email, or student account may already exist.";
        }
    }
}

if(isset($_POST['update_status'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    $accountId = $_POST['accountid'];
    $status = $_POST['status'];
    $sql = "UPDATE tblusers SET Status = :status WHERE id = :accountid AND Role = 'student' AND ClassId = :classid";
    $query = $dbh->prepare($sql);
    $query->execute(array(':status' => $status, ':accountid' => $accountId, ':classid' => $classId));
    audit_log($dbh, 'student_account_status_changed', 'tblusers', $accountId, 'Status changed to ' . $status);
    $msg = "Account status updated.";
}

if(isset($_POST['reset_password'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    $accountId = $_POST['accountid'];
    $passwordHash = password_hash($_POST['newpassword'], PASSWORD_DEFAULT);
    $sql = "UPDATE tblusers SET PasswordHash = :passwordhash WHERE id = :accountid AND Role = 'student' AND ClassId = :classid";
    $query = $dbh->prepare($sql);
    $query->execute(array(':passwordhash' => $passwordHash, ':accountid' => $accountId, ':classid' => $classId));
    audit_log($dbh, 'student_password_reset', 'tblusers', $accountId, 'Password reset by class teacher');
    $msg = "Student password reset successfully.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Student Accounts | SRMS</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" type="text/css" href="js/DataTables/datatables.min.css">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <link rel="stylesheet" href="css/custom.css" media="screen">
</head>
<body class="top-navbar-fixed">
<div class="main-wrapper">
<?php include('includes/teacher-topbar.php');?>
<div class="content-wrapper"><div class="content-container">
<?php include('includes/teacher-leftbar.php');?>
<div class="main-page"><div class="container-fluid">
<div class="row page-title-div"><div class="col-md-6"><h2 class="title">Manage Student Accounts</h2></div></div>
<section class="section"><div class="row">
    <div class="col-md-5"><div class="panel"><div class="panel-heading"><h5>Create Student Account</h5></div><div class="panel-body">
        <?php if($msg){?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php } ?>
        <?php if($error){?><div class="alert alert-danger"><?php echo htmlentities($error); ?></div><?php } ?>
        <form method="post">
            <?php csrf_field(); ?>
            <div class="form-group">
                <label>Student</label>
                <select name="studentid" class="form-control" required>
                    <option value="">Select Student</option>
                    <?php
                    $students = $dbh->prepare("SELECT StudentId, StudentName, RollId FROM tblstudents WHERE ClassId = :classid ORDER BY StudentName");
                    $students->execute(array(':classid' => $classId));
                    foreach($students->fetchAll(PDO::FETCH_OBJ) as $student) { ?>
                    <option value="<?php echo htmlentities($student->StudentId); ?>"><?php echo htmlentities($student->StudentName . ' - ' . $student->RollId); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-group"><label>Username</label><input type="text" name="username" class="form-control" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control"></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" class="form-control" required></div>
            <div class="form-group"><label>Status</label><select name="status" class="form-control"><option value="1">Active</option><option value="0">Inactive</option></select></div>
            <button type="submit" name="create_account" class="btn btn-primary">Create Account</button>
        </form>
    </div></div></div>
    <div class="col-md-7"><div class="panel"><div class="panel-heading"><h5>Student Accounts</h5></div><div class="panel-body">
        <table id="example" class="display table table-striped table-bordered">
            <thead><tr><th>#</th><th>Student</th><th>Username</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php
            $sql = "SELECT u.id, u.FullName, u.Username, u.Email, u.Status, s.RollId
                    FROM tblusers u
                    LEFT JOIN tblstudents s ON s.StudentId = u.StudentId
                    WHERE u.Role = 'student' AND u.ClassId = :classid
                    ORDER BY u.FullName";
            $query = $dbh->prepare($sql);
            $query->execute(array(':classid' => $classId));
            $cnt = 1;
            foreach($query->fetchAll(PDO::FETCH_OBJ) as $account) { ?>
                <tr>
                    <td><?php echo htmlentities($cnt); ?></td>
                    <td><?php echo htmlentities($account->FullName . ' - ' . $account->RollId); ?></td>
                    <td><?php echo htmlentities($account->Username); ?></td>
                    <td><?php echo htmlentities($account->Email); ?></td>
                    <td><?php echo $account->Status ? 'Active' : 'Inactive'; ?></td>
                    <td>
                        <form method="post" class="form-inline-block">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="accountid" value="<?php echo htmlentities($account->id); ?>">
                            <input type="hidden" name="status" value="<?php echo $account->Status ? '0' : '1'; ?>">
                            <button type="submit" name="update_status" class="btn btn-xs btn-warning"><?php echo $account->Status ? 'Deactivate' : 'Activate'; ?></button>
                        </form>
                        <form method="post" class="form-inline-block action-top-sm">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="accountid" value="<?php echo htmlentities($account->id); ?>">
                            <input type="password" name="newpassword" class="form-control input-sm input-password-md" placeholder="New password" required>
                            <button type="submit" name="reset_password" class="btn btn-xs btn-info">Reset</button>
                        </form>
                    </td>
                </tr>
            <?php $cnt++; } ?>
            </tbody>
        </table>
    </div></div></div>
</div></section>
</div></div></div></div></div>
<script src="js/jquery/jquery-2.2.4.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/DataTables/datatables.min.js"></script>
<script src="js/main.js"></script>
<script>$(function($){ $('#example').DataTable(); });</script>
</body>
</html>
