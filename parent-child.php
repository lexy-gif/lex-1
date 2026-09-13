<?php
require_once 'includes/config.php';
require_once 'includes/parent-auth.php';
require_once 'includes/senior-school.php';
require_once 'includes/portal-layout.php';
$parent=require_parent();
$student=filter_var($_GET['student']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:0;
$child=parent_child($dbh,$parent['id'],$student);
$year=filter_var($_GET['year']??academic_year($dbh),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$years=cbe_rows($dbh,'SELECT id,AcademicYear FROM tblacademicyears ORDER BY AcademicYear DESC');
if(!$year||!in_array($year,array_map('intval',array_column($years,'id')),true)){http_response_code(404);exit('Academic year unavailable.');}
// Retain the former student portal's academic-year and placement history.
$learner=senior_learners($dbh,['year'=>$year,'student'=>$student])[0]??null;
$class=(int)($learner['ClassId']??0);
$classTeacher=academic_query($dbh,'SELECT u.FullName FROM tblclassteacherassignments a JOIN tblusers u ON u.id=a.TeacherId WHERE a.ClassId=? AND a.AcademicYearId=? AND a.Status=1',[$class,$year])->fetchColumn();
$subjects=cbe_rows($dbh,'SELECT s.SubjectName,s.SubjectCode,ss.AssignmentSource,s.Status,
    (SELECT GROUP_CONCAT(DISTINCT u.FullName ORDER BY u.FullName SEPARATOR ", ") FROM tblsubjectteacherassignments a JOIN tblusers u ON u.id=a.TeacherId WHERE a.ClassId=ss.ClassId AND a.SubjectId=ss.SubjectId AND a.AcademicYearId=ss.AcademicYearId AND a.Status=1 AND (a.TermId IS NULL OR ?<>? OR EXISTS(SELECT 1 FROM tblterms t WHERE t.id=a.TermId AND t.IsActive=1))) Teacher
    FROM tblstudentsubjects ss JOIN tblsubjects s ON s.id=ss.SubjectId WHERE ss.StudentId=? AND ss.ClassId=? AND ss.AcademicYearId=? AND ss.Status=1 ORDER BY s.SubjectName',[$year,academic_year($dbh),$student,$class,$year]);
portal_start('Learner academic profile');portal_child_navigation($parent['id'],$student);
?><form method="get" class="parent-selector"><input type="hidden" name="student" value="<?= $student ?>"><label for="year">Academic year</label><select id="year" name="year" class="form-control"><?php foreach($years as $y){ ?><option value="<?= (int)$y['id'] ?>" <?= (int)$y['id']===$year?'selected':'' ?>><?= academic_h($y['AcademicYear']) ?></option><?php } ?></select><button class="btn btn-default">View year</button></form>
<section class="parent-card"><h2><?= academic_h($child['StudentName']) ?></h2><div class="report-meta"><?php
foreach(['Admission number'=>$child['RollId'],'Grade'=>$learner['GradeNumber']??'Not enrolled','Class'=>$learner['Class']??'Not enrolled','Date of birth'=>$child['DOB']?:'Not recorded','Gender'=>$child['Gender']?:'Not recorded','Pathway'=>$learner['Pathway']??'Not allocated','Track'=>$learner['Track']??'Not allocated','Subject combination'=>$learner['Combination']??(!empty($learner['AllocationId'])?'Individual selection':'Not allocated'),'Class teacher'=>$classTeacher?:'Not assigned','Academic year'=>academic_query($dbh,'SELECT AcademicYear FROM tblacademicyears WHERE id=?',[$year])->fetchColumn(),'Guardian relationship'=>$child['Relationship']] as $label=>$value)echo '<p><strong>'.academic_h($label).':</strong> '.academic_h($value).'</p>';
?></div><?php if(!$learner)echo '<p>No Senior School enrollment is available for this academic year.</p>'; ?>
<h3>Registered subjects</h3><div class="table-responsive"><table class="table"><thead><tr><th>Subject</th><th>Code</th><th>Selection</th><th>Status</th><th>Teacher</th></tr></thead><tbody><?php foreach($subjects as $subject){ ?><tr><td><?= academic_h($subject['SubjectName']) ?></td><td><?= academic_h($subject['SubjectCode']) ?></td><td><?= academic_h(ucfirst(str_replace('_',' ',$subject['AssignmentSource']))) ?></td><td><?= $subject['Status']?'Active':'Inactive' ?></td><td><?= academic_h($subject['Teacher']??'Not assigned') ?></td></tr><?php }if(!$subjects)echo '<tr><td colspan="5">No subjects are registered for this academic year.</td></tr>'; ?></tbody></table></div></section>
<?php portal_end();
