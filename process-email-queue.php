<?php
if(php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

include('includes/config.php');
include('includes/email-service.php');

$limit = isset($argv[1]) ? (int)$argv[1] : 20;
email_service_process_queue($dbh, $limit);
echo "Email queue processed.\n";
?>
