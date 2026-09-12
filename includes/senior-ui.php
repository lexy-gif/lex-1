<?php
require_once __DIR__.'/senior-school.php';
require_once __DIR__.'/cbe-ui.php';

function senior_url($page,$changes=[]) {
    $query=array_intersect_key($_GET,array_flip(['year','grade','class','pathway','track','combination','student','subject','gender','assignment','q','report']));
    $query=array_filter(array_merge($query,$changes),fn($v)=>is_scalar($v)&&$v!==''&&$v!==null);
    return $page.($query?'?'.http_build_query($query):'');
}
function senior_choices($db,$kind,$f=[]) {
    switch($kind) {
        case 'grades':return cbe_rows($db,'SELECT id,Name Label FROM tblgrades WHERE GradeNumber BETWEEN 10 AND 12 ORDER BY GradeNumber');
        case 'promotion-grades':return cbe_rows($db,'SELECT id,Name Label FROM tblgrades WHERE GradeNumber BETWEEN 10 AND 12 ORDER BY GradeNumber');
        case 'classes':return cbe_rows($db,'SELECT c.id,CONCAT(c.ClassName," ",c.Section) Label FROM tblclasses c JOIN tblgrades g ON g.id=c.GradeId WHERE g.GradeNumber BETWEEN 10 AND 12 ORDER BY g.GradeNumber,c.Section');
        case 'promotion-classes':return cbe_rows($db,'SELECT c.id,CONCAT(c.ClassName," ",c.Section) Label FROM tblclasses c JOIN tblgrades g ON g.id=c.GradeId WHERE g.GradeNumber BETWEEN 10 AND 12 ORDER BY g.GradeNumber,c.Section');
        case 'pathways':return cbe_rows($db,'SELECT id,CONCAT(Name,IF(Status=0," (inactive)","")) Label FROM tblpathways ORDER BY Name');
        case 'tracks':return cbe_rows($db,'SELECT t.id,CONCAT(p.Name," / ",t.Name,IF(t.Status=0," (inactive)","")) Label FROM tblpathwaytracks t JOIN tblpathways p ON p.id=t.PathwayId WHERE (?=0 OR p.id=?) ORDER BY p.Name,t.Name',[$f['pathway']??0,$f['pathway']??0]);
        case 'language':case 'mathematics':return cbe_rows($db,"SELECT id,SubjectName Label FROM tblsubjects WHERE SeniorType='core' AND CoreRole=? AND Status=1 ORDER BY SubjectName",[$kind]);
        case 'senior-subjects':return cbe_rows($db,'SELECT id,SubjectName Label FROM tblsubjects WHERE SeniorType IS NOT NULL AND Status=1 ORDER BY SubjectName');
        case 'electives':return cbe_rows($db,"SELECT id,SubjectName Label FROM tblsubjects WHERE SeniorType='elective' AND Status=1 ORDER BY SubjectName");
        case 'combinations':return cbe_rows($db,'SELECT co.id,CONCAT(p.Name," / ",co.Name,IF(co.Status=0," (inactive)","")) Label FROM tblschoolcombinations co JOIN tblpathwaytracks t ON t.id=co.TrackId JOIN tblpathways p ON p.id=t.PathwayId WHERE co.AcademicYearId=? AND (?=0 OR p.id=?) AND (?=0 OR t.id=?) ORDER BY p.Name,co.Name',[$f['year']??academic_year($db),$f['pathway']??0,$f['pathway']??0,$f['track']??0,$f['track']??0]);
        default:return cbe_options($db,$kind);
    }
}
function senior_field($db,$name,$spec,$value=null) {
    [$label,$type]=$spec;$choices=$spec[2]??null;
    if(str_ends_with($type,'[]'))$value=is_array($value)?array_filter($value,'is_scalar'):[];
    elseif(!is_scalar($value))$value=null;
    ob_start();cbe_field($db,$name,$type,$value,$choices);$html=ob_get_clean();
    $fieldId='senior-'.preg_replace('/[^a-zA-Z0-9_-]/','-',$name).'-'.(++$GLOBALS['seniorFieldCounter']);
    $html=preg_replace('/<label>.*?<\/label>/', '<label for="'.$fieldId.'">'.academic_h($label).'</label>',$html,1);
    $html=preg_replace('/<(input|select|textarea) /','<$1 id="'.$fieldId.'" ',$html,1);
    echo $html;
}
$GLOBALS['seniorFieldCounter']=0;
function senior_form($db,$title,$action,$fields,$values=[],$modal=true,$hidden=[]) {
    $retry=($_SERVER['REQUEST_METHOD']??'GET')==='POST' && ($_POST['action']??'')===$action;
    if($retry)$values=array_merge($values,$_POST);
    $open=$retry||!empty($values['id']);$id='senior-'.$action;
    if($modal)echo '<div class="modal fade senior-editor" id="'.$id.'" tabindex="-1" role="dialog" aria-labelledby="'.$id.'-title" data-open="'.($open?'1':'0').'"><div class="modal-dialog modal-lg" role="document"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal" aria-label="Close">&times;</button><h3 class="modal-title" id="'.$id.'-title">'.academic_h($title).'</h3></div><div class="modal-body">';
    else echo '<div class="panel panel-body"><h3>'.academic_h($title).'</h3>';
    echo '<form method="post" id="'.$id.'-form" class="senior-form" data-senior-form="'.$action.'">';csrf_field();
    echo '<input type="hidden" name="action" value="'.$action.'">';
    if(isset($values['id']))echo '<input type="hidden" name="id" value="'.(int)$values['id'].'">';
    foreach($hidden as $key=>$value)echo '<input type="hidden" name="'.academic_h($key).'" value="'.academic_h($value).'">';
    echo '<div class="row">';foreach($fields as $name=>$spec){echo '<div class="col-md-6">';senior_field($db,$name,$spec,$values[$name]??null);echo '</div>';}echo '</div>';
    if($action==='combination')echo '<p class="help-block">Combinations contain electives only. Existing learners keep their recorded subjects until you explicitly reapply the combination.</p>';
    if(in_array($action,['assign','promote'],true))echo '<p class="help-block">Select learners in the table above. The whole batch is validated before changes are saved.</p>';
    echo '<div class="srms-actions"><button class="btn btn-primary" type="submit">Save '.($action==='assign'?'Assignments':($action==='promote'?'Promotion':'Changes')).'</button>';
    if($modal)echo '<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>';
    echo '</div></form></div>';if($modal)echo '</div></div></div>';
}
function senior_modal_button($label,$action) {echo '<button class="btn btn-primary" type="button" data-toggle="modal" data-target="#senior-'.$action.'">'.academic_h($label).'</button>';}
function senior_action($entity,$id,$status,$delete=false) {
    echo '<form method="post" class="form-inline-action" data-confirm="'.academic_h($delete?'Delete this unused combination?':($status?'Activate this record?':'Deactivate this record? Existing academic history will be kept.')).'">';csrf_field();
    foreach(['action'=>$delete?'delete':'toggle','entity'=>$entity,'id'=>$id,'Status'=>$status] as $key=>$value)echo '<input type="hidden" name="'.$key.'" value="'.academic_h($value).'">';
    echo '<button class="btn btn-xs '.($delete?'btn-danger':'btn-default').'">'.($delete?'Delete':($status?'Activate':'Deactivate')).'</button></form>';
}
function senior_table($rows,$actions=null,$selectForm=null) {
    if(!$rows){echo '<p class="text-muted">No records match these filters.</p>';return;}
    $keys=array_keys($rows[0]);echo '<div class="table-responsive"><table class="table table-striped senior-table"><thead><tr>';
    if($selectForm)echo '<th><input type="checkbox" class="senior-select-all" data-form="'.$selectForm.'" aria-label="Select all visible learners"></th>';
    foreach($keys as $key)if(!str_starts_with($key,'_'))echo '<th>'.academic_h($key).'</th>';
    if($actions)echo '<th>Actions</th>';echo '</tr></thead><tbody>';
    foreach($rows as $row) {
        echo '<tr>';
        if($selectForm){$selected=is_array($_POST['Students']??null)?array_filter($_POST['Students'],'is_scalar'):[];$checked=in_array((string)$row['_id'],array_map('strval',$selected),true);echo '<td><input type="checkbox" name="Students[]" value="'.(int)$row['_id'].'" form="'.$selectForm.'" '.($checked?'checked ':'').(empty($row['_active'])?'disabled ':'').' aria-label="Select '.academic_h($row['Learner']).'"><input type="hidden" name="ExpectedAllocations['.(int)$row['_id'].']" value="'.(int)($row['_allocation']??0).'" form="'.$selectForm.'"></td>';}
        foreach($row as $key=>$value)if(!str_starts_with($key,'_')) {
            echo '<td>';
            if($key==='Status')echo '<span class="status-badge '.($value==='Active'?'status-active':'status-inactive').'">'.academic_h($value).'</span>';
            else echo academic_h($value??'');echo '</td>';
        }
        if($actions){echo '<td class="senior-row-actions">';$actions($row);echo '</td>';}echo '</tr>';
    }
    echo '</tbody></table></div>';
}
function senior_filter_form($db,$f,$area) {
    $promotion=$area==='promotions';$fields=[
        'year'=>['Academic year','years',senior_choices($db,'years')],
        'grade'=>['Grade','grades?',senior_choices($db,$promotion?'promotion-grades':'grades')],
        'class'=>['Class','classes?',senior_choices($db,$promotion?'promotion-classes':'classes')],
        'pathway'=>['Pathway','pathways?',senior_choices($db,'pathways')],
        'track'=>['Track','tracks?',senior_choices($db,'tracks',$f)]
    ];
    if(in_array($area,['assignments','promotions','reports'],true))$fields+=[
        'combination'=>['Combination','combinations?',senior_choices($db,'combinations',$f)],
        'gender'=>['Gender','enum:Male|Female|Other?'],
        'assignment'=>['Assignment status','enum:assigned|unassigned?'],
        'q'=>['Name or admission number','text?']
    ];
    echo '<form method="get" class="panel panel-body senior-filters"><div class="row">';
    foreach($fields as $key=>$spec){echo '<div class="col-sm-6 col-md-3">';senior_field($db,$key,$spec,$f[$key]?:null);echo '</div>';}
    if($area==='reports'){echo '<div class="col-md-3">';senior_field($db,'report',['Report','enum:pathways|tracks|combinations|enrollment|teachers'],$_GET['report']??'pathways');echo '</div>';}
    echo '</div><button class="btn btn-default">Apply Filters</button></form>';
}
