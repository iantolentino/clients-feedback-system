-- Apply once to an existing database before deploying the updated PHP files.
-- Existing submissions remain readable through their legacy fields.
ALTER TABLE feedback_submissions
  ADD COLUMN poc_name VARCHAR(190) DEFAULT NULL AFTER staff_name,
  ADD COLUMN business_needs_score TINYINT UNSIGNED DEFAULT NULL AFTER poc_name,
  ADD COLUMN communication_support_score TINYINT UNSIGNED DEFAULT NULL AFTER business_needs_score,
  ADD COLUMN concerns_comfort_score TINYINT UNSIGNED DEFAULT NULL AFTER communication_support_score,
  ADD COLUMN partnership_confidence_score TINYINT UNSIGNED DEFAULT NULL AFTER concerns_comfort_score,
  ADD COLUMN improvement_feedback TEXT DEFAULT NULL AFTER partnership_confidence_score,
  ADD COLUMN relationship_description TEXT DEFAULT NULL AFTER improvement_feedback;
