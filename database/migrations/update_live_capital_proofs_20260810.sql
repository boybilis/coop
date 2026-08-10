ALTER TABLE `capital_contributions`
  ADD COLUMN `reference_number` VARCHAR(100) DEFAULT NULL AFTER `period_label`,
  ADD COLUMN `proof_image` VARCHAR(255) DEFAULT NULL AFTER `reference_number`;
