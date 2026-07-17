-- Live database structure update only.
-- This script preserves existing live data and only adds missing columns.
-- Run this in phpMyAdmin after selecting your live database.

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

CALL add_column_if_missing('borrowers', 'savings_closed', '`savings_closed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`');
CALL add_column_if_missing('borrowers', 'first_name', '`first_name` VARCHAR(100) DEFAULT NULL AFTER `name`');
CALL add_column_if_missing('borrowers', 'last_name', '`last_name` VARCHAR(100) DEFAULT NULL AFTER `first_name`');
CALL add_column_if_missing('borrowers', 'gcash_name', '`gcash_name` VARCHAR(150) DEFAULT NULL AFTER `last_name`');
CALL add_column_if_missing('borrowers', 'gcash_number', '`gcash_number` VARCHAR(50) DEFAULT NULL AFTER `gcash_name`');

CALL add_column_if_missing('loan_requests', 'is_guarantor', '`is_guarantor` TINYINT(1) NOT NULL DEFAULT 0 AFTER `approved_loan_id`');
CALL add_column_if_missing('loan_requests', 'guest_borrower_name', '`guest_borrower_name` VARCHAR(150) DEFAULT NULL AFTER `is_guarantor`');

CALL add_column_if_missing('loans', 'is_guarantor', '`is_guarantor` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`');
CALL add_column_if_missing('loans', 'guest_borrower_name', '`guest_borrower_name` VARCHAR(150) DEFAULT NULL AFTER `is_guarantor`');

DROP PROCEDURE IF EXISTS add_column_if_missing;
