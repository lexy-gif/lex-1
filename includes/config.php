<?php 
// DB credentials.
define('DB_HOST','db');
define('DB_USER','srms_user');
define('DB_PASS','srms_password');
define('DB_NAME','srms');
// Establish database connection.
try
{
$dbh = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME,DB_USER, DB_PASS,array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
}
catch (PDOException $e)
{
exit("Error: " . $e->getMessage());
}
?>