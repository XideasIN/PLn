-- Migration: Add payment_details column to fee_sent_forms table
-- Date: 2024-01-15
-- Description: Adds JSON column to store payment method specific details

USE loanflow;

-- Add payment_details column to fee_sent_forms table
ALTER TABLE `fee_sent_forms` 
ADD COLUMN `payment_details` json DEFAULT NULL 
COMMENT 'Payment method specific details (sending_bank, account_holder, transfer_id, etc.)' 
AFTER `country`;

-- Add index for payment_details if needed for queries
-- ALTER TABLE `fee_sent_forms` ADD INDEX `idx_payment_details` ((CAST(payment_details AS CHAR(255))));

COMMIT;