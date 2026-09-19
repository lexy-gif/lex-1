-- Additive academic coordination records. Existing users and academic history are retained.
CREATE TABLE IF NOT EXISTS tblacademicplans (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    AcademicYearId INT NOT NULL,
    TermId INT NOT NULL,
    Kind VARCHAR(32) NOT NULL,
    Title VARCHAR(200) NOT NULL,
    TeacherId INT NOT NULL,
    DueDate DATE NOT NULL,
    Status VARCHAR(20) NOT NULL DEFAULT 'open',
    Details TEXT NOT NULL,
    Outcome TEXT NOT NULL,
    CreatedBy VARCHAR(150) NOT NULL,
    CreatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (AcademicYearId) REFERENCES tblacademicyears(id),
    FOREIGN KEY (TermId) REFERENCES tblterms(id),
    FOREIGN KEY (TeacherId) REFERENCES tblusers(id),
    INDEX (AcademicYearId,TermId,Status,DueDate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblacademicresourcerequests (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    AcademicYearId INT NOT NULL,
    TermId INT NOT NULL,
    Title VARCHAR(200) NOT NULL,
    Justification TEXT NOT NULL,
    Quantity INT NOT NULL,
    RequiredDate DATE NOT NULL,
    Status VARCHAR(20) NOT NULL DEFAULT 'submitted',
    RequestedBy VARCHAR(150) NOT NULL,
    DecisionBy VARCHAR(150) DEFAULT NULL,
    DecisionReason TEXT,
    CreatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (AcademicYearId) REFERENCES tblacademicyears(id),
    FOREIGN KEY (TermId) REFERENCES tblterms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblassessmentdeadlines (
    AssessmentId INT NOT NULL PRIMARY KEY,
    DueDate DATE NOT NULL,
    FOREIGN KEY (AssessmentId) REFERENCES tblassessments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblassessmentreviews (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    AssessmentId INT NOT NULL,
    Actor VARCHAR(150) NOT NULL,
    Decision VARCHAR(32) NOT NULL,
    Reason TEXT NOT NULL,
    CreatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (AssessmentId) REFERENCES tblassessments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblacademicreminders (
    EntityType VARCHAR(32) NOT NULL,
    EntityId INT NOT NULL,
    TeacherId INT NOT NULL,
    ReminderDate DATE NOT NULL,
    PRIMARY KEY (EntityType,EntityId,TeacherId,ReminderDate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
