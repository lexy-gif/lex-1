<?php
const ACADEMIC_TEACHER_ROLES = "'class_teacher','subject_teacher','head_of_department','exams_officer','deputy_dean'";
function academic_query($db, $sql, $params = []) {
    $q = $db->prepare($sql); $q->execute($params); return $q;
}
function academic_ready($db) {
    static $ready;
    if ($ready === null) {
        try { $ready = (bool)$db->query("SELECT 1 FROM tblschemamigrations WHERE Name='relational-teacher-management-v1'")->fetchColumn(); }
        catch (PDOException $e) { $ready = false; }
    }
    return $ready;
}
function academic_year($db) {
    return (int)$db->query('SELECT id FROM tblacademicyears WHERE IsActive=1 ORDER BY id DESC LIMIT 1')->fetchColumn();
}
function academic_period($db, $year, $term = null) {
    if (!academic_query($db,'SELECT id FROM tblacademicyears WHERE id=?',[$year])->fetchColumn()) throw new DomainException('Select a valid academic year.');
    if ($term && !academic_query($db,'SELECT id FROM tblterms WHERE id=? AND AcademicYearId=?',[$term,$year])->fetchColumn()) throw new DomainException('The term must belong to the selected academic year.');
}
function academic_teacher($db, $teacher) {
    $row=academic_query($db,'SELECT * FROM tblusers WHERE id=? AND Role IN ('.ACADEMIC_TEACHER_ROLES.') FOR UPDATE',[$teacher])->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)$row['Status']!==1) throw new DomainException('Only active teachers can receive assignments.');
    return $row;
}
function academic_class_lock($db, $class) {
    if (!academic_query($db,'SELECT id FROM tblclasses WHERE id=? FOR UPDATE',[$class])->fetchColumn()) throw new DomainException('Select a valid class.');
}
function academic_offering($db,$class,$subject) {
    if (!academic_query($db,'SELECT id FROM tblsubjectcombination WHERE ClassId=? AND SubjectId=? AND status=1',[$class,$subject])->fetchColumn()) throw new DomainException('The subject must be actively offered in the selected class.');
}
// Caller owns the transaction; lock the class to serialize assignments, including all-year/term overlaps.
function academic_assign($db,$kind,$teacher,$class,$subject,$year,$term=null,$confirmed=[]) {
    require_once __DIR__.'/senior-school.php';
    if (!$db->inTransaction()) throw new LogicException('Assignment changes require a transaction.');
    academic_period($db,$year,$term); academic_class_lock($db,$class); academic_teacher($db,$teacher);
    $isSubject=$kind==='subject';
    if (!$isSubject && $kind!=='class') throw new DomainException('Invalid assignment type.');
    $table=$isSubject?'tblsubjectteacherassignments':'tblclassteacherassignments';
    if ($isSubject) {
        academic_offering($db,$class,$subject);
        if(senior_is_class($db,$class))senior_timetable_validate($db,$class,$subject,$year);
        $rows=academic_query($db,"SELECT a.*,u.FullName FROM $table a JOIN tblusers u ON u.id=a.TeacherId WHERE a.ClassId=? AND a.SubjectId=? AND a.AcademicYearId=? AND a.Status=1 AND (a.TermId IS NULL OR ? IS NULL OR a.TermId=?) FOR UPDATE",[$class,$subject,$year,$term,$term])->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $rows=academic_query($db,"SELECT a.*,u.FullName FROM $table a JOIN tblusers u ON u.id=a.TeacherId WHERE a.AcademicYearId=? AND a.Status=1 AND (a.ClassId=? OR a.TeacherId=?) FOR UPDATE",[$year,$class,$teacher])->fetchAll(PDO::FETCH_ASSOC);
    }
    foreach ($rows as $r) {
        if ((int)$r['TeacherId']===$teacher && (int)$r['ClassId']===$class && (!$isSubject || (int)$r['TermId']===(int)$term)) throw new DomainException('This teacher already has that assignment.');
        if (!in_array($kind.':'.$r['id'], $confirmed,true)) {
            $label=academic_query($db,'SELECT CONCAT(ClassName," ",Section) FROM tblclasses WHERE id=?',[$r['ClassId']])->fetchColumn();
            $subjectLabel=$isSubject?academic_query($db,'SELECT SubjectName FROM tblsubjects WHERE id=?',[$subject])->fetchColumn():'Class teacher';
            throw new AcademicConflict($subjectLabel.' for '.$label.' is currently assigned to '.$r['FullName'].'. Replace this assignment?', $kind.':'.$r['id']);
        }
    }
    foreach ($rows as $r) academic_query($db,"UPDATE $table SET Status=0,EndedAt=CURRENT_TIMESTAMP WHERE id=?",[$r['id']]);
    if ($isSubject) academic_query($db,"INSERT INTO $table (TeacherId,ClassId,SubjectId,AcademicYearId,TermId,AssignedBy) VALUES(?,?,?,?,?,?)",[$teacher,$class,$subject,$year,$term,'dean:'.($_SESSION['alogin']??'')]);
    else academic_query($db,"INSERT INTO $table (TeacherId,ClassId,AcademicYearId) VALUES(?,?,?)",[$teacher,$class,$year]);
    $assignmentId=(int)$db->lastInsertId();
    if($isSubject && senior_is_class($db,$class))senior_notify_teacher_assignment($db,$assignmentId);
    return $assignmentId;
}
class AcademicConflict extends DomainException {
    public $assignmentKey;
    function __construct($message,$key) { parent::__construct($message); $this->assignmentKey=$key; }
}
function academic_responsibility($db,$teacher,$type,$year,$start,$end,$notes) {
    if (!$db->inTransaction()) throw new LogicException('Responsibility changes require a transaction.');
    academic_period($db,$year); academic_teacher($db,$teacher);
    if (!academic_query($db,'SELECT id FROM tblresponsibilitytypes WHERE id=? AND Active=1',[$type])->fetchColumn()) throw new DomainException('Select an active responsibility type.');
    foreach ([$start,$end] as $date) if ($date && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date) || date('Y-m-d',strtotime($date))!==$date)) throw new DomainException('Enter valid responsibility dates.');
    if (!$start || ($end && $end<$start)) throw new DomainException('The end date cannot precede the start date.');
    academic_query($db,'INSERT INTO tblteacherresponsibilities (TeacherId,ResponsibilityTypeId,AcademicYearId,StartDate,EndDate,Notes) VALUES(?,?,?,?,?,?)',[$teacher,$type,$year,$start,$end?:null,$notes]);
}
function academic_students($db,$class,$subject,$year) {
    $sql='SELECT s.StudentId,s.StudentName,s.RollId FROM tblstudents s WHERE s.ClassId=? AND s.Status=1'; $p=[$class];
    if ($subject) { $sql.=' AND EXISTS (SELECT 1 FROM tblstudentsubjects ss WHERE ss.StudentId=s.StudentId AND ss.ClassId=s.ClassId AND ss.SubjectId=? AND ss.AcademicYearId=? AND ss.Status=1)'; $p[]=$subject; $p[]=$year; }
    return academic_query($db,$sql.' ORDER BY s.StudentName',$p)->fetchAll(PDO::FETCH_ASSOC);
}
function academic_register_student($db,$student,$year,$subjects) {
    if (!$db->inTransaction()) throw new LogicException('Student subject changes require a transaction.');
    require_once __DIR__.'/senior-school.php';
    if(senior_ready($db))senior_lock($db);
    academic_period($db,$year);
    $s=academic_query($db,'SELECT * FROM tblstudents WHERE StudentId=? AND Status=1 FOR UPDATE',[$student])->fetch(PDO::FETCH_ASSOC);
    if (!$s) throw new DomainException('Select an active student.');
    $subjects=array_map('intval',$subjects);
    if (count($subjects)!==count(array_unique($subjects))) throw new DomainException('Duplicate student subject.');
    senior_guard_registration($db,$student,$year,$subjects);
    foreach ($subjects as $subject) academic_offering($db,$s['ClassId'],$subject);
    academic_query($db,'UPDATE tblstudentsubjects SET Status=0 WHERE StudentId=? AND AcademicYearId=?',[$student,$year]);
    foreach ($subjects as $subject) academic_query($db,'INSERT INTO tblstudentsubjects(StudentId,SubjectId,ClassId,AcademicYearId) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE Status=1',[$student,$subject,$s['ClassId'],$year]);
}
function academic_creation_assignments($db,$teacher,$post) {
    $year=(int)($post['academic_year']??0); $term=(int)($post['academic_term']??0)?:null;
    $seen=[];
    if (!empty($post['class_role']) && empty($post['academic_class'])) throw new DomainException('Select the class for the Class Teacher role.');
    if (!empty($post['subject_role'])) foreach (($post['assignment_class']??[]) as $i=>$class) {
        $subject=(int)($post['assignment_subject'][$i]??0); $class=(int)$class;
        if (!$class && !$subject) continue;
        $key=$class.':'.$subject;
        if (isset($seen[$key])) throw new DomainException('Duplicate class and subject in the assignment form.');
        $seen[$key]=true;
        academic_assign($db,'subject',(int)$teacher,$class,$subject,$year,$term,$post['confirmed_assignments']??[]);
    }
    if (!empty($post['subject_role']) && !$seen) throw new DomainException('Add at least one teaching assignment for the Subject Teacher role.');
    if (!empty($post['class_role']) && !empty($post['academic_class'])) academic_assign($db,'class',(int)$teacher,(int)$post['academic_class'],0,$year,null,$post['confirmed_assignments']??[]);
    foreach (($post['responsibility_types']??[]) as $type) academic_responsibility($db,(int)$teacher,(int)$type,$year,date('Y-m-d'),null,'');
}
function academic_h($text) { return htmlspecialchars((string)$text,ENT_QUOTES,'UTF-8'); }
