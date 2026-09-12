<?php
require_once __DIR__.'/parent-auth.php';
require_once __DIR__.'/sms.php';
function parent_save($db,$post) {
    $id=(int)($post['id']??0);
    $old=$id?cbe_one($db,"SELECT * FROM tblusers WHERE id=? AND Role='parent' FOR UPDATE",[$id]):null;
    if ($id&&!$old) throw new DomainException('Guardian account not found.');
    $name=cbe_text($post,'FullName',150); $username=cbe_text($post,'Username',100);
    if (!preg_match('/^[a-zA-Z0-9_.@-]{3,100}$/D',$username)) throw new DomainException('Use a username of 3–100 letters, numbers, dots, dashes or @.');
    $email=cbe_text($post,'Email',150,false);
    if ($email&&!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new DomainException('Enter a valid email address.');
    $raw=cbe_text($post,'ParentPhone',30,false); $phone=normalize_phone_number($raw);
    if ($raw&&!$phone) throw new DomainException('Enter a valid Kenyan mobile number, such as 07XXXXXXXX or 01XXXXXXXX.');
    $status=(string)($post['Status']??''); if (!in_array($status,['0','1'],true)) throw new DomainException('Select an account status.');
    if ($id) academic_query($db,"UPDATE tblusers SET FullName=?,Username=?,Email=?,ParentPhone=?,Status=?,SessionVersion=SessionVersion+1 WHERE id=? AND Role='parent'",[$name,$username,$email?:null,$phone?:null,$status,$id]);
    else {
        $hash=security_password($post['Password']??null);
        academic_query($db,"INSERT INTO tblusers(FullName,Username,Email,ParentPhone,Status,PasswordHash,Role,CreatedBy,MustChangePassword) VALUES(?,?,?,?,?,?,'parent',?,1)",[$name,$username,$email?:null,$phone?:null,$status,$hash,cbe_actor()]);
        $id=(int)$db->lastInsertId();
    }
    cbe_audit($db,'guardian_saved','tblusers',$id,null,['name'=>$name,'status'=>$status]); return $id;
}
function parent_manage($db,$post) {
    $action=$post['action']??'';
    if ($action==='save') return parent_save($db,$post);
    $id=filter_var($post['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if (!$id||!cbe_one($db,"SELECT id FROM tblusers WHERE id=? AND Role='parent' FOR UPDATE",[$id])) throw new DomainException('Select a guardian account.');
    if ($action==='reset') {
        academic_query($db,"UPDATE tblusers SET PasswordHash=?,MustChangePassword=1,SessionVersion=SessionVersion+1 WHERE id=? AND Role='parent'",[security_password($post['Password']??null),$id]);
    } elseif ($action==='link') {
        $admission=cbe_text($post,'Admission',100);
        $student=cbe_one($db,'SELECT s.StudentId FROM tblstudents s JOIN tblclasses c ON c.id=s.ClassId WHERE s.RollId=? AND s.Status=1 AND c.ClassNameNumeric IN (10,11,12)',[$admission]);
        if (!$student) throw new DomainException('No active Senior School learner has this admission number.');
        $relationship=cbe_text($post,'Relationship',50);
        $notify=isset($post['NotifyResults'])?1:0;
        academic_query($db,'INSERT INTO tblparentstudents(ParentId,StudentId,Relationship,NotifyResults,CreatedBy) VALUES(?,?,?,?,?)
            ON DUPLICATE KEY UPDATE Status=1,Relationship=VALUES(Relationship),NotifyResults=VALUES(NotifyResults)',[$id,$student['StudentId'],$relationship,$notify,cbe_actor()]);
    } elseif ($action==='unlink') {
        academic_query($db,'UPDATE tblparentstudents SET Status=0 WHERE ParentId=? AND StudentId=?',[$id,(int)($post['StudentId']??0)]);
    } else throw new DomainException('Select a valid guardian action.');
    cbe_audit($db,'guardian_'.$action,'tblusers',$id,null,['student'=>$post['StudentId']??$post['Admission']??null]); return $id;
}
