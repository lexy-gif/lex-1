<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/config.php';require __DIR__.'/../includes/parent-sms-queue.php';
$loop=in_array('--loop',$argv,true);
do { try { $count=parent_sms_process($dbh); if($count)echo "Processed $count publication messages.\n"; }
    catch(Throwable $e){error_log((string)$e);if(!$loop)exit(1);}
    if($loop)sleep(30);
} while($loop);
