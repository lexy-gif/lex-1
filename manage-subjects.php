<?php require_once 'includes/bootstrap.php';
$error=$msg='';

require_once 'includes/config.php';
require_once 'includes/management-list.php';
require_once 'includes/csrf.php';
if(empty($_SESSION['alogin']))
{   header("Location: index.php");
}else{

//Code for Deletion
if(isset($_POST['id']))
{
csrf_require_valid($_POST['csrf_token'] ?? '');
$subid=$_POST['id'];
$sql="delete from tblsubjects where id = :subid";
$query = $dbh->prepare($sql);
$query->bindParam(':subid',$subid,PDO::PARAM_STR);
try {
$query->execute();
echo '<script>alert("Data deleted.")</script>';
echo "<script>window.location.href ='manage-subjects.php'</script>";
} catch(PDOException $e) { $error='This subject has related school records or assignment history and cannot be deleted.'; }
}

$sql="SELECT * from tblsubjects";
$query=management_list_query($dbh,$sql,['SubjectName','SubjectCode'],'SubjectName');
$query->execute();
$allListRows=$query->fetchAll(PDO::FETCH_OBJ);
$GLOBALS['management_list_has_next']=count($allListRows)>25;
$results=array_slice($allListRows,0,25);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
    	<meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Admin Manage Subjects</title>
        <link rel="stylesheet" href="css/bootstrap.min.css" media="screen" >
        <link rel="stylesheet" href="css/font-awesome.min.css" media="screen" >
        <link rel="stylesheet" href="css/animate-css/animate.min.css" media="screen" >
        <link rel="stylesheet" href="css/lobipanel/lobipanel.min.css" media="screen" >
        <link rel="stylesheet" href="css/prism/prism.css" media="screen" > <!-- USED FOR DEMO HELP - YOU CAN REMOVE IT -->
        <link rel="stylesheet" type="text/css" href="js/DataTables/datatables.min.css"/>
        <link rel="stylesheet" href="css/main.css" media="screen" >
        <link rel="stylesheet" href="css/custom.css">
        <script src="js/modernizr/modernizr.min.js"></script>
    </head>
    <body class="top-navbar-fixed">
        <div class="main-wrapper">

            <!-- ========== TOP NAVBAR ========== -->
   <?php include('includes/topbar.php');?>
            <!-- ========== WRAPPER FOR BOTH SIDEBARS & MAIN CONTENT ========== -->
            <div class="content-wrapper">
                <div class="content-container">
<?php include('includes/leftbar.php');?>

                    <div class="main-page">
                        <div class="container-fluid">
                            <div class="row page-title-div">
                                <div class="col-md-6">
                                    <p><a class="btn btn-primary" href="dean-teacher-relationships.php?role=subject">Manage Subject Teachers and Students</a></p><h2 class="title">Manage Subjects</h2>

                                </div>

                                <!-- /.col-md-6 text-right -->
                            </div>
                            <!-- /.row -->
                            <div class="row breadcrumb-div">
                                <div class="col-md-6">
                                    <ul class="breadcrumb">
            							<li><a href="dashboard.php"><i class="fa fa-home"></i> Home</a></li>
                                        <li> Subjects</li>
            							<li class="active">Manage Subjects</li>
            						</ul>
                                </div>

                            </div>
                            <!-- /.row -->
                        </div>
                        <!-- /.container-fluid -->

                        <section class="section">
                            <div class="container-fluid">



                                <div class="row">
                                    <div class="col-md-12">

                                        <div class="panel">
                                            <div class="panel-heading">
                                                <div class="panel-title">
                                                    <h5>View Subjects Info</h5>
                                                </div>
                                            </div>
<?php if($msg){?>
<div class="alert alert-success left-icon-alert" role="alert">
 <strong>Well done!</strong><?php echo htmlentities($msg); ?>
 </div><?php }
else if($error){?>
    <div class="alert alert-danger left-icon-alert" role="alert">
                                            <strong>Oh snap!</strong> <?php echo htmlentities($error); ?>
                                        </div>
                                        <?php } ?>
                                            <div class="panel-body p-20">

                                                <?php management_list_controls(); ?><table id="example" class="display table table-striped table-bordered" cellspacing="0" width="100%">
                                                    <thead>
                                                        <tr>
                                                            <th>#</th>
                                                            <th>Subject Name</th>
                                                            <th>Subject Code</th>
                                                            <th>Creation Date</th>
                                                            <th>Updation Date</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tfoot>
                                                        <tr>
                                                          <th>#</th>
                                                             <th>Subject Name</th>
                                                            <th>Subject Code</th>
                                                            <th>Creation Date</th>
                                                            <th>Updation Date</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </tfoot>
                                                    <tbody>
<?php
$cnt=1;
if($query->rowCount() > 0)
{
foreach($results as $result)
{   ?>
<tr>
 <td><?php echo htmlentities($cnt);?></td>
                                                            <td><?php echo htmlentities($result->SubjectName);?><?php include 'includes/academic-subject-summary.php'; ?></td>
                                                            <td><?php echo htmlentities($result->SubjectCode);?></td>
                                                            <td><?php echo htmlentities($result->Creationdate);?></td>
                                                            <td><?php echo htmlentities($result->UpdationDate);?></td>
<td>
<a href="edit-subject.php?subjectid=<?php echo htmlentities($result->id);?>" class="btn btn-info btn-xs">Eidt  </a>
  <form method="post" class="form-inline-block"><?php csrf_field(); ?><input type="hidden" name="id" value="<?php echo $result->id; ?>"><input type="hidden" name="del" value="delete"><button class="btn btn-warning btn-xs" onclick="return confirm('Confirm delete?')">Delete</button></form>

</td>
</tr>
<?php $cnt=$cnt+1;}} ?>


                                                    </tbody>
                                                </table>


                                                <!-- /.col-md-12 -->
                                            </div>
                                        </div>
                                    </div>
                                    <!-- /.col-md-6 -->


                                                </div>
                                                <!-- /.col-md-12 -->
                                            </div>
                                        </div>
                                        <!-- /.panel -->
                                    </div>
                                    <!-- /.col-md-6 -->

                                </div>
                                <!-- /.row -->

                            </div>
                            <!-- /.container-fluid -->
                        </section>
                        <!-- /.section -->

                    </div>
                    <!-- /.main-page -->



                </div>
                <!-- /.content-container -->
            </div>
            <!-- /.content-wrapper -->

        </div>
        <!-- /.main-wrapper -->

        <!-- ========== COMMON JS FILES ========== -->
        <script src="js/jquery/jquery-3.7.1.min.js"></script>
        <script src="js/bootstrap/bootstrap.min.js"></script>
        <script src="js/pace/pace.min.js"></script>
        <script src="js/lobipanel/lobipanel.min.js"></script>
        <script src="js/iscroll/iscroll.js"></script>

        <!-- ========== PAGE JS FILES ========== -->
        <script src="js/prism/prism.js"></script>
        <script src="js/DataTables/datatables.min.js"></script>

        <!-- ========== THEME JS ========== -->
        <script src="js/main.js"></script>
        <script>
            $(function($) {
                $('#example').DataTable({paging:false,searching:false,info:false});

                $('#example2').DataTable( {
                    "scrollY":        "300px",
                    "scrollCollapse": true,
                    "paging":         false
                } );

                $('#example3').DataTable();
            });
        </script>
    </body>
</html>
<?php } ?>

