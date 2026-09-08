<?php
include_once(__DIR__ . '/notification-service.php');
include_once(__DIR__ . '/csrf.php');
$teacherNotificationCount = function_exists('teacher_id') ? notification_unread_count($dbh, teacher_id()) : 0;
$teacherRecentNotifications = function_exists('teacher_id') ? notification_recent($dbh, teacher_id(), 5) : array();
?>
<nav class="navbar top-navbar bg-white box-shadow">
    <div class="container-fluid">
        <div class="row">
            <div class="navbar-header no-padding">
                <a class="navbar-brand" href="<?php echo teacher_class_id() ? 'teacher-dashboard.php' : 'teacher-academic-assignments.php'; ?>">SRMS | <?php echo htmlentities(function_exists('teacher_role_label') ? teacher_role_label() : 'Teacher'); ?></a>
                <span class="small-nav-handle hidden-sm hidden-xs"><i class="fa fa-outdent"></i></span>
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar-collapse-1" aria-expanded="false">
                    <span class="sr-only">Toggle navigation</span>
                    <i class="fa fa-ellipsis-v"></i>
                </button>
                <button type="button" class="navbar-toggle mobile-nav-toggle">
                    <i class="fa fa-bars"></i>
                </button>
            </div>
            <div class="collapse navbar-collapse" id="navbar-collapse-1">
                <ul class="nav navbar-nav navbar-right" data-dropdown-in="fadeIn" data-dropdown-out="fadeOut">
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-bell"></i>
                            <span id="teacher-notification-badge" class="badge badge-danger <?php echo $teacherNotificationCount > 0 ? '' : 'badge-hidden'; ?>"><?php echo $teacherNotificationCount > 0 ? htmlentities($teacherNotificationCount) : ''; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-notifications">
                            <li class="dropdown-header">
                                Notifications
                                <form method="post" action="teacher-notifications.php" class="pull-right form-inline-action">
                                    <?php csrf_field(); ?>
                                    <button type="submit" name="mark_all_read" class="btn btn-link btn-xs btn-link-reset">Mark all as read</button>
                                </form>
                            </li>
                            <li class="divider"></li>
                            <?php if($teacherRecentNotifications) {
                                foreach($teacherRecentNotifications as $note) {
                                    $noteUrl = $note->ActionUrl ? $note->ActionUrl : 'teacher-notifications.php';
                                    $isUnread = $note->ReadAt === null;
                            ?>
                                <li>
                                    <a href="teacher-notifications.php?action=open&id=<?php echo htmlentities($note->id); ?>&redirect=<?php echo urlencode($noteUrl); ?>" class="<?php echo $isUnread ? 'link-strong' : ''; ?>">
                                        <?php echo $isUnread ? '<i class="fa fa-circle text-primary"></i> ' : '<i class="fa fa-check text-muted"></i> '; ?>
                                        <?php echo htmlentities($note->Title); ?><br>
                                        <small class="text-muted"><?php echo htmlentities(substr($note->Message, 0, 72)); ?></small>
                                    </a>
                                </li>
                            <?php }} else { ?>
                                <li><a href="teacher-notifications.php" class="text-muted">No notifications yet.</a></li>
                            <?php } ?>
                            <li class="divider"></li>
                            <li><a href="teacher-notifications.php" class="text-center">View All Notifications</a></li>
                        </ul>
                    </li>
                    <li><a href="teacher-profile.php"><i class="fa fa-user"></i> <?php echo htmlentities(teacher_name()); ?></a></li>
                    <li><a href="teacher-logout.php" class="color-danger text-center"><i class="fa fa-sign-out"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
<script>
setInterval(function() {
    if(!window.jQuery) { return; }
    $.getJSON('teacher-notification-count.php', function(data) {
        var badge = $('#teacher-notification-badge');
        if(data.count && data.count > 0) {
            badge.text(data.count).show();
        } else {
            badge.text('').hide();
        }
    });
}, 30000);
</script>
