-- Upgrade for databases imported from an earlier version of this project.
-- Adds (or updates) the barangay_settings table without touching users, requests or concerns.
-- The barangay profile is optional, so its columns allow NULL.
-- Import in phpMyAdmin (select the barangay_eservices database, then Import), or:
--   C:/xampp/mysql/bin/mysql.exe -u root barangay_eservices < upgrade_add_settings.sql

USE barangay_eservices;

CREATE TABLE IF NOT EXISTS barangay_settings (
  settings_id   TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  barangay_name VARCHAR(100) NULL,
  municipality  VARCHAR(100) NULL,
  province      VARCHAR(100) NULL,
  office_hours  VARCHAR(150) NULL,
  logo_path     VARCHAR(255) NULL,
  updated_by    INT UNSIGNED NULL,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_settings_single CHECK (settings_id = 1),
  CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tables created by the earlier version required these columns; make them optional.
ALTER TABLE barangay_settings
  MODIFY barangay_name VARCHAR(100) NULL,
  MODIFY municipality  VARCHAR(100) NULL,
  MODIFY province      VARCHAR(100) NULL,
  MODIFY office_hours  VARCHAR(150) NULL;

INSERT IGNORE INTO barangay_settings (settings_id, office_hours) VALUES (1, 'Monday to Friday, 8:00 AM to 5:00 PM');
