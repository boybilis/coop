CREATE TABLE IF NOT EXISTS `loan_interest_rates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `monthly_rate` decimal(8,4) NOT NULL,
  `implementation_date` date NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `loan_interest_rates_implementation_unique` (`implementation_date`),
  KEY `loan_interest_rates_date_index` (`implementation_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `loan_interest_rates` (`monthly_rate`, `implementation_date`)
SELECT 2.0000, '2026-06-30'
WHERE NOT EXISTS (
  SELECT 1
  FROM `loan_interest_rates`
  LIMIT 1
);
