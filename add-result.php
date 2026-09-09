<?php
session_start();
include('includes/config.php');
include('includes/csrf.php');
include('includes/sms.php');
require_once 'includes/dean-auth.php';
require_once 'includes/exam-results.php';
require_dean();
$msg=$error='';
$formValue=static fn($key)=>is_scalar($_POST[$key]??null)?(string)$_POST[$key]:'';
    if(isset($_POST['submit'])) {
        csrf_require_valid($_POST['csrf_token'] ?? '');
        try {
        $dbh->beginTransaction();
        $exam = result_create($dbh,$_POST);
        $class = result_id($_POST['class']);
        $examid = result_id($_POST['examid']);
        $studentid = result_id($_POST['studentid']);

        $examStmt = $dbh->prepare("SELECT e.ExamName, e.Status, e.ClassId, ay.AcademicYear, t.TermName
                                   FROM tblexams e
                                   JOIN tblacademicyears ay ON ay.id = e.AcademicYearId
                                   JOIN tblterms t ON t.id = e.TermId
                                   WHERE e.id = :examid
                                   LIMIT 1");
        $examStmt->bindParam(':examid', $examid, PDO::PARAM_STR);
        $examStmt->execute();
        $exam = $examStmt->fetch(PDO::FETCH_ASSOC);

            $examName = $exam['AcademicYear'] . " - " . $exam['TermName'] . " - " . $exam['ExamName'];
            $dbh->commit();
            // Calculate total marks for each student in the same class
            $sql_rank = "SELECT StudentId, SUM(marks) AS totalMarks 
                         FROM tblresult 
                         WHERE ClassId = :class AND ExamId = :examid
                         GROUP BY StudentId 
                         ORDER BY totalMarks DESC";
            $query_rank = $dbh->prepare($sql_rank);
            $query_rank->bindParam(':class', $class, PDO::PARAM_STR);
            $query_rank->bindParam(':examid', $examid, PDO::PARAM_STR);
            $query_rank->execute();
            $results_rank = $query_rank->fetchAll(PDO::FETCH_ASSOC);

            $rank = 0;
            $studentRank = 0;
            $totalStudents = count($results_rank);

            foreach($results_rank as $r) {
                $rank++;
                if($r['StudentId'] == $studentid) {
                    $studentRank = $rank;
                    $studentTotal = $r['totalMarks'];
                    break;
                }
            }

            $msg = "Result info added successfully for <strong>" . htmlentities($examName) . "</strong>. Student scored <strong>$studentTotal</strong> marks and is ranked <strong>$studentRank</strong> out of <strong>$totalStudents</strong> students in the class.";

            $studentSql = "SELECT StudentName, ParentPhone FROM tblstudents WHERE StudentId = :studentid LIMIT 1";
            $studentQuery = $dbh->prepare($studentSql);
            $studentQuery->bindParam(':studentid', $studentid, PDO::PARAM_STR);
            $studentQuery->execute();
            $student = $studentQuery->fetch(PDO::FETCH_ASSOC);

            if($student && !empty($student['ParentPhone'])) {
                $smsText = "Hello Parent, " . $student['StudentName'] . "'s " . $examName . " results are ready. Total: " . $studentTotal . " marks. Rank: " . $studentRank . " out of " . $totalStudents . ". Please login to SRMS for full details.";
                $smsResult = send_africastalking_sms($student['ParentPhone'], $smsText);

                if(!empty($smsResult['success'])) {
                    $msg .= " SMS sent to parent.";
                } elseif(!empty($smsResult['skipped'])) {
                    $msg .= " SMS not sent: " . htmlentities($smsResult['message']);
                } else {
                    $msg .= " Result saved, but SMS failed: " . htmlentities($smsResult['message']);
                }
            } else {
                $msg .= " SMS not sent because this student has no parent phone number.";
            }
        } catch(Throwable $e) {
            if($dbh->inTransaction())$dbh->rollBack();
            $error=$e instanceof DomainException?$e->getMessage():'Could not save the result. Please try again.';
            if(!($e instanceof DomainException))error_log($e->getMessage());
        }
    }
$formSubjects=[];$subjectMessage='Select a class, exam and learner to load registered subjects.';
if($formValue('class')!=='' && $formValue('examid')!=='' && $formValue('studentid')!=='') {
    try {
        $context=result_entry_context($dbh,$formValue('class'),$formValue('studentid'),$formValue('examid'));
        $formSubjects=$context['subjects'];
        $subjectMessage=$formSubjects?'':'No active subject registrations for this learner in the exam year. Register subjects before entering marks.';
    } catch(DomainException $e) { $subjectMessage=$e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SMS Admin | Add Result</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" href="css/animate-css/animate.min.css" media="screen">
    <link rel="stylesheet" href="css/lobipanel/lobipanel.min.css" media="screen">
    <link rel="stylesheet" href="css/prism/prism.css" media="screen">
    <link rel="stylesheet" href="css/select2/select2.min.css">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <link rel="stylesheet" href="css/custom.css" media="screen">
    <script src="js/modernizr/modernizr.min.js"></script>
</head>

<body class="top-navbar-fixed">
    <div class="main-wrapper">
        <?php include('includes/topbar.php');?>
        <div class="content-wrapper">
            <div class="content-container">
                <?php include('includes/leftbar.php');?>
                <div class="main-page">
                    <div class="container-fluid">
                        <div class="row page-title-div">
                            <div class="col-md-6">
                                <h2 class="title">Declare Result</h2>
                            </div>
                        </div>
                        <div class="row breadcrumb-div">
                            <div class="col-md-6">
                                <ul class="breadcrumb">
                                    <li><a href="dashboard.php"><i class="fa fa-home"></i> Home</a></li>
                                    <li class="active">Student Result</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="panel">
                                    <div class="panel-body">
                                        <?php if($msg){?>
                                        <div class="alert alert-success left-icon-alert" role="alert">
                                            <strong>Well done!</strong> <?php echo $msg; ?>
                                        </div>
                                        <?php } else if($error){?>
                                        <div class="alert alert-danger left-icon-alert" role="alert">
                                            <strong>Oh snap!</strong> <?php echo htmlentities($error); ?>
                                        </div>
                                        <?php } ?>
                                        <form class="form-horizontal" method="post">
                                            <?php csrf_field(); ?>
                                            <div class="form-group">
                                                <label for="default" class="col-sm-2 control-label">Class</label>
                                                <div class="col-sm-10">
                                                    <select name="class" class="form-control clid" id="classid"
                                                        onChange="getStudent(this.value);" required="required">
                                                        <option value="">Select Class</option>
                                                        <?php 
                                                            $sql = "SELECT * from tblclasses";
                                                            $query = $dbh->prepare($sql);
                                                            $query->execute();
                                                            $results = $query->fetchAll(PDO::FETCH_OBJ);
                                                            if($query->rowCount() > 0) {
                                                                foreach($results as $result) { ?>
                                                        <option value="<?php echo htmlentities($result->id); ?>" <?= $formValue('class')===(string)$result->id?'selected':'' ?>>
                                                            <?php echo htmlentities($result->ClassName); ?>&nbsp;
                                                            Section-<?php echo htmlentities($result->Section); ?>
                                                        </option>
                                                        <?php }} ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="examid" class="col-sm-2 control-label">Exam</label>
                                                <div class="col-sm-10">
                                                    <select name="examid" class="form-control examid" id="examid"
                                                        required="required" onChange="getresult();">
                                                        <option value="">Select Exam</option>
                                                        <?php
                                                            $examSql = "SELECT e.id, e.ExamName, e.Status, e.ClassId, ay.AcademicYear, t.TermName, c.ClassName, c.Section
                                                                        FROM tblexams e
                                                                        JOIN tblacademicyears ay ON ay.id = e.AcademicYearId
                                                                        JOIN tblterms t ON t.id = e.TermId
                                                                        LEFT JOIN tblclasses c ON c.id = e.ClassId
                                                                        WHERE e.Status IN ('draft','marks_entry','submitted','under_review','approved','published')
                                                                        ORDER BY ay.AcademicYear DESC, t.id DESC, e.ExamName ASC";
                                                            $examQuery = $dbh->prepare($examSql);
                                                            $examQuery->execute();
                                                            $exams = $examQuery->fetchAll(PDO::FETCH_OBJ);
                                                            foreach($exams as $examRow) {
                                                                $classLabel = $examRow->ClassName ? " - " . $examRow->ClassName . " Section-" . $examRow->Section : " - All Classes";
                                                        ?>
                                                        <option value="<?php echo htmlentities($examRow->id); ?>" <?= $formValue('examid')===(string)$examRow->id?'selected':'' ?>>
                                                            <?php echo htmlentities($examRow->AcademicYear . " - " . $examRow->TermName . " - " . $examRow->ExamName . $classLabel); ?>
                                                        </option>
                                                        <?php } ?>
                                                    </select>
                                                    <span class="help-block">Marks use the learner's subjects registered for the exam's academic year. <a href="student-subjects.php">Manage student subjects</a>.</span>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="date" class="col-sm-2 control-label">Student Name</label>
                                                <div class="col-sm-10">
                                                    <select name="studentid" class="form-control stid" id="studentid"
                                                        required="required" onChange="getresult(this.value);">
                                                        <option value="">Select Student</option>
                                                        <?php foreach(academic_students($dbh,(int)$formValue('class'),0,0) as $learner) { ?>
                                                        <option value="<?= (int)$learner['StudentId'] ?>" <?= $formValue('studentid')===(string)$learner['StudentId']?'selected':'' ?>><?= academic_h($learner['StudentName']) ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-sm-10">
                                                    <div id="reslt"></div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="date" class="col-sm-2 control-label">Subjects</label>
                                                <div class="col-sm-10">
                                                    <div id="subject"><?php result_subject_fields($formSubjects,is_array($_POST['marks']??null)?$_POST['marks']:[]); ?><?php if($subjectMessage!=='') { ?><p class="help-block"><?= academic_h($subjectMessage) ?></p><?php } ?></div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="col-sm-offset-2 col-sm-10">
                                                    <button type="submit" name="submit" id="submit"
                                                        class="btn btn-primary" <?= !$formSubjects||$msg!==''?'disabled':'' ?>>Declare Result</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="js/jquery/jquery-2.2.4.min.js"></script>
    <script src="js/bootstrap/bootstrap.min.js"></script>
    <script src="js/pace/pace.min.js"></script>
    <script src="js/lobipanel/lobipanel.min.js"></script>
    <script src="js/iscroll/iscroll.js"></script>
    <script src="js/prism/prism.js"></script>
    <script src="js/select2/select2.min.js"></script>
    <script src="js/main.js"></script>
    <script src="js/result-entry.js"></script>
    <script>
    $(function($) {
        $(".js-states").select2();
        $(".js-states-limit").select2({
            maximumSelectionLength: 2
        });
        $(".js-states-hide").select2({
            minimumResultsForSearch: Infinity
        });
    });
    </script>
</body>

</html>
