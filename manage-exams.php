<?php
require_once 'includes/bootstrap.php';
$error=$msg='';
require_once 'includes/config.php';
require_once 'includes/csrf.php';
require_once 'includes/dean-auth.php';
require_once 'includes/academic-periods.php';
require_once 'includes/cbe-academics.php';
require_dean();
$msg=$error='';
$formValue=static fn($key)=>is_scalar($_POST[$key]??null)?(string)$_POST[$key]:'';
if(isset($_POST['submit'])) {
    csrf_require_valid($_POST['csrf_token']??'');
    try {
        $examName=academic_period_name($_POST['examname']??null,'Exam name',100);
        [$startDate,$endDate]=academic_period_dates($_POST['startdate']??null,$_POST['enddate']??null,'Exam');
        [$marksOpenDate,$marksDeadline]=academic_period_dates($_POST['marksopendate']??null,$_POST['marksdeadline']??null,'Marks entry');
        $classId=$_POST['class']??'';
        if($classId==='')$classId=null;
        else {
            $classId=filter_var($classId,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if(!$classId)throw new DomainException('Select a valid class.');
        }
        $status=$_POST['status']??'draft';
        if(!in_array($status,['draft','marks_entry'],true))throw new DomainException('Select a valid exam status.');
        $maximum=cbe_number($_POST['maximummarks']??100,0.01,999999);
        $dbh->beginTransaction();
        $period=academic_period_ensure($dbh,$_POST['academicyear']??null,$_POST['termname']??null);
        if($classId&&!academic_query($dbh,'SELECT id FROM tblclasses WHERE id=?',[$classId])->fetchColumn())throw new DomainException('Select a valid class.');
        // NULL class scopes also need an explicit duplicate check.
        if(academic_query($dbh,'SELECT id FROM tblexams WHERE AcademicYearId=? AND TermId=? AND ExamName=? AND ClassId <=> ?',[$period['AcademicYearId'],$period['TermId'],$examName,$classId])->fetchColumn())throw new DomainException('This exam already exists for the selected period and class.');
        academic_query($dbh,'INSERT INTO tblexams(AcademicYearId,TermId,ExamName,ClassId,StartDate,EndDate,MarksOpenDate,MarksDeadline,Status,MaximumMarks) VALUES(?,?,?,?,?,?,?,?,?,?)',[$period['AcademicYearId'],$period['TermId'],$examName,$classId,$startDate,$endDate,$marksOpenDate,$marksDeadline,$status,$maximum]);
        audit_log($dbh,'exam_created','tblexams',$dbh->lastInsertId(),json_encode($period+['ExamName'=>$examName],JSON_THROW_ON_ERROR));
        $dbh->commit();$msg='Exam created successfully';
    } catch(Throwable $e) {
        if($dbh->inTransaction())$dbh->rollBack();
        $error=$e instanceof DomainException?$e->getMessage():'Exam could not be created. Please try again.';
        error_log($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SMS Admin | Manage Exams</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" href="css/animate-css/animate.min.css" media="screen">
    <link rel="stylesheet" href="css/lobipanel/lobipanel.min.css" media="screen">
    <link rel="stylesheet" href="css/prism/prism.css" media="screen">
    <link rel="stylesheet" type="text/css" href="js/DataTables/datatables.min.css">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <link rel="stylesheet" href="css/custom.css">
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
                                <h2 class="title">Manage Exams</h2>
                            </div>
                        </div>
                        <div class="row breadcrumb-div">
                            <div class="col-md-6">
                                <ul class="breadcrumb">
                                    <li><a href="dashboard.php"><i class="fa fa-home"></i> Home</a></li>
                                    <li> Results</li>
                                    <li class="active">Manage Exams</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <section class="section">
                        <div class="container-fluid">
                            <div class="row">
                                <div class="col-md-5">
                                    <div class="panel">
                                        <div class="panel-heading">
                                            <div class="panel-title">
                                                <h5>Create Exam</h5>
                                            </div>
                                        </div>
                                        <div class="panel-body">
                                            <?php if($msg){?>
                                            <div class="alert alert-success left-icon-alert" role="alert">
                                                <strong>Well done!</strong> <?php echo htmlentities($msg); ?>
                                            </div>
                                            <?php } else if($error){?>
                                            <div class="alert alert-danger left-icon-alert" role="alert">
                                                <strong>Oh snap!</strong> <?php echo htmlentities($error); ?>
                                            </div>
                                            <?php } ?>
                                            <form method="post">
                                                <?php csrf_field(); ?>
                                                <p>New years and terms stay inactive. Set the active period in <a href="dean-academic-periods.php">Academic Years &amp; Terms</a>.</p>
                                                <div class="form-group">
                                                    <label>Academic Year</label>
                                                    <input type="text" name="academicyear" class="form-control" placeholder="2026" maxlength="20" value="<?= academic_h($formValue('academicyear')) ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Term</label>
                                                    <input type="text" name="termname" class="form-control" placeholder="Term 1" maxlength="50" value="<?= academic_h($formValue('termname')) ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Exam Name</label>
                                                    <input type="text" name="examname" class="form-control" placeholder="Mid Term Exam" maxlength="100" value="<?= academic_h($formValue('examname')) ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Class</label>
                                                    <select name="class" class="form-control">
                                                        <option value="">All Classes</option>
                                                        <?php
                                                        $classSql = "SELECT id, ClassName, Section FROM tblclasses WHERE ClassNameNumeric IN (10,11,12) ORDER BY ClassNameNumeric, Section";
                                                        $classQuery = $dbh->prepare($classSql);
                                                        $classQuery->execute();
                                                        $classes = $classQuery->fetchAll(PDO::FETCH_OBJ);
                                                        foreach($classes as $class) { ?>
                                                        <option value="<?php echo htmlentities($class->id); ?>" <?= $formValue('class')===(string)$class->id?'selected':'' ?>>
                                                            <?php echo htmlentities($class->ClassName); ?> Section-<?php echo htmlentities($class->Section); ?>
                                                        </option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label>Exam Start Date</label>
                                                    <input type="date" name="startdate" class="form-control" value="<?= academic_h($formValue('startdate')) ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label>Exam End Date</label>
                                                    <input type="date" name="enddate" class="form-control" value="<?= academic_h($formValue('enddate')) ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label>Marks Entry Opens</label>
                                                    <input type="date" name="marksopendate" class="form-control" value="<?= academic_h($formValue('marksopendate')) ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label>Marks Entry Deadline</label>
                                                    <input type="date" name="marksdeadline" class="form-control" value="<?= academic_h($formValue('marksdeadline')) ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label for="maximummarks">Maximum marks</label><input type="number" id="maximummarks" name="maximummarks" min="0.01" max="999999" step="0.01" value="100" class="form-control" required><label>Status</label>
                                                    <select name="status" class="form-control" required>
                                                        <option value="draft" <?= ($formValue('status')?:'draft')==='draft'?'selected':'' ?>>Draft</option>
                                                        <option value="marks_entry" <?= ($formValue('status')?:'draft')==='marks_entry'?'selected':'' ?>>Marks Entry</option>
                                                    </select>
                                                </div>
                                                <button type="submit" name="submit" class="btn btn-primary">Create Exam</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-7">
                                    <div class="panel">
                                        <div class="panel-heading">
                                            <div class="panel-title">
                                                <h5>Available Exams</h5>
                                            </div>
                                        </div>
                                        <div class="panel-body p-20">
                                            <table id="example" class="display table table-striped table-bordered" cellspacing="0" width="100%">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Academic Year</th>
                                                        <th>Term</th>
                                                        <th>Exam</th>
                                                        <th>Class</th>
                                                        <th>Deadline</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php
                                                $examSql = "SELECT e.ExamName, e.Status, e.MarksDeadline, ay.AcademicYear, t.TermName, c.ClassName, c.Section
                                                            FROM tblexams e
                                                            JOIN tblacademicyears ay ON ay.id = e.AcademicYearId
                                                            JOIN tblterms t ON t.id = e.TermId
                                                            LEFT JOIN tblclasses c ON c.id = e.ClassId
                                                            ORDER BY ay.AcademicYear DESC, t.id DESC, e.id DESC";
                                                $examQuery = $dbh->prepare($examSql);
                                                $examQuery->execute();
                                                $exams = $examQuery->fetchAll(PDO::FETCH_OBJ);
                                                $cnt = 1;
                                                foreach($exams as $exam) { ?>
                                                    <tr>
                                                        <td><?php echo htmlentities($cnt); ?></td>
                                                        <td><?php echo htmlentities($exam->AcademicYear); ?></td>
                                                        <td><?php echo htmlentities($exam->TermName); ?></td>
                                                        <td><?php echo htmlentities($exam->ExamName); ?></td>
                                                        <td><?php echo $exam->ClassName ? htmlentities($exam->ClassName . " (" . $exam->Section . ")") : "All Classes"; ?></td>
                                                        <td><?php echo htmlentities($exam->MarksDeadline); ?></td>
                                                        <td><?php echo htmlentities(ucfirst(str_replace('_', ' ', $exam->Status))); ?></td>
                                                    </tr>
                                                <?php $cnt++; } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>

    <script src="js/jquery/jquery-3.7.1.min.js"></script>
    <script src="js/bootstrap/bootstrap.min.js"></script>
    <script src="js/pace/pace.min.js"></script>
    <script src="js/lobipanel/lobipanel.min.js"></script>
    <script src="js/iscroll/iscroll.js"></script>
    <script src="js/prism/prism.js"></script>
    <script src="js/DataTables/datatables.min.js"></script>
    <script src="js/main.js"></script>
    <script>
    $(function($) {
        $('#example').DataTable();
    });
    </script>
</body>
</html>
