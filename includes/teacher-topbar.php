<?php
include_once(__DIR__ . '/notification-service.php');
include_once(__DIR__ . '/csrf.php');
$teacherNotificationCount = function_exists('teacher_id') ? notification_unread_count($dbh, teacher_id()) : 0;
$teacherRecentNotifications = function_exists('teacher_id') ? notification_recent($dbh, teacher_id(), 5) : array();
?>
<nav class="navbar top-navbar bg-white box-shadow" aria-label="Application navigation">
    <div class="container-fluid">
        <div class="srms-navbar-inner">
            <div class="navbar-header no-padding">
                <a class="navbar-brand" href="<?php echo teacher_class_id() ? 'teacher-dashboard.php' : 'teacher-academic-assignments.php'; ?>"><span class="srms-brand-mark" aria-hidden="true"><i class="fa fa-graduation-cap"></i></span><span class="srms-brand-text">SRMS<small><?php echo htmlentities(function_exists('teacher_role_label') ? teacher_role_label() : 'Teacher'); ?></small></span></a>
                <button type="button" class="small-nav-handle srms-icon-button hidden-sm hidden-xs" aria-label="Collapse sidebar" aria-expanded="true" aria-controls="srms-sidebar" title="Collapse sidebar"><i class="fa fa-outdent" aria-hidden="true"></i></button>
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar-collapse-1" aria-expanded="false" aria-controls="navbar-collapse-1" aria-label="Account and notifications">
                    <span class="sr-only">Toggle navigation</span>
                    <i class="fa fa-ellipsis-v"></i>
                </button>
                <button type="button" class="navbar-toggle mobile-nav-toggle" aria-label="Open sidebar" aria-expanded="false" aria-controls="srms-sidebar">
                    <i class="fa fa-bars" aria-hidden="true"></i>
                </button>
            </div>
            <div class="collapse navbar-collapse" id="navbar-collapse-1">
                <ul class="nav navbar-nav navbar-right">
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false" aria-haspopup="true" aria-label="Notifications" title="Notifications">
                            <i class="fa fa-bell-o" aria-hidden="true"></i>
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
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle srms-profile-toggle" data-toggle="dropdown" aria-expanded="false" aria-haspopup="true"><span class="srms-avatar" aria-hidden="true"><i class="fa fa-user"></i></span><span class="srms-profile-name"><?php echo htmlentities(teacher_name()); ?></span><i class="fa fa-angle-down" aria-hidden="true"></i></a>
                        <ul class="dropdown-menu">
                            <li><a href="teacher-profile.php"><i class="fa fa-user" aria-hidden="true"></i> My Profile</a></li>
                            <li role="separator" class="divider"></li>
                            <li><a href="teacher-logout.php" class="color-danger"><i class="fa fa-sign-out" aria-hidden="true"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
<button type="button" class="srms-nav-backdrop" aria-label="Close sidebar" tabindex="-1"></button>
<script src="js/srms-ui.js" defer></script>
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
