ALTER TABLE `payment_submissions`
  ADD COLUMN `selected_loan_id` INT UNSIGNED DEFAULT NULL AFTER `loan_payment`,
  ADD INDEX `payment_submissions_selected_loan_id_index` (`selected_loan_id`);
