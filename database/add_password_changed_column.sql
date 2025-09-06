-- Migration: Add password_changed column to users table
-- This ensures admin users must change their default password on first login

-- Add password_changed column to users table
ALTER TABLE `users` ADD COLUMN `password_changed` BOOLEAN NOT NULL DEFAULT FALSE AFTER `locked_until`;

-- Update existing admin users to require password change
UPDATE `users` SET `password_changed` = FALSE WHERE `role` IN ('admin', 'super_admin');

-- Add index for performance
ALTER TABLE `users` ADD INDEX `idx_password_changed` (`password_changed`);

-- Create admin_logs table if it doesn't exist (for security logging)
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert initial log entry
INSERT INTO `admin_logs` (`admin_id`, `action`, `details`, `ip_address`, `created_at`) 
SELECT `id`, 'password_change_required', 'Password change required for security compliance', 'system', NOW() 
FROM `users` 
WHERE `role` IN ('admin', 'super_admin') AND `password_changed` = FALSE;

COMMIT;