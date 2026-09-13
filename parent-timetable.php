<?php
require_once 'includes/config.php';
require_once 'includes/parent-auth.php';
require_once 'includes/portal-layout.php';
$parent=require_parent();
$student=filter_var($_GET['student']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:0;
$child=parent_child($dbh,$parent['id'],$student);
$year=filter_var($_GET['year']??academic_year($dbh),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$years=cbe_rows($dbh,'SELECT id,AcademicYear FROM tblacademicyears ORDER BY AcademicYear DESC');
$terms=$year?cbe_rows($dbh,'SELECT id,TermName,IsActive FROM tblterms WHERE AcademicYearId=? ORDER BY IsActive DESC,id',[$year]):[];
$term=filter_var($_GET['term']??($terms[0]['id']??0),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$year||!$term||!in_array($term,array_map('intval',array_column($terms,'id')),true)){http_response_code(404);exit('Academic period unavailable.');}
$enrollment=cbe_one($dbh,'SELECT ClassId FROM tblstudentenrollments WHERE StudentId=? AND AcademicYearId=?',[$student,$year]);
$class=(int)($enrollment['ClassId']??($year===academic_year($dbh)?$child['ClassId']:0));
// Each timetable row must be published and belong to this learner's registrations/pathway.
$scope=" AND x.ClassId=? AND x.Status='published' AND EXISTS(SELECT 1 FROM tblstudentsubjects ss WHERE ss.StudentId=? AND ss.ClassId=x.ClassId AND ss.SubjectId=x.SubjectId AND ss.AcademicYearId=? AND ss.Status=1)
    AND (x.PathwayId IS NULL OR EXISTS(SELECT 1 FROM tblstudentpathways sp WHERE sp.StudentId=? AND sp.ClassId=x.ClassId AND sp.AcademicYearId=? AND sp.PathwayId=x.PathwayId AND sp.Status=1))";
$params=[$year,$term,$class,$student,$year,$student,$year];
$lessons=cbe_rows($dbh,"SELECT x.DayOfWeek,x.StartTime,x.EndTime,s.SubjectName,u.FullName Teacher,r.RoomName FROM tblclasstimetableentries x JOIN tblsubjects s ON s.id=x.SubjectId LEFT JOIN tblusers u ON u.id=x.TeacherId LEFT JOIN tblrooms r ON r.id=x.RoomId WHERE x.AcademicYearId=? AND x.TermId=?".$scope.' ORDER BY x.DayOfWeek,x.StartTime,s.SubjectName',$params);
$exams=cbe_rows($dbh,"SELECT e.ExamName,x.ExamDate,x.StartTime,x.EndTime,s.SubjectName,r.RoomName FROM tblexamtimetableentries x JOIN tblexams e ON e.id=x.ExamId JOIN tblsubjects s ON s.id=x.SubjectId LEFT JOIN tblrooms r ON r.id=x.RoomId WHERE e.AcademicYearId=? AND e.TermId=?".$scope.' ORDER BY x.ExamDate,x.StartTime,s.SubjectName',$params);
portal_start('Published timetables');portal_child_navigation($parent['id'],$student);
?><p><?= academic_h($child['StudentName']) ?></p><form method="get" class="parent-selector"><input type="hidden" name="student" value="<?= $student ?>"><label for="year">Academic year</label><select id="year" name="year" class="form-control"><?php foreach($years as $y){ ?><option value="<?= (int)$y['id'] ?>" <?= (int)$y['id']===$year?'selected':'' ?>><?= academic_h($y['AcademicYear']) ?></option><?php } ?></select><button class="btn btn-default">Select year</button></form>
<form method="get" class="parent-selector"><input type="hidden" name="student" value="<?= $student ?>"><input type="hidden" name="year" value="<?= $year ?>"><label for="term">Term</label><select id="term" name="term" class="form-control"><?php foreach($terms as $t){ ?><option value="<?= (int)$t['id'] ?>" <?= (int)$t['id']===$term?'selected':'' ?>><?= academic_h($t['TermName']) ?></option><?php } ?></select><button class="btn btn-default">View term</button></form>
<div class="report-actions"><button class="btn btn-primary" onclick="window.print()">Print / Save PDF</button></div>
<section class="parent-card"><h2>Class timetable</h2><div class="table-responsive"><table class="table"><thead><tr><th>Day</th><th>Time</th><th>Subject</th><th>Teacher</th><th>Room</th></tr></thead><tbody><?php foreach($lessons as $row){ ?><tr><td><?= academic_h($row['DayOfWeek']) ?></td><td><?= academic_h(substr($row['StartTime'],0,5).' – '.substr($row['EndTime'],0,5)) ?></td><td><?= academic_h($row['SubjectName']) ?></td><td><?= academic_h($row['Teacher']??'Not assigned') ?></td><td><?= academic_h($row['RoomName']??'Not assigned') ?></td></tr><?php }if(!$lessons)echo '<tr><td colspan="5">No class timetable has been published for this student and term.</td></tr>'; ?></tbody></table></div></section>
<section class="parent-card"><h2>Exam timetable</h2><div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Time</th><th>Exam</th><th>Subject</th><th>Room</th></tr></thead><tbody><?php foreach($exams as $row){ ?><tr><td><?= academic_h($row['ExamDate']) ?></td><td><?= academic_h(substr($row['StartTime'],0,5).' – '.substr($row['EndTime'],0,5)) ?></td><td><?= academic_h($row['ExamName']) ?></td><td><?= academic_h($row['SubjectName']) ?></td><td><?= academic_h($row['RoomName']??'Not assigned') ?></td></tr><?php }if(!$exams)echo '<tr><td colspan="5">No exam timetable has been published for this student and term.</td></tr>'; ?></tbody></table></div></section>
<?php portal_end();
