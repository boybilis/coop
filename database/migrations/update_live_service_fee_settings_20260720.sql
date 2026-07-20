-- Live database structure update only.
-- This script preserves existing live data and adds service fee support.

DELIMITER $$

DROP PROCEDURE IF EXISTS add_column_if_missing $$

CREATE PROCEDURE add_column_if_missing(
    IN table_name_input VARCHAR(64),
    IN column_name_input VARCHAR(64),
    IN column_definition_input TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = table_name_input
          AND COLUMN_NAME = column_name_input
    ) THEN
        SET @alter_sql = CONCAT(
            'ALTER TABLE `',
            table_name_input,
            '` ADD COLUMN ',
            column_definition_input
        );
        PREPARE alter_statement FROM @alter_sql;
        EXECUTE alter_statement;
        DEALLOCATE PREPARE alter_statement;
    END IF;
END $$

DELIMITER ;

CALL add_column_if_missing('loans', 'service_fee', '`service_fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `interest`');

DROP PROCEDURE IF EXISTS add_column_if_missing;

CREATE TABLE IF NOT EXISTS `loan_service_fee_rates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `service_fee_rate` decimal(8,4) NOT NULL,
  `implementation_date` date NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `loan_service_fee_rates_implementation_unique` (`implementation_date`),
  KEY `loan_service_fee_rates_date_index` (`implementation_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `loan_service_fee_rates` (`service_fee_rate`, `implementation_date`)
SELECT 0.0000, '2026-06-30'
WHERE NOT EXISTS (
  SELECT 1
  FROM `loan_service_fee_rates`
  LIMIT 1
);
