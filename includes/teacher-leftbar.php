<div class="left-sidebar bg-black-300 box-shadow">
    <div class="sidebar-content">
        <div class="user-info closed">
            <img src="http://placehold.it/90/c2c2c2?text=CT" alt="Class Teacher" class="img-circle profile-img">
            <h6 class="title"><?php echo htmlentities(teacher_name()); ?></h6>
            <small class="info"><?php echo htmlentities(function_exists('teacher_role_label') ? teacher_role_label() : 'Teacher'); ?></small>
        </div>
        <div class="sidebar-nav">
            <ul class="side-nav color-gray">
                <li class="nav-header"><span>Main Category</span></li>
                <li><a href="teacher-academic-assignments.php"><i class="fa fa-book"></i> <span>My Teaching Assignments</span></a></li>
                <li><a href="teacher-academics.php"><i class="fa fa-graduation-cap"></i> <span>My Academic Workspace</span></a></li>
                <?php if(teacher_class_id()) { ?>
                <li><a href="teacher-dashboard.php"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a></li>
                <li><a href="teacher-students.php"><i class="fa fa-users"></i> <span>Students</span></a></li>
                <li><a href="teacher-accounts.php"><i class="fa fa-user-plus"></i> <span>Manage Accounts</span></a></li>
                <li><a href="teacher-results.php"><i class="fa fa-check-square-o"></i> <span>Results Review</span></a></li>
                <li><a href="teacher-attendance.php"><i class="fa fa-calendar-check-o"></i> <span>Attendance</span></a></li>
                <li><a href="teacher-timetable.php"><i class="fa fa-table"></i> <span>Timetable</span></a></li>
                <li><a href="teacher-report-cards.php"><i class="fa fa-file-text"></i> <span>Report Cards</span></a></li>
                <li><a href="teacher-performance.php"><i class="fa fa-line-chart"></i> <span>Performance</span></a></li>
                <li><a href="teacher-reports.php"><i class="fa fa-bar-chart"></i> <span>Reports</span></a></li>
                <?php } ?>
                <li><a href="teacher-notifications.php"><i class="fa fa-bell"></i> <span>Notifications</span></a></li>
                <li><a href="teacher-profile.php"><i class="fa fa-user"></i> <span>Profile</span></a></li>
            </ul>
        </div>
    </div>
</div>
