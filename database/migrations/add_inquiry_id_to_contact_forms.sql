-- Migration: Add inquiry_id field to contact_forms table
-- Created: 2024-01-20

ALTER TABLE `contact_forms` 
ADD COLUMN `inquiry_id` varchar(50) DEFAULT NULL AFTER `id`,
ADD INDEX `idx_inquiry_id` (`inquiry_id`);

-- Update existing records with generated inquiry IDs
UPDATE `contact_forms` 
SET `inquiry_id` = CONCAT('INQ-', DATE_FORMAT(submitted_at, '%Y%m%d'), '-', LPAD(id, 4, '0'))
WHERE `inquiry_id` IS NULL;

COMMIT;