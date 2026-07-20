DELIMITER $$

DROP PROCEDURE IF EXISTS add_column_if_missing $$
CREATE PROCEDURE add_column_if_missing(
    IN table_name_param VARCHAR(64),
    IN column_name_param VARCHAR(64),
    IN alter_sql TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = table_name_param
        AND COLUMN_NAME = column_name_param
    ) THEN
        SET @sql = alter_sql;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

CALL add_column_if_missing('users', 'two_factor_secret', 'ALTER TABLE `users` ADD COLUMN `two_factor_secret` VARCHAR(64) DEFAULT NULL AFTER `password`');
CALL add_column_if_missing('users', 'two_factor_enabled', 'ALTER TABLE `users` ADD COLUMN `two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `two_factor_secret`');
CALL add_column_if_missing('users', 'two_factor_confirmed_at', 'ALTER TABLE `users` ADD COLUMN `two_factor_confirmed_at` DATETIME DEFAULT NULL AFTER `two_factor_enabled`');

DROP PROCEDURE IF EXISTS add_column_if_missing;
