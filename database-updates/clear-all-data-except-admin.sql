-- Clear all SRMS data except administrator login records.
-- This keeps the `admin` table untouched and empties every other application table.

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `tblpayments`;
TRUNCATE TABLE `tblfeestructures`;
TRUNCATE TABLE `tblfeetypes`;
TRUNCATE TABLE `tblattendance`;
TRUNCATE TABLE `tblauditlog`;
TRUNCATE TABLE `tblresult`;
TRUNCATE TABLE `tblexams`;
TRUNCATE TABLE `tblterms`;
TRUNCATE TABLE `tblacademicyears`;
TRUNCATE TABLE `tblgradingscales`;
TRUNCATE TABLE `tblnotice`;
TRUNCATE TABLE `tblsubjectcombination`;
TRUNCATE TABLE `tblsubjects`;
TRUNCATE TABLE `tblstudents`;
TRUNCATE TABLE `tblclasses`;

SET FOREIGN_KEY_CHECKS = 1;
