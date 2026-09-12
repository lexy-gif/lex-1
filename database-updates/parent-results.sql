-- Additive guardian and publication schema. Existing students/results are retained.
CREATE TABLE IF NOT EXISTS tblparentstudents (
 ParentId INT NOT NULL, StudentId INT NOT NULL,
 Relationship VARCHAR(50) NOT NULL DEFAULT 'Guardian',
 Status TINYINT NOT NULL DEFAULT 1, NotifyResults TINYINT NOT NULL DEFAULT 1,
 CreatedBy VARCHAR(100) NOT NULL, CreationDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (ParentId,StudentId), KEY idx_guardian_student (StudentId,Status),
 FOREIGN KEY (ParentId) REFERENCES tblusers(id),
 FOREIGN KEY (StudentId) REFERENCES tblstudents(StudentId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblresultsubmissions (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 ClassId INT NOT NULL, SubjectId INT NOT NULL, ExamId INT NOT NULL, TeacherId INT NOT NULL,
 Status ENUM('draft','submitted') NOT NULL DEFAULT 'draft', SubmittedAt DATETIME NULL,
 UNIQUE KEY uk_result_submission (ClassId,SubjectId,ExamId),
 FOREIGN KEY (ClassId) REFERENCES tblclasses(id), FOREIGN KEY (SubjectId) REFERENCES tblsubjects(id),
 FOREIGN KEY (ExamId) REFERENCES tblexams(id), FOREIGN KEY (TeacherId) REFERENCES tblusers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblresultpublications (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, ClassId INT NOT NULL, ExamId INT NOT NULL,
 PublishedBy VARCHAR(100) NOT NULL, PublishedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uk_result_publication (ClassId,ExamId),
 FOREIGN KEY (ClassId) REFERENCES tblclasses(id), FOREIGN KEY (ExamId) REFERENCES tblexams(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblparentnotifications (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, ParentId INT NOT NULL, StudentId INT NOT NULL,
 EventKey VARCHAR(100) NOT NULL, Title VARCHAR(150) NOT NULL, Message TEXT NOT NULL,
 ActionUrl VARCHAR(255) NOT NULL, ReadAt DATETIME NULL,
 CreationDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uk_parent_event (ParentId,StudentId,EventKey), KEY idx_parent_unread (ParentId,ReadAt,id),
 FOREIGN KEY (ParentId) REFERENCES tblusers(id), FOREIGN KEY (StudentId) REFERENCES tblstudents(StudentId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblparentsms (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, NotificationId INT NOT NULL,
 ParentId INT NOT NULL, StudentId INT NOT NULL, Destination VARCHAR(30) NOT NULL,
 Message TEXT NOT NULL, Status ENUM('pending','processing','accepted','failed','uncertain','skipped') NOT NULL DEFAULT 'pending',
 Attempts INT NOT NULL DEFAULT 0, ProviderReference VARCHAR(150) NULL,
 ProviderResponse TEXT NULL, ErrorMessage VARCHAR(500) NULL,
 AttemptedAt DATETIME NULL, CreationDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uk_parent_sms_event (NotificationId), KEY idx_parent_sms_queue (Status,id),
 FOREIGN KEY (NotificationId) REFERENCES tblparentnotifications(id),
 FOREIGN KEY (ParentId) REFERENCES tblusers(id), FOREIGN KEY (StudentId) REFERENCES tblstudents(StudentId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblloginattempts (
 AttemptKey CHAR(64) NOT NULL PRIMARY KEY, Failures INT NOT NULL DEFAULT 0,
 WindowStarted DATETIME NOT NULL, LockedUntil DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
