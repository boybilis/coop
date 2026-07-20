CREATE TABLE IF NOT EXISTS `audit_trails` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `user_status` varchar(30) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int unsigned DEFAULT NULL,
  `metadata` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `audit_trails_user_id_index` (`user_id`),
  KEY `audit_trails_action_index` (`action`),
  KEY `audit_trails_entity_index` (`entity_type`,`entity_id`),
  KEY `audit_trails_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `users`
MODIFY `status` ENUM('SuperAdmin','Admin','Member') NOT NULL DEFAULT 'Member';

UPDATE `users`
SET `status` = 'SuperAdmin'
WHERE `username` = 'admin'
AND (`status` = 'Admin' OR `status` = '' OR `status` IS NULL)
AND NOT EXISTS (
  SELECT 1
  FROM (SELECT `id` FROM `users` WHERE `status` = 'SuperAdmin' LIMIT 1) AS existing_superadmin
);

UPDATE `users`
SET `status` = 'SuperAdmin'
WHERE `status` = 'Admin'
AND NOT EXISTS (
  SELECT 1
  FROM (SELECT `id` FROM `users` WHERE `status` = 'SuperAdmin' LIMIT 1) AS existing_superadmin
)
ORDER BY `id` ASC
LIMIT 1;
