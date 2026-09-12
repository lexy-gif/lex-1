<?php
require_once __DIR__.'/cbe-academics.php';require_once __DIR__.'/sms.php';
function student_validate($db,$p,$id=0) {
    $name=cbe_text($p,'fullanme',100);$admission=cbe_text($p,'rollid',100);
    if(!preg_match("/^[\p{L}\p{M} .'-]+$/uD",$name))throw new DomainException('Enter a valid learner name.');
    $email=cbe_text($p,'emailid',100,false);if($email&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new DomainException('Enter a valid email address.');
    $phone=cbe_text($p,'parentphone',30,false);$normalized=normalize_phone_number($phone);if($phone&&!$normalized)throw new DomainException('Enter a valid Kenyan guardian mobile number.');
    $class=filter_var($p['class']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!$class||!academic_query($db,'SELECT c.id FROM tblclasses c JOIN tblgrades g ON g.id=c.GradeId AND g.Status=1 WHERE c.id=? AND c.ClassNameNumeric IN (10,11,12) AND g.GradeNumber=c.ClassNameNumeric',[$class])->fetchColumn())throw new DomainException('Select an active Grade 10, 11 or 12 class.');
    $dob=cbe_text($p,'dob',10,false);if($dob){cbe_date($dob);if($dob>date('Y-m-d'))throw new DomainException('Date of birth cannot be in the future.');}
    if(!in_array($p['gender']??null,['Male','Female','Other'],true))throw new DomainException('Select a valid gender.');
    if(isset($p['status'])&&!in_array((string)$p['status'],['0','1'],true))throw new DomainException('Select active or inactive.');
    if(academic_query($db,'SELECT StudentId FROM tblstudents WHERE RollId=? AND StudentId<>?',[$admission,$id])->fetchColumn())throw new DomainException('This admission number is already registered.');
    return ['name'=>$name,'admission'=>$admission,'email'=>$email,'phone'=>$normalized,'class'=>$class,'dob'=>$dob];
}
