<?php
session_start();
error_reporting(0);
include('includes/config.php'); // must provide $dbh PDO

// Ensure request came via POST with required fields
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['rollid']) || empty($_POST['class'])) {
    // redirect back to index (or show a message)
    header("Location: index.php");
    exit;
}

$rollid = trim($_POST['rollid']);
$classid = trim($_POST['class']);

$_SESSION['rollid'] = $rollid;
$_SESSION['classid'] = $classid;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Result Management System</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" href="css/animate-css/animate.min.css" media="screen">
    <link rel="stylesheet" href="css/lobipanel/lobipanel.min.css" media="screen">
    <link rel="stylesheet" href="css/prism/prism.css" media="screen">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <script src="js/modernizr/modernizr.min.js"></script>
    <style>
        /* small cosmetic tweak to keep the print icon clickable area */
        .print-icon { cursor: pointer; }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <nav class="navbar navbar-inverse">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" href="#">Student Result Management System</a>
                </div>
                <ul class="nav navbar-nav navbar-right">
                    <li><a href="index.php"><i class="fa fa-home"></i> Home</a></li>
                </ul>
            </div>
        </nav>

        <div class="content-wrapper">
            <div class="content-container">
                <div class="main-page">
                    <div class="container-fluid">
                        <div class="row page-title-div">
                            <div class="col-md-12">
                                <h2 class="title text-center">Result Management System</h2>
                            </div>
                        </div>
                    </div>

                    <section class="section" id="exampl">
                        <div class="container-fluid">
                            <div class="row">
                                <div class="col-md-8 col-md-offset-2">
                                    <div class="panel">
                                        <div class="panel-heading">
                                            <div class="panel-title text-center">
                                                <h3>Student Result Details</h3>
                                                <hr />
<?php
// Fetch student info (with class)
try {
    $qery = "SELECT s.StudentName, s.RollId, s.RegDate, s.StudentId,
                    s.Status, c.ClassName, c.Section
             FROM tblstudents AS s
             JOIN tblclasses AS c ON c.id = s.ClassId
             WHERE s.RollId = :rollid AND s.ClassId = :classid
             LIMIT 1";
    $stmt = $dbh->prepare($qery);
    $stmt->bindParam(':rollid', $rollid, PDO::PARAM_STR);
    $stmt->bindParam(':classid', $classid, PDO::PARAM_STR);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_OBJ);
} catch (Exception $e) {
    $student = false;
}
?>

<?php if ($student): ?>
                                                <p><b>Student Name:</b> <?php echo htmlentities($student->StudentName); ?></p>
                                                <p><b>Student Registration ID:</b> <?php echo htmlentities($student->RollId); ?></p>
                                                <p><b>Student Class:</b> <?php echo htmlentities($student->ClassName); ?> (<?php echo htmlentities($student->Section); ?>)</p>
                                            </div>
                                            <div class="panel-body p-20">
                                                <div class="panel panel-info">
                                                    <div class="panel-heading text-center">
                                                        <h3 class="panel-title"><i class="fa fa-user-circle"></i>
                                                            Welcome, <?php echo htmlentities($student->StudentName); ?>!
                                                        </h3>
                                                    </div>
                                                    <div class="panel-body text-center">
                                                        <p>🎓 Hello <b><?php echo htmlentities($student->StudentName); ?></b>, welcome to your <b>Student Result Management Portal</b>.</p>
                                                        <p>Here’s a quick overview of your academic performance below.</p>
                                                    </div>
                                                </div>

                                                <table class="table table-hover table-bordered" border="1" width="100%">
                                                    <thead>
                                                        <tr style="text-align: center">
                                                            <th style="text-align: center">#</th>
                                                            <th style="text-align: center">Subject</th>
                                                            <th style="text-align: center">Marks</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
<?php
// Fetch subject-wise marks for this student (tblresult is used per your confirmation)
try {
    $query = "SELECT sub.SubjectName, r.marks
              FROM tblresult AS r
              JOIN tblsubjects AS sub ON sub.id = r.SubjectId
              JOIN tblstudents AS s ON s.StudentId = r.StudentId
              WHERE s.RollId = :rollid AND r.ClassId = :classid
              ORDER BY sub.SubjectName ASC";
    $stmt2 = $dbh->prepare($query);
    $stmt2->bindParam(':rollid', $rollid, PDO::PARAM_STR);
    $stmt2->bindParam(':classid', $classid, PDO::PARAM_STR);
    $stmt2->execute();
    $results = $stmt2->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    $results = [];
}

$cnt = 1;
$totlcount = 0;
$subjectCount = count($results);

if ($subjectCount > 0):
    foreach ($results as $result):
        $marks = (int)$result->marks;
        $totlcount += $marks;
?>
                                                        <tr>
                                                            <th scope="row" style="text-align: center"><?php echo $cnt; ?></th>
                                                            <td style="text-align: center"><?php echo htmlentities($result->SubjectName); ?></td>
                                                            <td style="text-align: center"><?php echo htmlentities($marks); ?></td>
                                                        </tr>
<?php
        $cnt++;
    endforeach;

    // compute out of and percentage safely (avoid division by zero)
    $outof = $subjectCount * 100;
    $percentage = ($outof > 0) ? round(($totlcount * 100) / $outof, 2) : 0;

    // RANKING: compute student's DB id and rank within class using tblresult sums
    try {
        $studentIdQuery = "SELECT StudentId FROM tblstudents WHERE RollId = :rollid AND ClassId = :classid LIMIT 1";
        $studentStmt = $dbh->prepare($studentIdQuery);
        $studentStmt->bindParam(':rollid', $rollid, PDO::PARAM_STR);
        $studentStmt->bindParam(':classid', $classid, PDO::PARAM_STR);
        $studentStmt->execute();
        $studentData = $studentStmt->fetch(PDO::FETCH_OBJ);
        $studentId = $studentData ? $studentData->StudentId : null;

        $rankQuery = "SELECT r.StudentId, SUM(r.marks) AS totalMarks
                      FROM tblresult r
                      WHERE r.ClassId = :classid
                      GROUP BY r.StudentId
                      ORDER BY totalMarks DESC";
        $rankStmt = $dbh->prepare($rankQuery);
        $rankStmt->bindParam(':classid', $classid, PDO::PARAM_STR);
        $rankStmt->execute();
        $rankResults = $rankStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $rankResults = [];
        $studentId = null;
    }

    $rank = 0;
    $studentRank = 0;
    $totalStudents = count($rankResults);

    foreach ($rankResults as $r) {
        $rank++;
        if ((string)$r['StudentId'] === (string)$studentId) {
            $studentRank = $rank;
            break;
        }
    }
?>
                                                        <tr>
                                                            <th scope="row" colspan="2" style="text-align: center">Total Marks</th>
                                                            <td style="text-align: center"><b><?php echo htmlentities($totlcount); ?></b> out of <b><?php echo htmlentities($outof); ?></b></td>
                                                        </tr>

                                                        <tr>
                                                            <th scope="row" colspan="2" style="text-align: center">Percentage</th>
                                                            <td style="text-align: center"><b><?php echo $percentage; ?>%</b></td>
                                                        </tr>

                                                        <tr>
                                                            <th scope="row" colspan="2" style="text-align: center">Class Position</th>
                                                            <td style="text-align: center"><b><?php echo $studentRank ?: 'N/A'; ?></b> out of <b><?php echo $totalStudents ?: 'N/A'; ?></b></td>
                                                        </tr>
                                                    </tbody>
                                                </table>

                                                <div class="panel panel-success">
                                                    <div class="panel-heading text-center">
                                                        <h4><i class="fa fa-line-chart"></i> Performance Summary</h4>
                                                    </div>
                                                    <div class="panel-body text-center">
                                                        <div class="row">
                                                            <div class="col-md-4">
                                                                <h5><i class="fa fa-book"></i> Total Subjects</h5>
                                                                <p><b><?php echo htmlentities($subjectCount); ?></b></p>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <h5><i class="fa fa-calculator"></i> Total Marks</h5>
                                                                <p><b><?php echo htmlentities($totlcount); ?></b> / <?php echo htmlentities($outof); ?></p>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <h5><i class="fa fa-percent"></i> Percentage</h5>
                                                                <p><b><?php echo $percentage; ?>%</b></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="alert alert-success text-center" role="alert">
                                                    <i class="fa fa-trophy"></i>
                                                    <strong>Your Position:</strong>
                                                    <?php echo $studentRank ? htmlentities($studentRank) . " / " . htmlentities($totalStudents) : 'N/A'; ?>
                                                    <br>
                                                    <i class="fa fa-lightbulb-o"></i>
                                                    <strong>Keep going, <?php echo htmlentities($student->StudentName); ?>!</strong>
                                                </div>

                                                <div class="text-center" style="padding:10px;">
                                                    <i class="fa fa-print fa-2x print-icon" aria-hidden="true" title="Print" onclick="CallPrint()"></i>
                                                </div>

<?php else: // no subject rows found ?>
                                                        </tbody>
                                                </table>

                                                <div class="alert alert-warning left-icon-alert" role="alert">
                                                    <strong>Notice!</strong> Your result has not been declared yet.
                                                </div>
<?php endif; // subjectCount > 0 ?>

<?php else: // student not found ?>
                                            </div>
                                            <div class="panel-body p-20">
                                                <div class="alert alert-danger left-icon-alert" role="alert">
                                                    <strong>Oh snap!</strong> Invalid Roll Id or class. Please check your details and try again.
                                                </div>
<?php endif; // student exists ?>
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
    <script src="js/main.js"></script>
    <script>
        function CallPrint() {
            var prtContent = document.getElementById("exampl");
            var WinPrint = window.open('', '', 'left=0,top=0,width=900,height=900,toolbar=0,scrollbars=1,status=0');
            WinPrint.document.write('<html><head><title>Print Result</title>');
            // Optionally copy bootstrap CSS links for print styling:
            WinPrint.document.write('<link rel="stylesheet" href="css/bootstrap.min.css">');
            WinPrint.document.write('</head><body>');
            WinPrint.document.write(prtContent.innerHTML);
            WinPrint.document.write('</body></html>');
            WinPrint.document.close();
            WinPrint.focus();
            WinPrint.print();
            WinPrint.close();
        }
    </script>
</body>

</html>
