<?php
require_once __DIR__.'/../../includes/bootstrap.php';
$schoolName = htmlspecialchars(getenv('SCHOOL_NAME') ?: 'Senior School SRMS', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Staff Login | <?= $schoolName ?></title>
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/font-awesome.min.css">
    <link rel="stylesheet" href="/css/custom.css">
    <link rel="stylesheet" href="/css/staff-login.css">
</head>
<body class="staff-login-page">
    <a class="staff-skip-link" href="#staff-accounts">Skip to login options</a>
    <div class="staff-shell">
        <header class="staff-header">
            <a class="staff-brand" href="/index.php">
                <span class="staff-brand-mark" aria-hidden="true"><i class="fa fa-graduation-cap"></i></span>
                <span><strong><?= $schoolName ?></strong><span class="staff-brand-caption">Student Result Management System</span></span>
            </a>
            <span class="staff-access-label"><i class="fa fa-lock" aria-hidden="true"></i> Staff access</span>
        </header>

        <main class="staff-panel">
            <section class="staff-welcome" aria-labelledby="staff-welcome-title">
                <span class="staff-eyebrow"><span class="staff-accent-line" aria-hidden="true"></span> CBC Senior School</span>
                <h2 id="staff-welcome-title">Every learner.<br><span>Every possibility.</span></h2>
                <p>Plan learning, follow progress and help every student move forward.</p>

                <svg class="staff-illustration" viewBox="0 0 360 208" fill="none" aria-hidden="true" focusable="false">
                    <circle cx="286" cy="50" r="29" fill="#e6ba78" fill-opacity=".14"/>
                    <circle cx="286" cy="50" r="15" fill="#e6ba78"/>
                    <path d="M40 174V90a45 45 0 0 1 90 0v84M230 174V106a34 34 0 0 1 68 0v68" stroke="#89aabe" stroke-opacity=".22" stroke-width="1.5"/>
                    <path d="M72 174V91a69 69 0 0 1 138 0v83" stroke="#89aabe" stroke-opacity=".25" stroke-width="1.5"/>
                    <path d="M107 107c28-10 51-5 73 10v74c-22-15-45-20-73-10V107Z" fill="#224762" stroke="#9cbbce" stroke-width="1.5" stroke-linejoin="round"/>
                    <path d="M253 107c-28-10-51-5-73 10v74c22-15 45-20 73-10V107Z" fill="#2d5570" stroke="#9cbbce" stroke-width="1.5" stroke-linejoin="round"/>
                    <path d="M95 118v72c33-10 58-6 85 9 27-15 52-19 85-9v-72" stroke="#e6ba78" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M121 125c15-3 30 0 43 7m-43 9c15-3 30 0 43 7m32-16c13-7 28-10 43-7m-43 23c13-7 28-10 43-7" stroke="#9cbbce" stroke-opacity=".6" stroke-width="2" stroke-linecap="round"/>
                    <path d="M39 198h88m106 0h88M61 120v14m-7-7h14M309 128v12m-6-6h12" stroke="#9cbbce" stroke-opacity=".4" stroke-width="1.5" stroke-linecap="round"/>
                    <circle cx="216" cy="62" r="3" fill="#9cbbce"/>
                    <circle cx="80" cy="164" r="3" fill="#e6ba78"/>
                </svg>

                <div class="staff-welcome-footer"><span>Teach.</span><span>Guide.</span><span>Inspire.</span></div>
            </section>

            <section class="staff-accounts" id="staff-accounts" aria-labelledby="staff-login-title" tabindex="-1">
                <div class="staff-accounts-heading">
                    <span class="staff-eyebrow">Your school workspace</span>
                    <h1 id="staff-login-title">Staff Login</h1>
                    <p>Welcome back. Choose your account to continue.</p>
                </div>

                <div class="staff-role-options">
                    <a class="staff-role-card staff-role-card--teacher" href="/teacher-login.php" aria-labelledby="teacher-login-label" aria-describedby="teacher-login-description">
                        <span class="staff-role-icon" aria-hidden="true"><i class="fa fa-book"></i></span>
                        <div class="staff-role-content">
                            <h2 id="teacher-login-label">Teacher Login</h2>
                            <span class="staff-role-description" id="teacher-login-description">For class teachers and subject teachers.</span>
                            <span class="staff-role-action">Classes, assessments &amp; learner progress <i class="fa fa-arrow-right" aria-hidden="true"></i></span>
                        </div>
                    </a>

                    <a class="staff-role-card staff-role-card--dean" href="/admin-login.php" aria-labelledby="dean-login-label" aria-describedby="dean-login-description">
                        <span class="staff-role-icon" aria-hidden="true"><i class="fa fa-university"></i></span>
                        <div class="staff-role-content">
                            <h2 id="dean-login-label">Dean of Studies Login</h2>
                            <span class="staff-role-description" id="dean-login-description">For the Dean of Studies.</span>
                            <span class="staff-role-action">Academic oversight &amp; result publication <i class="fa fa-arrow-right" aria-hidden="true"></i></span>
                        </div>
                    </a>
                </div>

                <p class="staff-login-help"><i class="fa fa-question-circle-o" aria-hidden="true"></i><span>Need help signing in?<br>Contact your school administrator for account support.</span></p>
            </section>
        </main>

        <footer class="staff-footer">
            <span>&copy; <?= date('Y') ?> <?= $schoolName ?></span>
            <a href="/index.php"><i class="fa fa-arrow-left" aria-hidden="true"></i> Back to school portal</a>
        </footer>
    </div>
</body>
</html>
