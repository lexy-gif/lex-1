<?php
session_start();
require_once 'includes/dean-auth.php';
require_dean();
require_once 'includes/config.php';
require_once 'includes/exam-results.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (isset($_POST['classid'])) {
        $students=academic_students($dbh,result_id($_POST['classid']),0,0);
        echo json_encode(['students'=>$students],JSON_THROW_ON_ERROR);
    } else {
        $class=result_id($_POST['class']??null);
        $student=result_id($_POST['studentid']??null);
        $exam=result_id($_POST['examid']??null);
        $context=result_entry_context($dbh,$class,$student,$exam);
        $duplicate=(bool)academic_query($dbh,'SELECT id FROM tblresult WHERE StudentId=? AND ExamId=? LIMIT 1',[$student,$exam])->fetchColumn();
        $message=$duplicate?'Result already declared for this student and exam.':(!$context['subjects']?'No active subject registrations for this learner in the exam year. Register subjects before entering marks.':'');
        ob_start();result_subject_fields($context['subjects']);$fields=ob_get_clean();
        echo json_encode(['fields'=>$fields,'message'=>$message,'canSubmit'=>!$duplicate && (bool)$context['subjects']],JSON_THROW_ON_ERROR);
    }
} catch(Throwable $e) {
    http_response_code($e instanceof DomainException?422:500);
    if(!($e instanceof DomainException))error_log($e->getMessage());
    echo json_encode(['message'=>$e instanceof DomainException?$e->getMessage():'Could not load result entry. Please try again.']);
}
