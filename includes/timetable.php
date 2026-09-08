<?php
include_once(__DIR__ . '/notification-service.php');

function time_ranges_overlap($startA, $endA, $startB, $endB)
{
    return $startA < $endB && $startB < $endA;
}

function timetable_active_period($dbh)
{
    $sql = "SELECT ay.id AS AcademicYearId, t.id AS TermId, ay.AcademicYear, t.TermName
            FROM tblterms t
            JOIN tblacademicyears ay ON ay.id = t.AcademicYearId
            WHERE t.IsActive = 1 AND ay.IsActive = 1
            ORDER BY ay.id DESC, t.id DESC
            LIMIT 1";
    $query = $dbh->prepare($sql);
    $query->execute();
    return $query->fetch(PDO::FETCH_OBJ);
}

function timetable_lesson_conflicts($dbh, $academicYearId, $termId, $classId, $teacherId, $roomId, $day, $startTime, $endTime, $ignoreId = 0)
{
    $conflicts = array();

    $baseSql = "SELECT e.id, c.ClassName, c.Section, s.SubjectName, u.FullName, r.RoomName, e.StartTime, e.EndTime
                FROM tblclasstimetableentries e
                JOIN tblclasses c ON c.id = e.ClassId
                JOIN tblsubjects s ON s.id = e.SubjectId
                LEFT JOIN tblusers u ON u.id = e.TeacherId
                LEFT JOIN tblrooms r ON r.id = e.RoomId
                WHERE e.AcademicYearId = :yearid
                  AND e.TermId = :termid
                  AND e.DayOfWeek = :day
                  AND e.Status NOT IN ('cancelled','archived')
                  AND e.id <> :ignoreid
                  AND e.StartTime < :endtime
                  AND :starttime < e.EndTime";
    $baseParams = array(':yearid' => $academicYearId, ':termid' => $termId, ':day' => $day, ':ignoreid' => $ignoreId, ':endtime' => $endTime, ':starttime' => $startTime);

    $query = $dbh->prepare($baseSql . " AND e.ClassId = :classid");
    $query->execute(array(':yearid' => $academicYearId, ':termid' => $termId, ':day' => $day, ':ignoreid' => $ignoreId, ':endtime' => $endTime, ':starttime' => $startTime, ':classid' => $classId));
    foreach($query->fetchAll(PDO::FETCH_OBJ) as $row) {
        $conflicts[] = "Class conflict: " . $row->ClassName . " Section-" . $row->Section . " already has " . $row->SubjectName . " from " . $row->StartTime . " to " . $row->EndTime . ".";
    }

    if($teacherId) {
        $query = $dbh->prepare($baseSql . " AND e.TeacherId = :teacherid");
        $params = $baseParams;
        $params[':teacherid'] = $teacherId;
        $query->execute($params);
        foreach($query->fetchAll(PDO::FETCH_OBJ) as $row) {
            $conflicts[] = "Teacher conflict: " . ($row->FullName ?: "Selected teacher") . " is already teaching " . $row->SubjectName . " for " . $row->ClassName . " Section-" . $row->Section . ".";
        }
    }

    if($roomId) {
        $query = $dbh->prepare($baseSql . " AND e.RoomId = :roomid");
        $params = $baseParams;
        $params[':roomid'] = $roomId;
        $query->execute($params);
        foreach($query->fetchAll(PDO::FETCH_OBJ) as $row) {
            $conflicts[] = "Room conflict: " . ($row->RoomName ?: "Selected room") . " is already booked for " . $row->ClassName . " Section-" . $row->Section . ".";
        }
    }

    return $conflicts;
}

function timetable_exam_invigilators($dbh, $sessionId)
{
    // Keep older sessions that only have InvigilatorId visible until they are edited.
    $q = $dbh->prepare('SELECT InvigilatorId FROM tblexamtimetableentries WHERE id=? AND InvigilatorId IS NOT NULL UNION SELECT TeacherId FROM tblexaminvigilators WHERE SessionId=?');
    $q->execute([$sessionId, $sessionId]);
    return array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
}

function timetable_exam_conflicts($dbh, $examId, $classId, $roomId, $invigilators, $examDate, $startTime, $endTime, $ignoreId = 0)
{
    // ExamId remains in the signature for existing callers; resources are shared across exams.
    $teachers = is_array($invigilators) ? $invigilators : ($invigilators ? [$invigilators] : []);
    $q = $dbh->prepare("SELECT id,ClassId,RoomId FROM tblexamtimetableentries WHERE ExamDate=? AND Status NOT IN ('cancelled','archived') AND id<>? AND StartTime<? AND ?<EndTime");
    $q->execute([$examDate, $ignoreId, $endTime, $startTime]);
    $conflicts = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((int)$row['ClassId'] === (int)$classId) $conflicts[] = 'Class conflict with examination session #'.$row['id'].'.';
        if ($roomId && (int)$row['RoomId'] === (int)$roomId) $conflicts[] = 'Room conflict with examination session #'.$row['id'].'.';
        if (array_intersect($teachers, timetable_exam_invigilators($dbh, $row['id']))) $conflicts[] = 'Invigilator conflict with examination session #'.$row['id'].'.';
    }
    return $conflicts;
}

function timetable_record_version($dbh, $type, $relatedId, $action, $previousValue, $newValue, $reason, $createdBy)
{
    $versionQuery = $dbh->prepare("SELECT COALESCE(MAX(VersionNo), 0) + 1 FROM tbltimetableversions WHERE TimetableType = :type AND RelatedId = :relatedid");
    $versionQuery->execute(array(':type' => $type, ':relatedid' => $relatedId));
    $versionNo = $versionQuery->fetchColumn();

    $sql = "INSERT INTO tbltimetableversions(TimetableType, RelatedId, VersionNo, Action, PreviousValue, NewValue, Reason, CreatedBy)
            VALUES(:type, :relatedid, :versionno, :action, :previousvalue, :newvalue, :reason, :createdby)";
    $query = $dbh->prepare($sql);
    $query->execute(array(':type' => $type, ':relatedid' => $relatedId, ':versionno' => $versionNo, ':action' => $action, ':previousvalue' => $previousValue, ':newvalue' => $newValue, ':reason' => $reason, ':createdby' => $createdBy));
}

function timetable_notify_user($dbh, $teacherId, $classId, $title, $message)
{
    $category = stripos($title, 'exam') !== false ? 'EXAM_TIMETABLE' : 'TIMETABLE';
    return notification_create($dbh, $teacherId, $title, $message, array(
        'category' => $category,
        'type' => strtoupper(str_replace(' ', '_', $title)),
        'class_id' => $classId,
        'related_entity_type' => 'tblclasses',
        'related_entity_id' => $classId,
        'action_url' => $category === 'EXAM_TIMETABLE' ? 'teacher-timetable.php?view=exams' : 'teacher-timetable.php'
    ));
}
?>
