-- CHN | Cyprus Health-Care Network
-- DATABASE SCHEMA (v5) - MySQL 8 / MariaDB 10.x
-- This is the ONE canonical schema.sql for a NEW, EMPTY installation.
-- GitHub location: CHN/db/schema.sql
-- IMPORTANT: Do not import this file into a populated v3 database.
-- It does NOT delete data, but CREATE TABLE will fail if tables already exist.
-- Preserve existing v3 data by using a v3->v4 upgrade script after backup.
-- Rules covered: hospital/department, appointments, diagnoses, active treatments,
-- case closing restrictions, anonymous emergencies, external API requesters,
-- API call logging and token administration.
-- Do not insert real patient data or plaintext passwords/API tokens into SQL files.
-- -----------------------------------------------------------------------------

CREATE DATABASE IF NOT EXISTS `chn_db`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `chn_db`;

-- 1. USER: role is an account role; case.tier_color is medical triage.
-- Only store PHP password_hash() output in user.password.
-- phone supports up to 16 characters INCLUDING the leading + sign.
CREATE TABLE `user` (
  `user_id` INT NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL, -- store PHP password_hash() only
  `role` ENUM('patient','doctor','staff','admin') NOT NULL DEFAULT 'patient',
  `name` VARCHAR(255) NOT NULL,
  `surname` VARCHAR(255) NOT NULL,
  `gender` VARCHAR(255) NULL,
  `birth_date` DATE NULL,
  `nationality` CHAR(2) NULL,
  `national_id` VARCHAR(50) NULL,
  `phone` VARCHAR(16) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_user_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. HOSPITAL: `hospital_type` cannot contain private/non-public values.
-- Insert only verified state/public hospitals; no invented hospital data is seeded.
CREATE TABLE `hospital` (
  `hospital_id` INT NOT NULL AUTO_INCREMENT,
  `hospital_name` VARCHAR(200) NOT NULL,
  `hospital_type` ENUM('public') NOT NULL DEFAULT 'public',
  `city` VARCHAR(100) NULL,
  `address` VARCHAR(255) NULL,
  `phone` VARCHAR(30) NULL,
  PRIMARY KEY (`hospital_id`),
  UNIQUE KEY `uq_hospital_name` (`hospital_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. DEPARTMENT: can have the same name in different hospitals.
-- NULL hospital_id is permitted ONLY for old/unassigned departments.
-- The backend must require a real hospital when creating/assigning departments.
CREATE TABLE `department` (
  `department_id` INT NOT NULL AUTO_INCREMENT,
  `department_name` VARCHAR(255) NOT NULL,
  `hospital_id` INT NULL,
  PRIMARY KEY (`department_id`),
  UNIQUE KEY `uq_department_hospital_name` (`hospital_id`, `department_name`),
  UNIQUE KEY `uq_department_id_hospital` (`department_id`, `hospital_id`),
  KEY `idx_department_hospital_id` (`hospital_id`),
  CONSTRAINT `fk_department_hospital` FOREIGN KEY (`hospital_id`)
    REFERENCES `hospital` (`hospital_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. PATIENT
CREATE TABLE `patient` (
  `patient_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `full_address` VARCHAR(255) NULL,
  PRIMARY KEY (`patient_id`),
  UNIQUE KEY `uq_patient_user_id` (`user_id`),
  CONSTRAINT `fk_patient_user` FOREIGN KEY (`user_id`)
    REFERENCES `user` (`user_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. DOCTOR: hospital is determined through the linked department.
CREATE TABLE `doctor` (
  `doctor_id` INT NOT NULL AUTO_INCREMENT,
  `department_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  PRIMARY KEY (`doctor_id`),
  UNIQUE KEY `uq_doctor_user_id` (`user_id`),
  UNIQUE KEY `uq_doctor_id_department` (`doctor_id`, `department_id`),
  KEY `idx_doctor_department_id` (`department_id`),
  CONSTRAINT `fk_doctor_department` FOREIGN KEY (`department_id`)
    REFERENCES `department` (`department_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_doctor_user` FOREIGN KEY (`user_id`)
    REFERENCES `user` (`user_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. STAFF: nullable assignments permit migrating existing records without fake hospitals.
-- When both IDs are present, the composite FK ensures the department belongs
-- to the selected hospital. Backend must reject department_id without hospital_id.
CREATE TABLE `staff` (
  `staff_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `hospital_id` INT NULL,
  `department_id` INT NULL,
  PRIMARY KEY (`staff_id`),
  UNIQUE KEY `uq_staff_user_id` (`user_id`),
  KEY `idx_staff_hospital` (`hospital_id`),
  KEY `idx_staff_department_hospital` (`department_id`, `hospital_id`),
  CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`)
    REFERENCES `user` (`user_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_staff_hospital` FOREIGN KEY (`hospital_id`)
    REFERENCES `hospital` (`hospital_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_staff_department_hospital` FOREIGN KEY (`department_id`, `hospital_id`)
    REFERENCES `department` (`department_id`, `hospital_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. ADMIN
CREATE TABLE `admin` (
  `admin_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `uq_admin_user_id` (`user_id`),
  CONSTRAINT `fk_admin_user` FOREIGN KEY (`user_id`)
    REFERENCES `user` (`user_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. FEEDBACK
CREATE TABLE `feedback` (
  `feedback_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `feedback_msg` VARCHAR(255) NOT NULL,
  `feedback_date` DATE NOT NULL,
  PRIMARY KEY (`feedback_id`),
  KEY `idx_feedback_user_id` (`user_id`),
  CONSTRAINT `fk_feedback_user` FOREIGN KEY (`user_id`)
    REFERENCES `user` (`user_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. RESPONSE: one response per feedback (as in the original ER model).
CREATE TABLE `response` (
  `response_id` INT NOT NULL AUTO_INCREMENT,
  `feedback_id` INT NOT NULL,
  `response_msg` VARCHAR(255) NOT NULL,
  `response_date` DATE NOT NULL,
  PRIMARY KEY (`response_id`),
  UNIQUE KEY `uq_response_feedback_id` (`feedback_id`),
  CONSTRAINT `fk_response_feedback` FOREIGN KEY (`feedback_id`)
    REFERENCES `feedback` (`feedback_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. EMERGENCY_LOG: anonymous users have user_id=NULL.
-- created_at is the server-side timestamp; legacy date/time are optional.
CREATE TABLE `emergency_log` (
  `emergency_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL,
  `ip_address` VARCHAR(45) NULL,
  `emergency_date` DATE NULL,
  `emergency_time` TIME NULL,
  `emergency_location` VARCHAR(255) NULL,
  `status` ENUM('pending','in_progress','resolved','fraud') NOT NULL DEFAULT 'pending',
  `handled_by_staff_id` INT NULL,
  `handled_at` DATETIME NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`emergency_id`),
  KEY `idx_emergency_user_id` (`user_id`),
  KEY `idx_emergency_status_created` (`status`, `created_at`),
  KEY `idx_emergency_handled_by` (`handled_by_staff_id`),
  CONSTRAINT `fk_emergency_user` FOREIGN KEY (`user_id`)
    REFERENCES `user` (`user_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_emergency_handled_by_staff` FOREIGN KEY (`handled_by_staff_id`)
    REFERENCES `staff` (`staff_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. CASE: open=active, closed=passive for dashboard labels.
-- Patients/departments are authoritative; doctor_id is the optional responsible physician.
-- The composite key is used to prevent appointment patient/department mismatches.
CREATE TABLE `case` (
  `case_id` INT NOT NULL AUTO_INCREMENT,
  `patient_id` INT NOT NULL,
  `department_id` INT NOT NULL,
  `doctor_id` INT NULL,
  `case_description` TEXT NULL,
  `tier_color` ENUM('orange','yellow','green') NOT NULL,
  `case_state` ENUM('open','closed') NOT NULL DEFAULT 'open',
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at` DATETIME NULL,
  `closed_by_doctor_id` INT NULL,
  PRIMARY KEY (`case_id`),
  UNIQUE KEY `uq_case_id_patient_department` (`case_id`, `patient_id`, `department_id`),
  KEY `idx_case_patient_id` (`patient_id`),
  KEY `idx_case_department_id` (`department_id`),
  KEY `idx_case_responsible_doctor` (`doctor_id`, `department_id`),
  KEY `idx_case_closed_by_doctor` (`closed_by_doctor_id`),
  KEY `idx_case_state_created` (`case_state`, `created_at`),
  KEY `idx_case_created_at` (`created_at`),
  CONSTRAINT `fk_case_patient` FOREIGN KEY (`patient_id`)
    REFERENCES `patient` (`patient_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_case_department` FOREIGN KEY (`department_id`)
    REFERENCES `department` (`department_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_case_doctor_department` FOREIGN KEY (`doctor_id`, `department_id`)
    REFERENCES `doctor` (`doctor_id`, `department_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_case_closed_by_doctor` FOREIGN KEY (`closed_by_doctor_id`)
    REFERENCES `doctor` (`doctor_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. APPOINTMENT: department_id explicitly identifies booked department.
-- Composite FKs prevent appointment.patient_id/department_id from disagreeing
-- with its case, and prevent doctor_id from belonging to a different department.
-- patient_id and department_id are redundant for case-linked appointments;
-- constraints preserve consistency while supporting direct dashboard filtering.
CREATE TABLE `appointment` (
  `appointment_id` INT NOT NULL AUTO_INCREMENT,
  `case_id` INT NOT NULL,
  `doctor_id` INT NOT NULL,
  `patient_id` INT NOT NULL,
  `department_id` INT NOT NULL,
  `appointment_date` DATE NOT NULL,
  `appointment_time` TIME NOT NULL,
  `appointment_status` ENUM('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`appointment_id`),
  KEY `idx_appointment_case_id` (`case_id`),
  KEY `idx_appointment_patient_id` (`patient_id`),
  KEY `idx_appointment_department_date` (`department_id`, `appointment_date`),
  KEY `idx_appointment_case_patient_department` (`case_id`, `patient_id`, `department_id`),
  KEY `idx_appointment_doctor_department` (`doctor_id`, `department_id`),
  KEY `idx_appointment_doctor_schedule` (`doctor_id`, `appointment_date`, `appointment_time`),
  CONSTRAINT `fk_appointment_case` FOREIGN KEY (`case_id`)
    REFERENCES `case` (`case_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_appointment_doctor` FOREIGN KEY (`doctor_id`)
    REFERENCES `doctor` (`doctor_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_appointment_patient` FOREIGN KEY (`patient_id`)
    REFERENCES `patient` (`patient_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_appointment_department` FOREIGN KEY (`department_id`)
    REFERENCES `department` (`department_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_appointment_case_consistency` FOREIGN KEY (`case_id`, `patient_id`, `department_id`)
    REFERENCES `case` (`case_id`, `patient_id`, `department_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_appointment_doctor_department` FOREIGN KEY (`doctor_id`, `department_id`)
    REFERENCES `doctor` (`doctor_id`, `department_id`) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. TREATMENT: active until explicitly completed/inactivated by a doctor.
-- The database triggers block case closure while any active treatment exists.
CREATE TABLE `treatment` (
  `treatment_id` INT NOT NULL AUTO_INCREMENT,
  `case_id` INT NOT NULL,
  `doctor_id` INT NULL,
  `treatment_desc` VARCHAR(255) NOT NULL,
  `treatment_date` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `treatment_status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `completed_at` DATETIME NULL,
  `deactivated_by_doctor_id` INT NULL,
  PRIMARY KEY (`treatment_id`),
  KEY `idx_treatment_case_id` (`case_id`),
  KEY `idx_treatment_case_date` (`case_id`, `treatment_date`),
  KEY `idx_treatment_case_status` (`case_id`, `treatment_status`),
  KEY `idx_treatment_doctor` (`doctor_id`),
  KEY `idx_treatment_deactivated_by` (`deactivated_by_doctor_id`),
  CONSTRAINT `fk_treatment_case` FOREIGN KEY (`case_id`)
    REFERENCES `case` (`case_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_treatment_doctor` FOREIGN KEY (`doctor_id`)
    REFERENCES `doctor` (`doctor_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_treatment_deactivated_by_doctor` FOREIGN KEY (`deactivated_by_doctor_id`)
    REFERENCES `doctor` (`doctor_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. DIAGNOSIS: separate from treatment_desc; multiple diagnoses per case.
CREATE TABLE `diagnosis` (
  `diagnosis_id` INT NOT NULL AUTO_INCREMENT,
  `case_id` INT NOT NULL,
  `doctor_id` INT NULL,
  `diagnosis_text` TEXT NOT NULL,
  `icd_code` VARCHAR(20) NULL,
  `diagnosed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`diagnosis_id`),
  KEY `idx_diagnosis_case_date` (`case_id`, `diagnosed_at`),
  KEY `idx_diagnosis_doctor` (`doctor_id`),
  CONSTRAINT `fk_diagnosis_case` FOREIGN KEY (`case_id`)
    REFERENCES `case` (`case_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_diagnosis_doctor` FOREIGN KEY (`doctor_id`)
    REFERENCES `doctor` (`doctor_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. REQUESTER: identity of an API client, intentionally distinct from `user`.
-- A requester can represent an external person/service/organization.
CREATE TABLE `requester` (
  `requester_id` INT NOT NULL AUTO_INCREMENT,
  `requester_name` VARCHAR(200) NOT NULL,
  `organization_name` VARCHAR(200) NULL,
  `contact_email` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`requester_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. API_AUTH: token is a hash of a secret, not the plaintext API token.
-- requester_id may be NULL only for unmapped legacy tokens: new tokens are
-- required to have requester and issuing admin by trigger.
-- user_id refers to an optional CHN end-user on whose behalf the API is used.
CREATE TABLE `api_auth` (
  `api_auth_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token` VARCHAR(255) NOT NULL,
  `user_id` INT NULL,
  `requester_id` INT NULL,
  `created_by_admin_id` INT NULL,
  `updated_by_admin_id` INT NULL,
  `revoked_by_admin_id` INT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `valid_from` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NULL,
  `revoked_at` DATETIME NULL,
  `last_used_at` DATETIME NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `rate_limit_per_minute` SMALLINT UNSIGNED NULL,
  PRIMARY KEY (`api_auth_id`),
  UNIQUE KEY `uq_api_auth_token` (`token`),
  KEY `idx_api_auth_user_id` (`user_id`),
  KEY `idx_api_auth_requester` (`requester_id`),
  KEY `idx_api_auth_created_by_admin` (`created_by_admin_id`),
  KEY `idx_api_auth_updated_by_admin` (`updated_by_admin_id`),
  KEY `idx_api_auth_revoked_by_admin` (`revoked_by_admin_id`),
  KEY `idx_api_auth_expires_at` (`expires_at`),
  CONSTRAINT `fk_api_auth_user` FOREIGN KEY (`user_id`)
    REFERENCES `user` (`user_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_api_auth_requester` FOREIGN KEY (`requester_id`)
    REFERENCES `requester` (`requester_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_api_auth_created_by_admin` FOREIGN KEY (`created_by_admin_id`)
    REFERENCES `admin` (`admin_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_api_auth_updated_by_admin` FOREIGN KEY (`updated_by_admin_id`)
    REFERENCES `admin` (`admin_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_api_auth_revoked_by_admin` FOREIGN KEY (`revoked_by_admin_id`)
    REFERENCES `admin` (`admin_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. API_PERMISSION: stand-alone reference catalog ONLY.
-- There is intentionally no api_auth_permission link to individual tokens.
-- Backend must implement any per-token authorization itself.
CREATE TABLE `api_permission` (
  `permission_id` INT NOT NULL AUTO_INCREMENT,
  `permission_code` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL,
  PRIMARY KEY (`permission_id`),
  UNIQUE KEY `uq_permission_code` (`permission_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. API_CALL_LOG: HTTP requests made through the API. Do NOT log raw tokens,
-- medical request bodies, authorization headers, or query parameters with PII.
-- Unknown/unrecognized-token requests can have api_auth_id=NULL.
CREATE TABLE `api_call_log` (
  `api_call_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `api_auth_id` BIGINT UNSIGNED NULL,
  `request_method` ENUM('GET','POST','PUT','PATCH','DELETE') NOT NULL,
  `endpoint` VARCHAR(255) NOT NULL,
  `http_status` SMALLINT UNSIGNED NULL,
  `auth_result` ENUM('accepted','rejected') NOT NULL,
  `client_ip` VARCHAR(45) NULL,
  `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`api_call_id`),
  KEY `idx_api_call_auth_date` (`api_auth_id`, `requested_at`),
  KEY `idx_api_call_requested_at` (`requested_at`),
  CONSTRAINT `fk_api_call_auth` FOREIGN KEY (`api_auth_id`)
    REFERENCES `api_auth` (`api_auth_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- BUSINESS RULES: documented transitions and server-side consistency.
-- Triggers check state invariants, but application authorization must ALSO ensure
-- that the signed-in doctor/admin has the right to make each change.
DELIMITER $$

CREATE TRIGGER `tr_case_bi` BEFORE INSERT ON `case`
FOR EACH ROW
BEGIN
  IF NEW.case_state = 'closed' THEN
    IF NEW.closed_by_doctor_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Doctor ID required to close a case';
    END IF;
    IF NEW.closed_at IS NULL THEN
      SET NEW.closed_at = CURRENT_TIMESTAMP;
    END IF;
  END IF;
END$$

CREATE TRIGGER `tr_case_bu` BEFORE UPDATE ON `case`
FOR EACH ROW
BEGIN
  IF NEW.case_state = 'closed' AND OLD.case_state <> 'closed' THEN
    IF NEW.closed_by_doctor_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Doctor ID required to close a case';
    END IF;
    IF EXISTS (
      SELECT 1 FROM `treatment` t
      WHERE t.case_id = NEW.case_id AND t.treatment_status = 'active'
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot close case with active treatments';
    END IF;
    IF NEW.closed_at IS NULL THEN
      SET NEW.closed_at = CURRENT_TIMESTAMP;
    END IF;
  END IF;
  IF NEW.case_state = 'open' AND OLD.case_state = 'closed' THEN
    SET NEW.closed_at = NULL;
    SET NEW.closed_by_doctor_id = NULL;
  END IF;
END$$

CREATE TRIGGER `tr_treatment_bi` BEFORE INSERT ON `treatment`
FOR EACH ROW
BEGIN
  DECLARE v_case_state VARCHAR(10) DEFAULT NULL;
  -- Lock parent case to serialize against concurrent closure.
  SELECT c.case_state INTO v_case_state
    FROM `case` c WHERE c.case_id = NEW.case_id FOR UPDATE;
  IF NEW.treatment_status = 'active' AND v_case_state = 'closed' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot add active treatment to closed case';
  END IF;
  IF NEW.treatment_status = 'inactive' AND NEW.deactivated_by_doctor_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Doctor ID required for inactive treatment';
  END IF;
  IF NEW.treatment_status = 'inactive' AND NEW.completed_at IS NULL THEN
    SET NEW.completed_at = CURRENT_TIMESTAMP;
  END IF;
END$$

CREATE TRIGGER `tr_treatment_bu` BEFORE UPDATE ON `treatment`
FOR EACH ROW
BEGIN
  DECLARE v_case_state VARCHAR(10) DEFAULT NULL;
  IF NEW.case_id <> OLD.case_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Treatment case_id cannot be reassigned';
  END IF;
  IF NEW.treatment_status = 'active' THEN
    SELECT c.case_state INTO v_case_state
      FROM `case` c WHERE c.case_id = NEW.case_id FOR UPDATE;
    IF v_case_state = 'closed' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot activate treatment in closed case';
    END IF;
    IF OLD.treatment_status = 'inactive' THEN
      SET NEW.completed_at = NULL;
      SET NEW.deactivated_by_doctor_id = NULL;
    END IF;
  ELSEIF OLD.treatment_status <> 'inactive' THEN
    IF NEW.deactivated_by_doctor_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Doctor ID required to inactivate treatment';
    END IF;
    IF NEW.completed_at IS NULL THEN
      SET NEW.completed_at = CURRENT_TIMESTAMP;
    END IF;
  END IF;
END$$

CREATE TRIGGER `tr_api_auth_bi` BEFORE INSERT ON `api_auth`
FOR EACH ROW
BEGIN
  IF NEW.requester_id IS NULL OR NEW.created_by_admin_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'API token needs requester and issuing admin';
  END IF;
END$$

CREATE TRIGGER `tr_api_auth_bu` BEFORE UPDATE ON `api_auth`
FOR EACH ROW
BEGIN
  IF NEW.is_active = 1 AND (NEW.requester_id IS NULL OR NEW.created_by_admin_id IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active API token needs requester and admin';
  END IF;
END$$

DELIMITER ;
