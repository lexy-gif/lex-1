<?php
if($seniorArea==='pathways') {
    echo '<div class="srms-actions senior-toolbar">';senior_modal_button('Add Pathway','pathway');senior_modal_button('Add Track','track');senior_modal_button('Manage Pathway Subjects','mapping');echo '</div>';
    $rows=cbe_rows($dbh,'SELECT p.id _id,p.Name Pathway,(SELECT GROUP_CONCAT(t.Name SEPARATOR ", ") FROM tblpathwaytracks t WHERE t.PathwayId=p.id) Tracks,
        (SELECT COUNT(DISTINCT ps.SubjectId) FROM tblpathwaysubjects ps WHERE ps.PathwayId=p.id) Subjects,
        (SELECT COUNT(*) FROM tblstudentpathways a JOIN tblstudents s ON s.StudentId=a.StudentId AND s.Status=1 WHERE a.PathwayId=p.id AND a.AcademicYearId=? AND a.Status=1) Learners,
        IF(p.Status=1,"Active","Inactive") Status FROM tblpathways p WHERE (?=0 OR p.id=?) ORDER BY p.Name',[$f['year'],$f['pathway'],$f['pathway']]);
    senior_table($rows,function($r)use($base){
        foreach(['View'=>senior_url($base,['pathway'=>$r['_id']]),'Edit'=>senior_url($base,['id'=>$r['_id'],'entity'=>'pathway']),'Manage Subjects'=>senior_url($base,['pathway'=>$r['_id'],'mapping'=>1]),'Assign Learners'=>senior_url('dean-senior-assignments.php',['pathway'=>$r['_id']])] as $label=>$url)echo '<a class="btn btn-xs btn-default" href="'.academic_h($url).'">'.$label.'</a> ';
        senior_action('pathway',$r['_id'],$r['Status']==='Active'?0:1);
    });
    $value=$edit&&$entity==='pathway'?cbe_one($dbh,'SELECT * FROM tblpathways WHERE id=?',[$edit]):['Status'=>1];
    senior_form($dbh,'Create / Edit Pathway','pathway',['Name'=>['Pathway name','text'],'Description'=>['Description','textarea'],'MathSubjectId'=>$mathField,'ElectiveCount'=>['Electives (blank uses school default)','number?'],'Status'=>$status],$value?:[]);
    echo '<div class="panel panel-body"><h3>Pathway Tracks</h3>';
    senior_table(cbe_rows($dbh,'SELECT t.id _id,p.Name Pathway,t.Name Track,IF(t.Status=1,"Active","Inactive") Status FROM tblpathwaytracks t JOIN tblpathways p ON p.id=t.PathwayId WHERE (?=0 OR p.id=?) ORDER BY p.Name,t.Name',[$f['pathway'],$f['pathway']]),function($r)use($base){echo '<a class="btn btn-xs btn-default" href="'.academic_h(senior_url($base,['id'=>$r['_id'],'entity'=>'track'])).'">Edit</a> ';senior_action('track',$r['_id'],$r['Status']==='Active'?0:1);});echo '</div>';
    $value=$edit&&$entity==='track'?cbe_one($dbh,'SELECT * FROM tblpathwaytracks WHERE id=?',[$edit]):['PathwayId'=>$f['pathway'],'Status'=>1];
    senior_form($dbh,'Create / Edit Track','track',['PathwayId'=>$pathField,'Name'=>['Track name','text'],'Status'=>$status],$value?:[]);
    if($f['pathway']) {
        echo '<div class="panel panel-body"><h3>Configured Pathway Subjects</h3>';
        senior_table(cbe_rows($dbh,'SELECT s.SubjectName Subject,COALESCE(t.Name,"All tracks") Track,IF(s.Status=1,"Active","Inactive") Status FROM tblpathwaysubjects ps JOIN tblsubjects s ON s.id=ps.SubjectId LEFT JOIN tblpathwaytracks t ON t.id=ps.TrackId WHERE ps.PathwayId=? ORDER BY t.Name,s.SubjectName',[$f['pathway']]));echo '</div>';
    }
} elseif($seniorArea==='subjects') {
    echo '<div class="srms-actions senior-toolbar">';senior_modal_button('Add Subject','subject');senior_modal_button('Manage Pathway Subjects','mapping');senior_modal_button('Core Subject Policy','settings');echo '</div>';
    $rows=cbe_rows($dbh,'SELECT s.id _id,s.SubjectCode Code,s.SubjectName Subject,s.SeniorType Type,
        (SELECT GROUP_CONCAT(g.GradeNumber ORDER BY g.GradeNumber) FROM tblsubjectgrades sg JOIN tblgrades g ON g.id=sg.GradeId WHERE sg.SubjectId=s.id AND g.GradeNumber BETWEEN 10 AND 12) Grades,
        (SELECT GROUP_CONCAT(DISTINCT p.Name SEPARATOR ", ") FROM tblpathwaysubjects ps JOIN tblpathways p ON p.id=ps.PathwayId WHERE ps.SubjectId=s.id) Pathways,
        IF(s.Status=1,"Active","Inactive") Status FROM tblsubjects s WHERE s.SeniorType IS NOT NULL AND (?=0 OR EXISTS(SELECT 1 FROM tblsubjectgrades sg WHERE sg.SubjectId=s.id AND sg.GradeId=?))
        AND (?=0 OR s.SeniorType="core" OR EXISTS(SELECT 1 FROM tblpathwaysubjects ps WHERE ps.SubjectId=s.id AND ps.PathwayId=? AND (?=0 OR ps.TrackId IS NULL OR ps.TrackId=?))) ORDER BY s.SubjectName',[$f['grade'],$f['grade'],$f['pathway'],$f['pathway'],$f['track'],$f['track']]);
    senior_table($rows,function($r)use($base){echo '<a class="btn btn-xs btn-default" href="'.academic_h(senior_url($base,['id'=>$r['_id']])).'">Edit</a> <a class="btn btn-xs btn-default" href="'.academic_h(senior_url('dean-senior-teachers.php',['subject'=>$r['_id']])).'">Assign Teachers</a> ';senior_action('subject',$r['_id'],$r['Status']==='Active'?0:1);});
    $value=$edit?cbe_one($dbh,'SELECT * FROM tblsubjects WHERE id=?',[$edit]):['Status'=>1,'SeniorType'=>'elective','CoreRole'=>'common','Grades'=>array_column(senior_choices($dbh,'grades'),'id')];
    if($edit&&$value)$value['Grades']=academic_query($dbh,'SELECT GradeId FROM tblsubjectgrades WHERE SubjectId=?',[$edit])->fetchAll(PDO::FETCH_COLUMN);
    senior_form($dbh,'Create / Edit Senior School Subject','subject',['SubjectCode'=>['Subject code','text'],'SubjectName'=>['Subject name','text'],'SeniorType'=>['Subject type','enum:core|elective'],'CoreRole'=>['Core role (used only for Core subjects)','enum:common|language|mathematics'],'Grades'=>['Applicable grades','grades[]',senior_choices($dbh,'grades')],'Status'=>$status],$value?:[]);
    senior_form($dbh,'Core Subjects and Elective Policy','settings',['SeniorElectiveCount'=>['Default number of electives','number'],'SeniorLanguageSubjectId'=>['Default language','subjects',senior_choices($dbh,'language')],'SeniorMathSubjectId'=>['Mathematics before pathway assignment','subjects',senior_choices($dbh,'mathematics')]],cbe_one($dbh,'SELECT * FROM tblacademicsettings WHERE id=1'));
    echo '<p class="help-block">All common core subjects mapped to the learner\'s grade are assigned automatically, with one language and one mathematics subject. Pathway mathematics defaults and learner overrides take precedence over the school mathematics default.</p>';
} elseif($seniorArea==='combinations') {
    echo '<div class="srms-actions senior-toolbar">';senior_modal_button('Create Combination','combination');echo '</div>';
    $rows=cbe_rows($dbh,'SELECT co.id _id,co.Name Combination,co.Code,p.Name Pathway,t.Name Track,
        (SELECT GROUP_CONCAT(s.SubjectName SEPARATOR ", ") FROM tblschoolcombinationsubjects cs JOIN tblsubjects s ON s.id=cs.SubjectId WHERE cs.CombinationId=co.id) Subjects,
        (SELECT COUNT(*) FROM tblstudentpathways a WHERE a.CombinationId=co.id AND a.Status=1) Learners,IF(co.Status=1,"Active","Inactive") Status
        FROM tblschoolcombinations co JOIN tblpathwaytracks t ON t.id=co.TrackId JOIN tblpathways p ON p.id=t.PathwayId WHERE co.AcademicYearId=? AND (?=0 OR p.id=?) AND (?=0 OR t.id=?) ORDER BY co.Name',[$f['year'],$f['pathway'],$f['pathway'],$f['track'],$f['track']]);
    senior_table($rows,function($r)use($base){echo '<a class="btn btn-xs btn-default" href="'.academic_h(senior_url($base,['id'=>$r['_id']])).'">Edit</a> <a class="btn btn-xs btn-default" href="'.academic_h(senior_url('dean-senior-assignments.php',['combination'=>$r['_id']])).'">Assign Combination</a> ';senior_action('combination',$r['_id'],$r['Status']==='Active'?0:1);senior_action('combination',$r['_id'],0,true);});
    $value=$edit?cbe_one($dbh,'SELECT co.*,t.PathwayId FROM tblschoolcombinations co JOIN tblpathwaytracks t ON t.id=co.TrackId WHERE co.id=?',[$edit]):['AcademicYearId'=>$f['year'],'PathwayId'=>$f['pathway'],'TrackId'=>$f['track'],'Status'=>1];
    if($edit&&$value)$value['Subjects']=academic_query($dbh,'SELECT SubjectId FROM tblschoolcombinationsubjects WHERE CombinationId=?',[$edit])->fetchAll(PDO::FETCH_COLUMN);
    senior_form($dbh,'Create / Edit Subject Combination','combination',['Name'=>['Combination name','text'],'Code'=>['Combination code','text?'],'AcademicYearId'=>$yearField,'PathwayId'=>$pathField,'TrackId'=>$trackField,'Subjects'=>$electiveField,'Status'=>$status],$value?:[]);
} elseif(in_array($seniorArea,['assignments','promotions'],true)) {
    $promote=$seniorArea==='promotions';$learners=senior_learners($dbh,$f,0,$promote);$action=$promote?'promote':'assign';
    $rows=array_map(fn($s)=>['_id'=>$s['StudentId'],'_active'=>$s['StudentStatus'],'_allocation'=>$s['AllocationId'],'Admission'=>$s['RollId'],'Learner'=>$s['StudentName'],'Grade'=>$s['GradeNumber'],'Class'=>$s['Class'],'Pathway'=>$s['Pathway']??'Unassigned','Track'=>$s['Track']??'','Combination'=>$s['Combination']??'','Status'=>$s['StudentStatus']?'Active':'Inactive'],$learners);
    senior_table($rows,function($r)use($base){echo '<a class="btn btn-xs btn-default" href="'.academic_h(senior_url($base,['student'=>$r['_id']])).'">View Learner</a>';},'senior-'.$action.'-form');
    $value=['AcademicYearId'=>$f['year'],'PathwayId'=>$f['pathway'],'TrackId'=>$f['track'],'CombinationId'=>$f['combination'],'PlacementSource'=>'School Dean'];
    if($f['combination']) {
        $co=cbe_one($dbh,'SELECT co.*,t.PathwayId FROM tblschoolcombinations co JOIN tblpathwaytracks t ON t.id=co.TrackId WHERE co.id=?',[$f['combination']]);
        if($co){$value['PathwayId']=$co['PathwayId'];$value['TrackId']=$co['TrackId'];}
    }
    if($promote) {
        $value['AcademicYearId']=null;
        senior_form($dbh,'Promote Selected Learners','promote',['AcademicYearId'=>['Destination academic year','years',senior_choices($dbh,'years')],'ClassId'=>['Destination class','classes',senior_choices($dbh,'classes')]],$value,false,['SourceYearId'=>$f['year']]);
        echo '<p class="help-block">Promotions move one grade into a later academic year. The pathway, track and recorded electives carry forward; earlier enrollments, registrations and marks remain available.</p>';
    } else {
        if($f['student'] && count($learners)===1) {
            $learner=$learners[0];$value=array_merge($value,['PathwayId'=>$learner['PathwayId']?:$value['PathwayId'],'TrackId'=>$learner['TrackId']?:$value['TrackId'],'CombinationId'=>$learner['CombinationId']?:$value['CombinationId'],'SeniorLanguageSubjectId'=>$learner['SeniorLanguageSubjectId'],'SeniorMathSubjectId'=>$learner['SeniorMathSubjectId']]);
            if($learner['AllocationId'])$value['Subjects']=academic_query($dbh,"SELECT SubjectId FROM tblpathwayallocationsubjects WHERE AllocationId=? AND SubjectType='elective'",[$learner['AllocationId']])->fetchAll(PDO::FETCH_COLUMN);
            echo '<div class="panel panel-body"><h3>'.academic_h($learner['StudentName']).': Current Subjects</h3>';
            senior_table(senior_learner_subjects($dbh,$f['student'],$f['year'],$learner['ClassId']));
            echo '<h4>Pathway History</h4>';
            senior_table(cbe_rows($dbh,'SELECT y.AcademicYear Year,g.GradeNumber Grade,p.Name Pathway,t.Name Track,co.Name Combination,
                (SELECT GROUP_CONCAT(CONCAT(s.SubjectName," (",aps.SubjectType,")") SEPARATOR ", ") FROM tblpathwayallocationsubjects aps JOIN tblsubjects s ON s.id=aps.SubjectId WHERE aps.AllocationId=a.id) Subjects,
                a.AssignedAt Assigned,a.EndedAt Ended FROM tblstudentpathways a JOIN tblacademicyears y ON y.id=a.AcademicYearId LEFT JOIN tblgrades g ON g.id=a.GradeId JOIN tblpathways p ON p.id=a.PathwayId JOIN tblpathwaytracks t ON t.id=a.TrackId LEFT JOIN tblschoolcombinations co ON co.id=a.CombinationId WHERE a.StudentId=? ORDER BY a.id DESC',[$f['student']]));echo '</div>';
        }
        senior_form($dbh,'Assign Selected Learners','assign',['AcademicYearId'=>$yearField,'PathwayId'=>$pathField,'TrackId'=>$trackField,'CombinationId'=>['Predefined combination (optional)','combinations?',senior_choices($dbh,'combinations',$f)],'Subjects'=>$electiveField,'SeniorLanguageSubjectId'=>$languageField,'SeniorMathSubjectId'=>$mathField,'ConfirmReplacement'=>['Replace existing allocations after review','bool'],'ReferenceNotes'=>['Reason or placement notes','textarea']],$value+['ConfirmReplacement'=>0],false);
        echo '<p class="help-block">Choose a combination or select electives individually. Core subjects are added automatically. A blank language or mathematics selection uses the configured default. Only the selected learners are changed.</p>';
    }
} elseif($seniorArea==='teachers') {
    senior_table(senior_report($dbh,'teachers',$f));
    $value=['AcademicYearId'=>$f['year'],'ClassId'=>$f['class'],'PathwayId'=>$f['pathway'],'SubjectId'=>$f['subject']];
    $hidden=[];
    if($conflict) {
        echo '<div class="alert alert-warning">'.academic_h($error).' Review the replacement below.</div>';
        $hidden['confirmed_assignments[]']=$conflict;
    }
    senior_form($dbh,'Assign Subject Teacher','teacher',['AcademicYearId'=>$yearField,'TermId'=>['Term (blank means whole year)','terms?'],'ClassId'=>['Class','classes',senior_choices($dbh,'classes')],'PathwayId'=>['Pathway (optional)','pathways?',cbe_options($dbh,'pathways')],'SubjectId'=>['Subject','subjects',senior_choices($dbh,'senior-subjects')],'TeacherId'=>['Teacher','teachers']],$value,false,$hidden);
    echo '<p><a href="dean-teacher-relationships.php">Open Teacher Relationships</a> to end assignments or review the full assignment history.</p>';
} elseif($seniorArea==='reports') {
    echo '<div class="srms-actions senior-toolbar cbe-no-print"><a class="btn btn-default" href="'.academic_h(senior_url($base,['export'=>'csv','report'=>$report])).'">Export CSV</a><button class="btn btn-default" type="button" onclick="window.print()">Print / Save PDF</button></div>';
    echo '<div class="panel panel-body"><h3>'.academic_h(ucfirst($report)).' Report</h3>';
    senior_table(senior_report($dbh,$report,$f));echo '</div>';
}
if(in_array($seniorArea,['pathways','subjects'],true)) {
    $value=['PathwayId'=>$f['pathway'],'TrackId'=>$f['track']];
    if($f['pathway'])$value['Subjects']=academic_query($dbh,'SELECT SubjectId FROM tblpathwaysubjects WHERE PathwayId=? AND TrackScope=?',[$f['pathway'],$f['track']])->fetchAll(PDO::FETCH_COLUMN);
    if(isset($_GET['mapping']))$value['id']=0;
    senior_form($dbh,'Configure Electives for a Pathway or Track','mapping',['PathwayId'=>$pathField,'TrackId'=>['Track (blank applies to all tracks)','tracks?',cbe_options($dbh,'tracks')],'Subjects'=>$electiveField],$value);
}
