-- Additive migration. No existing identities, marks or assignments are deleted.
CREATE TABLE IF NOT EXISTS tblstaffroles (
    AccountTable VARCHAR(16) NOT NULL,
    AccountId INT NOT NULL,
    RoleName VARCHAR(64) NOT NULL,
    GrantedBy VARCHAR(150) NOT NULL,
    GrantedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(AccountTable,AccountId,RoleName)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblstaffpermissions (
    AccountTable VARCHAR(16) NOT NULL,
    AccountId INT NOT NULL,
    PermissionName VARCHAR(64) NOT NULL,
    GrantedBy VARCHAR(150) NOT NULL,
    Reason VARCHAR(1000) NOT NULL,
    GrantedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(AccountTable,AccountId,PermissionName)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblstaffpermissionaudit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    Actor VARCHAR(150) NOT NULL,
    AccountTable VARCHAR(16) NOT NULL,
    AccountId INT NOT NULL,
    ChangeType VARCHAR(32) NOT NULL,
    ChangeName VARCHAR(64) NOT NULL,
    Reason VARCHAR(1000) NOT NULL,
    CreatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblresponsibilityscope (
    ResponsibilityTypeId INT NOT NULL PRIMARY KEY,
    IsAcademic TINYINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tblnoticescope (
    NoticeId INT NOT NULL PRIMARY KEY,
    Scope VARCHAR(16) NOT NULL DEFAULT 'general',
    CreatedBy VARCHAR(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
