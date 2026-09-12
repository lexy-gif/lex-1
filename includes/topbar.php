<?php
require_once __DIR__.'/csrf.php';
$deanDeliveryCount = 0;
try {
    if(isset($dbh)) {
        $deliveryQuery = $dbh->prepare("SELECT COUNT(*) FROM tblnotificationdeliveries WHERE Status IN ('PENDING','FAILED','RETRYING')");
        $deliveryQuery->execute();
        $deanDeliveryCount = (int)$deliveryQuery->fetchColumn();
    }
} catch(Exception $e) {
    $deanDeliveryCount = 0;
}
?>
<nav class="navbar top-navbar bg-white box-shadow" aria-label="Application navigation">
    <div class="container-fluid">
        <div class="srms-navbar-inner">
            <div class="navbar-header no-padding">
                <a class="navbar-brand" href="dashboard.php">
                    <span class="srms-brand-mark" aria-hidden="true"><i class="fa fa-graduation-cap"></i></span>
                    <span class="srms-brand-text">SRMS<small>Dean of Studies</small></span>
                </a>
                <button type="button" class="small-nav-handle srms-icon-button hidden-sm hidden-xs" aria-label="Collapse sidebar" aria-expanded="true" aria-controls="srms-sidebar" title="Collapse sidebar"><i class="fa fa-outdent" aria-hidden="true"></i></button>
                <button type="button" class="navbar-toggle mobile-nav-toggle" aria-label="Open sidebar" aria-expanded="false" aria-controls="srms-sidebar"><i class="fa fa-bars" aria-hidden="true"></i></button>
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar-collapse-1" aria-expanded="false" aria-controls="navbar-collapse-1" aria-label="Account and notifications"><i class="fa fa-ellipsis-v" aria-hidden="true"></i></button>
            </div>
            <div class="collapse navbar-collapse" id="navbar-collapse-1">
                <ul class="nav navbar-nav">
                    <li class="hidden-sm hidden-xs"><a href="#" class="user-info-handle" title="Sidebar profile" aria-label="Toggle sidebar profile"><i class="fa fa-user" aria-hidden="true"></i></a></li>
                    <li class="hidden-sm hidden-xs"><a href="#" class="full-screen-handle" title="Full screen" aria-label="Toggle full screen"><i class="fa fa-expand" aria-hidden="true"></i></a></li>
                </ul>
                <ul class="nav navbar-nav navbar-right">
                    <li><a href="dean-notification-deliveries.php" title="Notification deliveries" aria-label="Notification deliveries"><i class="fa fa-bell-o" aria-hidden="true"></i><?php if($deanDeliveryCount > 0) { ?> <span class="badge badge-danger"><?php echo htmlentities($deanDeliveryCount); ?></span><?php } ?></a></li>
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle srms-profile-toggle" data-toggle="dropdown" aria-expanded="false" aria-haspopup="true">
                            <span class="srms-avatar" aria-hidden="true"><i class="fa fa-user"></i></span>
                            <span class="srms-profile-name"><?php echo htmlentities($_SESSION['alogin'] ?? 'Dean of Studies'); ?></span>
                            <i class="fa fa-angle-down" aria-hidden="true"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li class="dropdown-header">Dean of Studies</li>
                            <li><a href="change-password.php"><i class="fa fa-lock" aria-hidden="true"></i> Change Password</a></li>
                            <li role="separator" class="divider"></li>
                            <li><form method="post" action="logout.php" class="srms-logout-form"><?php csrf_field(); ?><button type="submit" class="srms-logout-button color-danger"><i class="fa fa-sign-out" aria-hidden="true"></i> Logout</button></form></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
<button type="button" class="srms-nav-backdrop" aria-label="Close sidebar" tabindex="-1"></button>
<script src="js/srms-ui.js" defer></script>
