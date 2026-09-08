<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/csrf.php');
include('includes/audit.php');
include('includes/dean-auth.php');
include('includes/notification-service.php');
require_dean();
require_once 'includes/academic-assignments.php';
require_once 'includes/academic-teacher-summary.php';

$teacherRoles = array(
    'class_teacher' => 'Class Teacher',
    'subject_teacher' => 'Subject Teacher',
    'head_of_department' => 'Head of Department',
    'exams_officer' => 'Exams Officer',
    'deputy_dean' => 'Deputy Dean'
);
$teacherRoleKeys = array_keys($teacherRoles);
$roleSql = "'" . implode("','", $teacherRoleKeys) . "'";

function teacher_assignment_counts($dbh, $teacherId)
{
    $lessonQuery = $dbh->prepare("SELECT COUNT(*) FROM tblclasstimetableentries WHERE TeacherId = :teacherid AND Status <> 'cancelled'");
    $lessonQuery->execute(array(':teacherid' => $teacherId));
    $examQuery = $dbh->prepare("SELECT COUNT(*) FROM tblexamtimetableentries e WHERE (e.InvigilatorId=:teacherid OR EXISTS(SELECT 1 FROM tblexaminvigilators i WHERE i.SessionId=e.id AND i.TeacherId=:memberid)) AND e.Status NOT IN ('cancelled','archived')");
    $examQuery->execute(array(':teacherid' => $teacherId, ':memberid' => $teacherId));
    return array('lessons' => (int)$lessonQuery->fetchColumn(), 'exams' => (int)$examQuery->fetchColumn());
}

function teacher_status_label($status)
{
    return (int)$status === 1 ? 'ACTIVE' : 'INACTIVE';
}

if(isset($_POST['submit'])) {
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
    $role = $_POST['role'] ?? 'subject_teacher';
    $classId = null; // Academic class assignments are stored separately.
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmpassword'];
    $status = $_POST['status'];
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $createdBy = 'dean:' . dean_name();

    if($firstName === '' || $lastName === '' || $staffNumber === '' || $username === '' || $email === '' || $password === '' || $role === '') {
        $error = "First name, last name, staff number, email, username, role, and password are required.";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif(!isset($teacherRoles[$role])) {
        $error = "Please select a valid teacher role.";
    } elseif(strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif($password !== $confirmPassword) {
        $error = "Password and confirm password do not match.";
    } else {
        try {
            if (!in_array((string)$status, ['0','1'], true)) throw new DomainException('Select a valid account status.');
            $dbh->beginTransaction();
            $sql = "INSERT INTO tblusers(FirstName, MiddleName, LastName, FullName, StaffNumber, Username, Email, PhoneNumber, PasswordHash, MustChangePassword, Role, Department, ClassId, Status, CreatedBy)
                    VALUES(:firstname, :middlename, :lastname, :fullname, :staffnumber, :username, :email, :phonenumber, :passwordhash, 1, :role, :department, :classid, :status, :createdby)";
            $query = $dbh->prepare($sql);
            $query->execute(array(
                ':firstname' => $firstName,
                ':middlename' => $middleName,
                ':lastname' => $lastName,
                ':fullname' => $fullName,
                ':staffnumber' => $staffNumber,
                ':username' => $username,
                ':email' => $email,
                ':phonenumber' => $phoneNumber,
                ':passwordhash' => $passwordHash,
                ':role' => $role,
                ':department' => $department,
                ':classid' => $classId,
                ':status' => $status,
                ':createdby' => $createdBy
            ));
            $teacherId = $dbh->lastInsertId();
            if(academic_ready($dbh)) academic_creation_assignments($dbh, $teacherId, $_POST);
            notification_create($dbh, $teacherId, 'Welcome to the School SRMS', "Hello " . $fullName . ",\n\nAn account has been created for you on the Student Result Management System.\n\nUsername: " . $username . "\nRole: " . $teacherRoles[$role] . "\n\nPlease log into the SRMS to access your assigned classes, subjects, timetables and academic activities.\n\nFor security reasons, your password is not included in this email.", array(
                'category' => 'ACCOUNT',
                'type' => 'TEACHER_ACCOUNT_CREATED',
                'related_entity_type' => 'tblusers',
                'related_entity_id' => $teacherId,
                'action_url' => 'teacher-profile.php'
            ));
            audit_log($dbh, 'teacher_account_created', 'tblusers', $teacherId, 'Role: ' . $role . ', Staff Number: ' . $staffNumber);
            $dbh->commit();
            $_SESSION['teacher_created_notice'] = 'Teacher account and assignments created successfully.';
            header('Location: manage-teachers.php'); exit;
        } catch(Exception $e) {
            if($dbh->inTransaction()) $dbh->rollBack();
            $error = $e instanceof DomainException ? $e->getMessage() : 'Could not create teacher. Check duplicate username, staff number, or email.';
        }
    }
}

if(isset($_POST['bulk_status'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    $selectedTeachers = $_POST['selected_teachers'] ?? array();
    $newStatus = $_POST['bulk_status'] === 'activate' ? 1 : 0;
    foreach($selectedTeachers as $teacherId) {
        $teacherId = (int)$teacherId;
        $query = $dbh->prepare("UPDATE tblusers SET Status = :status WHERE id = :teacherid AND Role IN ($roleSql)");
        $query->execute(array(':status' => $newStatus, ':teacherid' => $teacherId));
        audit_log($dbh, $newStatus ? 'teacher_account_activated' : 'teacher_account_deactivated', 'tblusers', $teacherId, 'Bulk status update by Dean of Studies');
    }
    $msg = "Selected teacher accounts updated.";
}

if(isset($_POST['update_status'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    $teacherId = (int)$_POST['teacherid'];
    $status = (int)$_POST['status'];
    $sql = "UPDATE tblusers SET Status = :status WHERE id = :teacherid AND Role IN ($roleSql)";
    $query = $dbh->prepare($sql);
    $query->execute(array(':status' => $status, ':teacherid' => $teacherId));
    notification_create($dbh, $teacherId, 'Account Status Updated', 'Your SRMS teacher account is now ' . ($status ? 'active' : 'inactive') . '.', array(
        'category' => 'ACCOUNT',
        'type' => 'TEACHER_STATUS_CHANGED',
        'related_entity_type' => 'tblusers',
        'related_entity_id' => $teacherId,
        'action_url' => 'teacher-profile.php'
    ));
    audit_log($dbh, $status ? 'teacher_account_activated' : 'teacher_account_deactivated', 'tblusers', $teacherId, 'Status changed to ' . $status);
    $msg = "Teacher status updated.";
}

if(isset($_POST['reset_password'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    $teacherId = (int)$_POST['teacherid'];
    $newPassword = $_POST['newpassword'];
    if(strlen($newPassword) < 8) {
        $error = "New password must be at least 8 characters.";
    } else {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE tblusers SET PasswordHash = :passwordhash, MustChangePassword = 1 WHERE id = :teacherid AND Role IN ($roleSql)";
        $query = $dbh->prepare($sql);
        $query->execute(array(':passwordhash' => $passwordHash, ':teacherid' => $teacherId));
        notification_create($dbh, $teacherId, 'Password Reset', 'Your SRMS password was reset by the Dean of Studies. Please contact the school office if you did not request this change.', array(
            'category' => 'ACCOUNT',
            'type' => 'TEACHER_PASSWORD_RESET',
            'related_entity_type' => 'tblusers',
            'related_entity_id' => $teacherId,
            'action_url' => 'teacher-profile.php'
        ));
        audit_log($dbh, 'teacher_password_reset', 'tblusers', $teacherId, 'Password reset by Dean of Studies');
        $msg = "Teacher password reset successfully.";
    }
}

if(isset($_SESSION['teacher_created_notice'])) { $msg=$_SESSION['teacher_created_notice']; unset($_SESSION['teacher_created_notice']); }
$summary = array();
$summary['total'] = $dbh->query("SELECT COUNT(*) FROM tblusers WHERE Role IN ($roleSql)")->fetchColumn();
$summary['active'] = $dbh->query("SELECT COUNT(*) FROM tblusers WHERE Role IN ($roleSql) AND Status = 1")->fetchColumn();
$summary['inactive'] = $dbh->query("SELECT COUNT(*) FROM tblusers WHERE Role IN ($roleSql) AND Status = 0")->fetchColumn();
$summary['classTeachers'] = academic_ready($dbh) ? academic_query($dbh, 'SELECT COUNT(DISTINCT TeacherId) FROM tblclassteacherassignments WHERE Status=1 AND AcademicYearId=?', [academic_year($dbh)])->fetchColumn() : 0;
$summary['hods'] = $dbh->query("SELECT COUNT(*) FROM tblusers WHERE Role = 'head_of_department'")->fetchColumn();

$search = trim($_GET['search'] ?? '');
$department = trim($_GET['department'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';
$roleFilter = $_GET['role'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = "WHERE u.Role IN ($roleSql)";
$params = array();
if($search !== '') {
    $where .= " AND (u.FullName LIKE :search OR u.StaffNumber LIKE :search OR u.Email LIKE :search OR u.Username LIKE :search OR u.Department LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if($department !== '') {
    $where .= " AND u.Department = :department";
    $params[':department'] = $department;
}
if($statusFilter === 'active' || $statusFilter === 'inactive') {
    $where .= " AND u.Status = :status";
    $params[':status'] = $statusFilter === 'active' ? 1 : 0;
}
if($roleFilter !== 'all' && isset($teacherRoles[$roleFilter])) {
    if (academic_ready($dbh) && in_array($roleFilter,['class_teacher','subject_teacher'],true)) {
        $assignmentTable=$roleFilter==='class_teacher'?'tblclassteacherassignments':'tblsubjectteacherassignments';
        $where .= " AND EXISTS (SELECT 1 FROM $assignmentTable a WHERE a.TeacherId=u.id AND a.Status=1 AND a.AcademicYearId=:role_year)";
        $params[':role_year']=academic_year($dbh);
    } else { $where .= " AND u.Role = :role"; $params[':role'] = $roleFilter; }
}

$countQuery = $dbh->prepare("SELECT COUNT(*) FROM tblusers u $where");
$countQuery->execute($params);
$totalRows = (int)$countQuery->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$sql = "SELECT u.id, u.FullName, u.Username, u.Email, u.StaffNumber, u.Department, u.Role, u.Status, u.CreationDate, u.LastLoginAt, c.ClassName, c.Section,
        (SELECT COUNT(*) FROM tblclasstimetableentries t WHERE t.TeacherId = u.id AND t.Status <> 'cancelled') AS LessonCount,
        (SELECT COUNT(*) FROM tblexamtimetableentries et WHERE (et.InvigilatorId=u.id OR EXISTS(SELECT 1 FROM tblexaminvigilators i WHERE i.SessionId=et.id AND i.TeacherId=u.id)) AND et.Status NOT IN ('cancelled','archived')) AS ExamDutyCount
        FROM tblusers u
        LEFT JOIN tblclasses c ON c.id = u.ClassId
        $where
        ORDER BY u.FullName ASC
        LIMIT $perPage OFFSET $offset";
$query = $dbh->prepare($sql);
$query->execute($params);
$teachers = $query->fetchAll(PDO::FETCH_OBJ);

$departments = $dbh->query("SELECT DISTINCT Department FROM tblusers WHERE Role IN ($roleSql) AND Department IS NOT NULL AND Department <> '' ORDER BY Department")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Teachers | SRMS</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <link rel="stylesheet" href="css/custom.css" media="screen">
</head>
<body class="top-navbar-fixed">
<div class="main-wrapper">
<?php include('includes/topbar.php');?>
<div class="content-wrapper"><div class="content-container">
<?php include('includes/leftbar.php');?>
<div class="main-page"><div class="container-fluid">
<div class="row page-title-div">
    <div class="col-md-8"><h2 class="title">Manage Teachers</h2></div>
    <div class="col-md-4 text-right"><a href="#create-teacher" class="btn btn-primary"><i class="fa fa-plus"></i> Create Teacher</a></div>
</div>
<section class="section">
<?php include 'includes/academic-overview.php'; ?>
<?php if($msg){?><div class="alert alert-success"><?php echo htmlentities($msg); ?></div><?php } ?>
<?php if($error){?><div class="alert alert-danger"><?php echo htmlentities($error); ?></div><?php } ?>

<div class="row">
    <div class="col-sm-2"><div class="summary-box"><span class="number"><?php echo htmlentities($summary['total']); ?></span>Total Teachers</div></div>
    <div class="col-sm-2"><div class="summary-box"><span class="number"><?php echo htmlentities($summary['active']); ?></span>Active</div></div>
    <div class="col-sm-2"><div class="summary-box"><span class="number"><?php echo htmlentities($summary['inactive']); ?></span>Inactive</div></div>
    <div class="col-sm-3"><div class="summary-box"><span class="number"><?php echo htmlentities($summary['classTeachers']); ?></span>Class Teachers</div></div>
    <div class="col-sm-3"><div class="summary-box"><span class="number"><?php echo htmlentities($summary['hods']); ?></span>Heads of Department</div></div>
</div>

<div class="panel"><div class="panel-heading"><h5>Search and Filter</h5></div><div class="panel-body">
<form method="get" class="row">
    <div class="col-sm-4"><div class="form-group"><label>Search</label><input type="text" name="search" class="form-control" value="<?php echo htmlentities($search); ?>" placeholder="Name, staff no., email, username, department"></div></div>
    <div class="col-sm-3"><div class="form-group"><label>Department</label><select name="department" class="form-control"><option value="">All Departments</option><?php foreach($departments as $dept){ ?><option value="<?php echo htmlentities($dept); ?>" <?php echo $department === $dept ? 'selected' : ''; ?>><?php echo htmlentities($dept); ?></option><?php } ?></select></div></div>
    <div class="col-sm-2"><div class="form-group"><label>Status</label><select name="status" class="form-control"><option value="all">All</option><option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option><option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option></select></div></div>
        <input type="hidden" name="role" value="subject_teacher">
    <div class="col-sm-12"><button type="submit" class="btn btn-primary">Apply Filters</button> <a href="manage-teachers.php" class="btn btn-default">Clear</a> <a href="teacher-departments.php" class="btn btn-default">Departments</a></div>
</form>
</div></div>

<div class="panel"><div class="panel-heading"><h5>Teachers</h5></div><div class="panel-body">
<form method="post" id="bulk-teacher-form">
<?php csrf_field(); ?>
</form>
<div class="teacher-table-wrapper">
<table class="table table-striped table-bordered">
    <thead><tr><th><input type="checkbox" id="select-all"></th><th>Teacher</th><th>Staff Number</th><th>Email</th><th>Department</th><th>Account Category</th><th>Class Teacher Of</th><th>Assignments</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($teachers as $teacher) {
        $counts = teacher_assignment_counts($dbh, $teacher->id);
        $warning = $counts['lessons'] . ' active timetable lessons and ' . $counts['exams'] . ' exam duties';
    ?>
        <tr>
            <td><input type="checkbox" name="selected_teachers[]" value="<?php echo htmlentities($teacher->id); ?>" form="bulk-teacher-form"></td>
            <td><?php echo htmlentities($teacher->FullName); ?><br><small><?php echo htmlentities($teacher->Username); ?></small></td>
            <td><?php echo htmlentities($teacher->StaffNumber); ?></td>
            <td><?php echo htmlentities($teacher->Email); ?></td>
            <td><?php echo htmlentities($teacher->Department); ?></td>
            <td><?php echo in_array($teacher->Role,['class_teacher','subject_teacher'],true) ? 'Teacher' : htmlentities($teacherRoles[$teacher->Role] ?? $teacher->Role); ?></td>
            <td><?php if(academic_ready($dbh)) { $names=academic_query($dbh,'SELECT CONCAT(c.ClassName," ",c.Section) FROM tblclassteacherassignments a JOIN tblclasses c ON c.id=a.ClassId WHERE a.TeacherId=? AND a.AcademicYearId=? AND a.Status=1',[$teacher->id,academic_year($dbh)])->fetchAll(PDO::FETCH_COLUMN); echo academic_h(implode(", ",$names)); } ?></td>
            <td><?php academic_teacher_summary($dbh,(int)$teacher->id); ?><?php echo htmlentities($teacher->LessonCount); ?> lessons/week<br><?php echo htmlentities($teacher->ExamDutyCount); ?> exam duties</td>
            <td><span class="status-badge <?php echo $teacher->Status ? 'status-active' : 'status-inactive'; ?>"><?php echo teacher_status_label($teacher->Status); ?></span></td>
            <td><?php echo $teacher->LastLoginAt ? htmlentities($teacher->LastLoginAt) : 'Never Logged In'; ?></td>
            <td>
                <a href="view-teacher.php?id=<?php echo htmlentities($teacher->id); ?>" class="btn btn-xs btn-default">View</a>
                <a href="edit-teacher.php?id=<?php echo htmlentities($teacher->id); ?>" class="btn btn-xs btn-primary">Edit</a>
                <a href="dean-teacher-relationships.php?teacher=<?php echo htmlentities($teacher->id); ?>" class="btn btn-xs btn-info">Assignments</a>
                <form method="post" class="form-inline-block">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="teacherid" value="<?php echo htmlentities($teacher->id); ?>">
                    <input type="hidden" name="status" value="<?php echo $teacher->Status ? '0' : '1'; ?>">
                    <button type="submit" name="update_status" class="btn btn-xs btn-warning" onclick="return confirm('<?php echo $teacher->Status ? 'Deactivate ' . addslashes($teacher->FullName) . '? This teacher will no longer be able to log in. Existing records stay available. Current responsibilities: ' . addslashes($warning) : 'Activate ' . addslashes($teacher->FullName) . '?'; ?>');"><?php echo $teacher->Status ? 'Deactivate' : 'Activate'; ?></button>
                </form>
                <form method="post" class="form-inline-block action-top-sm">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="teacherid" value="<?php echo htmlentities($teacher->id); ?>">
                    <input type="password" name="newpassword" class="form-control input-sm input-password-sm" placeholder="Min 8 chars" required>
                    <button type="submit" name="reset_password" class="btn btn-xs btn-danger">Reset</button>
                </form>
            </td>
        </tr>
    <?php } ?>
    </tbody>
</table>
</div>

<?php foreach($teachers as $teacher) { ?>
<div class="teacher-card">
    <strong><?php echo htmlentities($teacher->FullName); ?></strong>
    <span class="status-badge <?php echo $teacher->Status ? 'status-active' : 'status-inactive'; ?> pull-right"><?php echo teacher_status_label($teacher->Status); ?></span>
    <p><?php echo htmlentities($teacher->StaffNumber); ?> | <?php echo htmlentities($teacher->Department); ?> | <?php echo htmlentities($teacherRoles[$teacher->Role] ?? $teacher->Role); ?></p>
    <p>Last Login: <?php echo $teacher->LastLoginAt ? htmlentities($teacher->LastLoginAt) : 'Never Logged In'; ?></p>
    <a href="view-teacher.php?id=<?php echo htmlentities($teacher->id); ?>" class="btn btn-xs btn-default">View</a>
    <a href="edit-teacher.php?id=<?php echo htmlentities($teacher->id); ?>" class="btn btn-xs btn-primary">Edit</a>
    <a href="dean-teacher-relationships.php?teacher=<?php echo htmlentities($teacher->id); ?>" class="btn btn-xs btn-info">Assignments</a>
</div>
<?php } ?>

<div class="row">
    <div class="col-sm-6">
        <button type="submit" name="bulk_status" value="activate" class="btn btn-sm btn-success" form="bulk-teacher-form">Activate Selected</button>
        <button type="submit" name="bulk_status" value="deactivate" class="btn btn-sm btn-warning" form="bulk-teacher-form" onclick="return confirm('Deactivate selected teachers? They will no longer be able to log in, but historical records will remain.');">Deactivate Selected</button>
    </div>
    <div class="col-sm-6 text-right">
        Showing <?php echo $totalRows ? htmlentities($offset + 1) : 0; ?> - <?php echo htmlentities(min($offset + $perPage, $totalRows)); ?> of <?php echo htmlentities($totalRows); ?> teachers
        <?php if($page > 1) { ?><a class="btn btn-xs btn-default" href="?<?php echo http_build_query(array_merge($_GET, array('page' => $page - 1))); ?>">Previous</a><?php } ?>
        <?php if($page < $totalPages) { ?><a class="btn btn-xs btn-default" href="?<?php echo http_build_query(array_merge($_GET, array('page' => $page + 1))); ?>">Next</a><?php } ?>
    </div>
</div>
</div></div>

<div class="panel" id="create-teacher"><div class="panel-heading"><h5>Create Teacher</h5></div><div class="panel-body">
<form method="post">
    <?php csrf_field(); ?>
    <div class="row">
        <div class="col-sm-4"><div class="form-group"><label>First Name</label><input type="text" name="firstname" class="form-control" required></div></div>
        <div class="col-sm-4"><div class="form-group"><label>Middle Name</label><input type="text" name="middlename" class="form-control"></div></div>
        <div class="col-sm-4"><div class="form-group"><label>Last Name</label><input type="text" name="lastname" class="form-control" required></div></div>
    </div>
    <div class="row">
        <div class="col-sm-4"><div class="form-group"><label>Teacher/Staff Number</label><input type="text" name="staffnumber" class="form-control" required></div></div>
        <div class="col-sm-4"><div class="form-group"><label>Email Address</label><input type="email" name="email" class="form-control" required></div></div>
        <div class="col-sm-4"><div class="form-group"><label>Phone Number</label><input type="text" name="phonenumber" class="form-control"></div></div>
    </div>
    <div class="row">
        <div class="col-sm-4"><div class="form-group"><label>Username</label><input type="text" name="username" class="form-control" required></div></div>
        <input type="hidden" name="role" value="subject_teacher">
        <div class="col-sm-4"><div class="form-group"><label>Department</label><input type="text" name="department" class="form-control"></div></div>
    </div>
    <div class="row">
        <div class="col-sm-4"><div class="form-group"><label>Password</label><input type="password" name="password" class="form-control" required></div></div>
        <div class="col-sm-4"><div class="form-group"><label>Confirm Password</label><input type="password" name="confirmpassword" class="form-control" required></div></div>
    </div>
    <div class="form-group"><label>Account Status</label><select name="status" class="form-control"><option value="1">Active</option><option value="0">Inactive</option></select></div>
    <?php include 'includes/academic-assignment-form.php'; ?>
    <button type="submit" name="submit" class="btn btn-primary">Create Teacher</button>
</form>
</div></div>
</section>
</div></div></div></div></div>
<script src="js/jquery/jquery-2.2.4.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
$('#select-all').on('change', function() {
    $('input[name="selected_teachers[]"]').prop('checked', this.checked);
});
</script>
</body>
</html>
