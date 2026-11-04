-- Barangay E-Services and Community Concern Management System
-- Database structure (MySQL / MariaDB, InnoDB, utf8mb4)
-- Normalized to 3NF: every non-key column depends only on its table's key.

CREATE DATABASE IF NOT EXISTS barangay_eservices CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE barangay_eservices;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_logs, concern_updates, concerns, concern_categories,
  request_status_history, document_requests, document_requirements, document_types,
  email_verifications, barangay_settings, users, puroks, roles;
SET FOREIGN_KEY_CHECKS = 1;

-- Lookup: the three system roles
CREATE TABLE roles (
  role_id   TINYINT UNSIGNED PRIMARY KEY,
  role_key  VARCHAR(20) NOT NULL UNIQUE,      -- admin | staff | resident
  role_name VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

-- Lookup: puroks (zones) of the barangay, used for addresses and concern locations
CREATE TABLE puroks (
  purok_id   SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purok_name VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE users (
  user_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id         TINYINT UNSIGNED NOT NULL,
  purok_id        SMALLINT UNSIGNED NULL,
  first_name      VARCHAR(60)  NOT NULL,
  last_name       VARCHAR(60)  NOT NULL,
  email           VARCHAR(120) NOT NULL UNIQUE,
  contact_no      VARCHAR(13)  NULL,
  street_address  VARCHAR(150) NULL,
  password_hash   VARCHAR(255) NOT NULL,
  email_verified_at DATETIME   NULL,          -- NULL = not yet verified
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  failed_logins   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until    DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role  FOREIGN KEY (role_id)  REFERENCES roles(role_id),
  CONSTRAINT fk_users_purok FOREIGN KEY (purok_id) REFERENCES puroks(purok_id) ON DELETE SET NULL,
  INDEX idx_users_role (role_id)
) ENGINE=InnoDB;

-- Optional profile of the barangay this installation serves, edited by the administrator.
-- Empty by default: the system is a general "Barangay E-Services" system.
-- Exactly one row (settings_id = 1); the CHECK keeps a second row from being added.
CREATE TABLE barangay_settings (
  settings_id   TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  barangay_name VARCHAR(100) NULL,              -- without the word "Barangay"; NULL = not set
  municipality  VARCHAR(100) NULL,              -- city or municipality
  province      VARCHAR(100) NULL,
  office_hours  VARCHAR(150) NULL,
  logo_path     VARCHAR(255) NULL,              -- file in uploads/logo/, NULL = show initials
  updated_by    INT UNSIGNED NULL,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_settings_single CHECK (settings_id = 1),
  CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Email verification tokens: only a SHA-256 hash of the token is stored.
CREATE TABLE email_verifications (
  verification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at    DATETIME NULL,                   -- set once; a used token cannot be reused
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ev_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_ev_user (user_id)
) ENGINE=InnoDB;

-- Core entity 1: document types offered by the barangay (service information)
CREATE TABLE document_types (
  doc_type_id     SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(100) NOT NULL UNIQUE,
  description     VARCHAR(500) NOT NULL,
  fee             DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  processing_days TINYINT UNSIGNED NOT NULL DEFAULT 1,
  is_archived     TINYINT(1) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_dt_fee  CHECK (fee >= 0),
  CONSTRAINT chk_dt_days CHECK (processing_days BETWEEN 1 AND 30)
) ENGINE=InnoDB;

-- One row per requirement of a document type (avoids a repeating list column)
CREATE TABLE document_requirements (
  requirement_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  doc_type_id      SMALLINT UNSIGNED NOT NULL,
  requirement_text VARCHAR(200) NOT NULL,
  sort_order       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  CONSTRAINT fk_req_doctype FOREIGN KEY (doc_type_id) REFERENCES document_types(doc_type_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Core entity 2: document requests (transaction)
CREATE TABLE document_requests (
  request_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference_no VARCHAR(20) NULL UNIQUE,
  resident_id  INT UNSIGNED NOT NULL,
  doc_type_id  SMALLINT UNSIGNED NOT NULL,
  purpose      VARCHAR(255) NOT NULL,
  copies       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  fee_amount   DECIMAL(8,2) NOT NULL,          -- fee at the time of request (historical value, not derived)
  status       ENUM('Pending','Under Review','Approved','Rejected','Processing','Ready for Pickup','Released','Cancelled')
               NOT NULL DEFAULT 'Pending',
  remarks      VARCHAR(500) NULL,
  handled_by   INT UNSIGNED NULL,
  is_archived  TINYINT(1) NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_dr_resident FOREIGN KEY (resident_id) REFERENCES users(user_id),
  CONSTRAINT fk_dr_doctype  FOREIGN KEY (doc_type_id) REFERENCES document_types(doc_type_id),
  CONSTRAINT fk_dr_handler  FOREIGN KEY (handled_by)  REFERENCES users(user_id) ON DELETE SET NULL,
  CONSTRAINT chk_dr_copies CHECK (copies BETWEEN 1 AND 5),
  INDEX idx_dr_status (status),
  INDEX idx_dr_created (created_at)
) ENGINE=InnoDB;

-- Transaction history of every request status change
CREATE TABLE request_status_history (
  history_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id  INT UNSIGNED NOT NULL,
  from_status VARCHAR(20) NULL,
  to_status   VARCHAR(20) NOT NULL,
  remarks     VARCHAR(500) NULL,
  changed_by  INT UNSIGNED NOT NULL,
  changed_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rsh_request FOREIGN KEY (request_id) REFERENCES document_requests(request_id) ON DELETE CASCADE,
  CONSTRAINT fk_rsh_user    FOREIGN KEY (changed_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- Core entity 3: concern categories, each with a default priority (1 Low .. 4 Urgent)
CREATE TABLE concern_categories (
  category_id      SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name             VARCHAR(100) NOT NULL UNIQUE,
  description      VARCHAR(500) NOT NULL,
  default_priority TINYINT UNSIGNED NOT NULL DEFAULT 2,
  is_archived      TINYINT(1) NOT NULL DEFAULT 0,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_cc_priority CHECK (default_priority BETWEEN 1 AND 4)
) ENGINE=InnoDB;

-- Core entity 4: community concerns (transaction)
CREATE TABLE concerns (
  concern_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference_no VARCHAR(20) NULL UNIQUE,
  resident_id  INT UNSIGNED NOT NULL,
  category_id  SMALLINT UNSIGNED NOT NULL,
  purok_id     SMALLINT UNSIGNED NOT NULL,
  title        VARCHAR(120) NOT NULL,
  description  TEXT NOT NULL,
  location     VARCHAR(200) NOT NULL,         -- landmark / street within the purok
  priority     TINYINT UNSIGNED NOT NULL,      -- set by staff during review; starts at category default
  status       ENUM('Submitted','Under Review','Assigned','In Progress','Resolved','Closed','Rejected','Withdrawn')
               NOT NULL DEFAULT 'Submitted',
  assigned_to  INT UNSIGNED NULL,
  resolution   VARCHAR(1000) NULL,
  is_archived  TINYINT(1) NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_c_resident FOREIGN KEY (resident_id) REFERENCES users(user_id),
  CONSTRAINT fk_c_category FOREIGN KEY (category_id) REFERENCES concern_categories(category_id),
  CONSTRAINT fk_c_purok    FOREIGN KEY (purok_id)    REFERENCES puroks(purok_id),
  CONSTRAINT fk_c_assignee FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE SET NULL,
  CONSTRAINT chk_c_priority CHECK (priority BETWEEN 1 AND 4),
  INDEX idx_c_status (status),
  INDEX idx_c_created (created_at)
) ENGINE=InnoDB;

-- Transaction history of every concern status change / assignment
CREATE TABLE concern_updates (
  update_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  concern_id  INT UNSIGNED NOT NULL,
  from_status VARCHAR(20) NULL,
  to_status   VARCHAR(20) NOT NULL,
  note        VARCHAR(1000) NULL,
  changed_by  INT UNSIGNED NOT NULL,
  changed_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cu_concern FOREIGN KEY (concern_id) REFERENCES concerns(concern_id) ON DELETE CASCADE,
  CONSTRAINT fk_cu_user    FOREIGN KEY (changed_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- Activity log: who did what, to which record, and when
CREATE TABLE activity_logs (
  log_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,               -- NULL for anonymous events (e.g. failed login)
  action      VARCHAR(60)  NOT NULL,
  entity_type VARCHAR(40)  NULL,
  entity_id   INT UNSIGNED NULL,
  details     VARCHAR(500) NULL,
  ip_address  VARCHAR(45)  NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
  INDEX idx_log_created (created_at),
  INDEX idx_log_action (action)
) ENGINE=InnoDB;

-- Empty profile; the administrator may fill it in under Settings.
INSERT INTO barangay_settings (settings_id, office_hours) VALUES (1, 'Monday to Friday, 8:00 AM to 5:00 PM');

INSERT INTO roles (role_id, role_key, role_name) VALUES
  (1, 'admin', 'Administrator'),
  (2, 'staff', 'Barangay Official / Staff'),
  (3, 'resident', 'Resident');
