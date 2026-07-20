CREATE TABLE IF NOT EXISTS `loan_payment_schedule_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `payment_type` enum('monthly','semi_monthly','weekly') NOT NULL DEFAULT 'semi_monthly',
  `monthly_day` tinyint unsigned DEFAULT NULL,
  `semi_monthly_day_one` tinyint unsigned DEFAULT NULL,
  `semi_monthly_day_two` tinyint unsigned DEFAULT NULL,
  `weekly_day` tinyint unsigned DEFAULT NULL,
  `implementation_date` date NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `loan_payment_schedule_implementation_unique` (`implementation_date`),
  KEY `loan_payment_schedule_date_index` (`implementation_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `loan_payment_schedule_settings`
(`payment_type`, `monthly_day`, `semi_monthly_day_one`, `semi_monthly_day_two`, `weekly_day`, `implementation_date`)
SELECT 'semi_monthly', NULL, 15, 31, NULL, '2026-06-30'
WHERE NOT EXISTS (
  SELECT 1
  FROM `loan_payment_schedule_settings`
  WHERE `implementation_date` = '2026-06-30'
);
