-- Team review required before applying to any shared database.
-- Keeps doctor closure attribution and the active-treatment restriction.
ALTER TABLE `case` ADD COLUMN `closed_by_patient_id` INT NULL,
  ADD CONSTRAINT `fk_case_closed_by_patient` FOREIGN KEY (`closed_by_patient_id`)
    REFERENCES `patient` (`patient_id`) ON UPDATE CASCADE ON DELETE RESTRICT;
DROP TRIGGER IF EXISTS `tr_case_bu`;
DELIMITER $$
CREATE TRIGGER `tr_case_bu` BEFORE UPDATE ON `case`
FOR EACH ROW
BEGIN
  IF NEW.closed_by_patient_id IS NOT NULL AND NEW.closed_by_patient_id <> NEW.patient_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only the case patient can close this case';
  END IF;
  IF NEW.case_state = 'closed' AND OLD.case_state <> 'closed' THEN
    IF (NEW.closed_by_doctor_id IS NULL AND NEW.closed_by_patient_id IS NULL)
       OR (NEW.closed_by_doctor_id IS NOT NULL AND NEW.closed_by_patient_id IS NOT NULL) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'One closure actor required';
    END IF;
    IF EXISTS (SELECT 1 FROM `treatment` t WHERE t.case_id=NEW.case_id AND t.treatment_status='active') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot close case with active treatments';
    END IF;
    SET NEW.closed_at = CURRENT_TIMESTAMP;
  END IF;
  IF NEW.case_state = 'open' AND OLD.case_state = 'closed' THEN
    SET NEW.closed_at = NULL;
    SET NEW.closed_by_doctor_id = NULL;
    SET NEW.closed_by_patient_id = NULL;
  END IF;
END$$
DELIMITER ;
