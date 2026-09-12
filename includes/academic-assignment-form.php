<?php
require_once __DIR__.'/academic-assignments.php';
if (academic_ready($dbh)) {
    $ayRows=$dbh->query('SELECT * FROM tblacademicyears ORDER BY AcademicYear DESC')->fetchAll(PDO::FETCH_ASSOC);
    $acRows=$dbh->query('SELECT id,CONCAT(ClassName," ",Section) Label FROM tblclasses WHERE ClassNameNumeric IN (10,11,12) ORDER BY ClassNameNumeric,Section')->fetchAll(PDO::FETCH_ASSOC);
    $asRows=$dbh->query('SELECT id,SubjectName Label FROM tblsubjects ORDER BY SubjectName')->fetchAll(PDO::FETCH_ASSOC);
?>
<fieldset class="academic-form"><legend>Academic roles and responsibilities</legend>
<div class="row"><div class="col-sm-6 form-group"><label>Academic year</label><select class="form-control" name="academic_year"><?php foreach($ayRows as $a) { ?><option value="<?= (int)$a['id'] ?>" <?= $a['IsActive']?'selected':'' ?>><?= academic_h($a['AcademicYear']) ?></option><?php } ?></select></div>
<div class="col-sm-6 form-group"><label>Term</label><select class="form-control" name="academic_term"><option value="">Whole year</option><?php foreach($dbh->query('SELECT t.*,y.AcademicYear FROM tblterms t JOIN tblacademicyears y ON y.id=t.AcademicYearId ORDER BY y.AcademicYear DESC,t.id') as $t) { ?><option value="<?= (int)$t['id'] ?>"><?= academic_h($t['AcademicYear'].' / '.$t['TermName']) ?></option><?php } ?></select></div></div>
<label><input type="checkbox" name="subject_role" value="1"> Subject Teacher</label>
<p class="help-block">Only subjects offered by the selected class can be assigned. <a href="add-subjectcombination.php">Configure class subject offerings</a>.</p>
<div id="academic-assignment-rows"><div class="row academic-assignment-row">
<div class="col-sm-5 form-group"><label>Class</label><select name="assignment_class[]" class="form-control academic-search"><option value="">Select class</option><?php foreach($acRows as $a) { ?><option value="<?= (int)$a['id'] ?>"><?= academic_h($a['Label']) ?></option><?php } ?></select></div>
<div class="col-sm-5 form-group"><label>Subject</label><select name="assignment_subject[]" class="form-control academic-search"><option value="">Select subject</option><?php foreach($asRows as $a) { ?><option value="<?= (int)$a['id'] ?>"><?= academic_h($a['Label']) ?></option><?php } ?></select></div>
<div class="col-sm-2"><button type="button" class="btn btn-default academic-remove">Remove</button></div></div></div>
<button type="button" class="btn btn-default" id="academic-add">Add Another Assignment</button><hr>
<label><input type="checkbox" name="class_role" value="1"> Class Teacher</label>
<div class="form-group"><label>Class teacher of</label><select name="academic_class" class="form-control academic-search"><option value="">Select class</option><?php foreach($acRows as $a) { ?><option value="<?= (int)$a['id'] ?>"><?= academic_h($a['Label']) ?></option><?php } ?></select></div>
<div class="form-group"><label>Additional responsibilities (select several)</label><select name="responsibility_types[]" multiple class="form-control academic-search"><?php foreach($dbh->query('SELECT * FROM tblresponsibilitytypes WHERE Active=1 ORDER BY Name') as $r) { ?><option value="<?= (int)$r['id'] ?>"><?= academic_h($r['Name']) ?></option><?php } ?></select></div>
<p class="help-block">Assignments can be changed later without creating another teacher account.</p>
<?php
$incumbents=[];
foreach($dbh->query('SELECT a.*,u.FullName,CONCAT(c.ClassName," ",c.Section) ClassLabel,s.SubjectName FROM tblsubjectteacherassignments a JOIN tblusers u ON u.id=a.TeacherId JOIN tblclasses c ON c.id=a.ClassId JOIN tblsubjects s ON s.id=a.SubjectId WHERE a.Status=1') as $a) $incumbents[]=['kind'=>'subject','id'=>(int)$a['id'],'class'=>(int)$a['ClassId'],'subject'=>(int)$a['SubjectId'],'year'=>(int)$a['AcademicYearId'],'term'=>(int)$a['TermId'],'message'=>$a['SubjectName'].' for '.$a['ClassLabel'].' is currently assigned to '.$a['FullName'].'. Replace this assignment?'];
foreach($dbh->query('SELECT a.*,u.FullName,CONCAT(c.ClassName," ",c.Section) ClassLabel FROM tblclassteacherassignments a JOIN tblusers u ON u.id=a.TeacherId JOIN tblclasses c ON c.id=a.ClassId WHERE a.Status=1') as $a) $incumbents[]=['kind'=>'class','id'=>(int)$a['id'],'class'=>(int)$a['ClassId'],'year'=>(int)$a['AcademicYearId'],'message'=>'Class teacher for '.$a['ClassLabel'].' is currently assigned to '.$a['FullName'].'. Replace this assignment?'];
?>
<script type="application/json" id="academic-incumbents"><?= json_encode($incumbents,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
<script type="application/json" id="academic-offerings"><?= json_encode($dbh->query('SELECT ClassId,SubjectId FROM tblsubjectcombination WHERE status=1')->fetchAll(PDO::FETCH_ASSOC),JSON_HEX_TAG) ?></script>
</fieldset>
<script src="js/academic-assignments.js" defer></script>
<?php } ?>
