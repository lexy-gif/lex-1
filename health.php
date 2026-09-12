<?php
header('Content-Type: application/json');header('Cache-Control: no-store');
try {
    $db=new PDO('mysql:host='.getenv('DB_HOST').';dbname='.getenv('DB_NAME').';charset=utf8mb4',getenv('DB_USER'),getenv('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>3]);
    $db->query('SELECT 1');echo '{"status":"ok"}';
}catch(Throwable $e){error_log('Health check: '.$e->getMessage());http_response_code(503);echo '{"status":"unavailable"}';}
