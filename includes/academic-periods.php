<?php
require_once __DIR__.'/academic-assignments.php';
require_once __DIR__.'/audit.php';

function academic_periods_lock($db) {
    if(!$db->inTransaction())throw new LogicException('Academic period changes require a transaction.');
    // This singleton also coordinates timetable writes. Hold it until commit/rollback.
    if(!academic_query($db,'SELECT id FROM tblacademicsettings WHERE id=1 FOR UPDATE')->fetchColumn())throw new DomainException('Academic setup is missing.');
}

function academic_period_name($value,$label,$limit) {
    if(!is_string($value))throw new DomainException($label.' is required.');
    $value=trim($value);
    if($value===''||strlen($value)>$limit)throw new DomainException($label.' is required and must be at most '.$limit.' characters.');
    return $value;
}

function academic_period_dates($start,$end,$label='Term') {
    $dates=[];
    foreach([$start,$end] as $value) {
        if($value===null||$value===''){$dates[]=null;continue;}
        if(!is_string($value))throw new DomainException('Enter valid '.$label.' dates.');
        $date=DateTime::createFromFormat('!Y-m-d',$value);
        if(!$date||$date->format('Y-m-d')!==$value)throw new DomainException('Enter valid '.$label.' dates.');
        $dates[]=$value;
    }
    if($dates[0]&&$dates[1]&&$dates[1]<$dates[0])throw new DomainException($label.' end date cannot precede its start date.');
    return $dates;
}

// Reuse existing periods; new periods stay inactive until explicitly activated.
function academic_period_ensure($db,$academicYear,$termName) {
    $academicYear=academic_period_name($academicYear,'Academic year',20);
    $termName=academic_period_name($termName,'Term name',50);
    academic_periods_lock($db);
    $year=academic_query($db,'SELECT id FROM tblacademicyears WHERE AcademicYear=? FOR UPDATE',[$academicYear])->fetchColumn();
    if(!$year){academic_query($db,'INSERT INTO tblacademicyears(AcademicYear,IsActive) VALUES(?,0)',[$academicYear]);$year=$db->lastInsertId();}
    $term=academic_query($db,'SELECT id FROM tblterms WHERE AcademicYearId=? AND TermName=? FOR UPDATE',[$year,$termName])->fetchColumn();
    if(!$term){academic_query($db,'INSERT INTO tblterms(AcademicYearId,TermName,IsActive) VALUES(?,?,0)',[$year,$termName]);$term=$db->lastInsertId();}
    return ['AcademicYearId'=>(int)$year,'TermId'=>(int)$term];
}

function academic_period_activate($db,$year,$term) {
    $year=filter_var($year,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $term=filter_var($term,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!$year||!$term)throw new DomainException('Select an existing academic year and term.');
    academic_periods_lock($db);
    if(!academic_query($db,'SELECT id FROM tblacademicyears WHERE id=? FOR UPDATE',[$year])->fetchColumn())throw new DomainException('Select an existing academic year.');
    if(!academic_query($db,'SELECT id FROM tblterms WHERE id=? AND AcademicYearId=? FOR UPDATE',[$term,$year])->fetchColumn())throw new DomainException('The term must belong to the selected academic year.');
    $oldYears=array_map('intval',academic_query($db,'SELECT id FROM tblacademicyears WHERE IsActive=1 ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN));
    $oldTerms=array_map('intval',academic_query($db,'SELECT id FROM tblterms WHERE IsActive=1 ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN));
    if($oldYears===[$year]&&$oldTerms===[$term])return false;
    academic_query($db,'UPDATE tblacademicyears SET IsActive=0 WHERE IsActive<>0 AND id<>?',[$year]);
    academic_query($db,'UPDATE tblterms SET IsActive=0 WHERE IsActive<>0 AND id<>?',[$term]);
    academic_query($db,'UPDATE tblacademicyears SET IsActive=1 WHERE id=?',[$year]);
    academic_query($db,'UPDATE tblterms SET IsActive=1 WHERE id=?',[$term]);
    audit_log($db,'academic_period_activated','tblterms',$term,json_encode(['old'=>['years'=>$oldYears,'terms'=>$oldTerms],'new'=>['year'=>$year,'term'=>$term]],JSON_THROW_ON_ERROR));
    return true;
}

function academic_period_save($db,$p) {
    [$start,$end]=academic_period_dates($p['startdate']??null,$p['enddate']??null);
    if(isset($p['activate'])&&!in_array($p['activate'],['1',1],true))throw new DomainException('Invalid activation selection.');
    $period=academic_period_ensure($db,$p['academicyear']??null,$p['termname']??null);
    // These fields describe the term. Existing year dates and activation flags are retained.
    academic_query($db,'UPDATE tblterms SET StartDate=?,EndDate=? WHERE id=?',[$start,$end,$period['TermId']]);
    if(isset($p['activate']))academic_period_activate($db,$period['AcademicYearId'],$period['TermId']);
    audit_log($db,'academic_period_saved','tblterms',$period['TermId'],json_encode($period+['StartDate'=>$start,'EndDate'=>$end],JSON_THROW_ON_ERROR));
    return $period;
}
