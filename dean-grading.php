<?php
require_once 'includes/bootstrap.php';require_once 'includes/config.php';require_once 'includes/dean-auth.php';require_dean();require_once 'includes/portal-layout.php';
$id=max(0,(int)($_GET['id']??0));$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_require_valid($_POST['csrf_token']??'');
    try{
        $dbh->beginTransaction();$dbh->query('SELECT id FROM tblacademicsettings WHERE id=1 FOR UPDATE')->fetchColumn();
        $id=max(0,(int)($_POST['id']??0));
        if($id&&!cbe_one($dbh,'SELECT id FROM tblgradingscales WHERE id=?',[$id]))throw new DomainException('Performance band not found.');
        if(($_POST['action']??'')==='delete')academic_query($dbh,'DELETE FROM tblgradingscales WHERE id=?',[$id]);
        else {
            $grade=cbe_text($_POST,'Grade',10);$remark=cbe_text($_POST,'Remark',100);$min=cbe_number($_POST['MinMark']??null);$max=cbe_number($_POST['MaxMark']??null);
            if($min>$max)throw new DomainException('The minimum cannot exceed the maximum.');
            if(academic_query($dbh,'SELECT id FROM tblgradingscales WHERE id<>? AND MinMark<=? AND MaxMark>=?',[$id,$max,$min])->fetchColumn())throw new DomainException('Performance bands cannot overlap.');
            if($id)academic_query($dbh,'UPDATE tblgradingscales SET Grade=?,Remark=?,MinMark=?,MaxMark=? WHERE id=?',[$grade,$remark,$min,$max,$id]);
            else academic_query($dbh,'INSERT INTO tblgradingscales(Grade,Remark,MinMark,MaxMark) VALUES(?,?,?,?)',[$grade,$remark,$min,$max]);
        }
        audit_log($dbh,'exam_performance_scale_updated','tblgradingscales',$id,'School performance configuration changed');$dbh->commit();header('Location: dean-grading.php?saved=1');exit;
    }catch(Throwable $e){if($dbh->inTransaction())$dbh->rollBack();error_log($e->getMessage());$error=$e instanceof DomainException?$e->getMessage():'Could not save the performance scale. Check duplicate labels.';}
}
$values=$error?$_POST:($id?cbe_one($dbh,'SELECT * FROM tblgradingscales WHERE id=?',[$id]):[]);
portal_start('Examination performance scale','dean');portal_alert($error);if(isset($_GET['saved']))portal_alert('Performance scale saved.','success');?><section class="parent-card"><p>Configure school-approved performance labels and percentage bands. Examination scores are normalized against the configured maximum. Published reports retain the scale used at publication.</p><form method="post" class="portal-form"><?php csrf_field();?><input type="hidden" name="id" value="<?= $id ?>"><?php portal_input('Performance label','Grade',$values['Grade']??'','text',true,'maxlength="10"');portal_input('Descriptor','Remark',$values['Remark']??'','text',true,'maxlength="100"');foreach(['MinMark'=>'Minimum percentage','MaxMark'=>'Maximum percentage'] as $key=>$label)portal_input($label,$key,$values[$key]??'','number',true,'min="0" max="100" step="0.01"');?><button name="action" value="save" class="btn btn-primary">Save band</button></form><div class="table-responsive"><table class="table"><thead><tr><th>Level</th><th>Descriptor</th><th>Minimum %</th><th>Maximum %</th><th>Actions</th></tr></thead><tbody><?php foreach(cbe_rows($dbh,'SELECT * FROM tblgradingscales ORDER BY MinMark DESC') as $r){?><tr><td><?= academic_h($r['Grade']) ?></td><td><?= academic_h($r['Remark']) ?></td><td><?= academic_h($r['MinMark']) ?></td><td><?= academic_h($r['MaxMark']) ?></td><td><a class="btn btn-default btn-sm" href="?id=<?= (int)$r['id'] ?>">Edit</a><form method="post" class="portal-inline"><?php csrf_field();?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button name="action" value="delete" class="btn btn-warning btn-sm">Remove band</button></form></td></tr><?php }?></tbody></table></div></section><?php portal_end('dean'); ?>
