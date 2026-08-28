<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$password = $argv[1] ?? '';
if ($password === '') {
    fwrite(STDERR, "Usage: php password_hash.php <new-password>\n");
    exit(1);
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

echo $hashedPassword . PHP_EOL;
?>
