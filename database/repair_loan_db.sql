CREATE DATABASE IF NOT EXISTS `loan_db_repaired`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `loan_db_repaired`;

CREATE TABLE IF NOT EXISTS `borrowers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `gcash_name` varchar(150) DEFAULT NULL,
  `gcash_number` varchar(50) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `savings_closed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `borrowers_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `borrowers`
  ADD COLUMN IF NOT EXISTS `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active' AFTER `name`;

ALTER TABLE `borrowers`
  ADD COLUMN IF NOT EXISTS `savings_closed` tinyint(1) NOT NULL DEFAULT 0 AFTER `status`;

ALTER TABLE `borrowers`
  ADD COLUMN IF NOT EXISTS `first_name` varchar(100) DEFAULT NULL AFTER `name`;

ALTER TABLE `borrowers`
  ADD COLUMN IF NOT EXISTS `last_name` varchar(100) DEFAULT NULL AFTER `first_name`;

ALTER TABLE `borrowers`
  ADD COLUMN IF NOT EXISTS `gcash_name` varchar(150) DEFAULT NULL AFTER `last_name`;

ALTER TABLE `borrowers`
  ADD COLUMN IF NOT EXISTS `gcash_number` varchar(50) DEFAULT NULL AFTER `gcash_name`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('Admin','Member') NOT NULL DEFAULT 'Member',
  `borrower_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`),
  KEY `users_borrower_id_index` (`borrower_id`),
  CONSTRAINT `users_borrower_id_fk`
    FOREIGN KEY (`borrower_id`) REFERENCES `borrowers` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`username`, `password`, `status`)
SELECT 'admin', '$2y$10$tC8XBjQ1fpcOxn9I3ItQyum7FGeds5ZWti1wnLbw8USA0zKRDR2Fq', 'Admin'
WHERE NOT EXISTS (
  SELECT 1 FROM `users` WHERE `username` = 'admin'
);

INSERT INTO `users` (`username`, `password`, `status`, `borrower_id`)
SELECT `borrowers`.`name`, '', 'Member', `borrowers`.`id`
FROM `borrowers`
WHERE NOT EXISTS (
  SELECT 1 FROM `users` WHERE `users`.`borrower_id` = `borrowers`.`id`
);

CREATE TABLE IF NOT EXISTS `loans` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `borrower_id` int unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `interest` decimal(12,2) NOT NULL DEFAULT 0.00,
  `months` decimal(6,2) NOT NULL DEFAULT 0.00,
  `total_payable` decimal(12,2) NOT NULL DEFAULT 0.00,
  `start_date` date NOT NULL,
  `status` enum('Active','Completed') NOT NULL DEFAULT 'Active',
  `is_guarantor` tinyint(1) NOT NULL DEFAULT 0,
  `guest_borrower_name` varchar(150) DEFAULT NULL,
  `guest_gcash_name` varchar(150) DEFAULT NULL,
  `guest_gcash_number` varchar(50) DEFAULT NULL,
  `disbursement_reference_number` varchar(100) DEFAULT NULL,
  `disbursement_proof_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `loans_borrower_id_index` (`borrower_id`),
  CONSTRAINT `loans_borrower_id_fk`
    FOREIGN KEY (`borrower_id`) REFERENCES `borrowers` (`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `loan_id` int unsigned NOT NULL,
  `payment_no` int unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `due_date` date NOT NULL,
  `paid` tinyint(1) NOT NULL DEFAULT 0,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payments_loan_id_index` (`loan_id`),
  KEY `payments_due_date_paid_index` (`due_date`, `paid`),
  CONSTRAINT `payments_loan_id_fk`
    FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `capital_contributions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `borrower_id` int unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `type` enum('INITIAL','CUTOFF') NOT NULL DEFAULT 'CUTOFF',
  `contribution_date` date NOT NULL,
  `period_label` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `capital_contributions_borrower_id_index` (`borrower_id`),
  KEY `capital_contributions_date_type_index` (`contribution_date`, `type`),
  CONSTRAINT `capital_contributions_borrower_id_fk`
    FOREIGN KEY (`borrower_id`) REFERENCES `borrowers` (`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `savings_transactions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `borrower_id` int unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `type` enum('DEPOSIT','WITHDRAWAL') NOT NULL DEFAULT 'DEPOSIT',
  `transaction_date` date NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `savings_transactions_borrower_id_index` (`borrower_id`),
  KEY `savings_transactions_date_type_index` (`transaction_date`, `type`),
  CONSTRAINT `savings_transactions_borrower_id_fk`
    FOREIGN KEY (`borrower_id`) REFERENCES `borrowers` (`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_submissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `borrower_id` int unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `cutoff_date` date NOT NULL,
  `capital_contribution` decimal(12,2) NOT NULL DEFAULT 0.00,
  `loan_payment` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reference_number` varchar(100) NOT NULL,
  `proof_image` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payment_submissions_borrower_id_index` (`borrower_id`),
  KEY `payment_submissions_cutoff_date_index` (`cutoff_date`),
  CONSTRAINT `payment_submissions_borrower_id_fk`
    FOREIGN KEY (`borrower_id`) REFERENCES `borrowers` (`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `savings_submissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `borrower_id` int unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reference_number` varchar(100) NOT NULL,
  `proof_image` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `savings_submissions_borrower_id_index` (`borrower_id`),
  KEY `savings_submissions_status_created_at_index` (`status`, `created_at`),
  CONSTRAINT `savings_submissions_borrower_id_fk`
    FOREIGN KEY (`borrower_id`) REFERENCES `borrowers` (`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `savings_withdrawal_requests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `borrower_id` int unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gcash_name` varchar(150) NOT NULL,
  `gcash_number` varchar(50) NOT NULL,
  `admin_reference_number` varchar(100) DEFAULT NULL,
  `admin_proof_image` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `savings_withdrawal_requests_borrower_id_index` (`borrower_id`),
  KEY `savings_withdrawal_requests_status_created_at_index` (`status`, `created_at`),
  CONSTRAINT `savings_withdrawal_requests_borrower_id_fk`
    FOREIGN KEY (`borrower_id`) REFERENCES `borrowers` (`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_account_links` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `linked_user_id` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_account_links_unique` (`user_id`, `linked_user_id`),
  KEY `user_account_links_linked_user_id_index` (`linked_user_id`),
  CONSTRAINT `user_account_links_user_id_fk`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT `user_account_links_linked_user_id_fk`
    FOREIGN KEY (`linked_user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `loan_requests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `borrower_id` int unsigned NOT NULL,
  `requested_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `requested_months` decimal(6,2) NOT NULL DEFAULT 0.00,
  `approved_amount` decimal(12,2) DEFAULT NULL,
  `approved_months` decimal(6,2) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `approved_loan_id` int unsigned DEFAULT NULL,
  `is_guarantor` tinyint(1) NOT NULL DEFAULT 0,
  `guest_borrower_name` varchar(150) DEFAULT NULL,
  `guest_gcash_name` varchar(150) DEFAULT NULL,
  `guest_gcash_number` varchar(50) DEFAULT NULL,
  `disbursement_reference_number` varchar(100) DEFAULT NULL,
  `disbursement_proof_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loan_requests_borrower_id_index` (`borrower_id`),
  KEY `loan_requests_status_created_at_index` (`status`, `created_at`),
  KEY `loan_requests_approved_loan_id_index` (`approved_loan_id`),
  CONSTRAINT `loan_requests_borrower_id_fk`
    FOREIGN KEY (`borrower_id`) REFERENCES `borrowers` (`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT `loan_requests_approved_loan_id_fk`
    FOREIGN KEY (`approved_loan_id`) REFERENCES `loans` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `borrowers`
JOIN (
  SELECT
    `borrower_id`,
    IFNULL(SUM(CASE WHEN `type` = 'DEPOSIT' THEN `amount` ELSE 0 END), 0) AS `deposits`,
    IFNULL(SUM(CASE WHEN `type` = 'WITHDRAWAL' THEN `amount` ELSE 0 END), 0) AS `withdrawals`
  FROM `savings_transactions`
  GROUP BY `borrower_id`
) AS `savings_summary`
  ON `savings_summary`.`borrower_id` = `borrowers`.`id`
SET `borrowers`.`savings_closed` = 1
WHERE `borrowers`.`savings_closed` = 0
  AND `savings_summary`.`withdrawals` > 0
  AND `savings_summary`.`deposits` - `savings_summary`.`withdrawals` <= 0;

UPDATE `loans`
SET `status` = 'Completed'
WHERE `id` IN (
  SELECT `loan_id`
  FROM (
    SELECT
      `loan_id`,
      COUNT(*) AS `total_payments`,
      SUM(CASE WHEN `paid` = 1 THEN 1 ELSE 0 END) AS `paid_payments`
    FROM `payments`
    GROUP BY `loan_id`
  ) AS `payment_summary`
  WHERE `payment_summary`.`total_payments` > 0
    AND `payment_summary`.`total_payments` = `payment_summary`.`paid_payments`
);
