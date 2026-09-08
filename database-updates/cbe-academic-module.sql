CREATE TABLE IF NOT EXISTS tblschoollevels (
 id INT AUTO_INCREMENT PRIMARY KEY, Name VARCHAR(100) NOT NULL UNIQUE, SeniorSchool TINYINT NOT NULL DEFAULT 0, Status TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tblgrades (
 id INT AUTO_INCREMENT PRIMARY KEY, SchoolLevelId INT NOT NULL, Name VARCHAR(100) NOT NULL UNIQUE,
 GradeNumber INT NOT NULL UNIQUE, PathwayEntry TINYINT NOT NULL DEFAULT 0, GuidanceEligible TINYINT NOT NULL DEFAULT 0, Status TINYINT NOT NULL DEFAULT 1,
 FOREIGN KEY(SchoolLevelId) REFERENCES tblschoollevels(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tblsubjectgrades (
 SubjectId INT NOT NULL, GradeId INT NOT NULL, PRIMARY KEY(SubjectId,GradeId),
 FOREIGN KEY(SubjectId) REFERENCES tblsubjects(id), FOREIGN KEY(GradeId) REFERENCES tblgrades(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tblacademicsettings (
 id INT PRIMARY KEY, WorkloadLow INT NULL, WorkloadHigh INT NULL, SupportThreshold DECIMAL(5,2) NULL
) ENGINE=InnoDB;
INSERT IGNORE INTO tblacademicsettings(id) VALUES(1);
CREATE TABLE IF NOT EXISTS tblpathways (
 id INT AUTO_INCREMENT PRIMARY KEY, Name VARCHAR(120) NOT NULL UNIQUE, Description TEXT, Status TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tblpathwaytracks (
 id INT AUTO_INCREMENT PRIMARY KEY, PathwayId INT NOT NULL, Name VARCHAR(150) NOT NULL, Status TINYINT NOT NULL DEFAULT 1,
 UNIQUE(PathwayId,Name), FOREIGN KEY(PathwayId) REFERENCES tblpathways(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tblschoolcombinations (
 id INT AUTO_INCREMENT PRIMARY KEY, TrackId INT NOT NULL, AcademicYearId INT NOT NULL,
 Code VARCHAR(50), Name VARCHAR(150) NOT NULL, Status TINYINT NOT NULL DEFAULT 1,
 UNIQUE(TrackId,AcademicYearId,Name), FOREIGN KEY(TrackId) REFERENCES tblpathwaytracks(id), FOREIGN KEY(AcademicYearId) REFERENCES tblacademicyears(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tblschoolcombinationsubjects (
 CombinationId INT NOT NULL, SubjectId INT NOT NULL, PRIMARY KEY(CombinationId,SubjectId),
 FOREIGN KEY(CombinationId) REFERENCES tblschoolcombinations(id), FOREIGN KEY(SubjectId) REFERENCES tblsubjects(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tblstudentpathways (
 id INT AUTO_INCREMENT PRIMARY KEY, StudentId INT NOT NULL, ClassId INT NOT NULL, CombinationId INT NOT NULL, AcademicYearId INT NOT NULL,
 PlacementSource VARCHAR(150) NOT NULL, ReferenceNotes TEXT, Status TINYINT NOT NULL DEFAULT 1,
 AssignedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP, EndedAt TIMESTAMP NULL,
 ActiveStudentId INT GENERATED ALWAYS AS (CASE WHEN Status=1 THEN StudentId ELSE NULL END) STORED,
 UNIQUE(ActiveStudentId,AcademicYearId), FOREIGN KEY(StudentId) REFERENCES tblstudents(StudentId), FOREIGN KEY(ClassId) REFERENCES tblclasses(id),
 FOREIGN KEY(CombinationId) REFERENCES tblschoolcombinations(id), FOREIGN KEY(AcademicYearId) REFERENCES tblacademicyears(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tblpathwayguidance (
 id INT AUTO_INCREMENT PRIMARY KEY, StudentId INT NOT NULL, AcademicYearId INT NOT NULL,
 Interests TEXT, ProposedPathwayId INT NULL, ChosenTrackId INT NULL, PreferredCombinationId INT NULL,
 ExternalAssessmentReference TEXT, Notes TEXT, RecordedBy VARCHAR(100) NOT NULL, CreationDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(StudentId) REFERENCES tblstudents(StudentId), FOREIGN KEY(AcademicYearId) REFERENCES tblacademicyears(id),
 FOREIGN KEY(ProposedPathwayId) REFERENCES tblpathways(id), FOREIGN KEY(ChosenTrackId) REFERENCES tblpathwaytracks(id), FOREIGN KEY(PreferredCombinationId) REFERENCES tblschoolcombinations(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tbldepartmentpermissions (
 TeacherId INT NOT NULL, DepartmentId INT NOT NULL, CanReadAcademic TINYINT NOT NULL DEFAULT 0,
 PRIMARY KEY(TeacherId,DepartmentId), FOREIGN KEY(TeacherId) REFERENCES tblusers(id), FOREIGN KEY(DepartmentId) REFERENCES tbldepartments(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tblassessmenttypes (
 id INT AUTO_INCREMENT PRIMARY KEY, Name VARCHAR(100) NOT NULL UNIQUE, Status TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tblperformancelevels (
 id INT AUTO_INCREMENT PRIMARY KEY, Name VARCHAR(100) NOT NULL UNIQUE, Description TEXT, SortOrder INT NOT NULL DEFAULT 0, Status TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tblcompetencies (
 id INT AUTO_INCREMENT PRIMARY KEY, SubjectId INT NOT NULL, Title VARCHAR(200) NOT NULL, Description TEXT, Status TINYINT NOT NULL DEFAULT 1,
 FOREIGN KEY(SubjectId) REFERENCES tblsubjects(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tbllearningoutcomes (
 id INT AUTO_INCREMENT PRIMARY KEY, CompetencyId INT NOT NULL, Title VARCHAR(200) NOT NULL, Status TINYINT NOT NULL DEFAULT 1,
 FOREIGN KEY(CompetencyId) REFERENCES tblcompetencies(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tblassessments (
 id INT AUTO_INCREMENT PRIMARY KEY, Title VARCHAR(200) NOT NULL, AssessmentTypeId INT NOT NULL,
 SubjectId INT NOT NULL, ClassId INT NOT NULL, AcademicYearId INT NOT NULL, TermId INT NOT NULL, TeacherId INT NOT NULL,
 MaximumScore DECIMAL(8,2) NULL, AssessmentDate DATE NOT NULL, Status VARCHAR(20) NOT NULL DEFAULT 'draft',
 CreationDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UpdationDate TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
 KEY( AcademicYearId,TermId,ClassId,SubjectId),
 FOREIGN KEY(AssessmentTypeId) REFERENCES tblassessmenttypes(id), FOREIGN KEY(SubjectId) REFERENCES tblsubjects(id), FOREIGN KEY(ClassId) REFERENCES tblclasses(id),
 FOREIGN KEY(AcademicYearId) REFERENCES tblacademicyears(id), FOREIGN KEY(TermId) REFERENCES tblterms(id), FOREIGN KEY(TeacherId) REFERENCES tblusers(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tblassessmentoutcomes (
 AssessmentId INT NOT NULL, OutcomeId INT NOT NULL, PRIMARY KEY(AssessmentId,OutcomeId),
 FOREIGN KEY(AssessmentId) REFERENCES tblassessments(id), FOREIGN KEY(OutcomeId) REFERENCES tbllearningoutcomes(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tblassessmentresults (
 id INT AUTO_INCREMENT PRIMARY KEY, AssessmentId INT NOT NULL, StudentId INT NOT NULL, Score DECIMAL(8,2) NULL, PerformanceLevelId INT NULL,
 Evidence TEXT, RecordedBy VARCHAR(100) NOT NULL, UpdationDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE(AssessmentId,StudentId), FOREIGN KEY(AssessmentId) REFERENCES tblassessments(id), FOREIGN KEY(StudentId) REFERENCES tblstudents(StudentId), FOREIGN KEY(PerformanceLevelId) REFERENCES tblperformancelevels(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tbloutcomeobservations (
 AssessmentId INT NOT NULL, StudentId INT NOT NULL, OutcomeId INT NOT NULL, PerformanceLevelId INT NOT NULL, Evidence TEXT,
 PRIMARY KEY(AssessmentId,StudentId,OutcomeId), FOREIGN KEY(AssessmentId,OutcomeId) REFERENCES tblassessmentoutcomes(AssessmentId,OutcomeId),
 FOREIGN KEY(StudentId) REFERENCES tblstudents(StudentId), FOREIGN KEY(PerformanceLevelId) REFERENCES tblperformancelevels(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tblcurriculumcoverage (
 id INT AUTO_INCREMENT PRIMARY KEY, TeacherId INT NOT NULL, ClassId INT NOT NULL, SubjectId INT NOT NULL, AcademicYearId INT NOT NULL, TermId INT NOT NULL,
 ContentReference VARCHAR(255) NOT NULL, ExpectedProgress DECIMAL(5,2) NOT NULL, ActualProgress DECIMAL(5,2) NOT NULL,
 ReportDate DATE NOT NULL, Notes TEXT, CreationDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY(AcademicYearId,TermId,ClassId,SubjectId,ReportDate), FOREIGN KEY(TeacherId) REFERENCES tblusers(id), FOREIGN KEY(ClassId) REFERENCES tblclasses(id),
 FOREIGN KEY(SubjectId) REFERENCES tblsubjects(id), FOREIGN KEY(AcademicYearId) REFERENCES tblacademicyears(id), FOREIGN KEY(TermId) REFERENCES tblterms(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tblacademicinterventions (
 id INT AUTO_INCREMENT PRIMARY KEY, StudentId INT NOT NULL, SubjectId INT NOT NULL, TeacherId INT NOT NULL, AcademicYearId INT NOT NULL, TermId INT NOT NULL,
 Reason TEXT NOT NULL, ActionPlan TEXT NOT NULL, ReviewDate DATE NOT NULL, Status VARCHAR(20) NOT NULL DEFAULT 'active', ReviewNotes TEXT,
 CreationDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UpdationDate TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(StudentId) REFERENCES tblstudents(StudentId), FOREIGN KEY(SubjectId) REFERENCES tblsubjects(id), FOREIGN KEY(TeacherId) REFERENCES tblusers(id),
 FOREIGN KEY(AcademicYearId) REFERENCES tblacademicyears(id), FOREIGN KEY(TermId) REFERENCES tblterms(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tblexaminvigilators (
 SessionId INT NOT NULL, TeacherId INT NOT NULL, PRIMARY KEY(SessionId,TeacherId),
 FOREIGN KEY(SessionId) REFERENCES tblexamtimetableentries(id), FOREIGN KEY(TeacherId) REFERENCES tblusers(id)
) ENGINE=InnoDB;
INSERT IGNORE INTO tblschoollevels(Name,SeniorSchool) VALUES('Primary School',0),('Junior School',0),('Senior School',1);
INSERT IGNORE INTO tblpathways(Name) VALUES('STEM'),('Social Sciences'),('Arts & Sports');
INSERT IGNORE INTO tblpathwaytracks(PathwayId,Name) SELECT id,'Pure Sciences' FROM tblpathways WHERE Name='STEM';
INSERT IGNORE INTO tblpathwaytracks(PathwayId,Name) SELECT id,'Applied Sciences' FROM tblpathways WHERE Name='STEM';
INSERT IGNORE INTO tblpathwaytracks(PathwayId,Name) SELECT id,'Technical Studies' FROM tblpathways WHERE Name='STEM';
INSERT IGNORE INTO tblpathwaytracks(PathwayId,Name) SELECT id,'Languages & Literature' FROM tblpathways WHERE Name='Social Sciences';
INSERT IGNORE INTO tblpathwaytracks(PathwayId,Name) SELECT id,'Humanities & Business Studies' FROM tblpathways WHERE Name='Social Sciences';
INSERT IGNORE INTO tblpathwaytracks(PathwayId,Name) SELECT id,'Arts' FROM tblpathways WHERE Name='Arts & Sports';
INSERT IGNORE INTO tblpathwaytracks(PathwayId,Name) SELECT id,'Sports' FROM tblpathways WHERE Name='Arts & Sports';
INSERT IGNORE INTO tblassessmenttypes(Name) VALUES('Formative'),('Summative'),('Project'),('Practical activity'),('Assignment'),('Test'),('End-term assessment');
