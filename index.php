<?php
error_reporting(0);
include('includes/config.php'); 
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="Student Result Management System" />
    <meta name="author" content="" />
    <title>Student Result Management System</title>
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
                    <li class="nav-item"><a class="nav-link active" href="#!">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="find-result.php">Students</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin-login.php">Class Teacher</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="py-5 bg-image-full text-white d-flex align-items-center justify-content-center"
        style="background-image: url('images/school system background.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat; min-height: 60vh; position: relative;">
        <div style="background: rgba(0,0,0,0.5); position: absolute; inset: 0;"></div>
        <div class="container text-center position-relative">
            <h1 class="display-5 fw-bold">Welcome to the Student Result Management System</h1>
            <p class="lead mb-4">Effortlessly manage, view, and access student academic results online.</p>
            <a href="find-result.php" class="btn btn-primary btn-lg me-2">Studet Login</a>
            <a href="admin-login.php" class="btn btn-outline-light btn-lg">Class Teacher Login</a>
        </div>
    </header>

    <!-- About / Features Section -->
    <section class="py-5 bg-light">
        <div class="container text-center">
            <h2 class="fw-bold mb-4">Why Choose SRMS?</h2>
            <div class="row justify-content-center">
                <div class="col-md-4 mb-4">
                    <div class="p-4 border rounded-3 shadow-sm bg-white h-100">
                        <h5 class="fw-bold mb-2">🎓 Student Access</h5>
                        <p>Easily check your academic performance and download results anytime, anywhere.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="p-4 border rounded-3 shadow-sm bg-white h-100">
                        <h5 class="fw-bold mb-2">🧑‍💼 Class Teacher Management</h5>
                        <p>Class Teacher can efficiently upload results, manage students, and post important notices.
                        </p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="p-4 border rounded-3 shadow-sm bg-white h-100">
                        <h5 class="fw-bold mb-2">📢 Notice Board</h5>
                        <p>Stay informed with the latest academic announcements and school updates.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Notice Board -->
    <section class="py-5">
        <div class="container my-5">
            <div class="row justify-content-center">
                <div class="col-lg-10 text-center">
                    <h2>📢 Our Class Notice Board</h2>
                    <p class="text-muted">Click the links below to view and read the latest school notices.</p>

                    <hr color="#000" />

                    <ul
                        style="display: flex; flex-wrap: wrap; justify-content: center; gap: 30px; list-style: disc; padding-left: 40px; margin: 0;">
                        <?php 
              $sql = "SELECT * FROM tblnotice";
              $query = $dbh->prepare($sql);
              $query->execute();
              $results = $query->fetchAll(PDO::FETCH_OBJ);
              if($query->rowCount() > 0) {
                foreach($results as $result) { 
            ?>
                        <li style="margin: 0; padding: 0;">
                            <a href="notice-details.php?nid=<?php echo htmlentities($result->id);?>" target="_blank"
                                class="notice-link">
                                <?php echo htmlentities($result->noticeTitle);?>
                            </a>
                        </li>
                        <?php }} ?>
                    </ul>
                </div>
            </div>
        </div>

        <style>
        .notice-link {
            color: #000;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .notice-link:hover {
            color: #007bff;
            text-decoration: underline;
        }

        ul {
            list-style-position: outside;
        }
        </style>
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

    <!-- Bootstrap core JS-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Core theme JS-->
    <script src="js/scripts.js"></script>
</body>

</html>