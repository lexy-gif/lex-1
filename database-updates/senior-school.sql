-- Run through scripts/migrate-senior-school.php after the existing CBE migration.
CREATE TABLE IF NOT EXISTS tblpathwaysubjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    PathwayId INT NOT NULL,
    TrackId INT NULL,
    SubjectId INT NOT NULL,
    TrackScope INT GENERATED ALWAYS AS (COALESCE(TrackId, 0)) STORED,
    UNIQUE KEY uk_pathway_subject (PathwayId, TrackScope, SubjectId),
    FOREIGN KEY (PathwayId) REFERENCES tblpathways(id),
    FOREIGN KEY (TrackId) REFERENCES tblpathwaytracks(id),
    FOREIGN KEY (SubjectId) REFERENCES tblsubjects(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tblstudentenrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    StudentId INT NOT NULL,
    AcademicYearId INT NOT NULL,
    ClassId INT NOT NULL,
    GradeId INT NOT NULL,
    EnrolledAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    RecordedBy VARCHAR(100) NOT NULL,
    UNIQUE KEY uk_student_enrollment_year (StudentId, AcademicYearId),
    KEY idx_enrollment_class_year (ClassId, AcademicYearId),
    FOREIGN KEY (StudentId) REFERENCES tblstudents(StudentId),
    FOREIGN KEY (AcademicYearId) REFERENCES tblacademicyears(id),
    FOREIGN KEY (ClassId) REFERENCES tblclasses(id),
    FOREIGN KEY (GradeId) REFERENCES tblgrades(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tblpathwayallocationsubjects (
    AllocationId INT NOT NULL,
    SubjectId INT NOT NULL,
    SubjectType ENUM('core', 'elective') NOT NULL,
    PRIMARY KEY (AllocationId, SubjectId),
    FOREIGN KEY (AllocationId) REFERENCES tblstudentpathways(id),
    FOREIGN KEY (SubjectId) REFERENCES tblsubjects(id)
) ENGINE=InnoDB;
