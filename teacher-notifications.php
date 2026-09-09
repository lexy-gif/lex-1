<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/csrf.php');
include('includes/teacher-auth.php');
include('includes/notification-service.php');
require_teacher();

$teacherId = teacher_id();
$allowedFilters = array('all','unread','read','ACCOUNT','CLASS_ASSIGNMENT','SUBJECT_ASSIGNMENT','TIMETABLE','EXAM_TIMETABLE','RESULTS','MARKS','DEADLINE','ATTENDANCE','SYSTEM');
$filter = $_GET['filter'] ?? 'all';
if(!in_array($filter, $allowedFilters)) {
    $filter = 'all';
}

if(isset($_POST['mark_all_read'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    notification_mark_all_read($dbh, $teacherId);
    header("Location: teacher-notifications.php?filter=" . urlencode($filter));
    exit;
}

if(isset($_POST['mark_read'])) {
    csrf_require_valid($_POST['csrf_token'] ?? '');
    notification_mark_read($dbh, $teacherId, (int)$_POST['notificationid']);
    header("Location: teacher-notifications.php?filter=" . urlencode($filter));
    exit;
}

if(($_GET['action'] ?? '') === 'open' && isset($_GET['id'])) {
    $notificationId = (int)$_GET['id'];
    if(notification_mark_read($dbh, $teacherId, $notificationId)) {
        $redirect = $_GET['redirect'] ?? '';
        if($redirect !== '' && preg_match('/^[a-zA-Z0-9_\-\/]+\.php(\?.*)?$/', $redirect)) {
            header("Location: " . $redirect);
            exit;
        }
    }
    header("Location: teacher-notifications.php");
    exit;
}

$where = "WHERE n.TeacherId = :teacherid";
$params = array(':teacherid' => $teacherId);
if($filter === 'unread') {
    $where .= " AND n.ReadAt IS NULL";
} elseif($filter === 'read') {
    $where .= " AND n.ReadAt IS NOT NULL";
} elseif($filter !== 'all') {
    $where .= " AND n.Category = :category";
    $params[':category'] = $filter;
}

$sql = "SELECT n.*, c.ClassName, c.Section, s.SubjectName
        FROM tblteachernotifications n
        LEFT JOIN tblclasses c ON c.id = n.ClassId
        LEFT JOIN tblsubjects s ON s.id = n.SubjectId
        $where
        ORDER BY n.CreationDate DESC";
$query = $dbh->prepare($sql);
$query->execute($params);
$notes = $query->fetchAll(PDO::FETCH_OBJ);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notifications | SRMS</title>
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
<div class="row page-title-div"><div class="col-md-8"><h2 class="title">Notification Centre</h2></div></div>
<section class="section">
<div class="panel"><div class="panel-heading">
    <h5>Notifications
        <form method="post" class="pull-right">
            <?php csrf_field(); ?>
            <button type="submit" name="mark_all_read" class="btn btn-xs btn-primary">Mark All as Read</button>
        </form>
    </h5>
</div><div class="panel-body">
    <div class="filter-links">
        <?php foreach($allowedFilters as $item) {
            $label = $item === 'all' ? 'All' : ($item === 'unread' ? 'Unread' : ($item === 'read' ? 'Read' : ucwords(strtolower(str_replace('_', ' ', $item)))));
            $class = $filter === $item ? 'btn btn-primary btn-sm' : 'btn btn-default btn-sm';
            echo '<a class="' . $class . '" href="teacher-notifications.php?filter=' . urlencode($item) . '">' . htmlentities($label) . '</a>';
        } ?>
    </div>
    <hr>
    <?php if($notes) {
        foreach($notes as $note) {
            $isUnread = $note->ReadAt === null;
            $actionUrl = $note->ActionUrl ? $note->ActionUrl : 'teacher-notifications.php';
    ?>
        <div class="notification-item <?php echo $isUnread ? 'unread' : ''; ?>">
            <div class="row">
                <div class="col-sm-8">
                    <strong><?php echo $isUnread ? '<i class="fa fa-circle text-primary"></i> ' : '<i class="fa fa-check text-muted"></i> '; ?><?php echo htmlentities($note->Title); ?></strong>
                    <p><?php echo nl2br(htmlentities($note->Message)); ?></p>
                    <div class="notification-meta">
                        <?php echo htmlentities(ucwords(strtolower(str_replace('_', ' ', $note->Category)))); ?>
                        <?php if($note->ClassName) { echo ' | ' . htmlentities($note->ClassName . ' Section-' . $note->Section); } ?>
                        <?php if($note->SubjectName) { echo ' | ' . htmlentities($note->SubjectName); } ?>
                        <?php echo ' | ' . htmlentities($note->CreationDate); ?>
                    </div>
                </div>
                <div class="col-sm-4 text-right">
                    <a class="btn btn-xs btn-success" href="teacher-notifications.php?action=open&id=<?php echo htmlentities($note->id); ?>&redirect=<?php echo urlencode($actionUrl); ?>">Open</a>
                    <?php if($isUnread) { ?>
                    <form method="post" class="form-inline-action">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="notificationid" value="<?php echo htmlentities($note->id); ?>">
                        <button type="submit" name="mark_read" class="btn btn-xs btn-default">Mark as Read</button>
                    </form>
                    <?php } ?>
                </div>
            </div>
        </div>
    <?php }} else { ?>
        <p class="text-muted">No notifications found.</p>
    <?php } ?>
</div></div>
</section>
</div></div></div></div></div>
<script src="js/jquery/jquery-2.2.4.min.js"></script>
<script src="js/bootstrap/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
