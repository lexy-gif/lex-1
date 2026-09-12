-- Complete empty SRMS schema. No accounts, credentials or school records.
-- For a NEW database only; existing installations use migration scripts.

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblacademiccalendar` (
  `id` int NOT NULL AUTO_INCREMENT,
  `AcademicYearId` int DEFAULT NULL,
  `TermId` int DEFAULT NULL,
  `EventTitle` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `EventType` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `StartDate` date NOT NULL,
  `EndDate` date DEFAULT NULL,
  `Description` text COLLATE utf8mb4_general_ci,
  `Status` tinyint(1) NOT NULL DEFAULT '1',
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_calendar_dates` (`StartDate`,`EndDate`),
  KEY `fk_calendar_year` (`AcademicYearId`),
  KEY `fk_calendar_term` (`TermId`),
  CONSTRAINT `fk_calendar_term` FOREIGN KEY (`TermId`) REFERENCES `tblterms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_calendar_year` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblacademicinterventions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `StudentId` int NOT NULL,
  `SubjectId` int NOT NULL,
  `TeacherId` int NOT NULL,
  `AcademicYearId` int NOT NULL,
  `TermId` int NOT NULL,
  `Reason` text NOT NULL,
  `ActionPlan` text NOT NULL,
  `ReviewDate` date NOT NULL,
  `Status` varchar(20) NOT NULL DEFAULT 'active',
  `ReviewNotes` text,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `StudentId` (`StudentId`),
  KEY `SubjectId` (`SubjectId`),
  KEY `TeacherId` (`TeacherId`),
  KEY `AcademicYearId` (`AcademicYearId`),
  KEY `TermId` (`TermId`),
  CONSTRAINT `tblacademicinterventions_ibfk_1` FOREIGN KEY (`StudentId`) REFERENCES `tblstudents` (`StudentId`),
  CONSTRAINT `tblacademicinterventions_ibfk_2` FOREIGN KEY (`SubjectId`) REFERENCES `tblsubjects` (`id`),
  CONSTRAINT `tblacademicinterventions_ibfk_3` FOREIGN KEY (`TeacherId`) REFERENCES `tblusers` (`id`),
  CONSTRAINT `tblacademicinterventions_ibfk_4` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`),
  CONSTRAINT `tblacademicinterventions_ibfk_5` FOREIGN KEY (`TermId`) REFERENCES `tblterms` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblacademicsettings` (
  `id` int NOT NULL,
  `WorkloadLow` int DEFAULT NULL,
  `WorkloadHigh` int DEFAULT NULL,
  `SupportThreshold` decimal(5,2) DEFAULT NULL,
  `SeniorElectiveCount` int NOT NULL DEFAULT '3',
  `SeniorLanguageSubjectId` int DEFAULT NULL,
  `SeniorMathSubjectId` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `SeniorLanguageSubjectId` (`SeniorLanguageSubjectId`),
  KEY `SeniorMathSubjectId` (`SeniorMathSubjectId`),
  CONSTRAINT `tblacademicsettings_ibfk_1` FOREIGN KEY (`SeniorLanguageSubjectId`) REFERENCES `tblsubjects` (`id`),
  CONSTRAINT `tblacademicsettings_ibfk_2` FOREIGN KEY (`SeniorMathSubjectId`) REFERENCES `tblsubjects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblacademicyears` (
  `id` int NOT NULL AUTO_INCREMENT,
  `AcademicYear` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `StartDate` date DEFAULT NULL,
  `EndDate` date DEFAULT NULL,
  `IsActive` tinyint(1) NOT NULL DEFAULT '0',
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_academic_year` (`AcademicYear`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblassessmentoutcomes` (
  `AssessmentId` int NOT NULL,
  `OutcomeId` int NOT NULL,
  PRIMARY KEY (`AssessmentId`,`OutcomeId`),
  KEY `OutcomeId` (`OutcomeId`),
  CONSTRAINT `tblassessmentoutcomes_ibfk_1` FOREIGN KEY (`AssessmentId`) REFERENCES `tblassessments` (`id`),
  CONSTRAINT `tblassessmentoutcomes_ibfk_2` FOREIGN KEY (`OutcomeId`) REFERENCES `tbllearningoutcomes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblassessmentresults` (
  `id` int NOT NULL AUTO_INCREMENT,
  `AssessmentId` int NOT NULL,
  `StudentId` int NOT NULL,
  `Score` decimal(8,2) DEFAULT NULL,
  `PerformanceLevelId` int DEFAULT NULL,
  `Evidence` text,
  `RecordedBy` varchar(100) NOT NULL,
  `UpdationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `AssessmentId` (`AssessmentId`,`StudentId`),
  KEY `StudentId` (`StudentId`),
  KEY `PerformanceLevelId` (`PerformanceLevelId`),
  CONSTRAINT `tblassessmentresults_ibfk_1` FOREIGN KEY (`AssessmentId`) REFERENCES `tblassessments` (`id`),
  CONSTRAINT `tblassessmentresults_ibfk_2` FOREIGN KEY (`StudentId`) REFERENCES `tblstudents` (`StudentId`),
  CONSTRAINT `tblassessmentresults_ibfk_3` FOREIGN KEY (`PerformanceLevelId`) REFERENCES `tblperformancelevels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblassessments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `Title` varchar(200) NOT NULL,
  `AssessmentTypeId` int NOT NULL,
  `SubjectId` int NOT NULL,
  `ClassId` int NOT NULL,
  `AcademicYearId` int NOT NULL,
  `TermId` int NOT NULL,
  `TeacherId` int NOT NULL,
  `MaximumScore` decimal(8,2) DEFAULT NULL,
  `AssessmentDate` date NOT NULL,
  `Status` varchar(20) NOT NULL DEFAULT 'draft',
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `AcademicYearId` (`AcademicYearId`,`TermId`,`ClassId`,`SubjectId`),
  KEY `AssessmentTypeId` (`AssessmentTypeId`),
  KEY `SubjectId` (`SubjectId`),
  KEY `ClassId` (`ClassId`),
  KEY `TermId` (`TermId`),
  KEY `TeacherId` (`TeacherId`),
  CONSTRAINT `tblassessments_ibfk_1` FOREIGN KEY (`AssessmentTypeId`) REFERENCES `tblassessmenttypes` (`id`),
  CONSTRAINT `tblassessments_ibfk_2` FOREIGN KEY (`SubjectId`) REFERENCES `tblsubjects` (`id`),
  CONSTRAINT `tblassessments_ibfk_3` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`),
  CONSTRAINT `tblassessments_ibfk_4` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`),
  CONSTRAINT `tblassessments_ibfk_5` FOREIGN KEY (`TermId`) REFERENCES `tblterms` (`id`),
  CONSTRAINT `tblassessments_ibfk_6` FOREIGN KEY (`TeacherId`) REFERENCES `tblusers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblassessmenttypes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `Name` varchar(100) NOT NULL,
  `Status` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `Name` (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblattendance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `StudentId` int NOT NULL,
  `ClassId` int NOT NULL,
  `AttendanceDate` date NOT NULL,
  `Status` enum('present','absent','late','excused') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'present',
  `Remarks` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `RecordedBy` int DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_attendance_date` (`StudentId`,`AttendanceDate`),
  KEY `idx_attendance_class_date` (`ClassId`,`AttendanceDate`),
  CONSTRAINT `fk_attendance_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`),
  CONSTRAINT `fk_attendance_student` FOREIGN KEY (`StudentId`) REFERENCES `tblstudents` (`StudentId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblauditlog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `Actor` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Action` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `EntityType` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `EntityId` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Details` text COLLATE utf8mb4_general_ci,
  `IpAddress` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_entity` (`EntityType`,`EntityId`),
  KEY `idx_audit_action_date` (`Action`,`CreationDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblclasses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ClassName` varchar(80) DEFAULT NULL,
  `ClassNameNumeric` int DEFAULT NULL,
  `Section` varchar(5) DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL,
  `GradeId` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `GradeId` (`GradeId`),
  CONSTRAINT `tblclasses_ibfk_1` FOREIGN KEY (`GradeId`) REFERENCES `tblgrades` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblclassteacherassignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `TeacherId` int NOT NULL,
  `ClassId` int NOT NULL,
  `AcademicYearId` int NOT NULL,
  `Status` tinyint NOT NULL DEFAULT '1',
  `AssignedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `EndedAt` timestamp NULL DEFAULT NULL,
  `ActiveClassId` int GENERATED ALWAYS AS ((case when (`Status` = 1) then `ClassId` else NULL end)) STORED,
  `ActiveTeacherId` int GENERATED ALWAYS AS ((case when (`Status` = 1) then `TeacherId` else NULL end)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_class_teacher_year` (`ActiveClassId`,`AcademicYearId`),
  UNIQUE KEY `uk_teacher_class_year` (`ActiveTeacherId`,`AcademicYearId`),
  KEY `TeacherId` (`TeacherId`),
  KEY `ClassId` (`ClassId`),
  KEY `AcademicYearId` (`AcademicYearId`),
  CONSTRAINT `tblclassteacherassignments_ibfk_1` FOREIGN KEY (`TeacherId`) REFERENCES `tblusers` (`id`),
  CONSTRAINT `tblclassteacherassignments_ibfk_2` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`),
  CONSTRAINT `tblclassteacherassignments_ibfk_3` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblclasstimetableentries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `AcademicYearId` int NOT NULL,
  `TermId` int NOT NULL,
  `ClassId` int NOT NULL,
  `SubjectId` int NOT NULL,
  `TeacherId` int DEFAULT NULL,
  `RoomId` int DEFAULT NULL,
  `DayOfWeek` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') COLLATE utf8mb4_general_ci NOT NULL,
  `StartTime` time NOT NULL,
  `EndTime` time NOT NULL,
  `Status` enum('draft','ready_for_review','approved','published','updated','archived','cancelled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `ChangeReason` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `CreatedBy` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `PathwayId` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_class_timetable_class` (`AcademicYearId`,`TermId`,`ClassId`,`DayOfWeek`),
  KEY `idx_class_timetable_teacher` (`TeacherId`,`DayOfWeek`,`StartTime`,`EndTime`),
  KEY `idx_class_timetable_room` (`RoomId`,`DayOfWeek`,`StartTime`,`EndTime`),
  KEY `fk_cte_term` (`TermId`),
  KEY `fk_cte_class` (`ClassId`),
  KEY `fk_cte_subject` (`SubjectId`),
  KEY `PathwayId` (`PathwayId`),
  CONSTRAINT `fk_cte_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cte_room` FOREIGN KEY (`RoomId`) REFERENCES `tblrooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cte_subject` FOREIGN KEY (`SubjectId`) REFERENCES `tblsubjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cte_teacher` FOREIGN KEY (`TeacherId`) REFERENCES `tblusers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cte_term` FOREIGN KEY (`TermId`) REFERENCES `tblterms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cte_year` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tblclasstimetableentries_ibfk_1` FOREIGN KEY (`PathwayId`) REFERENCES `tblpathways` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblcompetencies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `SubjectId` int NOT NULL,
  `Title` varchar(200) NOT NULL,
  `Description` text,
  `Status` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `SubjectId` (`SubjectId`),
  CONSTRAINT `tblcompetencies_ibfk_1` FOREIGN KEY (`SubjectId`) REFERENCES `tblsubjects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblcurriculumcoverage` (
  `id` int NOT NULL AUTO_INCREMENT,
  `TeacherId` int NOT NULL,
  `ClassId` int NOT NULL,
  `SubjectId` int NOT NULL,
  `AcademicYearId` int NOT NULL,
  `TermId` int NOT NULL,
  `ContentReference` varchar(255) NOT NULL,
  `ExpectedProgress` decimal(5,2) NOT NULL,
  `ActualProgress` decimal(5,2) NOT NULL,
  `ReportDate` date NOT NULL,
  `Notes` text,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `AcademicYearId` (`AcademicYearId`,`TermId`,`ClassId`,`SubjectId`,`ReportDate`),
  KEY `TeacherId` (`TeacherId`),
  KEY `ClassId` (`ClassId`),
  KEY `SubjectId` (`SubjectId`),
  KEY `TermId` (`TermId`),
  CONSTRAINT `tblcurriculumcoverage_ibfk_1` FOREIGN KEY (`TeacherId`) REFERENCES `tblusers` (`id`),
  CONSTRAINT `tblcurriculumcoverage_ibfk_2` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`),
  CONSTRAINT `tblcurriculumcoverage_ibfk_3` FOREIGN KEY (`SubjectId`) REFERENCES `tblsubjects` (`id`),
  CONSTRAINT `tblcurriculumcoverage_ibfk_4` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`),
  CONSTRAINT `tblcurriculumcoverage_ibfk_5` FOREIGN KEY (`TermId`) REFERENCES `tblterms` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbldean` (
  `id` int NOT NULL AUTO_INCREMENT,
  `UserName` varchar(100) DEFAULT NULL,
  `Password` varchar(255) DEFAULT NULL,
  `updationDate` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dean_username` (`UserName`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbldeanapprovals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ClassId` int NOT NULL,
  `ExamId` int NOT NULL,
  `ApprovedBy` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `Status` enum('pending','approved','rejected','published') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `DecisionReason` text COLLATE utf8mb4_general_ci,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dean_approval_scope` (`ClassId`,`ExamId`),
  KEY `idx_dean_approvals_status` (`Status`),
  KEY `fk_dean_approvals_exam` (`ExamId`),
  CONSTRAINT `fk_dean_approvals_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dean_approvals_exam` FOREIGN KEY (`ExamId`) REFERENCES `tblexams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbldepartmentpermissions` (
  `TeacherId` int NOT NULL,
  `DepartmentId` int NOT NULL,
  `CanReadAcademic` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`TeacherId`,`DepartmentId`),
  KEY `DepartmentId` (`DepartmentId`),
  CONSTRAINT `tbldepartmentpermissions_ibfk_1` FOREIGN KEY (`TeacherId`) REFERENCES `tblusers` (`id`),
  CONSTRAINT `tbldepartmentpermissions_ibfk_2` FOREIGN KEY (`DepartmentId`) REFERENCES `tbldepartments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbldepartments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `DepartmentName` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `HeadTeacherId` int DEFAULT NULL,
  `Status` tinyint(1) NOT NULL DEFAULT '1',
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_department_name` (`DepartmentName`),
  KEY `fk_departments_head_teacher` (`HeadTeacherId`),
  CONSTRAINT `fk_departments_head_teacher` FOREIGN KEY (`HeadTeacherId`) REFERENCES `tblusers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblexaminvigilators` (
  `SessionId` int NOT NULL,
  `TeacherId` int NOT NULL,
  PRIMARY KEY (`SessionId`,`TeacherId`),
  KEY `TeacherId` (`TeacherId`),
  CONSTRAINT `tblexaminvigilators_ibfk_1` FOREIGN KEY (`SessionId`) REFERENCES `tblexamtimetableentries` (`id`),
  CONSTRAINT `tblexaminvigilators_ibfk_2` FOREIGN KEY (`TeacherId`) REFERENCES `tblusers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblexams` (
  `id` int NOT NULL AUTO_INCREMENT,
  `AcademicYearId` int NOT NULL,
  `TermId` int NOT NULL,
  `ExamName` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `ClassId` int DEFAULT NULL,
  `StartDate` date DEFAULT NULL,
  `EndDate` date DEFAULT NULL,
  `MarksOpenDate` date DEFAULT NULL,
  `MarksDeadline` date DEFAULT NULL,
  `Status` enum('draft','marks_entry','submitted','under_review','approved','published','archived') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `EntryLocked` tinyint NOT NULL DEFAULT '0',
  `MaximumMarks` decimal(8,2) NOT NULL DEFAULT '100.00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_exam_scope` (`AcademicYearId`,`TermId`,`ExamName`,`ClassId`),
  KEY `idx_exams_term` (`TermId`),
  KEY `idx_exams_class` (`ClassId`),
  CONSTRAINT `fk_exams_academic_year` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`),
  CONSTRAINT `fk_exams_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`),
  CONSTRAINT `fk_exams_term` FOREIGN KEY (`TermId`) REFERENCES `tblterms` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblexamtimetableentries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ExamId` int NOT NULL,
  `ClassId` int NOT NULL,
  `SubjectId` int NOT NULL,
  `RoomId` int DEFAULT NULL,
  `InvigilatorId` int DEFAULT NULL,
  `ExamDate` date NOT NULL,
  `StartTime` time NOT NULL,
  `EndTime` time NOT NULL,
  `Status` enum('draft','ready_for_review','approved','published','updated','archived','cancelled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `ChangeReason` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `CreatedBy` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `PathwayId` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_exam_timetable_class` (`ExamId`,`ClassId`,`ExamDate`),
  KEY `idx_exam_timetable_room` (`RoomId`,`ExamDate`,`StartTime`,`EndTime`),
  KEY `idx_exam_timetable_invigilator` (`InvigilatorId`,`ExamDate`,`StartTime`,`EndTime`),
  KEY `fk_ete_class` (`ClassId`),
  KEY `fk_ete_subject` (`SubjectId`),
  KEY `PathwayId` (`PathwayId`),
  CONSTRAINT `fk_ete_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ete_exam` FOREIGN KEY (`ExamId`) REFERENCES `tblexams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ete_invigilator` FOREIGN KEY (`InvigilatorId`) REFERENCES `tblusers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ete_room` FOREIGN KEY (`RoomId`) REFERENCES `tblrooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ete_subject` FOREIGN KEY (`SubjectId`) REFERENCES `tblsubjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tblexamtimetableentries_ibfk_1` FOREIGN KEY (`PathwayId`) REFERENCES `tblpathways` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblfeestructures` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ClassId` int NOT NULL,
  `FeeTypeId` int NOT NULL,
  `AcademicYearId` int NOT NULL,
  `TermId` int DEFAULT NULL,
  `Amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `DueDate` date DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fee_structure` (`ClassId`,`FeeTypeId`,`AcademicYearId`,`TermId`),
  KEY `idx_fee_structure_year` (`AcademicYearId`),
  KEY `idx_fee_structure_term` (`TermId`),
  KEY `fk_fee_structure_type` (`FeeTypeId`),
  CONSTRAINT `fk_fee_structure_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`),
  CONSTRAINT `fk_fee_structure_term` FOREIGN KEY (`TermId`) REFERENCES `tblterms` (`id`),
  CONSTRAINT `fk_fee_structure_type` FOREIGN KEY (`FeeTypeId`) REFERENCES `tblfeetypes` (`id`),
  CONSTRAINT `fk_fee_structure_year` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblfeetypes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `FeeName` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `Description` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Status` tinyint(1) NOT NULL DEFAULT '1',
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fee_name` (`FeeName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblgrades` (
  `id` int NOT NULL AUTO_INCREMENT,
  `SchoolLevelId` int NOT NULL,
  `Name` varchar(100) NOT NULL,
  `GradeNumber` int NOT NULL,
  `PathwayEntry` tinyint NOT NULL DEFAULT '0',
  `GuidanceEligible` tinyint NOT NULL DEFAULT '0',
  `Status` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `Name` (`Name`),
  UNIQUE KEY `GradeNumber` (`GradeNumber`),
  KEY `SchoolLevelId` (`SchoolLevelId`),
  CONSTRAINT `tblgrades_ibfk_1` FOREIGN KEY (`SchoolLevelId`) REFERENCES `tblschoollevels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblgradingscales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `Grade` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `MinMark` decimal(5,2) NOT NULL,
  `MaxMark` decimal(5,2) NOT NULL,
  `Remark` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_grade` (`Grade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbllearningoutcomes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `CompetencyId` int NOT NULL,
  `Title` varchar(200) NOT NULL,
  `Status` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `CompetencyId` (`CompetencyId`),
  CONSTRAINT `tbllearningoutcomes_ibfk_1` FOREIGN KEY (`CompetencyId`) REFERENCES `tblcompetencies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblloginattempts` (
  `AttemptKey` char(64) NOT NULL,
  `Failures` int NOT NULL DEFAULT '0',
  `WindowStarted` datetime NOT NULL,
  `LockedUntil` datetime DEFAULT NULL,
  PRIMARY KEY (`AttemptKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblnotice` (
  `id` int NOT NULL AUTO_INCREMENT,
  `noticeTitle` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `noticeDetails` mediumtext COLLATE utf8mb4_general_ci,
  `postingDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblnotificationdeliveries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `NotificationId` int DEFAULT NULL,
  `UserId` int NOT NULL,
  `Channel` enum('IN_APP','EMAIL','SMS','WHATSAPP','PUSH') COLLATE utf8mb4_general_ci NOT NULL,
  `Destination` varchar(180) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Status` enum('PENDING','SENT','FAILED','RETRYING','SKIPPED') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PENDING',
  `SentAt` datetime DEFAULT NULL,
  `FailedAt` datetime DEFAULT NULL,
  `RetryCount` int NOT NULL DEFAULT '0',
  `ErrorMessage` text COLLATE utf8mb4_general_ci,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notification_deliveries_notification` (`NotificationId`),
  KEY `idx_notification_deliveries_user` (`UserId`,`Channel`,`Status`),
  CONSTRAINT `fk_notification_deliveries_notification` FOREIGN KEY (`NotificationId`) REFERENCES `tblteachernotifications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notification_deliveries_user` FOREIGN KEY (`UserId`) REFERENCES `tblusers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblnotificationpreferences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `UserId` int NOT NULL,
  `Category` enum('ACCOUNT','CLASS_ASSIGNMENT','SUBJECT_ASSIGNMENT','TIMETABLE','EXAM_TIMETABLE','RESULTS','MARKS','DEADLINE','ATTENDANCE','SYSTEM') COLLATE utf8mb4_general_ci NOT NULL,
  `EmailEnabled` tinyint(1) NOT NULL DEFAULT '1',
  `InAppEnabled` tinyint(1) NOT NULL DEFAULT '1',
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_notification_preferences_user_category` (`UserId`,`Category`),
  CONSTRAINT `fk_notification_preferences_user` FOREIGN KEY (`UserId`) REFERENCES `tblusers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbloutcomeobservations` (
  `AssessmentId` int NOT NULL,
  `StudentId` int NOT NULL,
  `OutcomeId` int NOT NULL,
  `PerformanceLevelId` int NOT NULL,
  `Evidence` text,
  PRIMARY KEY (`AssessmentId`,`StudentId`,`OutcomeId`),
  KEY `AssessmentId` (`AssessmentId`,`OutcomeId`),
  KEY `StudentId` (`StudentId`),
  KEY `PerformanceLevelId` (`PerformanceLevelId`),
  CONSTRAINT `tbloutcomeobservations_ibfk_1` FOREIGN KEY (`AssessmentId`, `OutcomeId`) REFERENCES `tblassessmentoutcomes` (`AssessmentId`, `OutcomeId`),
  CONSTRAINT `tbloutcomeobservations_ibfk_2` FOREIGN KEY (`StudentId`) REFERENCES `tblstudents` (`StudentId`),
  CONSTRAINT `tbloutcomeobservations_ibfk_3` FOREIGN KEY (`PerformanceLevelId`) REFERENCES `tblperformancelevels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblparentnotifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ParentId` int NOT NULL,
  `StudentId` int NOT NULL,
  `EventKey` varchar(100) NOT NULL,
  `Title` varchar(150) NOT NULL,
  `Message` text NOT NULL,
  `ActionUrl` varchar(255) NOT NULL,
  `ReadAt` datetime DEFAULT NULL,
  `CreationDate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_parent_event` (`ParentId`,`StudentId`,`EventKey`),
  KEY `idx_parent_unread` (`ParentId`,`ReadAt`,`id`),
  KEY `StudentId` (`StudentId`),
  CONSTRAINT `tblparentnotifications_ibfk_1` FOREIGN KEY (`ParentId`) REFERENCES `tblusers` (`id`),
  CONSTRAINT `tblparentnotifications_ibfk_2` FOREIGN KEY (`StudentId`) REFERENCES `tblstudents` (`StudentId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblparentsms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `NotificationId` int NOT NULL,
  `ParentId` int NOT NULL,
  `StudentId` int NOT NULL,
  `Destination` varchar(30) NOT NULL,
  `Message` text NOT NULL,
  `Status` enum('pending','processing','accepted','failed','uncertain','skipped') NOT NULL DEFAULT 'pending',
  `Attempts` int NOT NULL DEFAULT '0',
  `ProviderReference` varchar(150) DEFAULT NULL,
  `ProviderResponse` text,
  `ErrorMessage` varchar(500) DEFAULT NULL,
  `AttemptedAt` datetime DEFAULT NULL,
  `CreationDate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_parent_sms_event` (`NotificationId`),
  KEY `idx_parent_sms_queue` (`Status`,`id`),
  KEY `ParentId` (`ParentId`),
  KEY `StudentId` (`StudentId`),
  CONSTRAINT `tblparentsms_ibfk_1` FOREIGN KEY (`NotificationId`) REFERENCES `tblparentnotifications` (`id`),
  CONSTRAINT `tblparentsms_ibfk_2` FOREIGN KEY (`ParentId`) REFERENCES `tblusers` (`id`),
  CONSTRAINT `tblparentsms_ibfk_3` FOREIGN KEY (`StudentId`) REFERENCES `tblstudents` (`StudentId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblparentstudents` (
  `ParentId` int NOT NULL,
  `StudentId` int NOT NULL,
  `Relationship` varchar(50) NOT NULL DEFAULT 'Guardian',
  `Status` tinyint NOT NULL DEFAULT '1',
  `NotifyResults` tinyint NOT NULL DEFAULT '1',
  `CreatedBy` varchar(100) NOT NULL,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ParentId`,`StudentId`),
  KEY `idx_guardian_student` (`StudentId`,`Status`),
  CONSTRAINT `tblparentstudents_ibfk_1` FOREIGN KEY (`ParentId`) REFERENCES `tblusers` (`id`),
  CONSTRAINT `tblparentstudents_ibfk_2` FOREIGN KEY (`StudentId`) REFERENCES `tblstudents` (`StudentId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblpathwayallocationsubjects` (
  `AllocationId` int NOT NULL,
  `SubjectId` int NOT NULL,
  `SubjectType` enum('core','elective') NOT NULL,
  PRIMARY KEY (`AllocationId`,`SubjectId`),
  KEY `SubjectId` (`SubjectId`),
  CONSTRAINT `tblpathwayallocationsubjects_ibfk_1` FOREIGN KEY (`AllocationId`) REFERENCES `tblstudentpathways` (`id`),
  CONSTRAINT `tblpathwayallocationsubjects_ibfk_2` FOREIGN KEY (`SubjectId`) REFERENCES `tblsubjects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblpathwayguidance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `StudentId` int NOT NULL,
  `AcademicYearId` int NOT NULL,
  `Interests` text,
  `ProposedPathwayId` int DEFAULT NULL,
  `ChosenTrackId` int DEFAULT NULL,
  `PreferredCombinationId` int DEFAULT NULL,
  `ExternalAssessmentReference` text,
  `Notes` text,
  `RecordedBy` varchar(100) NOT NULL,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `StudentId` (`StudentId`),
  KEY `AcademicYearId` (`AcademicYearId`),
  KEY `ProposedPathwayId` (`ProposedPathwayId`),
  KEY `ChosenTrackId` (`ChosenTrackId`),
  KEY `PreferredCombinationId` (`PreferredCombinationId`),
  CONSTRAINT `tblpathwayguidance_ibfk_1` FOREIGN KEY (`StudentId`) REFERENCES `tblstudents` (`StudentId`),
  CONSTRAINT `tblpathwayguidance_ibfk_2` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`),
  CONSTRAINT `tblpathwayguidance_ibfk_3` FOREIGN KEY (`ProposedPathwayId`) REFERENCES `tblpathways` (`id`),
  CONSTRAINT `tblpathwayguidance_ibfk_4` FOREIGN KEY (`ChosenTrackId`) REFERENCES `tblpathwaytracks` (`id`),
  CONSTRAINT `tblpathwayguidance_ibfk_5` FOREIGN KEY (`PreferredCombinationId`) REFERENCES `tblschoolcombinations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblpathways` (
  `id` int NOT NULL AUTO_INCREMENT,
  `Name` varchar(120) NOT NULL,
  `Description` text,
  `Status` tinyint NOT NULL DEFAULT '1',
  `MathSubjectId` int DEFAULT NULL,
  `ElectiveCount` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `Name` (`Name`),
  KEY `MathSubjectId` (`MathSubjectId`),
  CONSTRAINT `tblpathways_ibfk_1` FOREIGN KEY (`MathSubjectId`) REFERENCES `tblsubjects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblpathwaysubjects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `PathwayId` int NOT NULL,
  `TrackId` int DEFAULT NULL,
  `SubjectId` int NOT NULL,
  `TrackScope` int GENERATED ALWAYS AS (coalesce(`TrackId`,0)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pathway_subject` (`PathwayId`,`TrackScope`,`SubjectId`),
  KEY `TrackId` (`TrackId`),
  KEY `SubjectId` (`SubjectId`),
  CONSTRAINT `tblpathwaysubjects_ibfk_1` FOREIGN KEY (`PathwayId`) REFERENCES `tblpathways` (`id`),
  CONSTRAINT `tblpathwaysubjects_ibfk_2` FOREIGN KEY (`TrackId`) REFERENCES `tblpathwaytracks` (`id`),
  CONSTRAINT `tblpathwaysubjects_ibfk_3` FOREIGN KEY (`SubjectId`) REFERENCES `tblsubjects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblpathwaytracks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `PathwayId` int NOT NULL,
  `Name` varchar(150) NOT NULL,
  `Status` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `PathwayId` (`PathwayId`,`Name`),
  CONSTRAINT `tblpathwaytracks_ibfk_1` FOREIGN KEY (`PathwayId`) REFERENCES `tblpathways` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblpayments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `StudentId` int NOT NULL,
  `FeeStructureId` int NOT NULL,
  `AmountPaid` decimal(10,2) NOT NULL DEFAULT '0.00',
  `PaymentDate` date NOT NULL,
  `PaymentMode` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ReceiptNo` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `ReceivedBy` int DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_receipt_no` (`ReceiptNo`),
  KEY `idx_payments_student` (`StudentId`),
  KEY `idx_payments_fee_structure` (`FeeStructureId`),
  CONSTRAINT `fk_payments_fee_structure` FOREIGN KEY (`FeeStructureId`) REFERENCES `tblfeestructures` (`id`),
  CONSTRAINT `fk_payments_student` FOREIGN KEY (`StudentId`) REFERENCES `tblstudents` (`StudentId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblperformancelevels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `Name` varchar(100) NOT NULL,
  `Description` text,
  `SortOrder` int NOT NULL DEFAULT '0',
  `Status` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `Name` (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblresponsibilitytypes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `Name` varchar(150) NOT NULL,
  `Description` text,
  `Active` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `Name` (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblresult` (
  `id` int NOT NULL AUTO_INCREMENT,
  `StudentId` int DEFAULT NULL,
  `ClassId` int DEFAULT NULL,
  `ExamId` int DEFAULT NULL,
  `SubjectId` int DEFAULT NULL,
  `marks` decimal(8,2) NOT NULL,
  `PostingDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_result_student_subject_exam` (`StudentId`,`ClassId`,`SubjectId`,`ExamId`),
  KEY `idx_result_exam` (`ExamId`),
  KEY `fk_result_class` (`ClassId`),
  KEY `fk_result_subject` (`SubjectId`),
  CONSTRAINT `fk_result_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`),
  CONSTRAINT `fk_result_exam` FOREIGN KEY (`ExamId`) REFERENCES `tblexams` (`id`),
  CONSTRAINT `fk_result_student` FOREIGN KEY (`StudentId`) REFERENCES `tblstudents` (`StudentId`),
  CONSTRAINT `fk_result_subject` FOREIGN KEY (`SubjectId`) REFERENCES `tblsubjects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblresultpublications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ClassId` int NOT NULL,
  `ExamId` int NOT NULL,
  `PublishedBy` varchar(100) NOT NULL,
  `PublishedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `GradeScale` longtext,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_result_publication` (`ClassId`,`ExamId`),
  KEY `ExamId` (`ExamId`),
  CONSTRAINT `tblresultpublications_ibfk_1` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`),
  CONSTRAINT `tblresultpublications_ibfk_2` FOREIGN KEY (`ExamId`) REFERENCES `tblexams` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblresultreviews` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ClassId` int NOT NULL,
  `ExamId` int NOT NULL,
  `ReviewedBy` int NOT NULL,
  `Status` enum('pending','approved','correction_requested') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `CorrectionReason` text COLLATE utf8mb4_general_ci,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_result_review_scope` (`ClassId`,`ExamId`),
  KEY `idx_result_reviews_status` (`Status`),
  KEY `fk_result_reviews_exam` (`ExamId`),
  KEY `fk_result_reviews_teacher` (`ReviewedBy`),
  CONSTRAINT `fk_result_reviews_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_result_reviews_exam` FOREIGN KEY (`ExamId`) REFERENCES `tblexams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_result_reviews_teacher` FOREIGN KEY (`ReviewedBy`) REFERENCES `tblusers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblresultsubmissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ClassId` int NOT NULL,
  `SubjectId` int NOT NULL,
  `ExamId` int NOT NULL,
  `TeacherId` int NOT NULL,
  `Status` enum('draft','submitted') NOT NULL DEFAULT 'draft',
  `SubmittedAt` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_result_submission` (`ClassId`,`SubjectId`,`ExamId`),
  KEY `SubjectId` (`SubjectId`),
  KEY `ExamId` (`ExamId`),
  KEY `TeacherId` (`TeacherId`),
  CONSTRAINT `tblresultsubmissions_ibfk_1` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`),
  CONSTRAINT `tblresultsubmissions_ibfk_2` FOREIGN KEY (`SubjectId`) REFERENCES `tblsubjects` (`id`),
  CONSTRAINT `tblresultsubmissions_ibfk_3` FOREIGN KEY (`ExamId`) REFERENCES `tblexams` (`id`),
  CONSTRAINT `tblresultsubmissions_ibfk_4` FOREIGN KEY (`TeacherId`) REFERENCES `tblusers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblrooms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `RoomName` varchar(80) COLLATE utf8mb4_general_ci NOT NULL,
  `Capacity` int DEFAULT NULL,
  `Status` tinyint(1) NOT NULL DEFAULT '1',
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_room_name` (`RoomName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblscheduledreminders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ReminderType` enum('lesson','exam') COLLATE utf8mb4_general_ci NOT NULL,
  `RelatedId` int NOT NULL,
  `RecipientUserId` int DEFAULT NULL,
  `ReminderMinutes` int NOT NULL DEFAULT '15',
  `SentAt` timestamp NULL DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_scheduled_reminder` (`ReminderType`,`RelatedId`,`RecipientUserId`,`ReminderMinutes`),
  KEY `fk_scheduled_reminder_user` (`RecipientUserId`),
  CONSTRAINT `fk_scheduled_reminder_user` FOREIGN KEY (`RecipientUserId`) REFERENCES `tblusers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblschemamigrations` (
  `Name` varchar(150) NOT NULL,
  `AppliedAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblschoolcombinations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `TrackId` int NOT NULL,
  `AcademicYearId` int NOT NULL,
  `Code` varchar(50) DEFAULT NULL,
  `Name` varchar(150) NOT NULL,
  `Status` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `TrackId` (`TrackId`,`AcademicYearId`,`Name`),
  KEY `AcademicYearId` (`AcademicYearId`),
  CONSTRAINT `tblschoolcombinations_ibfk_1` FOREIGN KEY (`TrackId`) REFERENCES `tblpathwaytracks` (`id`),
  CONSTRAINT `tblschoolcombinations_ibfk_2` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblschoolcombinationsubjects` (
  `CombinationId` int NOT NULL,
  `SubjectId` int NOT NULL,
  PRIMARY KEY (`CombinationId`,`SubjectId`),
  KEY `SubjectId` (`SubjectId`),
  CONSTRAINT `tblschoolcombinationsubjects_ibfk_1` FOREIGN KEY (`CombinationId`) REFERENCES `tblschoolcombinations` (`id`),
  CONSTRAINT `tblschoolcombinationsubjects_ibfk_2` FOREIGN KEY (`SubjectId`) REFERENCES `tblsubjects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblschoollevels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `Name` varchar(100) NOT NULL,
  `SeniorSchool` tinyint NOT NULL DEFAULT '0',
  `Status` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `Name` (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblstudentenrollments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `StudentId` int NOT NULL,
  `AcademicYearId` int NOT NULL,
  `ClassId` int NOT NULL,
  `GradeId` int NOT NULL,
  `EnrolledAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `RecordedBy` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_enrollment_year` (`StudentId`,`AcademicYearId`),
  KEY `idx_enrollment_class_year` (`ClassId`,`AcademicYearId`),
  KEY `AcademicYearId` (`AcademicYearId`),
  KEY `GradeId` (`GradeId`),
  CONSTRAINT `tblstudentenrollments_ibfk_1` FOREIGN KEY (`StudentId`) REFERENCES `tblstudents` (`StudentId`),
  CONSTRAINT `tblstudentenrollments_ibfk_2` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`),
  CONSTRAINT `tblstudentenrollments_ibfk_3` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`),
  CONSTRAINT `tblstudentenrollments_ibfk_4` FOREIGN KEY (`GradeId`) REFERENCES `tblgrades` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblstudentpathways` (
  `id` int NOT NULL AUTO_INCREMENT,
  `StudentId` int NOT NULL,
  `ClassId` int NOT NULL,
  `CombinationId` int DEFAULT NULL,
  `AcademicYearId` int NOT NULL,
  `PlacementSource` varchar(150) NOT NULL,
  `ReferenceNotes` text,
  `Status` tinyint NOT NULL DEFAULT '1',
  `AssignedAt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `EndedAt` timestamp NULL DEFAULT NULL,
  `ActiveStudentId` int GENERATED ALWAYS AS ((case when (`Status` = 1) then `StudentId` else NULL end)) STORED,
  `SeniorLanguageSubjectId` int DEFAULT NULL,
  `SeniorMathSubjectId` int DEFAULT NULL,
  `PathwayId` int DEFAULT NULL,
  `TrackId` int DEFAULT NULL,
  `GradeId` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ActiveStudentId` (`ActiveStudentId`,`AcademicYearId`),
  KEY `StudentId` (`StudentId`),
  KEY `ClassId` (`ClassId`),
  KEY `CombinationId` (`CombinationId`),
  KEY `AcademicYearId` (`AcademicYearId`),
  KEY `SeniorLanguageSubjectId` (`SeniorLanguageSubjectId`),
  KEY `SeniorMathSubjectId` (`SeniorMathSubjectId`),
  KEY `PathwayId` (`PathwayId`),
  KEY `TrackId` (`TrackId`),
  KEY `GradeId` (`GradeId`),
  CONSTRAINT `tblstudentpathways_ibfk_1` FOREIGN KEY (`StudentId`) REFERENCES `tblstudents` (`StudentId`),
  CONSTRAINT `tblstudentpathways_ibfk_2` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`),
  CONSTRAINT `tblstudentpathways_ibfk_3` FOREIGN KEY (`CombinationId`) REFERENCES `tblschoolcombinations` (`id`),
  CONSTRAINT `tblstudentpathways_ibfk_4` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`),
  CONSTRAINT `tblstudentpathways_ibfk_5` FOREIGN KEY (`SeniorLanguageSubjectId`) REFERENCES `tblsubjects` (`id`),
  CONSTRAINT `tblstudentpathways_ibfk_6` FOREIGN KEY (`SeniorMathSubjectId`) REFERENCES `tblsubjects` (`id`),
  CONSTRAINT `tblstudentpathways_ibfk_7` FOREIGN KEY (`PathwayId`) REFERENCES `tblpathways` (`id`),
  CONSTRAINT `tblstudentpathways_ibfk_8` FOREIGN KEY (`TrackId`) REFERENCES `tblpathwaytracks` (`id`),
  CONSTRAINT `tblstudentpathways_ibfk_9` FOREIGN KEY (`GradeId`) REFERENCES `tblgrades` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblstudents` (
  `StudentId` int NOT NULL AUTO_INCREMENT,
  `StudentName` varchar(100) DEFAULT NULL,
  `RollId` varchar(100) DEFAULT NULL,
  `StudentEmail` varchar(100) DEFAULT NULL,
  `ParentPhone` varchar(30) DEFAULT NULL,
  `Gender` varchar(10) DEFAULT NULL,
  `DOB` varchar(100) DEFAULT NULL,
  `ClassId` int DEFAULT NULL,
  `RegDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL,
  `Status` int DEFAULT NULL,
  `SeniorLanguageSubjectId` int DEFAULT NULL,
  `SeniorMathSubjectId` int DEFAULT NULL,
  PRIMARY KEY (`StudentId`),
  UNIQUE KEY `uk_student_admission` (`RollId`),
  KEY `SeniorLanguageSubjectId` (`SeniorLanguageSubjectId`),
  KEY `SeniorMathSubjectId` (`SeniorMathSubjectId`),
  KEY `fk_student_class` (`ClassId`),
  CONSTRAINT `fk_student_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`),
  CONSTRAINT `tblstudents_ibfk_1` FOREIGN KEY (`SeniorLanguageSubjectId`) REFERENCES `tblsubjects` (`id`),
  CONSTRAINT `tblstudents_ibfk_2` FOREIGN KEY (`SeniorMathSubjectId`) REFERENCES `tblsubjects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblstudentsubjects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `StudentId` int NOT NULL,
  `SubjectId` int NOT NULL,
  `ClassId` int NOT NULL,
  `AcademicYearId` int NOT NULL,
  `Status` tinyint NOT NULL DEFAULT '1',
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `AssignmentSource` enum('manual','core','elective') NOT NULL DEFAULT 'manual',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_subject_year` (`StudentId`,`SubjectId`,`ClassId`,`AcademicYearId`),
  KEY `SubjectId` (`SubjectId`),
  KEY `ClassId` (`ClassId`),
  KEY `AcademicYearId` (`AcademicYearId`),
  CONSTRAINT `tblstudentsubjects_ibfk_1` FOREIGN KEY (`StudentId`) REFERENCES `tblstudents` (`StudentId`),
  CONSTRAINT `tblstudentsubjects_ibfk_2` FOREIGN KEY (`SubjectId`) REFERENCES `tblsubjects` (`id`),
  CONSTRAINT `tblstudentsubjects_ibfk_3` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`),
  CONSTRAINT `tblstudentsubjects_ibfk_4` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblsubjectcombination` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ClassId` int DEFAULT NULL,
  `SubjectId` int DEFAULT NULL,
  `status` int DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `Updationdate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblsubjectgrades` (
  `SubjectId` int NOT NULL,
  `GradeId` int NOT NULL,
  PRIMARY KEY (`SubjectId`,`GradeId`),
  KEY `GradeId` (`GradeId`),
  CONSTRAINT `tblsubjectgrades_ibfk_1` FOREIGN KEY (`SubjectId`) REFERENCES `tblsubjects` (`id`),
  CONSTRAINT `tblsubjectgrades_ibfk_2` FOREIGN KEY (`GradeId`) REFERENCES `tblgrades` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblsubjects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `SubjectName` varchar(100) NOT NULL,
  `SubjectCode` varchar(100) DEFAULT NULL,
  `Creationdate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL,
  `Status` tinyint NOT NULL DEFAULT '1',
  `DepartmentId` int DEFAULT NULL,
  `SeniorType` enum('core','elective') DEFAULT NULL,
  `CoreRole` enum('common','language','mathematics') DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `DepartmentId` (`DepartmentId`),
  CONSTRAINT `tblsubjects_ibfk_1` FOREIGN KEY (`DepartmentId`) REFERENCES `tbldepartments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblsubjectteacherassignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `TeacherId` int NOT NULL,
  `ClassId` int NOT NULL,
  `SubjectId` int NOT NULL,
  `AcademicYearId` int NOT NULL,
  `TermId` int DEFAULT NULL,
  `Status` tinyint(1) NOT NULL DEFAULT '1',
  `AssignedBy` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `EndedAt` timestamp NULL DEFAULT NULL,
  `ActiveSubjectId` int GENERATED ALWAYS AS ((case when (`Status` = 1) then `SubjectId` else NULL end)) STORED,
  `PeriodTermId` int GENERATED ALWAYS AS (coalesce(`TermId`,0)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_primary_subject_period` (`ClassId`,`ActiveSubjectId`,`AcademicYearId`,`PeriodTermId`),
  KEY `idx_subject_teacher_class` (`ClassId`,`SubjectId`),
  KEY `fk_academic_sta_subject` (`SubjectId`),
  KEY `fk_academic_sta_year` (`AcademicYearId`),
  KEY `fk_academic_sta_term` (`TermId`),
  KEY `idx_assignment_teacher` (`TeacherId`),
  CONSTRAINT `fk_academic_sta_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`),
  CONSTRAINT `fk_academic_sta_subject` FOREIGN KEY (`SubjectId`) REFERENCES `tblsubjects` (`id`),
  CONSTRAINT `fk_academic_sta_teacher` FOREIGN KEY (`TeacherId`) REFERENCES `tblusers` (`id`),
  CONSTRAINT `fk_academic_sta_term` FOREIGN KEY (`TermId`) REFERENCES `tblterms` (`id`),
  CONSTRAINT `fk_academic_sta_year` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblteacheravailability` (
  `id` int NOT NULL AUTO_INCREMENT,
  `TeacherId` int NOT NULL,
  `DayOfWeek` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') COLLATE utf8mb4_general_ci NOT NULL,
  `StartTime` time NOT NULL,
  `EndTime` time NOT NULL,
  `Reason` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_teacher_availability` (`TeacherId`,`DayOfWeek`,`StartTime`,`EndTime`),
  CONSTRAINT `fk_teacher_availability_teacher` FOREIGN KEY (`TeacherId`) REFERENCES `tblusers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblteachercomments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `StudentId` int NOT NULL,
  `ClassId` int NOT NULL,
  `ExamId` int DEFAULT NULL,
  `TeacherId` int NOT NULL,
  `CommentText` text COLLATE utf8mb4_general_ci NOT NULL,
  `Status` enum('draft','submitted','published') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_teacher_comment_scope` (`StudentId`,`ExamId`,`TeacherId`),
  KEY `idx_teacher_comments_class` (`ClassId`,`ExamId`),
  KEY `fk_teacher_comments_exam` (`ExamId`),
  KEY `fk_teacher_comments_teacher` (`TeacherId`),
  CONSTRAINT `fk_teacher_comments_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teacher_comments_exam` FOREIGN KEY (`ExamId`) REFERENCES `tblexams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_teacher_comments_student` FOREIGN KEY (`StudentId`) REFERENCES `tblstudents` (`StudentId`) ON DELETE CASCADE,
  CONSTRAINT `fk_teacher_comments_teacher` FOREIGN KEY (`TeacherId`) REFERENCES `tblusers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblteachernotifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `TeacherId` int DEFAULT NULL,
  `ClassId` int DEFAULT NULL,
  `Type` varchar(80) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'GENERAL',
  `Category` enum('ACCOUNT','CLASS_ASSIGNMENT','SUBJECT_ASSIGNMENT','TIMETABLE','EXAM_TIMETABLE','RESULTS','MARKS','DEADLINE','ATTENDANCE','SYSTEM') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'SYSTEM',
  `SubjectId` int DEFAULT NULL,
  `RelatedEntityType` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `RelatedEntityId` int DEFAULT NULL,
  `ActionUrl` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Title` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `Message` text COLLATE utf8mb4_general_ci NOT NULL,
  `IsRead` tinyint(1) NOT NULL DEFAULT '0',
  `ReadAt` datetime DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_teacher_notifications_teacher` (`TeacherId`,`IsRead`),
  KEY `idx_teacher_notifications_class` (`ClassId`,`IsRead`),
  KEY `idx_teacher_notifications_filter` (`TeacherId`,`Category`,`ReadAt`,`CreationDate`),
  KEY `idx_teacher_notifications_subject` (`SubjectId`),
  CONSTRAINT `fk_teacher_notifications_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teacher_notifications_teacher` FOREIGN KEY (`TeacherId`) REFERENCES `tblusers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblteacherresponsibilities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `TeacherId` int NOT NULL,
  `ResponsibilityTypeId` int NOT NULL,
  `AcademicYearId` int NOT NULL,
  `StartDate` date NOT NULL,
  `EndDate` date DEFAULT NULL,
  `Status` tinyint NOT NULL DEFAULT '1',
  `Notes` text,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ActiveTypeId` int GENERATED ALWAYS AS ((case when (`Status` = 1) then `ResponsibilityTypeId` else NULL end)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_teacher_responsibility` (`TeacherId`,`ActiveTypeId`,`AcademicYearId`),
  KEY `ResponsibilityTypeId` (`ResponsibilityTypeId`),
  KEY `AcademicYearId` (`AcademicYearId`),
  CONSTRAINT `tblteacherresponsibilities_ibfk_1` FOREIGN KEY (`TeacherId`) REFERENCES `tblusers` (`id`),
  CONSTRAINT `tblteacherresponsibilities_ibfk_2` FOREIGN KEY (`ResponsibilityTypeId`) REFERENCES `tblresponsibilitytypes` (`id`),
  CONSTRAINT `tblteacherresponsibilities_ibfk_3` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblterms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `AcademicYearId` int NOT NULL,
  `TermName` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `StartDate` date DEFAULT NULL,
  `EndDate` date DEFAULT NULL,
  `IsActive` tinyint(1) NOT NULL DEFAULT '0',
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_year_term` (`AcademicYearId`,`TermName`),
  KEY `idx_terms_year` (`AcademicYearId`),
  CONSTRAINT `fk_terms_academic_year` FOREIGN KEY (`AcademicYearId`) REFERENCES `tblacademicyears` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbltimetable` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ClassId` int NOT NULL,
  `SubjectId` int NOT NULL,
  `TeacherId` int DEFAULT NULL,
  `DayOfWeek` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') COLLATE utf8mb4_general_ci NOT NULL,
  `StartTime` time NOT NULL,
  `EndTime` time NOT NULL,
  `Room` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Status` enum('draft','published','archived') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_timetable_class_day` (`ClassId`,`DayOfWeek`),
  KEY `idx_timetable_teacher_day` (`TeacherId`,`DayOfWeek`),
  KEY `fk_timetable_subject` (`SubjectId`),
  CONSTRAINT `fk_timetable_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_timetable_subject` FOREIGN KEY (`SubjectId`) REFERENCES `tblsubjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_timetable_teacher` FOREIGN KEY (`TeacherId`) REFERENCES `tblusers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbltimetableperiods` (
  `id` int NOT NULL AUTO_INCREMENT,
  `PeriodName` varchar(80) COLLATE utf8mb4_general_ci NOT NULL,
  `DayOfWeek` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') COLLATE utf8mb4_general_ci NOT NULL,
  `StartTime` time NOT NULL,
  `EndTime` time NOT NULL,
  `PeriodType` enum('lesson','break','lunch','assembly','games','club','activity') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'lesson',
  `Status` tinyint(1) NOT NULL DEFAULT '1',
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_timetable_period` (`DayOfWeek`,`StartTime`,`EndTime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbltimetableversions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `TimetableType` enum('class','exam') COLLATE utf8mb4_general_ci NOT NULL,
  `RelatedId` int DEFAULT NULL,
  `VersionNo` int NOT NULL DEFAULT '1',
  `Action` varchar(80) COLLATE utf8mb4_general_ci NOT NULL,
  `PreviousValue` text COLLATE utf8mb4_general_ci,
  `NewValue` text COLLATE utf8mb4_general_ci,
  `Reason` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `CreatedBy` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_timetable_versions_related` (`TimetableType`,`RelatedId`,`VersionNo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblusers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `FirstName` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MiddleName` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `LastName` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `StaffNumber` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `FullName` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `Username` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `Email` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `EmailStatus` enum('UNVERIFIED','VERIFIED','BOUNCED','DISABLED') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'UNVERIFIED',
  `EmailVerifiedAt` datetime DEFAULT NULL,
  `PhoneNumber` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `PasswordHash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `MustChangePassword` tinyint(1) NOT NULL DEFAULT '1',
  `Role` enum('class_teacher','subject_teacher','head_of_department','exams_officer','deputy_dean','student','parent','accountant') COLLATE utf8mb4_general_ci NOT NULL,
  `Department` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ClassId` int DEFAULT NULL,
  `StudentId` int DEFAULT NULL,
  `ParentPhone` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Status` tinyint(1) NOT NULL DEFAULT '1',
  `CanCreateAdmin` tinyint(1) NOT NULL DEFAULT '0',
  `CreatedBy` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `LastLoginAt` datetime DEFAULT NULL,
  `SessionVersion` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`Username`),
  UNIQUE KEY `uk_users_email` (`Email`),
  UNIQUE KEY `uk_users_student` (`StudentId`),
  UNIQUE KEY `uk_users_staff_number` (`StaffNumber`),
  KEY `idx_users_role_class` (`Role`,`ClassId`),
  KEY `fk_users_class` (`ClassId`),
  CONSTRAINT `fk_users_class` FOREIGN KEY (`ClassId`) REFERENCES `tblclasses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_student` FOREIGN KEY (`StudentId`) REFERENCES `tblstudents` (`StudentId`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
