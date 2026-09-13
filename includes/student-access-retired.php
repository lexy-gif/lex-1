<?php
require_once __DIR__.'/bootstrap.php';
http_response_code(410);
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Student access retired | SRMS</title><link rel="stylesheet" href="/css/bootstrap.min.css"><link rel="stylesheet" href="/css/custom.css"></head>
<body><main class="container"><h1>Student account access has ended</h1><p>Parents and guardians can view linked learners' information through their own school-issued accounts. Contact the school to verify a guardian link.</p><a class="btn btn-primary" href="/parent-login.php">Parent/Guardian Login</a></main></body></html>
<?php exit;
