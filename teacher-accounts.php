<?php
require_once 'includes/config.php';
require_once 'includes/teacher-auth.php';
require_class_teacher();
// Retire creation, activation and password resets, including old POSTs.
require __DIR__.'/includes/student-access-retired.php';
