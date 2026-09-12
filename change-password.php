<?php
require_once 'includes/bootstrap.php';
$error=$msg='';

require_once 'includes/config.php';
require_once 'includes/csrf.php';
require_once 'includes/audit.php';
require_once 'includes/dean-account.php';
require_once 'includes/security.php';
if(empty($_SESSION['alogin']))
    {   
    header("Location: index.php"); 
    }
    else{
if(isset($_POST['submit']))
    {
csrf_require_valid($_POST['csrf_token'] ?? '');
try {
$password=is_string($_POST['password']??null)?$_POST['password']:'';
if(($_POST['newpassword']??null)!==($_POST['confirmpassword']??null))throw new DomainException('New passwords do not match.');
$newpassword=security_password($_POST['newpassword']??null);
$username=$_SESSION['alogin'];
$result = dean_find_by_username($dbh, $username);
$passwordMatches = $result && (password_verify($password, $result->Password) || hash_equals($result->Password, md5($password)));
if($passwordMatches)
{
dean_update_password($dbh, $username, $newpassword);
$_SESSION['dean_password_version']=hash('sha256',$newpassword);session_regenerate_id(true);
audit_log($dbh, 'dean_password_changed', 'dean', $username, 'Dean changed password');
$msg="Your password was successfully changed";
}
else {
$error="Your current password is wrong";    
}
} catch(DomainException $e){$error=$e->getMessage();}
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
    	<meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Admin change password</title>
        <link rel="stylesheet" href="css/bootstrap.css" media="screen" >
        <link rel="stylesheet" href="css/font-awesome.min.css" media="screen" >
        <link rel="stylesheet" href="css/animate-css/animate.min.css" media="screen" >
        <link rel="stylesheet" href="css/lobipanel/lobipanel.min.css" media="screen" >
        <link rel="stylesheet" href="css/prism/prism.css" media="screen" > <!-- USED FOR DEMO HELP - YOU CAN REMOVE IT -->
        <link rel="stylesheet" href="css/main.css" media="screen" >
        <link rel="stylesheet" href="css/custom.css">
        <script src="js/modernizr/modernizr.min.js"></script>
        <script type="text/javascript">
function valid()
{
if(document.chngpwd.newpassword.value!= document.chngpwd.confirmpassword.value)
{
alert("New Password and Confirm Password Field do not match  !!");
document.chngpwd.confirmpassword.focus();
return false;
}
return true;
}
</script>
    </head>
    <body class="top-navbar-fixed">
        <div class="main-wrapper">
            <?php include('includes/topbar.php');?>   
            <div class="content-wrapper">
                <div class="content-container">
<?php include('includes/leftbar.php');?>                   
 <!-- /.left-sidebar -->

                    <div class="main-page">
                        <div class="container-fluid">
                            <div class="row page-title-div">
                                <div class="col-md-6">
                                    <h2 class="title">Admin Change Password</h2>
                                </div>
                                
                            </div>
                            <!-- /.row -->
                            <div class="row breadcrumb-div">
                                <div class="col-md-6">
                                    <ul class="breadcrumb">
            							<li><a href="dashboard.php"><i class="fa fa-home"></i> Home</a></li>
            						
            							<li class="active">Admin change password</li>
            						</ul>
                                </div>
                               
                            </div>
                            <!-- /.row -->
                        </div>
                        <!-- /.container-fluid -->

                        <section class="section">
                            <div class="container-fluid">

                             

                              

                                <div class="row">
                                    <div class="col-md-8 col-md-offset-2">
                                        <div class="panel">
                                            <div class="panel-heading">
                                                <div class="panel-title">
                                                    <h5>Admin Change Password</h5>
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
  
                                            <div class="panel-body">

                                                <form  name="chngpwd" method="post" onSubmit="return valid();">
                                                    <?php csrf_field(); ?>
                                                    <div class="form-group has-success">
                                                        <label for="success" class="control-label">Current Password</label>
                                                		<div class="">
                                    <input type="password" name="password" class="form-control" required="required" id="success">
                                                      
                                                		</div>
                                                	</div>
                                                       <div class="form-group has-success">
                                                        <label for="success" class="control-label">New Password</label>
                                                        <div class="">
                                                            <input type="password" name="newpassword" required="required" class="form-control" id="success">
                                                        </div>
                                                    </div>
                                                     <div class="form-group has-success">
                                                        <label for="success" class="control-label">Confirm Password</label>
                                                        <div class="">
                                                            <input type="password" name="confirmpassword" class="form-control" required="required" id="success">
                                                        </div>
                                                    </div>
  <div class="form-group has-success">

                                                        <div class="">
                                                           <button type="submit" name="submit" class="btn btn-success btn-labeled">Change<span class="btn-label btn-label-right"><i class="fa fa-check"></i></span></button>
                                                    </div>


                                                    
                                                </form>

                                              
                                            </div>
                                        </div>
                                    </div>
                                    <!-- /.col-md-8 col-md-offset-2 -->
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
        <script src="js/jquery-ui/jquery-ui.min.js"></script>
        <script src="js/bootstrap/bootstrap.min.js"></script>
        <script src="js/pace/pace.min.js"></script>
        <script src="js/lobipanel/lobipanel.min.js"></script>
        <script src="js/iscroll/iscroll.js"></script>

        <!-- ========== PAGE JS FILES ========== -->
        <script src="js/prism/prism.js"></script>

        <!-- ========== THEME JS ========== -->
        <script src="js/main.js"></script>



        <!-- ========== ADD custom.js FILE BELOW WITH YOUR CHANGES ========== -->
    </body>
</html>
<?php  } ?>
