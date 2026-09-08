<?php
require_once __DIR__.'/cbe-academics.php';
require_once __DIR__.'/cbe-context.php';
function cbe_label($key) {return preg_replace('/(?<=[a-z])(?=[A-Z])/',' ',preg_replace('/Id$/','',$key));}
function cbe_ui_options($db,$kind) {
    global $cbeTeacherId,$year;
    if(empty($cbeTeacherId)||!in_array($kind,['classes','subjects','outcomes'],true))return cbe_options($db,$kind);
    $assignment='SELECT 1 FROM tblsubjectteacherassignments a WHERE a.TeacherId=? AND a.AcademicYearId=? AND a.Status=1';
    if($kind==='classes')$sql='SELECT c.id,CONCAT(c.ClassName," ",c.Section) Label FROM tblclasses c WHERE EXISTS('.$assignment.' AND a.ClassId=c.id) ORDER BY c.ClassNameNumeric,c.Section';
    elseif($kind==='subjects')$sql='SELECT s.id,s.SubjectName Label FROM tblsubjects s WHERE s.Status=1 AND EXISTS('.$assignment.' AND a.SubjectId=s.id) ORDER BY s.SubjectName';
    else $sql='SELECT o.id,CONCAT(c.Title," / ",o.Title) Label FROM tbllearningoutcomes o JOIN tblcompetencies c ON c.id=o.CompetencyId WHERE o.Status=1 AND c.Status=1 AND EXISTS('.$assignment.' AND a.SubjectId=c.SubjectId) ORDER BY o.Title';
    return cbe_rows($db,$sql,[$cbeTeacherId,$year]);
}
function cbe_field($db,$name,$type,$value=null,$choices=null) {
    $multiple=str_ends_with($type,'[]');if($multiple)$type=substr($type,0,-2);$optional=str_ends_with($type,'?');$type=rtrim($type,'?');
    echo '<div class="form-group"><label>'.academic_h(cbe_label($name)).'</label>';
    if($type==='textarea')echo '<textarea class="form-control" name="'.academic_h($name).'">'.academic_h($value).'</textarea>';
    elseif(in_array($type,['text','date','number'],true))echo '<input class="form-control" type="'.$type.'" name="'.academic_h($name).'" value="'.academic_h($value).'" '.($type==='number'?'step="any"':'').' '.(!$optional?'required':'').'>';
    else {
        $choices=$choices??($type==='bool'?[['id'=>1,'Label'=>'Active / Yes'],['id'=>0,'Label'=>'Inactive / No']]:(str_starts_with($type,'enum:')?array_map(fn($v)=>['id'=>$v,'Label'=>ucfirst(str_replace('_',' ',$v))],explode('|',substr($type,5))):cbe_ui_options($db,$type)));
        echo '<select class="form-control academic-search" name="'.academic_h($name).($multiple?'[]':'').'" '.($multiple?'multiple size="5"':'').' '.(!$optional&&!$multiple?'required':'').'>';
        if(!$multiple)echo '<option value="">'.($optional?'None':'Select').'</option>';
        foreach($choices as $r)echo '<option value="'.academic_h($r['id']).'" '.(in_array((string)$r['id'],array_map('strval',is_array($value)?$value:[$value]),true)?'selected':'').'>'.academic_h($r['Label']).'</option>';
        echo '</select>';
    }echo '</div>';
}
function cbe_form($db,$title,$action,$fields,$values=[],$hidden=[]) {
    $retry=($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&($_POST['action']??'')===$action;
    foreach($hidden as $key=>$value)if(($_POST[$key]??null)!=$value)$retry=false;
    if($retry)$values=array_merge($values,array_intersect_key($_POST,$fields+['id'=>true]));
    echo '<details class="panel panel-body cbe-form" '.(!empty($values['id'])||$retry?'open':'').'><summary><strong>'.academic_h($title).'</strong></summary><form method="post">';csrf_field();
    echo '<input type="hidden" name="action" value="'.academic_h($action).'">';
    foreach($hidden as $k=>$v)echo '<input type="hidden" name="'.academic_h($k).'" value="'.academic_h($v).'">';
    if(isset($values['id']))echo '<input type="hidden" name="id" value="'.(int)$values['id'].'">';
    echo '<div class="row">';foreach($fields as $k=>$type){echo '<div class="col-md-6">';cbe_field($db,$k,$type,$values[$k]??(str_contains($k,'Date')?date('Y-m-d'):($k==='Status'?1:null)));echo '</div>';}
    echo '</div><button class="btn btn-primary">Save</button></form></details>';
}
function cbe_table($rows,$editUrl=null) {
    if(!$rows){echo '<p class="text-muted">No records match this view.</p>';return;}
    echo '<div class="table-responsive"><table class="table table-striped table-bordered cbe-table"><thead><tr>';
    foreach(array_keys($rows[0]) as $key)echo '<th>'.academic_h(cbe_label($key)).'</th>';if($editUrl)echo '<th>Actions</th>';echo '</tr></thead><tbody>';
    foreach($rows as $r){echo '<tr>';foreach($r as $v)echo '<td>'.academic_h($v??'—').'</td>';if($editUrl){$parts=parse_url($editUrl);parse_str($parts['query']??'',$editQuery);$editQuery['id']=(int)$r['id'];$url=cbe_view_url($parts['path'],$_GET,$editQuery);echo '<td><a class="btn btn-xs btn-default" href="'.academic_h($url).'">Open / Edit</a></td>';}echo '</tr>';}
    echo '</tbody></table></div>';
}
function cbe_chart($title,$rows,$group,$value) {
    $totals=[];$counts=[];foreach($rows as $r)if(isset($r[$value])&&is_numeric($r[$value])){$k=$r[$group]?:'Unmapped';$totals[$k]=($totals[$k]??0)+(float)$r[$value];$counts[$k]=($counts[$k]??0)+1;}
    echo '<div class="panel panel-body"><h4>'.academic_h($title).'</h4>';if(!$totals)echo '<p>No numeric observations for this period.</p>';
    foreach($totals as $k=>$sum){$avg=$sum/$counts[$k];$max=max(1,max(array_map(fn($key)=>$totals[$key]/$counts[$key],array_keys($totals))));echo '<div class="cbe-chart-row"><span>'.academic_h($k).'</span><div class="progress"><div class="progress-bar" style="width:'.round(100*$avg/$max,2).'%"></div></div><strong>'.round($avg,2).'</strong></div>';}
    echo '</div>';
}
