<?php
require_once __DIR__.'/academic-assignments.php';
if(academic_ready($dbh)) {
    $sy=academic_year($dbh);
    $offeringRows=academic_query($dbh,'SELECT DISTINCT c.id,CONCAT(c.ClassName," ",c.Section) Label FROM tblsubjectcombination sc JOIN tblclasses c ON c.id=sc.ClassId WHERE sc.SubjectId=? AND sc.status=1 ORDER BY Label',[$result->id])->fetchAll(PDO::FETCH_ASSOC);
    foreach($offeringRows as $o) {
        echo '<details><summary>'.academic_h($o['Label']).'</summary>';
        $assigned=academic_query($dbh,'SELECT u.FullName,t.TermName FROM tblsubjectteacherassignments a JOIN tblusers u ON u.id=a.TeacherId LEFT JOIN tblterms t ON t.id=a.TermId WHERE a.ClassId=? AND a.SubjectId=? AND a.AcademicYearId=? AND a.Status=1 AND u.Status=1',[$o['id'],$result->id,$sy])->fetchAll(PDO::FETCH_ASSOC);
        foreach($assigned as $a) echo '<p>'.academic_h($a['FullName'].' — '.($a['TermName']?:'Whole year')).'</p>';
        if(!$assigned) echo '<p>No active teacher assigned.</p>';
        echo '<p>Students taking this subject:</p><ul>';
        foreach(academic_students($dbh,(int)$o['id'],(int)$result->id,$sy) as $s) echo '<li>'.academic_h($s['StudentName'].' ('.$s['RollId'].')').'</li>';
        echo '</ul><a href="dean-teacher-relationships.php?subject='.(int)$result->id.'&class='.(int)$o['id'].'&year='.$sy.'#assign">Assign / change / remove teacher</a></details>';
    }
    if(!$offeringRows) echo '<p>No active class offerings.</p>';
}
