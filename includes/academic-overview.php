<?php
require_once __DIR__.'/academic-assignments.php';
if (academic_ready($dbh)) {
    $arYear=$arYear??academic_year($dbh);
    $arPeriod='(a.TermId IS NULL OR EXISTS (SELECT 1 FROM tblterms t WHERE t.id=a.TermId AND t.IsActive=1))';
    if(isset($arTerm))$arPeriod=$arTerm?'(a.TermId IS NULL OR a.TermId='.(int)$arTerm.')':'1=1';
    $arSubject="EXISTS (SELECT 1 FROM tblsubjectteacherassignments a WHERE a.TeacherId=u.id AND a.Status=1 AND a.AcademicYearId=? AND $arPeriod)";
    $arClass='EXISTS (SELECT 1 FROM tblclassteacherassignments a WHERE a.TeacherId=u.id AND a.Status=1 AND a.AcademicYearId=?)';
    $arBase='SELECT COUNT(*) FROM tblusers u WHERE u.Status=1 AND u.Role IN ('.ACADEMIC_TEACHER_ROLES.')';
    $arStats=[
        'Active teachers'=>[$arBase,[]],
        'Subject teachers'=>[$arBase.' AND '.$arSubject,[$arYear]],
        'Class teachers'=>[$arBase.' AND '.$arClass,[$arYear]],
        'Teachers with multiple subjects'=>[$arBase." AND (SELECT COUNT(DISTINCT a.SubjectId) FROM tblsubjectteacherassignments a WHERE a.TeacherId=u.id AND a.Status=1 AND a.AcademicYearId=? AND $arPeriod)>1",[$arYear]],
        'Teachers without subjects'=>[$arBase.' AND NOT '.$arSubject,[$arYear]],
        'Classes without a class teacher'=>['SELECT COUNT(*) FROM tblclasses c WHERE NOT EXISTS (SELECT 1 FROM tblclassteacherassignments a JOIN tblusers u ON u.id=a.TeacherId AND u.Status=1 WHERE a.ClassId=c.id AND a.Status=1 AND a.AcademicYearId=?)',[$arYear]],
        'Subjects without teachers'=>["SELECT COUNT(*) FROM tblsubjects s WHERE NOT EXISTS (SELECT 1 FROM tblsubjectteacherassignments a JOIN tblusers u ON u.id=a.TeacherId AND u.Status=1 WHERE a.SubjectId=s.id AND a.Status=1 AND a.AcademicYearId=? AND $arPeriod)",[$arYear]],
        'Students without subject combinations'=>['SELECT COUNT(*) FROM tblstudents s WHERE s.Status=1 AND NOT EXISTS (SELECT 1 FROM tblstudentsubjects ss WHERE ss.StudentId=s.StudentId AND ss.ClassId=s.ClassId AND ss.Status=1 AND ss.AcademicYearId=?)',[$arYear]]
    ];
?><div class="panel panel-body"><h4>Academic assignment overview</h4><p class="help-block">Selected academic year; subject counts use <?= isset($arTerm)?($arTerm?'whole-year and selected-term assignments':'assignments from all terms'):'whole-year and active-term assignments' ?>.</p><div class="row"><?php foreach($arStats as $label=>$stat) { ?><div class="col-sm-3"><p><strong><?= (int)academic_query($dbh,$stat[0],$stat[1])->fetchColumn() ?></strong> <?= academic_h($label) ?></p></div><?php } ?></div><p><a href="manage-teachers.php#create-teacher">Add Teacher</a> · <a href="dean-teacher-relationships.php#assign">Assign Subject Teacher / Class Teacher</a> · <a href="manage-subjects.php">Manage Subjects</a> · <a href="dean-teacher-relationships.php#responsibilities">Manage Responsibilities</a> · <a href="student-subjects.php">Student Subject Combinations</a></p></div><?php } ?>
