<?php
require_once 'includes/config.php';
require_once 'includes/parent-auth.php';
require_once 'includes/portal-layout.php';
$parent=require_parent();
$student=filter_var($_GET['student']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:0;
$child=parent_child($dbh,$parent['id'],$student);
$page=portal_page($_GET);
$summary=cbe_rows($dbh,'SELECT Status,COUNT(*) Total FROM tblattendance WHERE StudentId=? GROUP BY Status',[$student]);
// Attendance remarks are staff notes with no publication flag; expose date/status only.
$rows=cbe_rows($dbh,'SELECT a.AttendanceDate,a.Status,c.ClassName,c.Section FROM tblattendance a JOIN tblclasses c ON c.id=a.ClassId WHERE a.StudentId=? ORDER BY a.AttendanceDate DESC,a.id DESC LIMIT 26 OFFSET '.(($page-1)*25),[$student]);
portal_start('Attendance');
portal_child_navigation($parent['id'],$student);
?><section class="parent-card"><h2><?= academic_h($child['StudentName']) ?></h2><p>Recorded attendance history. Dates without a record are not counted as absences.</p><div class="report-meta"><?php foreach($summary as $item){ ?><p><strong><?= academic_h(ucfirst($item['Status'])) ?>:</strong> <?= (int)$item['Total'] ?></p><?php } ?></div>
<div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Class</th><th>Status</th></tr></thead><tbody><?php foreach(array_slice($rows,0,25) as $row){ ?><tr><td><?= academic_h($row['AttendanceDate']) ?></td><td><?= academic_h($row['ClassName'].' '.$row['Section']) ?></td><td><?= academic_h(ucfirst($row['Status'])) ?></td></tr><?php } if(!$rows)echo '<tr><td colspan="3">No attendance has been recorded yet.</td></tr>'; ?></tbody></table></div></section>
<?php portal_pagination($page,count($rows)>25,['student'=>$student]);portal_end();
