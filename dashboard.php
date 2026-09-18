<?php
require_once 'includes/config.php';require_once 'includes/dean-auth.php';require_dean();
require_once 'includes/portal-layout.php';require_once 'includes/cbe-ui.php';require_once 'includes/cbe-reports.php';
$year=academic_year($dbh);$term=(int)academic_query($dbh,'SELECT id FROM tblterms WHERE AcademicYearId=? AND IsActive=1 LIMIT 1',[$year])->fetchColumn();
portal_start('Academic overview','dean');
echo '<p>Coordinate teaching, review academic progress and follow up outstanding work.</p><p><a class="btn btn-primary" href="dean-academics.php">Open Academic Workspace</a></p>';
if(staff_can('teaching.view')) include 'includes/academic-overview.php';
if(staff_can('results.review')) {
 $pending=(int)$dbh->query("SELECT COUNT(*) FROM tblresultreviews r WHERE r.Status='approved' AND NOT EXISTS(SELECT 1 FROM tblresultpublications p WHERE p.ClassId=r.ClassId AND p.ExamId=r.ExamId)")->fetchColumn();
 echo '<div class="panel panel-body"><a href="dean-result-approvals.php">Results awaiting approval or publication: '.$pending.'</a></div>';
}
if(staff_can('support.manage')) {
 echo '<h3>Overdue academic follow-ups</h3>';cbe_table(cbe_rows($dbh,"SELECT i.id,s.StudentName Learner,u.FullName Owner,i.ReviewDate,i.ActionPlan,i.Status FROM tblacademicinterventions i JOIN tblstudents s ON s.StudentId=i.StudentId JOIN tblusers u ON u.id=i.TeacherId WHERE i.Status IN ('active','review') AND i.ReviewDate<CURRENT_DATE ORDER BY i.ReviewDate"));
}
if(staff_can('assessments.manage')) {
 echo '<h3>Overdue examination submissions</h3>';cbe_table(cbe_rows($dbh,"SELECT e.ExamName,c.ClassName,s.SubjectName,u.FullName Teacher,e.MarksDeadline FROM tblexams e JOIN tblsubjectteacherassignments a ON a.AcademicYearId=e.AcademicYearId AND (a.TermId IS NULL OR a.TermId=e.TermId) AND a.Status=1 AND (e.ClassId IS NULL OR e.ClassId=a.ClassId) JOIN tblclasses c ON c.id=a.ClassId JOIN tblsubjects s ON s.id=a.SubjectId JOIN tblusers u ON u.id=a.TeacherId WHERE e.MarksDeadline<CURRENT_DATE AND e.Status<>'archived' AND NOT EXISTS(SELECT 1 FROM tblresultsubmissions r WHERE r.ClassId=a.ClassId AND r.SubjectId=a.SubjectId AND r.ExamId=e.id AND r.Status='submitted') ORDER BY e.MarksDeadline"));
}
if(staff_can('timetable.manage')) {
 echo '<h3>Timetable clashes requiring review</h3>';cbe_table(cbe_rows($dbh,"SELECT a.id FirstEntry,b.id SecondEntry,a.DayOfWeek,a.StartTime,a.EndTime FROM tblclasstimetableentries a JOIN tblclasstimetableentries b ON a.id<b.id AND a.AcademicYearId=b.AcademicYearId AND a.TermId=b.TermId AND a.DayOfWeek=b.DayOfWeek AND a.StartTime<b.EndTime AND b.StartTime<a.EndTime AND (a.ClassId=b.ClassId OR a.TeacherId=b.TeacherId OR (a.RoomId IS NOT NULL AND a.RoomId=b.RoomId)) WHERE a.AcademicYearId=? AND a.Status NOT IN ('cancelled','archived') AND b.Status NOT IN ('cancelled','archived')",[$year]));
}
portal_end('dean');
