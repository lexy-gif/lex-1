<?php
// Explicit capabilities: a new page or action is denied until added here.
function staff_permissions() {
    return [
        'academic.overview'=>'Academic overview', 'academic.periods'=>'Academic years and terms',
        'curriculum.manage'=>'School curriculum and subject offerings', 'learners.manage'=>'Learner academic records and enrolments',
        'teaching.view'=>'Academic teacher details and workload', 'teaching.assign'=>'Subject teaching allocations',
        'timetable.manage'=>'Timetable coordination and publication', 'assessments.manage'=>'Internal assessment coordination',
        'coverage.view'=>'Teaching progress submissions', 'support.manage'=>'Academic interventions and pathway guidance',
        'results.review'=>'Results completeness and correction requests', 'reports.view'=>'Academic reports and exports',
        'notices.academic'=>'Academic notices', 'notifications.academic'=>'Academic notification monitoring',
        'account.self'=>'Own password',
        'accounts.teachers'=>'Teacher account administration', 'accounts.guardians'=>'Guardian accounts and verified learner links',
        'assignments.class'=>'Appoint class teachers', 'assignments.responsibility'=>'Assign approved academic responsibilities',
        'departments.access'=>'Appoint department heads and grant department read access',
        'results.approve'=>'Final result approval', 'results.publish'=>'Publish approved results',
        'results.correct'=>'Reopen reviewed or approved results with a reason', 'grading.manage'=>'School-approved performance scales',
        'learners.promote'=>'Approve learner promotions',
        'results.legacy'=>'Legacy examination mark administration', 'assessment.scores'=>'Legacy assessment evidence administration',
        'coverage.record'=>'Record progress on behalf of teachers', 'responsibilities.manage'=>'Responsibility catalogue and nonacademic duties',
        'notices.all'=>'General school notices', 'notifications.all'=>'Account and system delivery monitoring',
        'audit.all'=>'System audit log', 'security.manage'=>'Staff roles and explicit delegations'
    ];
}
function staff_role_permissions() {
    $dean=['academic.overview','academic.periods','curriculum.manage','learners.manage','teaching.view','teaching.assign','timetable.manage','assessments.manage','coverage.view','support.manage','results.review','reports.view','notices.academic','notifications.academic','account.self'];
    return [
        'dean'=>$dean,
        'technical_administrator'=>['academic.overview','account.self','accounts.teachers','accounts.guardians','notifications.all','audit.all'],
        'academic_approver'=>['academic.overview','account.self','results.review','reports.view','results.approve','results.publish','results.correct','grading.manage','learners.promote','assignments.class','assignments.responsibility','departments.access'],
        'administrator'=>array_keys(staff_permissions())
    ];
}
function staff_delegatable_permissions() {
    return ['accounts.teachers','accounts.guardians','assignments.class','assignments.responsibility','departments.access','results.approve','results.publish','results.correct','grading.manage','learners.promote'];
}
function staff_identity($db) {
    if (empty($_SESSION['alogin'])) return null;
    require_once __DIR__.'/dean-account.php';
    $account=dean_find_by_username($db,$_SESSION['alogin'],$_SESSION['staff_account_table']??null);
    if (!$account || !hash_equals(hash('sha256',$account->Password),(string)($_SESSION['dean_password_version']??''))) return null;
    return ['table'=>$account->AccountTable,'id'=>(int)$account->id,'name'=>$account->UserName];
}
function staff_access($db) {
    $identity=staff_identity($db);
    if (!$identity) return ['identity'=>null,'roles'=>[],'permissions'=>[]];
    // Missing migrations fail closed for privileged access, including legacy sessions.
    try {
        $q=$db->prepare('SELECT RoleName FROM tblstaffroles WHERE AccountTable=? AND AccountId=?');
        $q->execute([$identity['table'],$identity['id']]);$roles=$q->fetchAll(PDO::FETCH_COLUMN);
        $q=$db->prepare('SELECT PermissionName FROM tblstaffpermissions WHERE AccountTable=? AND AccountId=?');
        $q->execute([$identity['table'],$identity['id']]);$grants=$q->fetchAll(PDO::FETCH_COLUMN);
    } catch(PDOException $e) {
        if (($e->errorInfo[1]??null)!==1146) throw $e;
        $roles=[];$grants=[];
    }
    // Unclassified legacy Dean accounts receive only the Dean allowlist.
    if (!$roles && $identity['table']==='tbldean') $roles=['dean'];
    $permissions=[];$catalogue=staff_role_permissions();
    foreach($roles as $role)$permissions=array_merge($permissions,$catalogue[$role]??[]);
    $permissions=array_merge($permissions,array_intersect($grants,staff_delegatable_permissions()));
    return ['identity'=>$identity,'roles'=>$roles,'permissions'=>array_values(array_unique($permissions))];
}
function staff_can($permission) {
    global $dbh;
    // Cache only within this HTTP request; revocation takes effect on the next request.
    if (!isset($GLOBALS['staff_access'])) $GLOBALS['staff_access']=staff_access($dbh);
    return is_string($permission)&&in_array($permission,$GLOBALS['staff_access']['permissions'],true);
}
function staff_forbid() {
    http_response_code(403);
    if (str_contains($_SERVER['HTTP_ACCEPT']??'','application/json') || in_array(basename($_SERVER['SCRIPT_NAME']??''),['get_student.php','dean-senior-options.php'],true)) {
        header('Content-Type: application/json');echo json_encode(['error'=>'Permission denied.']);
    } else echo 'Permission denied. Contact an authorised administrator.';
    exit;
}
function staff_require($permission) { if (!staff_can($permission)) staff_forbid(); }
function staff_assert($permission) { if (!staff_can($permission)) throw new DomainException('This action requires separate authorisation.'); }
function staff_routes() {
    return [
        'dashboard.php'=>'academic.overview','change-password.php'=>'account.self',
        'dean-academic-periods.php'=>'academic.periods','dean-academics.php'=>'academic.overview',
        'create-class.php'=>'curriculum.manage','manage-classes.php'=>'curriculum.manage','edit-class.php'=>'curriculum.manage',
        'create-subject.php'=>'curriculum.manage','manage-subjects.php'=>'curriculum.manage','edit-subject.php'=>'curriculum.manage',
        'add-subjectcombination.php'=>'curriculum.manage','manage-subjectcombination.php'=>'curriculum.manage',
        'add-students.php'=>'learners.manage','manage-students.php'=>'learners.manage','edit-student.php'=>'learners.manage',
        'student-subjects.php'=>'learners.manage','student-senior.php'=>'learners.manage',
        'view-teacher.php'=>'teaching.view','dean-teacher-relationships.php'=>'teaching.view',
        'manage-class-teachers.php'=>'assignments.class',
        'manage-teachers.php'=>'accounts.teachers','create-teacher.php'=>'accounts.teachers','edit-teacher.php'=>'accounts.teachers',
        'manage-parents.php'=>'accounts.guardians','dean-senior-options.php'=>'curriculum.manage',
        'dean-senior-pathways.php'=>'curriculum.manage','dean-senior-subjects.php'=>'curriculum.manage','dean-senior-combinations.php'=>'curriculum.manage',
        'dean-senior-assignments.php'=>'learners.manage','dean-senior-teachers.php'=>'teaching.assign',
        'dean-senior-promotions.php'=>'learners.promote','dean-senior-reports.php'=>'reports.view',
        'dean-timetable-setup.php'=>'timetable.manage','dean-class-timetable.php'=>'timetable.manage','dean-exam-timetable.php'=>'timetable.manage',
        'manage-exams.php'=>'assessments.manage','manage-results.php'=>'results.review','dean-result-approvals.php'=>'results.review',
        'add-result.php'=>'results.legacy','edit-result.php'=>'results.legacy','get_student.php'=>'results.legacy',
        'dean-grading.php'=>'grading.manage','add-notice.php'=>'notices.academic','manage-notices.php'=>'notices.academic',
        'dean-notification-deliveries.php'=>'notifications.academic','dean-sms-deliveries.php'=>'notifications.academic',
        'dean-audit-logs.php'=>'audit.all','staff-permissions.php'=>'security.manage'
    ];
}
function staff_area_permission($area) {
    return ['dashboard'=>'academic.overview','structure'=>'curriculum.manage','subjects'=>'curriculum.manage','pathways'=>'curriculum.manage',
        'allocation'=>'learners.manage','workload'=>'teaching.view','assessments'=>'assessments.manage','coverage'=>'coverage.view',
        'interventions'=>'support.manage','analytics'=>'reports.view','reports'=>'reports.view','class'=>'reports.view',
        'permissions'=>'departments.access','exams'=>'assessments.manage','timetable'=>'timetable.manage','department'=>'reports.view'][$area]??null;
}
function staff_action_permission($action,$post=[]) {
    if ($action==='config') return ($post['entity']??'')==='levels-performance'?'grading.manage':'curriculum.manage';
    return ['combination'=>'curriculum.manage','allocation'=>'learners.manage','guidance'=>'support.manage','assessment'=>'assessments.manage',
        'score'=>'assessment.scores','coverage'=>'coverage.record','intervention'=>'support.manage','lesson'=>'timetable.manage','exam_session'=>'timetable.manage',
        'class'=>'curriculum.manage','subject'=>'curriculum.manage','settings'=>'teaching.assign','permission'=>'departments.access',
        'exam_lock'=>'assessments.manage','carry'=>'assignments.class'][$action]??null;
}
function staff_require_route() {
    $route=basename($_SERVER['SCRIPT_NAME']??'');
    $public=['index.php','admin-login.php','teacher-login.php','parent-login.php','notice-details.php','health.php','logout.php'];
    if (in_array($route,$public,true) || str_starts_with($route,'teacher-') || str_starts_with($route,'parent-') || in_array($route,['result.php','find-result.php'],true)) return;
    $permission=staff_routes()[$route]??null;
    if (!$permission) staff_forbid();
    if (empty($_SESSION['alogin'])) {header('Location: admin-login.php');exit;}
    staff_require($permission);
    if ($route==='dean-academics.php') {
        staff_require(staff_area_permission($_GET['area']??'dashboard'));
        if (isset($_GET['export'])) staff_require('reports.view');
        if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') staff_require(staff_action_permission($_POST['action']??'',$_POST));
    }
}
