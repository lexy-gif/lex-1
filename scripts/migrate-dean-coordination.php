<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/config.php';
// DDL is idempotent so an interrupted migration can safely be resumed.
$dbh->exec(file_get_contents(__DIR__.'/../database-updates/dean-academic-coordination.sql'));
$column=$dbh->query("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='tbldepartmentpermissions' AND column_name='CanReviewAcademic'")->fetchColumn();
if(!$column)$dbh->exec('ALTER TABLE tbldepartmentpermissions ADD COLUMN CanReviewAcademic TINYINT NOT NULL DEFAULT 0');
$dbh->exec("INSERT IGNORE INTO tblschemamigrations(Name) VALUES('dean-coordination-v2')");
echo "Academic calendar, follow-ups, resource requests and assessment reviews are ready. No accounts or delegations were changed.\n";
