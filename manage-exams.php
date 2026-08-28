<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/csrf.php');
include('includes/audit.php');

if(strlen($_SESSION['alogin'])=="") {
    header("Location: index.php");
} else {
    if(isset($_POST['submit'])) {
        csrf_require_valid($_POST['csrf_token'] ?? '');
        $academicYear = trim($_POST['academicyear']);
        $termName = trim($_POST['termname']);
        $examName = trim($_POST['examname']);
        $classId = $_POST['class'] === '' ? null : $_POST['class'];
        $status = $_POST['status'];
        $startDate = $_POST['startdate'] ?: null;
        $endDate = $_POST['enddate'] ?: null;
        $marksOpenDate = $_POST['marksopendate'] ?: null;
        $marksDeadline = $_POST['marksdeadline'] ?: null;

        if($academicYear === '' || $termName === '' || $examName === '') {
            $error = "Academic year, term, and exam name are required.";
        } else {
            try {
                $dbh->beginTransaction();

                $yearSql = "INSERT IGNORE INTO tblacademicyears(AcademicYear, IsActive) VALUES(:academicyear, 1)";
                $yearQuery = $dbh->prepare($yearSql);
                $yearQuery->bindParam(':academicyear', $academicYear, PDO::PARAM_STR);
                $yearQuery->execute();

                $yearSelect = $dbh->prepare("SELECT id FROM tblacademicyears WHERE AcademicYear = :academicyear LIMIT 1");
                $yearSelect->bindParam(':academicyear', $academicYear, PDO::PARAM_STR);
                $yearSelect->execute();
                $year = $yearSelect->fetch(PDO::FETCH_OBJ);

                $termSql = "INSERT IGNORE INTO tblterms(AcademicYearId, TermName, IsActive) VALUES(:yearid, :termname, 1)";
                $termQuery = $dbh->prepare($termSql);
                $termQuery->bindParam(':yearid', $year->id, PDO::PARAM_STR);
                $termQuery->bindParam(':termname', $termName, PDO::PARAM_STR);
                $termQuery->execute();

                $termSelect = $dbh->prepare("SELECT id FROM tblterms WHERE AcademicYearId = :yearid AND TermName = :termname LIMIT 1");
                $termSelect->bindParam(':yearid', $year->id, PDO::PARAM_STR);
                $termSelect->bindParam(':termname', $termName, PDO::PARAM_STR);
                $termSelect->execute();
                $term = $termSelect->fetch(PDO::FETCH_OBJ);

                $examSql = "INSERT INTO tblexams(AcademicYearId, TermId, ExamName, ClassId, StartDate, EndDate, MarksOpenDate, MarksDeadline, Status)
                            VALUES(:yearid, :termid, :examname, :classid, :startdate, :enddate, :marksopendate, :marksdeadline, :status)";
                $examQuery = $dbh->prepare($examSql);
                $examQuery->bindParam(':yearid', $year->id, PDO::PARAM_STR);
                $examQuery->bindParam(':termid', $term->id, PDO::PARAM_STR);
                $examQuery->bindParam(':examname', $examName, PDO::PARAM_STR);
                if($classId === null) {
                    $examQuery->bindValue(':classid', null, PDO::PARAM_NULL);
                } else {
                    $examQuery->bindParam(':classid', $classId, PDO::PARAM_STR);
                }
                $examQuery->bindParam(':startdate', $startDate, PDO::PARAM_STR);
                $examQuery->bindParam(':enddate', $endDate, PDO::PARAM_STR);
                $examQuery->bindParam(':marksopendate', $marksOpenDate, PDO::PARAM_STR);
                $examQuery->bindParam(':marksdeadline', $marksDeadline, PDO::PARAM_STR);
                $examQuery->bindParam(':status', $status, PDO::PARAM_STR);
                $examQuery->execute();
                audit_log($dbh, 'exam_created', 'tblexams', $dbh->lastInsertId(), $academicYear . ' - ' . $termName . ' - ' . $examName);

                $dbh->commit();
                $msg = "Exam created successfully";
            } catch(Exception $e) {
                if($dbh->inTransaction()) {
                    $dbh->rollBack();
                }
                $error = "Exam could not be created. Please check if it already exists.";
            }
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
                                                <div class="form-group">
                                                    <label>Academic Year</label>
                                                    <input type="text" name="academicyear" class="form-control" placeholder="2026" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Term</label>
                                                    <input type="text" name="termname" class="form-control" placeholder="Term 1" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Exam Name</label>
                                                    <input type="text" name="examname" class="form-control" placeholder="Mid Term Exam" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Class</label>
                                                    <select name="class" class="form-control">
                                                        <option value="">All Classes</option>
                                                        <?php
                                                        $classSql = "SELECT id, ClassName, Section FROM tblclasses ORDER BY ClassNameNumeric, Section";
                                                        $classQuery = $dbh->prepare($classSql);
                                                        $classQuery->execute();
                                                        $classes = $classQuery->fetchAll(PDO::FETCH_OBJ);
                                                        foreach($classes as $class) { ?>
                                                        <option value="<?php echo htmlentities($class->id); ?>">
                                                            <?php echo htmlentities($class->ClassName); ?> Section-<?php echo htmlentities($class->Section); ?>
                                                        </option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label>Exam Start Date</label>
                                                    <input type="date" name="startdate" class="form-control">
                                                </div>
                                                <div class="form-group">
                                                    <label>Exam End Date</label>
                                                    <input type="date" name="enddate" class="form-control">
                                                </div>
                                                <div class="form-group">
                                                    <label>Marks Entry Opens</label>
                                                    <input type="date" name="marksopendate" class="form-control">
                                                </div>
                                                <div class="form-group">
                                                    <label>Marks Entry Deadline</label>
                                                    <input type="date" name="marksdeadline" class="form-control">
                                                </div>
                                                <div class="form-group">
                                                    <label>Status</label>
                                                    <select name="status" class="form-control" required>
                                                        <option value="draft">Draft</option>
                                                        <option value="marks_entry">Marks Entry</option>
                                                        <option value="submitted">Submitted</option>
                                                        <option value="under_review">Under Review</option>
                                                        <option value="approved">Approved</option>
                                                        <option value="published">Published</option>
                                                        <option value="archived">Archived</option>
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

    <script src="js/jquery/jquery-2.2.4.min.js"></script>
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
<?php } ?>
