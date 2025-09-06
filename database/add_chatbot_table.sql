-- Add missing chatbot_conversations table
-- This table is required for the ChatbotManager logging functionality

CREATE TABLE IF NOT EXISTS `chatbot_conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_message` text NOT NULL,
  `bot_response` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `conversation_context` json DEFAULT NULL,
  `response_time_ms` int(11) DEFAULT NULL,
  `api_model_used` varchar(100) DEFAULT NULL,
  `tokens_used` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_session_id` (`session_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add missing remember_tokens table for authentication
CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_token` (`user_id`),
  KEY `idx_token` (`token`),
  KEY `idx_expires_at` (`expires_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add missing system_settings entries for chatbot
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`) VALUES
('chatbot_enabled', '1', 'boolean', 'Enable/disable chatbot functionality', FALSE),
('openai_api_key', '', 'string', 'OpenAI API key for chatbot', FALSE),
('chatbot_model', 'gpt-3.5-turbo', 'string', 'OpenAI model to use for chatbot', FALSE),
('chatbot_max_tokens', '500', 'integer', 'Maximum tokens for chatbot responses', FALSE),
('chatbot_temperature', '0.7', 'decimal', 'Temperature setting for chatbot responses', FALSE);

-- Add company settings if not exists
CREATE TABLE IF NOT EXISTS `company_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','integer','decimal','boolean','json','text') NOT NULL DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_public` boolean NOT NULL DEFAULT FALSE,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default company settings
INSERT IGNORE INTO `company_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`) VALUES
('name', 'LoanFlow', 'string', 'Company name', TRUE),
('email', 'support@loanflow.com', 'string', 'Company email', TRUE),
('phone', '+1 (555) 123-4567', 'string', 'Company phone number', TRUE),
('address', '123 Business St, Suite 100', 'string', 'Company address', TRUE),
('city', 'New York', 'string', 'Company city', TRUE),
('state', 'NY', 'string', 'Company state/province', TRUE),
('postal_code', '10001', 'string', 'Company postal code', TRUE),
('country', 'USA', 'string', 'Company country', TRUE),
('website', 'https://loanflow.com', 'string', 'Company website', TRUE),
('business_hours', '{"monday": "9:00-17:00", "tuesday": "9:00-17:00", "wednesday": "9:00-17:00", "thursday": "9:00-17:00", "friday": "9:00-17:00", "saturday": "closed", "sunday": "closed"}', 'json', 'Business hours', TRUE);

COMMIT;