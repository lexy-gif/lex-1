<?php
session_start();
require 'includes/config.php'; require 'includes/csrf.php'; require 'includes/dean-auth.php'; require 'includes/audit.php';
require_once 'includes/academic-assignments.php'; require_dean();
$ready=academic_ready($dbh); $error=''; $msg=''; $conflict=null;
$year=(int)($_POST['year']??$_GET['year']??academic_year($dbh));
$teacher=(int)($_POST['teacher']??$_GET['teacher']??$_GET['id']??0);
$class=(int)($_POST['class']??$_GET['class']??0); $subject=(int)($_POST['subject']??$_GET['subject']??0);
$role=$_GET['role']??''; $search=trim($_GET['search']??'');
$editing=null;
$editKind=$_POST['edit_kind']??$_GET['edit_kind']??'';
$editId=(int)($_POST['edit_id']??$_GET['edit_id']??0);
if($ready && $editId && in_array($editKind,['subject','class'],true)) {
    $editTable=$editKind==='subject'?'tblsubjectteacherassignments':'tblclassteacherassignments';
    $editing=academic_query($dbh,"SELECT * FROM $editTable WHERE id=? AND Status=1",[$editId])->fetch(PDO::FETCH_ASSOC);
    if($editing && $_SERVER['REQUEST_METHOD']!=='POST') {$year=(int)$editing['AcademicYearId'];$teacher=(int)$editing['TeacherId'];$class=(int)$editing['ClassId'];$subject=(int)($editing['SubjectId']??0);}
}
if ($ready && $_SERVER['REQUEST_METHOD']==='POST') {
    csrf_require_valid($_POST['csrf_token']??'');
    try {
        $dbh->beginTransaction(); $action=$_POST['action']??'';
        if ($action==='subject' || $action==='class') {
            if($editId) {
                if(!$editing || $editKind!==$action || (int)$editing['AcademicYearId']!==$year) throw new DomainException('The assignment has changed. Reload before editing.');
                academic_class_lock($dbh,(int)$editing['ClassId']);
                $ended=academic_query($dbh,"UPDATE $editTable SET Status=0,EndedAt=CURRENT_TIMESTAMP WHERE id=? AND Status=1",[$editId]);
                if(!$ended->rowCount()) throw new DomainException('The assignment has already ended. Reload before editing.');
            }
            academic_assign($dbh,$action,$teacher,$class,$subject,$year,(int)($_POST['term']??0)?:null,$_POST['confirmed_assignments']??[]);
        }
        elseif ($action==='responsibility') academic_responsibility($dbh,$teacher,(int)($_POST['type']??0),$year,$_POST['start']??'',$_POST['end']??null,trim($_POST['notes']??''));
        elseif ($action==='type') {
            $name=trim($_POST['name']??''); if (!$name || strlen($name)>150) throw new DomainException('Enter a responsibility name of up to 150 characters.');
            academic_query($dbh,'INSERT INTO tblresponsibilitytypes(Name,Description) VALUES(?,?)',[$name,trim($_POST['description']??'')]);
        } elseif ($action==='type_status') academic_query($dbh,'UPDATE tblresponsibilitytypes SET Active=? WHERE id=?',[(int)!empty($_POST['active']),(int)($_POST['type']??0)]);
        elseif ($action==='end') {
            $kind=$_POST['kind']??''; $table=['subject'=>'tblsubjectteacherassignments','class'=>'tblclassteacherassignments','responsibility'=>'tblteacherresponsibilities'][$kind]??null;
            if (!$table) throw new DomainException('Invalid assignment type.');
            $date=$kind==='responsibility'?'EndDate=CURRENT_DATE':'EndedAt=CURRENT_TIMESTAMP';
            academic_query($dbh,"UPDATE $table SET Status=0,$date WHERE id=? AND AcademicYearId=?",[(int)($_POST['assignment']??0),$year]);
        } else throw new DomainException('Invalid action.');
        audit_log($dbh,'academic_assignment_'.$action,'tblusers',$teacher,'Academic year '.$year);
        $dbh->commit(); header('Location: dean-teacher-relationships.php?'.http_build_query(['year'=>$year,'teacher'=>$teacher,'saved'=>1])); exit;
    } catch (AcademicConflict $e) { if($dbh->inTransaction()) $dbh->rollBack(); $conflict=$e; }
    catch (Throwable $e) { if($dbh->inTransaction()) $dbh->rollBack(); $error=$e instanceof DomainException?$e->getMessage():'Could not save. Check for duplicate assignments or responsibilities.'; error_log($e->getMessage()); }
}
$years=$dbh->query('SELECT * FROM tblacademicyears ORDER BY AcademicYear DESC')->fetchAll(PDO::FETCH_ASSOC);
$teachers=$dbh->query('SELECT id,FullName Label,Status FROM tblusers WHERE Role IN ('.ACADEMIC_TEACHER_ROLES.') ORDER BY FullName')->fetchAll(PDO::FETCH_ASSOC);
$classes=$dbh->query('SELECT id,CONCAT(ClassName," ",Section) Label FROM tblclasses ORDER BY ClassNameNumeric,Section')->fetchAll(PDO::FETCH_ASSOC);
$subjects=$dbh->query('SELECT id,SubjectName Label FROM tblsubjects ORDER BY SubjectName')->fetchAll(PDO::FETCH_ASSOC);
function ar_options($rows,$selected=0) { foreach($rows as $r) echo '<option value="'.(int)$r['id'].'" '.((int)$r['id']===$selected?'selected':'').'>'.academic_h($r['Label']).'</option>'; }
function ar_hidden($name,$value) { if(is_array($value)) { foreach($value as $k=>$v) ar_hidden($name.'['.$k.']',$v); } else echo '<input type="hidden" name="'.academic_h($name).'" value="'.academic_h($value).'">'; }
?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Teacher Relationships | SRMS</title>
<link rel="stylesheet" href="css/bootstrap.min.css"><link rel="stylesheet" href="css/font-awesome.min.css"><link rel="stylesheet" href="css/main.css"><link rel="stylesheet" href="css/custom.css"></head>
<body class="top-navbar-fixed"><div class="main-wrapper"><?php include 'includes/topbar.php'; ?><div class="content-wrapper"><div class="content-container"><?php include 'includes/leftbar.php'; ?><div class="main-page"><div class="container-fluid"><h2>Teacher Relationships</h2><section class="section">
<?php if(!$ready) { ?><div class="alert alert-warning">The teacher relationship migration must be applied before using this module.</div><?php } else { ?>
<?php if($error) { ?><div class="alert alert-danger"><?= academic_h($error) ?></div><?php } if(isset($_GET['saved'])) { ?><div class="alert alert-success">Changes saved. Assignment history is preserved.</div><?php } ?>
<p><a class="btn btn-default" href="manage-teachers.php#create-teacher">Add Teacher</a> <a class="btn btn-default" href="manage-subjects.php">Manage Subjects</a> <a class="btn btn-default" href="student-subjects.php">Student Subject Combinations</a></p>
<form method="get" class="panel panel-body"><div class="row">
<div class="col-sm-2"><label>Academic year</label><select name="year" class="form-control"><?php foreach($years as $y) { ?><option value="<?= (int)$y['id'] ?>" <?= (int)$y['id']===$year?'selected':'' ?>><?= academic_h($y['AcademicYear']) ?></option><?php } ?></select></div>
<div class="col-sm-3"><label>Teacher</label><select name="teacher" class="form-control academic-search"><option value="0">All teachers</option><?php ar_options($teachers,$teacher); ?></select></div>
<div class="col-sm-2"><label>Class</label><select name="class" class="form-control academic-search"><option value="0">All classes</option><?php ar_options($classes,$class); ?></select></div>
<div class="col-sm-2"><label>Subject</label><select name="subject" class="form-control academic-search"><option value="0">All subjects</option><?php ar_options($subjects,$subject); ?></select></div>
<div class="col-sm-3"><label>Academic role</label><select name="role" class="form-control"><option value="">All roles</option><option value="subject" <?= $role==='subject'?'selected':'' ?>>Subject Teacher</option><option value="class" <?= $role==='class'?'selected':'' ?>>Class Teacher</option><option value="responsibility" <?= $role==='responsibility'?'selected':'' ?>>Other Responsibilities</option></select></div></div>
<label>Search teacher or responsibility</label><input name="search" class="form-control" value="<?= academic_h($search) ?>"><button class="btn btn-primary">Apply Filters</button></form>
<?php $arYear=$year; include 'includes/academic-overview.php'; ?>
<div class="panel panel-body"><h3>Current assignments and history</h3>
<?php foreach(['subject'=>'Subject Teacher','class'=>'Class Teacher','responsibility'=>'Other Responsibilities'] as $kind=>$label) {
if ($role && $role!==$kind) continue;
$params=[$year]; $where='a.AcademicYearId=?';
if($teacher) {$where.=' AND a.TeacherId=?';$params[]=$teacher;}
if($search!=='') {$where.=' AND (u.FullName LIKE ?'.($kind==='responsibility'?' OR rt.Name LIKE ?':'').')';$params[]='%'.$search.'%';if($kind==='responsibility')$params[]='%'.$search.'%';}
if($class && $kind!=='responsibility') {$where.=' AND a.ClassId=?';$params[]=$class;}
if($subject && $kind==='subject') {$where.=' AND a.SubjectId=?';$params[]=$subject;}
if($kind==='responsibility') $sql="SELECT a.*,u.FullName,rt.Name Label,NULL ClassLabel,NULL TermName FROM tblteacherresponsibilities a JOIN tblusers u ON u.id=a.TeacherId JOIN tblresponsibilitytypes rt ON rt.id=a.ResponsibilityTypeId WHERE $where";
else { $table=$kind==='subject'?'tblsubjectteacherassignments':'tblclassteacherassignments'; $extra=$kind==='subject'?'s.SubjectName Label,t.TermName':'NULL Label,NULL TermName';$join=$kind==='subject'?'JOIN tblsubjects s ON s.id=a.SubjectId LEFT JOIN tblterms t ON t.id=a.TermId':'';
$sql="SELECT a.*,u.FullName,CONCAT(c.ClassName,' ',c.Section) ClassLabel,$extra FROM $table a JOIN tblusers u ON u.id=a.TeacherId JOIN tblclasses c ON c.id=a.ClassId $join WHERE $where"; }
$rows=academic_query($dbh,$sql.' ORDER BY a.Status DESC,u.FullName,a.id DESC',$params)->fetchAll(PDO::FETCH_ASSOC);
?><h4><?= academic_h($label) ?></h4><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Teacher</th><th>Assignment</th><th>Period / dates</th><th>Status</th><th>Students / actions</th></tr></thead><tbody>
<?php foreach($rows as $r) { ?><tr><td><a href="view-teacher.php?id=<?= (int)$r['TeacherId'] ?>"><?= academic_h($r['FullName']) ?></a></td><td><span class="label label-info"><?= academic_h($label) ?></span><br><?= academic_h(($r['Label']??'').' '.($r['ClassLabel']??'')) ?><?php if($kind==='responsibility') echo '<p>'.academic_h($r['Notes']).'</p>'; ?></td><td><?= academic_h($r['TermName']??'Whole year') ?><br><?= academic_h($r['StartDate']??$r['AssignedAt']??$r['CreationDate']??'') ?> <?= academic_h($r['EndDate']??$r['EndedAt']??'') ?></td><td><?= $r['Status']?'Active':'Ended' ?></td><td>
<?php if($kind!=='responsibility' && $r['Status']) { ?><details><summary>View current students</summary><p class="help-block"><?= $kind==='subject'?'Active students registered for this subject and year.':'All active students currently in this class.' ?></p><ul><?php foreach(academic_students($dbh,(int)$r['ClassId'],$kind==='subject'?(int)$r['SubjectId']:0,$year) as $s) { ?><li><?= academic_h($s['StudentName'].' ('.$s['RollId'].')') ?></li><?php } ?></ul></details><?php } ?>
<?php if($r['Status']) { if($kind!=='responsibility') { ?><a class="btn btn-xs btn-primary" href="?edit_kind=<?= $kind ?>&edit_id=<?= (int)$r['id'] ?>#assign">Change assignment</a><?php } ?><form method="post"><?php csrf_field(); ?><input type="hidden" name="year" value="<?= $year ?>"><input type="hidden" name="teacher" value="<?= $teacher ?>"><input type="hidden" name="action" value="end"><input type="hidden" name="kind" value="<?= $kind ?>"><input type="hidden" name="assignment" value="<?= (int)$r['id'] ?>"><button class="btn btn-xs btn-warning" onclick="return confirm('End this assignment and keep its history?')">End assignment</button></form><?php } ?></td></tr><?php } ?></tbody></table></div><?php } ?></div>
<div class="panel panel-body" id="assign"><h3><?= $editing?'Change assignment':'Assign or reassign a teacher' ?></h3><p>Changes preserve assignment history. Replacing another teacher requires confirmation.</p>
<form method="post"><?php csrf_field(); ?><input type="hidden" name="year" value="<?= $year ?>">
<?php if($editing) { ?><input type="hidden" name="edit_id" value="<?= $editId ?>"><input type="hidden" name="edit_kind" value="<?= academic_h($editKind) ?>"><p>Editing assignment #<?= $editId ?>. Saving ends the old record and creates its replacement together.</p><?php } ?>
<div class="row"><div class="col-sm-4 form-group"><label>Teacher</label><select name="teacher" class="form-control academic-search" required><option value="">Select active teacher</option><?php ar_options(array_filter($teachers,function($t){return $t['Status']==1;}),$teacher); ?></select></div>
<div class="col-sm-4 form-group"><label>Class</label><select name="class" class="form-control academic-search" required><option value="">Select class</option><?php ar_options($classes,$class); ?></select></div>
<div class="col-sm-4 form-group"><label>Subject (for subject teachers)</label><select name="subject" class="form-control academic-search"><option value="">Select subject</option><?php ar_options($subjects,$subject); ?></select></div></div>
<div class="form-group"><label>Term (subject assignments)</label><select name="term" class="form-control"><option value="">Whole year</option><?php foreach(academic_query($dbh,'SELECT id,TermName FROM tblterms WHERE AcademicYearId=?',[$year]) as $t) { ?><option value="<?= (int)$t['id'] ?>" <?= (int)($editing['TermId']??0)===(int)$t['id']?'selected':'' ?>><?= academic_h($t['TermName']) ?></option><?php } ?></select></div>
<button class="btn btn-primary" name="action" value="subject">Assign Subject Teacher</button> <button class="btn btn-primary" name="action" value="class">Assign Class Teacher</button></form></div>
<div class="panel panel-body" id="responsibilities"><h3>Manage Responsibilities</h3><form method="post"><?php csrf_field(); ?><input type="hidden" name="year" value="<?= $year ?>"><input type="hidden" name="action" value="responsibility">
<label>Teacher</label><select name="teacher" class="form-control academic-search" required><?php ar_options(array_filter($teachers,function($t){return $t['Status']==1;}),$teacher); ?></select>
<label>Responsibility</label><select name="type" class="form-control academic-search" required><?php ar_options($dbh->query('SELECT id,Name Label FROM tblresponsibilitytypes WHERE Active=1 ORDER BY Name')->fetchAll(PDO::FETCH_ASSOC)); ?></select>
<label>Start date</label><input name="start" type="date" class="form-control" required value="<?= date('Y-m-d') ?>"><label>End date (optional)</label><input name="end" type="date" class="form-control"><label>Notes</label><textarea name="notes" class="form-control"></textarea><button class="btn btn-primary">Add Responsibility</button></form>
<hr><h4>Create responsibility type</h4><form method="post"><?php csrf_field(); ?><input type="hidden" name="year" value="<?= $year ?>"><input type="hidden" name="action" value="type"><label>Name</label><input name="name" class="form-control" maxlength="150" required><label>Description</label><textarea name="description" class="form-control"></textarea><button class="btn btn-default">Create Type</button></form>
<details><summary>Activate / deactivate responsibility types</summary><?php foreach($dbh->query('SELECT * FROM tblresponsibilitytypes ORDER BY Name') as $r) { ?><form method="post"><?php csrf_field(); ?><input type="hidden" name="year" value="<?= $year ?>"><input type="hidden" name="action" value="type_status"><input type="hidden" name="type" value="<?= (int)$r['id'] ?>"><input type="hidden" name="active" value="<?= $r['Active']?0:1 ?>"><?= academic_h($r['Name']) ?> <button class="btn btn-xs btn-default"><?= $r['Active']?'Deactivate':'Activate' ?></button></form><?php } ?></details></div>
<?php if($conflict) { ?><div class="modal fade" id="academic-confirmation" tabindex="-1" role="dialog" aria-labelledby="confirm-title"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h4 id="confirm-title">Confirm reassignment</h4></div><div class="modal-body"><p><?= academic_h($conflict->getMessage()) ?></p></div><div class="modal-footer"><form method="post"><?php foreach($_POST as $k=>$v) ar_hidden($k,$v); ?><input type="hidden" name="confirmed_assignments[]" value="<?= academic_h($conflict->assignmentKey) ?>"><button class="btn btn-warning">Replace assignment</button><a class="btn btn-default" href="dean-teacher-relationships.php?year=<?= $year ?>&teacher=<?= $teacher ?>">Cancel</a></form></div></div></div></div><?php } ?>
<?php } ?></section></div></div></div></div></div><script src="js/jquery/jquery-2.2.4.min.js"></script><script src="js/bootstrap/bootstrap.min.js"></script><script src="js/main.js"></script><script src="js/academic-assignments.js"></script></body></html>
