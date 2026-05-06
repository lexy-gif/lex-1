<?php
error_reporting(0);
include('includes/config.php'); 
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="Notice Details - Student Result Management System" />
    <meta name="author" content="" />
    <title>Notice Details | Student Result Management System</title>
    <!-- Favicon-->
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
    <!-- Core theme CSS (includes Bootstrap)-->
    <link href="css/styles.css" rel="stylesheet" />
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">SRMS - Student Result Management System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false"
                aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="find-result.php">Students</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin-login.php">Admin</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <header class="py-5 bg-image-full text-white d-flex align-items-center justify-content-center"
        style="background-image: url('images/school system background.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat; min-height: 40vh; position: relative;">
        <div style="background: rgba(0,0,0,0.5); position: absolute; inset: 0;"></div>
        <div class="container position-relative text-center">
            <h1 class="fw-bold">Notice Details</h1>
            <p class="lead">Stay informed with the latest updates and announcements</p>
        </div>
    </header>

    <!-- Notice Content -->
    <section class="py-5">
        <div class="container my-4">
            <div class="row justify-content-center">
                <div class="col-lg-10 bg-white p-5 rounded shadow-sm">

                    <?php 
          $noticeid = $_GET['nid'];
          $sql = "SELECT * FROM tblnotice WHERE id = :nid";
          $query = $dbh->prepare($sql);
          $query->bindParam(':nid', $noticeid, PDO::PARAM_STR);
          $query->execute();
          $results = $query->fetchAll(PDO::FETCH_OBJ);
          if ($query->rowCount() > 0) {
            foreach ($results as $result) { 
          ?>

                    <h2 class="fw-bold mb-3 text-center"><?php echo htmlentities($result->noticeTitle); ?></h2>
                    <p class="text-muted text-center mb-4"><strong>Posted on:</strong>
                        <?php echo htmlentities($result->postingDate); ?></p>
                    <hr class="mb-4" />
                    <p class="fs-5" style="text-align: justify;">
                        <?php echo nl2br(htmlentities($result->noticeDetails)); ?>
                    </p>

                    <?php }} else { ?>
                    <div class="text-center">
                        <h5 class="text-danger">No notice found!</h5>
                    </div>
                    <?php } ?>

                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-4 bg-dark text-white-50">
        <div class="container text-center">
            <p class="mb-1">Student Result Management System</p>
            <small>&copy; <span id="year"></span> All Rights Reserved.</small>
        </div>
        <script>
        document.getElementById("year").textContent = new Date().getFullYear();
        </script>
    </footer>

    <!-- Bootstrap core JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Core theme JS -->
    <script src="js/scripts.js"></script>

</body>

</html>