-- Apply using: php scripts/migrate-teacher-management.php
CREATE TABLE IF NOT EXISTS tblclassteacherassignments (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 TeacherId INT NOT NULL, ClassId INT NOT NULL, AcademicYearId INT NOT NULL,
 Status TINYINT NOT NULL DEFAULT 1,
 AssignedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 EndedAt TIMESTAMP NULL,
 ActiveClassId INT GENERATED ALWAYS AS (CASE WHEN Status = 1 THEN ClassId ELSE NULL END) STORED,
 ActiveTeacherId INT GENERATED ALWAYS AS (CASE WHEN Status = 1 THEN TeacherId ELSE NULL END) STORED,
 UNIQUE KEY uk_class_teacher_year (ActiveClassId, AcademicYearId),
 UNIQUE KEY uk_teacher_class_year (ActiveTeacherId, AcademicYearId),
 FOREIGN KEY (TeacherId) REFERENCES tblusers(id),
 FOREIGN KEY (ClassId) REFERENCES tblclasses(id),
 FOREIGN KEY (AcademicYearId) REFERENCES tblacademicyears(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tblresponsibilitytypes (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 Name VARCHAR(150) NOT NULL UNIQUE, Description TEXT NULL, Active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tblteacherresponsibilities (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 TeacherId INT NOT NULL, ResponsibilityTypeId INT NOT NULL, AcademicYearId INT NOT NULL,
 StartDate DATE NOT NULL, EndDate DATE NULL, Status TINYINT NOT NULL DEFAULT 1, Notes TEXT NULL,
 CreationDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 ActiveTypeId INT GENERATED ALWAYS AS (CASE WHEN Status = 1 THEN ResponsibilityTypeId ELSE NULL END) STORED,
 UNIQUE KEY uk_teacher_responsibility (TeacherId, ActiveTypeId, AcademicYearId),
 FOREIGN KEY (TeacherId) REFERENCES tblusers(id),
 FOREIGN KEY (ResponsibilityTypeId) REFERENCES tblresponsibilitytypes(id),
 FOREIGN KEY (AcademicYearId) REFERENCES tblacademicyears(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS tblstudentsubjects (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 StudentId INT NOT NULL, SubjectId INT NOT NULL, ClassId INT NOT NULL, AcademicYearId INT NOT NULL,
 Status TINYINT NOT NULL DEFAULT 1,
 CreationDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UpdationDate TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uk_student_subject_year (StudentId, SubjectId, ClassId, AcademicYearId),
 FOREIGN KEY (StudentId) REFERENCES tblstudents(StudentId),
 FOREIGN KEY (SubjectId) REFERENCES tblsubjects(id),
 FOREIGN KEY (ClassId) REFERENCES tblclasses(id),
 FOREIGN KEY (AcademicYearId) REFERENCES tblacademicyears(id)
) ENGINE=InnoDB;
INSERT IGNORE INTO tblresponsibilitytypes (Name) VALUES
 ('Exam Invigilator'), ('Games Teacher'), ('Sports Coordinator'), ('Discipline Teacher'),
 ('Club Patron'), ('Examination Coordinator'), ('Department Head'),
 ('Guidance and Counselling'), ('Boarding Master/Mistress'), ('Timetable Coordinator');
