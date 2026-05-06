<?php
session_start();
//error_reporting(0);
include('includes/config.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Login | School Result Management System</title>
    <link rel="stylesheet" href="css/bootstrap.min.css" media="screen">
    <link rel="stylesheet" href="css/font-awesome.min.css" media="screen">
    <link rel="stylesheet" href="css/animate-css/animate.min.css" media="screen">
    <link rel="stylesheet" href="css/icheck/skins/flat/blue.css">
    <link rel="stylesheet" href="css/main.css" media="screen">
    <script src="js/modernizr/modernizr.min.js"></script>

    <style>
    body {
        background-image: url('images/school system background.jpg');
        /* ✅ use your existing image */
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .login-box {
        background-color: rgba(255, 255, 255, 0.93);
        padding: 40px;
        border-radius: 10px;
        box-shadow: 0px 4px 20px rgba(0, 0, 0, 0.3);
        margin-top: 90px;
    }

    h4 {
        font-weight: 700;
        color: #333;
    }

    h1 {
        color: #fff;
        text-align: center;
        font-weight: 700;
        text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
        margin-top: 30px;
        margin-bottom: 20px;
    }

    label {
        font-weight: 600;
    }

    .btn-success {
        background-color: #28a745;
        border: none;
    }

    .btn-success:hover {
        background-color: #218838;
    }

    footer {
        margin-top: 60px;
        background: rgba(0, 0, 0, 0.7);
        color: #ccc;
        padding: 15px 0;
        text-align: center;
    }

    footer a {
        color: #00bfff;
        text-decoration: none;
    }

    footer a:hover {
        text-decoration: underline;
    }

    .panel {
        border: none;
        box-shadow: none;
    }

    .form-group a {
        color: #007bff;
        text-decoration: none;
    }

    .form-group a:hover {
        text-decoration: underline;
    }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <div class="container">
            <h1>Student Result Management System</h1>

            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="panel login-box">
                        <div class="panel-heading text-center mb-3">
                            <div class="panel-title">
                                <h4><i class="fa fa-user"></i> Student Login</h4>
                            </div>
                        </div>

                        <div class="panel-body p-20">
                            <form action="result.php" method="post">
                                <div class="form-group">
                                    <label for="rollid">Enter your Registration ID</label>
                                    <input type="text" class="form-control" id="rollid"
                                        placeholder="Enter your Registration ID" autocomplete=" off" name="rollid"
                                        required>
                                </div>

                                <div class="form-group">
                                    <label for="default">Class</label>
                                    <select name="class" class="form-control" id="default" required="required">
                                        <option value="">Select Class</option>
                                        <?php 
                                        $sql = "SELECT * from tblclasses";
                                        $query = $dbh->prepare($sql);
                                        $query->execute();
                                        $results=$query->fetchAll(PDO::FETCH_OBJ);
                                        if($query->rowCount() > 0)
                                        {
                                            foreach($results as $result)
                                            {   ?>
                                        <option value="<?php echo htmlentities($result->id); ?>">
                                            <?php echo htmlentities($result->ClassName); ?> - Section
                                            <?php echo htmlentities($result->Section); ?>
                                        </option>
                                        <?php }} ?>
                                    </select>
                                </div>

                                <div class="form-group mt-20">
                                    <button type="submit" class="btn btn-success btn-block">
                                        <i class="fa fa-sign-in"></i> Login
                                    </button>
                                </div>
                                <div class="form-group text-center">
                                    <a href="index.php"><i class="fa fa-home"></i> Back to Home</a>
                                </div>
                            </form>
                        </div>
                    </div>
                    <p class="text-muted text-center mt-3"><small>Access your academic performance securely</small></p>
                </div>
            </div>
        </div>

        <footer>
            <p>© <?php echo date("Y"); ?> <strong>Student Result Management System (SRMS)</strong></p>
        </footer>
    </div>

    <!-- ========== JS FILES ========== -->
    <script src="js/jquery/jquery-2.2.4.min.js"></script>
    <script src="js/jquery-ui/jquery-ui.min.js"></script>
    <script src="js/bootstrap/bootstrap.min.js"></script>
    <script src="js/pace/pace.min.js"></script>
    <script src="js/lobipanel/lobipanel.min.js"></script>
    <script src="js/iscroll/iscroll.js"></script>
    <script src="js/icheck/icheck.min.js"></script>
    <script src="js/main.js"></script>

    <script>
    $(function() {
        $('input.flat-blue-style').iCheck({
            checkboxClass: 'icheckbox_flat-blue'
        });
    });
    </script>
</body>

</html>