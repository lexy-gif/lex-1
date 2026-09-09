<?php
require_once __DIR__.'/cbe-learning.php';

function result_id($value) {
    if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string)$value)) {
        throw new DomainException('Select valid result records.');
    }
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if ($id === false) throw new DomainException('Select valid result records.');
    return $id;
}

// Caller owns the transaction. The student lock is shared with registration and pathway saves.
function result_entry_context($db, $class, $student, $exam) {
    $class = result_id($class); $student = result_id($student); $exam = result_id($exam);
    $period = cbe_exam_writable($db, $exam);
    academic_period($db, $period['AcademicYearId'], $period['TermId']);
    if ($period['ClassId'] && (int)$period['ClassId'] !== $class) {
        throw new DomainException('The selected exam does not belong to this class.');
    }
    $lock = $db->inTransaction() ? ' FOR UPDATE' : '';
    if (!academic_query($db, 'SELECT StudentId FROM tblstudents WHERE StudentId=? AND ClassId=? AND Status=1'.$lock, [$student,$class])->fetchColumn()) {
        throw new DomainException('Select an active student in this class.');
    }
    $rows = academic_query($db, 'SELECT s.id,s.SubjectName FROM tblstudentsubjects ss
        JOIN tblsubjects s ON s.id=ss.SubjectId AND s.Status=1
        JOIN tblsubjectcombination sc ON sc.ClassId=ss.ClassId AND sc.SubjectId=ss.SubjectId AND sc.status=1
        WHERE ss.StudentId=? AND ss.ClassId=? AND ss.AcademicYearId=? AND ss.Status=1
        ORDER BY s.SubjectName,s.id'.$lock, [$student,$class,$period['AcademicYearId']])->fetchAll(PDO::FETCH_ASSOC);
    $subjects = [];
    foreach ($rows as $row) $subjects[(int)$row['id']] = $row;
    return ['exam'=>$period, 'subjects'=>$subjects];
}

function result_create($db, $post) {
    if (!$db->inTransaction()) throw new LogicException('Result changes require a transaction.');
    $class = result_id($post['class']??null); $student = result_id($post['studentid']??null); $exam = result_id($post['examid']??null);
    $context = result_entry_context($db, $class, $student, $exam);
    if (!$context['subjects']) throw new DomainException('No active subject registrations for this learner in the exam year. Register subjects before entering marks.');
    $marks = $post['marks']??null;
    if (!is_array($marks) || !$marks) throw new DomainException('Enter marks for the registered subjects.');
    $values = [];
    foreach ($marks as $subject=>$mark) $values[result_id($subject)] = cbe_number($mark,0,100);
    $expected = array_keys($context['subjects']); $submitted = array_keys($values);
    sort($expected); sort($submitted);
    if ($expected !== $submitted) throw new DomainException('Subject registrations changed or invalid subjects were submitted. Reload the registered subjects and check the marks.');
    if (academic_query($db, 'SELECT id FROM tblresult WHERE StudentId=? AND ExamId=? LIMIT 1 FOR UPDATE', [$student,$exam])->fetchColumn()) {
        throw new DomainException('Result already declared for this student and exam.');
    }
    foreach ($values as $subject=>$mark) {
        academic_query($db, 'INSERT INTO tblresult(StudentId,ClassId,ExamId,SubjectId,marks) VALUES(?,?,?,?,?)', [$student,$class,$exam,$subject,$mark]);
    }
    cbe_audit($db,'exam_results_created','tblstudents',$student,null,['ExamId'=>$exam,'ClassId'=>$class,'marks'=>$values]);
    return $context['exam'];
}

function result_update($db, $student, $exam, $ids, $marks) {
    if (!$db->inTransaction()) throw new LogicException('Result changes require a transaction.');
    $student = result_id($student); $exam = result_id($exam);
    cbe_exam_writable($db,$exam);
    if (!is_array($ids) || !array_is_list($ids) || !$ids || !is_array($marks) || !array_is_list($marks) || count($ids)!==count($marks)) {
        throw new DomainException('Select valid result rows and marks.');
    }
    $ids = array_map('result_id',$ids);
    if (count($ids)!==count(array_unique($ids))) throw new DomainException('Duplicate or invalid selection.');
    $contexts = [];
    foreach ($ids as $index=>$id) {
        $row = cbe_one($db,'SELECT * FROM tblresult WHERE id=? AND StudentId=? AND ExamId=? FOR UPDATE',[$id,$student,$exam]);
        if (!$row) throw new DomainException('A result row does not belong to this student and examination.');
        $class = (int)$row['ClassId'];
        if (!isset($contexts[$class])) $contexts[$class] = result_entry_context($db,$class,$student,$exam);
        if (!isset($contexts[$class]['subjects'][(int)$row['SubjectId']])) {
            throw new DomainException('This result subject is not actively registered for the learner in the exam year. Restore its registration before editing.');
        }
        $mark = cbe_number($marks[$index],0,100);
        academic_query($db,'UPDATE tblresult SET marks=? WHERE id=?',[$mark,$id]);
        cbe_audit($db,'exam_result_updated','tblresult',$id,['marks'=>$row['marks']],['marks'=>$mark]);
    }
}

function result_subject_fields($subjects, $marks=[]) {
    foreach ($subjects as $subject) {
        $id = (int)$subject['id'];
        $value = is_scalar($marks[$id]??null) ? (string)$marks[$id] : '';
        echo '<p><label for="subject-mark-'.$id.'">'.academic_h($subject['SubjectName']).'</label>';
        echo '<input type="number" id="subject-mark-'.$id.'" name="marks['.$id.']" value="'.academic_h($value).'" min="0" max="100" step="any" class="form-control" required placeholder="Enter marks out of 100" autocomplete="off"></p>';
    }
}
