-- Upgrade for databases imported before the Settings feature was added.
-- Adds the barangay_settings table without touching existing users, requests or concerns.
-- Import in phpMyAdmin (select the barangay_eservices database, then Import), or:
--   C:/xampp/mysql/bin/mysql.exe -u root barangay_eservices < upgrade_add_settings.sql

USE barangay_eservices;

CREATE TABLE IF NOT EXISTS barangay_settings (
  settings_id   TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  barangay_name VARCHAR(100) NOT NULL,
  municipality  VARCHAR(100) NOT NULL,
  province      VARCHAR(100) NOT NULL,
  office_hours  VARCHAR(150) NOT NULL,
  logo_path     VARCHAR(255) NULL,
  updated_by    INT UNSIGNED NULL,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_settings_single CHECK (settings_id = 1),
  CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO barangay_settings (settings_id, barangay_name, municipality, province, office_hours)
VALUES (1, 'San Isidro', 'Sta. Maria', 'Laguna', 'Monday to Friday, 8:00 AM to 5:00 PM');
