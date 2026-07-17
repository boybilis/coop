-- Hostinger first-time import for Cooperative Loan and Savings Management System
-- Import this once into an EMPTY Hostinger MySQL database using phpMyAdmin.
-- This file creates tables if missing and seeds the default admin only if absent.
-- Do NOT re-import this over a live database as part of normal file updates.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+08:00";
SET NAMES utf8mb4;

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

CREATE TABLE IF NOT EXISTS `loans` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `borrower_id` int unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `interest` decimal(12,2) NOT NULL DEFAULT 0.00,
  `months` decimal(6,2) NOT NULL DEFAULT 0.00,
  `total_payable` decimal(12,2) NOT NULL DEFAULT 0.00,
  `start_date` date NOT NULL,
  `status` enum('Active','Completed') NOT NULL DEFAULT 'Active',
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
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at` timestamp NULL DEFAULT NULL,
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
