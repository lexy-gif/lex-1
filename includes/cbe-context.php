<?php
require_once __DIR__.'/academic-assignments.php';

function cbe_period_context($db, $query) {
    if(isset($query['year'])) {
        $year=(int)$query['year'];
        $defaultTerm=(int)academic_query($db,'SELECT id FROM tblterms WHERE AcademicYearId=? AND IsActive=1 ORDER BY id DESC LIMIT 1',[$year])->fetchColumn();
    } else {
        // Read the default pair in one statement so a concurrent switch cannot mix periods.
        $active=$db->query('SELECT y.id AcademicYearId,t.id TermId FROM tblacademicyears y LEFT JOIN tblterms t ON t.AcademicYearId=y.id AND t.IsActive=1 WHERE y.IsActive=1 ORDER BY y.id DESC,t.id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        $year=(int)($active['AcademicYearId']??0);$defaultTerm=(int)($active['TermId']??0);
    }
    $term = isset($query['term']) ? (int)$query['term'] : $defaultTerm;
    if ($year) academic_period($db, $year, $term);
    elseif ($term) throw new DomainException('Select an academic year before choosing a term.');
    return [$year, $term];
}

function cbe_filter_names($area, $teacherPortal=false, $report='performance') {
    $kind = $area === 'reports' ? $report : $area;
    $filters = ['year'];
    if (in_array($kind, ['dashboard','workload','assessments','coverage','interventions','analytics','performance','class','department','legacy','assignments','timetable'], true)) $filters[] = 'term';
    if (in_array($kind, ['allocation','pathways','assessments','coverage','interventions','analytics','performance','class','department','legacy','assignments','timetable','unallocated'], true) && $area !== 'pathways') $filters[] = 'class';
    if (in_array($kind, ['assessments','coverage','interventions','analytics','performance','department','legacy','assignments','timetable'], true)) $filters[] = 'subject';
    if (!$teacherPortal && in_array($kind, ['workload','assessments','coverage','interventions','analytics','performance','assignments','timetable'], true)) $filters[] = 'teacher';
    if (!$teacherPortal && in_array($kind, ['allocation','pathways','interventions','analytics','performance','legacy','unallocated'], true) && $area !== 'pathways') $filters[] = 'student';
    return $filters;
}

function cbe_view_url($base, $query, $changes=[]) {
    $allowed = array_flip(['area','year','term','class','subject','teacher','student','report','entity','kind','id']);
    $query = array_intersect_key(array_merge($query, $changes), $allowed);
    $query = array_filter($query, fn($value) => is_scalar($value));
    return $base.'?'.http_build_query($query);
}
