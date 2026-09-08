<?php
require_once __DIR__.'/academic-assignments.php';
function academic_teacher_summary($db,$id,$year=null,$students=false,$currentTermOnly=false) {
    if(!academic_ready($db)) return;
    $year=$year?:academic_year($db);
    $classRows=academic_query($db,'SELECT a.*,CONCAT(c.ClassName," ",c.Section) Label FROM tblclassteacherassignments a JOIN tblclasses c ON c.id=a.ClassId WHERE a.TeacherId=? AND a.AcademicYearId=? AND a.Status=1',[$id,$year])->fetchAll(PDO::FETCH_ASSOC);
    $subjectRows=academic_query($db,'SELECT a.*,s.SubjectName,CONCAT(c.ClassName," ",c.Section) Label,t.TermName FROM tblsubjectteacherassignments a JOIN tblclasses c ON c.id=a.ClassId JOIN tblsubjects s ON s.id=a.SubjectId LEFT JOIN tblterms t ON t.id=a.TermId WHERE a.TeacherId=? AND a.AcademicYearId=? AND a.Status=1 ORDER BY s.SubjectName,c.ClassName,t.id',[$id,$year])->fetchAll(PDO::FETCH_ASSOC);
    if($currentTermOnly) {
        $terms=academic_query($db,'SELECT id FROM tblterms WHERE AcademicYearId=? AND IsActive=1',[$year])->fetchAll(PDO::FETCH_COLUMN);
        $subjectRows=array_filter($subjectRows,function($r)use($terms){return !$r['TermId'] || in_array($r['TermId'],$terms);});
    }
    if($classRows) echo '<span class="label label-primary">Class Teacher</span> ';
    if($subjectRows) echo '<span class="label label-info">Subject Teacher</span>';
    foreach($classRows as $r) {
        echo '<p>Class teacher of: '.academic_h($r['Label']).'</p>';
        if($students) academic_student_list($db,$r['ClassId'],0,$year);
    }
    if($subjectRows) echo '<p>Subjects taught: '.academic_h(implode(', ',array_unique(array_column($subjectRows,'SubjectName')))).'</p>';
    foreach($subjectRows as $r) {
        echo '<p>'.academic_h($r['SubjectName'].' → '.$r['Label'].' ('.($r['TermName']?:'Whole year').')').'</p>';
        if($students) academic_student_list($db,$r['ClassId'],$r['SubjectId'],$year);
    }
    foreach(academic_query($db,'SELECT rt.Name,r.StartDate,r.EndDate,r.Notes FROM tblteacherresponsibilities r JOIN tblresponsibilitytypes rt ON rt.id=r.ResponsibilityTypeId WHERE r.TeacherId=? AND r.AcademicYearId=? AND r.Status=1',[$id,$year]) as $r) echo '<p><span class="label label-default">'.academic_h($r['Name']).'</span> '.academic_h($r['StartDate'].' – '.($r['EndDate']?:'ongoing')).'</p>';
    if(!$classRows && !$subjectRows) echo '<p class="text-muted">No academic assignments in this year.</p>';
}
function academic_student_list($db,$class,$subject,$year) {
    echo '<details><summary>'.($subject?'Students registered for this subject':'Active class students').'</summary><ul>';
    $rows=academic_students($db,$class,$subject,$year);
    foreach($rows as $s) echo '<li>'.academic_h($s['StudentName'].' ('.$s['RollId'].')').'</li>';
    if(!$rows) echo '<li>No active students registered.</li>';
    echo '</ul></details>';
}
