<?php
require_once 'includes/bootstrap.php';
$error=$msg='';

require_once 'includes/config.php';
require_once 'includes/csrf.php';
require_once 'includes/audit.php';
require_once 'includes/dean-auth.php';
require_once 'includes/notification-service.php';
require_dean();

$teacherRoles = array(
    'class_teacher' => 'Class Teacher',
    'subject_teacher' => 'Subject Teacher',
    'head_of_department' => 'Head of Department',
    'exams_officer' => 'Exams Officer',
    'deputy_dean' => 'Deputy Dean'
);
$roleSql = "'" . implode("','", array_keys($teacherRoles)) . "'";
$teacherId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($teacherId <= 0) {
    header("Location: manage-teachers.php");
    exit;
}

$teacherQuery = $dbh->prepare("SELECT * FROM tblusers WHERE id = :teacherid AND Role IN ($roleSql) LIMIT 1");
$teacherQuery->execute(array(':teacherid' => $teacherId));
$teacher = $teacherQuery->fetch(PDO::FETCH_OBJ);
if(!$teacher) {
    header("Location: manage-teachers.php");
    exit;
}

if(isset($_POST['update'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    $firstName = trim($_POST['firstname']);
    $middleName = trim($_POST['middlename']);
    $lastName = trim($_POST['lastname']);
    $fullName = trim($firstName . ' ' . ($middleName ? $middleName . ' ' : '') . $lastName);
    $staffNumber = strtoupper(trim($_POST['staffnumber']));
    $username = trim($_POST['username']);
    $email = notification_normalize_email($_POST['email']);
    $phoneNumber = trim($_POST['phonenumber']);
    $department = trim($_POST['department']);
    $role = $_POST['role'];
    $classId = $teacher->ClassId; // Legacy field is read-only; use relationship management.
    $status = $_POST['status'];
    if (!in_array((string)$status, ['0','1'], true)) { http_response_code(400); exit('Invalid account status.'); }

    if($firstName === '' || $lastName === '' || $staffNumber === '' || $username === '' || $email === '' || $role === '') {
        $error = "First name, last name, staff number, email, username, and role are required.";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif(!isset($teacherRoles[$role])) {
        $error = "Please select a valid teacher role.";
    } else {
        try {
            $oldStatus = (int)$teacher->Status;
            $oldClassId = $teacher->ClassId;
            $oldEmail = notification_normalize_email($teacher->Email);
            $sql = "UPDATE tblusers
                    SET FirstName = :firstname,
                        MiddleName = :middlename,
                        LastName = :lastname,
                        FullName = :fullname,
                        StaffNumber = :staffnumber,
                        Username = :username,
                        Email = :email,
                        PhoneNumber = :phonenumber,
                        Role = :role,
                        Department = :department,
                        ClassId = :classid,
                        Status = :status
                    WHERE id = :teacherid AND Role IN ($roleSql)";
            $query = $dbh->prepare($sql);
            $params = array(
                ':firstname' => $firstName,
                ':middlename' => $middleName,
                ':lastname' => $lastName,
                ':fullname' => $fullName,
                ':staffnumber' => $staffNumber,
                ':username' => $username,
                ':email' => $email,
                ':phonenumber' => $phoneNumber,
                ':role' => $role,
                ':department' => $department,
                ':status' => $status,
                ':teacherid' => $teacherId
            );
            if($classId === null) {
                $params[':classid'] = null;
            } else {
                $params[':classid'] = $classId;
            }
            $query->execute($params);

            if($oldEmail !== $email) {
                $emailUpdate = $dbh->prepare("UPDATE tblusers SET EmailStatus = 'UNVERIFIED', EmailVerifiedAt = NULL WHERE id = :teacherid");
                $emailUpdate->execute(array(':teacherid' => $teacherId));
                audit_log($dbh, 'teacher_email_changed', 'tblusers', $teacherId, 'Email changed from ' . $oldEmail . ' to ' . $email);
            }

            notification_create($dbh, $teacherId, 'Teacher Account Updated', 'Your SRMS teacher account details were updated by the Dean of Studies.', array(
                'category' => 'ACCOUNT',
                'type' => 'TEACHER_ACCOUNT_UPDATED',
                'related_entity_type' => 'tblusers',
                'related_entity_id' => $teacherId,
                'action_url' => 'teacher-profile.php'
            ));

            if($oldStatus !== (int)$status) {
                notification_create($dbh, $teacherId, 'Account Status Updated', 'Your SRMS teacher account is now ' . ((int)$status === 1 ? 'active' : 'inactive') . '.', array(
                    'category' => 'ACCOUNT',
                    'type' => 'TEACHER_STATUS_CHANGED',
                    'related_entity_type' => 'tblusers',
                    'related_entity_id' => $teacherId,
                    'action_url' => 'teacher-profile.php'
                ));
            }

            if((string)$oldClassId !== (string)$classId && $classId) {
                notification_create($dbh, $teacherId, 'Class Assignment Updated', 'Your class assignment was updated by the Dean of Studies. Please log into the SRMS to review your assigned class.', array(
                    'category' => 'CLASS_ASSIGNMENT',
                    'type' => 'TEACHER_CLASS_ASSIGNMENT_UPDATED',
                    'class_id' => $classId,
                    'related_entity_type' => 'tblclasses',
                    'related_entity_id' => $classId,
                    'action_url' => 'teacher-students.php'
                ));
            }

            audit_log($dbh, 'teacher_account_updated', 'tblusers', $teacherId, 'Updated by Dean of Studies');
            $msg = "Teacher details updated successfully.";
            $teacherQuery->execute(array(':teacherid' => $teacherId));
            $teacher = $teacherQuery->fetch(PDO::FETCH_OBJ);
        } catch(Exception $e) {
            $error = "Could not update teacher. Check duplicate username, staff number, or email.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Teacher | SRMS</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <link rel="stylesheet" href="css/custom.css">
</head>
<body class="top-navbar-fixed">
<div class="main-wrapper">
<?php include('includes/topbar.php');?>
<div class="content-wrapper"><div class="content-container">
<?php include('includes/leftbar.php');?>
<div class="main-page"><div class="container-fluid">
<div class="row page-title-div"><div class="col-md-8"><h2 class="title">Edit Teacher</h2></div></div>
<section class="section">
<div class="row"><div class="col-md-8">
<div class="panel"><div class="panel-heading"><h5>Teacher Details</h5></div><div class="panel-body">
<?php if($msg){?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php } ?>
<?php if($error){?><div class="alert alert-danger"><?php echo htmlentities($error); ?></div><?php } ?>
<form method="post">
    <?php csrf_field(); ?>
    <div class="row">
        <div class="col-sm-4"><div class="form-group"><label>First Name</label><input type="text" name="firstname" class="form-control" value="<?php echo htmlentities($teacher->FirstName); ?>" required></div></div>
        <div class="col-sm-4"><div class="form-group"><label>Middle Name</label><input type="text" name="middlename" class="form-control" value="<?php echo htmlentities($teacher->MiddleName); ?>"></div></div>
        <div class="col-sm-4"><div class="form-group"><label>Last Name</label><input type="text" name="lastname" class="form-control" value="<?php echo htmlentities($teacher->LastName); ?>" required></div></div>
    </div>
    <div class="row">
        <div class="col-sm-6"><div class="form-group"><label>Teacher/Staff Number</label><input type="text" name="staffnumber" class="form-control" value="<?php echo htmlentities($teacher->StaffNumber); ?>" required></div></div>
        <div class="col-sm-6"><div class="form-group"><label>Username</label><input type="text" name="username" class="form-control" value="<?php echo htmlentities($teacher->Username); ?>" required></div></div>
    </div>
    <div class="row">
        <div class="col-sm-6"><div class="form-group"><label>Email Address</label><input type="email" name="email" class="form-control" value="<?php echo htmlentities($teacher->Email); ?>" required></div></div>
        <div class="col-sm-6"><div class="form-group"><label>Phone Number</label><input type="text" name="phonenumber" class="form-control" value="<?php echo htmlentities($teacher->PhoneNumber); ?>"></div></div>
    </div>
    <div class="row">
        <div class="col-sm-6"><div class="form-group"><label>Account category</label><select name="role" class="form-control" required>
            <?php foreach($teacherRoles as $key => $label) { ?>
            <option value="<?php echo htmlentities($key); ?>" <?php echo $teacher->Role === $key ? 'selected' : ''; ?>><?php echo htmlentities($label); ?></option>
            <?php } ?>
        </select></div></div>
        <div class="col-sm-6"><div class="form-group"><label>Department</label><input type="text" name="department" class="form-control" value="<?php echo htmlentities($teacher->Department); ?>"></div></div>
    </div>
    <div class="row">
        <div class="col-sm-6"><p><a class="btn btn-info" href="dean-teacher-relationships.php?teacher=<?php echo (int)$teacher->id; ?>">Change Teaching / Class Assignments and Responsibilities</a></p></div>
        <div class="col-sm-6"><div class="form-group"><label>Account Status</label><select name="status" class="form-control">
            <option value="1" <?php echo (int)$teacher->Status === 1 ? 'selected' : ''; ?>>Active</option>
            <option value="0" <?php echo (int)$teacher->Status === 0 ? 'selected' : ''; ?>>Inactive</option>
        </select></div></div>
    </div>
    <button type="submit" name="update" class="btn btn-primary">Update Teacher</button>
    <a href="manage-teachers.php" class="btn btn-default">Back to Manage Teachers</a>
</form>
</div></div>
</div></div>
</section>
</div></div></div></div></div>
<script src="js/jquery/jquery-3.7.1.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
