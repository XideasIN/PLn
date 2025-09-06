<?php
/**
 * Funding System Helper Functions
 * LoanFlow Personal Loan Management System
 */

require_once 'functions.php';
require_once 'email_functions.php';

/**
 * Get funding status for an application
 */
function getFundingStatus($application_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT application_status, funding_initiated_at, funding_date, 
                   funding_amount, funding_reference, funding_status
            FROM loan_applications 
            WHERE id = ?
        ");
        $stmt->execute([$application_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Get funding status failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Get funding timeline for an application
 */
function getFundingTimeline($application_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT ft.*, 
                   u.first_name as admin_first_name, 
                   u.last_name as admin_last_name
            FROM funding_timeline ft
            LEFT JOIN users u ON ft.created_by = u.id
            WHERE ft.application_id = ?
            ORDER BY ft.created_at DESC
        ");
        $stmt->execute([$application_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get funding timeline failed: " . $e->getMessage());
        return [];
    }
}

/**
 * Add event to funding timeline
 */
function addFundingTimelineEvent($application_id, $event_type, $event_title, $event_description = null, $event_data = null, $created_by = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO funding_timeline 
            (application_id, event_type, event_title, event_description, event_data, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $event_data_json = $event_data ? json_encode($event_data) : null;
        
        return $stmt->execute([
            $application_id, 
            $event_type, 
            $event_title, 
            $event_description, 
            $event_data_json, 
            $created_by
        ]);
    } catch (Exception $e) {
        error_log("Add funding timeline event failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Initiate funding process
 */
function initiateFunding($application_id, $initiated_by) {
    try {
        $db = getDB();
        $db->beginTransaction();
        
        // Update application status
        $stmt = $db->prepare("
            UPDATE loan_applications 
            SET application_status = 'funding',
                funding_initiated_at = NOW(),
                funding_initiated_by = ?,
                funding_status = 'in_progress'
            WHERE id = ? AND application_status = 'approved'
        ");
        $stmt->execute([$initiated_by, $application_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Application not found or not in approved status");
        }
        
        // Add timeline event
        addFundingTimelineEvent(
            $application_id,
            'funding_initiated',
            'Funding Process Initiated',
            'Funding process has been started by admin',
            ['initiated_by' => $initiated_by],
            $initiated_by
        );
        
        // Send notification to client
        $client_info = getApplicationClient($application_id);
        if ($client_info) {
            sendFundingNotification($client_info['user_id'], 'funding_initiated', [
                'application_id' => $application_id,
                'client_name' => $client_info['first_name'] . ' ' . $client_info['last_name']
            ]);
        }
        
        $db->commit();
        return true;
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Initiate funding failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Complete funding process
 */
function completeFunding($application_id, $funding_amount, $funding_reference, $funding_notes = null, $funded_by = null) {
    try {
        $db = getDB();
        $db->beginTransaction();
        
        // Update application status
        $stmt = $db->prepare("
            UPDATE loan_applications 
            SET application_status = 'funded',
                funding_date = NOW(),
                funding_amount = ?,
                funding_reference = ?,
                funding_notes = ?,
                funded_by = ?,
                funding_status = 'completed'
            WHERE id = ? AND application_status = 'funding'
        ");
        $stmt->execute([$funding_amount, $funding_reference, $funding_notes, $funded_by, $application_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Application not found or not in funding status");
        }
        
        // Add timeline event
        addFundingTimelineEvent(
            $application_id,
            'funding_completed',
            'Funding Completed',
            'Loan funds have been successfully disbursed',
            [
                'funding_amount' => $funding_amount,
                'funding_reference' => $funding_reference,
                'funded_by' => $funded_by
            ],
            $funded_by
        );
        
        // Send notification to client
        $client_info = getApplicationClient($application_id);
        if ($client_info) {
            sendFundingNotification($client_info['user_id'], 'funding_completed', [
                'application_id' => $application_id,
                'funding_amount' => $funding_amount,
                'funding_reference' => $funding_reference,
                'client_name' => $client_info['first_name'] . ' ' . $client_info['last_name']
            ]);
        }
        
        $db->commit();
        return true;
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Complete funding failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Cancel funding process
 */
function cancelFunding($application_id, $cancel_reason, $cancelled_by = null) {
    try {
        $db = getDB();
        $db->beginTransaction();
        
        // Update application status
        $stmt = $db->prepare("
            UPDATE loan_applications 
            SET application_status = 'approved',
                funding_initiated_at = NULL,
                funding_cancel_reason = ?,
                funding_cancelled_by = ?,
                funding_cancelled_at = NOW(),
                funding_status = 'cancelled'
            WHERE id = ? AND application_status = 'funding'
        ");
        $stmt->execute([$cancel_reason, $cancelled_by, $application_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Application not found or not in funding status");
        }
        
        // Add timeline event
        addFundingTimelineEvent(
            $application_id,
            'funding_cancelled',
            'Funding Cancelled',
            'Funding process has been cancelled: ' . $cancel_reason,
            [
                'cancel_reason' => $cancel_reason,
                'cancelled_by' => $cancelled_by
            ],
            $cancelled_by
        );
        
        // Send notification to client
        $client_info = getApplicationClient($application_id);
        if ($client_info) {
            sendFundingNotification($client_info['user_id'], 'funding_cancelled', [
                'application_id' => $application_id,
                'cancel_reason' => $cancel_reason,
                'client_name' => $client_info['first_name'] . ' ' . $client_info['last_name']
            ]);
        }
        
        $db->commit();
        return true;
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Cancel funding failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get application client information
 */
function getApplicationClient($application_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT u.* 
            FROM users u
            JOIN loan_applications la ON u.id = la.user_id
            WHERE la.id = ?
        ");
        $stmt->execute([$application_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Get application client failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Send funding notification
 */
function sendFundingNotification($user_id, $notification_type, $data = []) {
    try {
        $db = getDB();
        
        // Get user information
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception("User not found");
        }
        
        // Get email template
        $template = getFundingEmailTemplate($notification_type, $user['country']);
        if (!$template) {
            throw new Exception("Email template not found for type: $notification_type");
        }
        
        // Replace placeholders in template
        $placeholders = array_merge($data, [
            'client_name' => $user['first_name'] . ' ' . $user['last_name'],
            'first_name' => $user['first_name'],
            'reference_number' => $user['reference_number'],
            'company_name' => getSystemSetting('company_name', 'QuickFunds'),
            'support_email' => getSystemSetting('support_email', 'support@quickfunds.com'),
            'website_url' => getSystemSetting('website_url', 'https://quickfunds.com')
        ]);
        
        $subject = replacePlaceholders($template['subject'], $placeholders);
        $message = replacePlaceholders($template['content'], $placeholders);
        
        // Send email
        $email_sent = sendEmail($user['email'], $subject, $message, true);
        
        // Log notification
        $stmt = $db->prepare("
            INSERT INTO funding_notifications 
            (application_id, user_id, notification_type, title, message, delivery_status, delivered_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['application_id'] ?? null,
            $user_id,
            $notification_type,
            $subject,
            $message,
            $email_sent ? 'delivered' : 'failed',
            $email_sent ? date('Y-m-d H:i:s') : null
        ]);
        
        return $email_sent;
        
    } catch (Exception $e) {
        error_log("Send funding notification failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get funding email template
 */
function getFundingEmailTemplate($template_type, $country = 'US') {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT * FROM email_templates 
            WHERE template_type = ? AND (country = ? OR country = 'default')
            ORDER BY country DESC
            LIMIT 1
        ");
        $stmt->execute([$template_type, $country]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Get funding email template failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Get funding statistics
 */
function getFundingStatistics($date_from = null, $date_to = null) {
    try {
        $db = getDB();
        
        $where_clause = "";
        $params = [];
        
        if ($date_from && $date_to) {
            $where_clause = "WHERE funding_date BETWEEN ? AND ?";
            $params = [$date_from, $date_to];
        }
        
        $stmt = $db->prepare("
            SELECT 
                COUNT(CASE WHEN application_status = 'approved' THEN 1 END) as ready_for_funding,
                COUNT(CASE WHEN application_status = 'funding' THEN 1 END) as funding_in_progress,
                COUNT(CASE WHEN application_status = 'funded' THEN 1 END) as funding_completed,
                SUM(CASE WHEN application_status = 'funded' THEN funding_amount ELSE 0 END) as total_funded_amount,
                AVG(CASE WHEN application_status = 'funded' AND funding_date IS NOT NULL AND funding_initiated_at IS NOT NULL 
                         THEN TIMESTAMPDIFF(HOUR, funding_initiated_at, funding_date) END) as avg_funding_time_hours,
                COUNT(CASE WHEN funding_initiated_at >= CURDATE() THEN 1 END) as today_initiated,
                COUNT(CASE WHEN funding_date >= CURDATE() THEN 1 END) as today_completed
            FROM loan_applications
            WHERE application_status IN ('approved', 'funding', 'funded')
            $where_clause
        ");
        
        $stmt->execute($params);
        return $stmt->fetch();
        
    } catch (Exception $e) {
        error_log("Get funding statistics failed: " . $e->getMessage());
        return [
            'ready_for_funding' => 0,
            'funding_in_progress' => 0,
            'funding_completed' => 0,
            'total_funded_amount' => 0,
            'avg_funding_time_hours' => 0,
            'today_initiated' => 0,
            'today_completed' => 0
        ];
    }
}

/**
 * Get funding settings
 */
function getFundingSetting($setting_key, $default_value = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT setting_value, setting_type 
            FROM funding_settings 
            WHERE setting_key = ? AND is_active = 1
        ");
        $stmt->execute([$setting_key]);
        $setting = $stmt->fetch();
        
        if (!$setting) {
            return $default_value;
        }
        
        // Convert value based on type
        switch ($setting['setting_type']) {
            case 'boolean':
                return filter_var($setting['setting_value'], FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return intval($setting['setting_value']);
            case 'decimal':
                return floatval($setting['setting_value']);
            default:
                return $setting['setting_value'];
        }
        
    } catch (Exception $e) {
        error_log("Get funding setting failed: " . $e->getMessage());
        return $default_value;
    }
}

/**
 * Update funding setting
 */
function updateFundingSetting($setting_key, $setting_value) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE funding_settings 
            SET setting_value = ?, updated_at = NOW()
            WHERE setting_key = ?
        ");
        return $stmt->execute([$setting_value, $setting_key]);
    } catch (Exception $e) {
        error_log("Update funding setting failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if funding is allowed for application
 */
function isFundingAllowed($application_id) {
    try {
        $db = getDB();
        
        // Get application details
        $stmt = $db->prepare("
            SELECT la.*, bd.verified as bank_verified
            FROM loan_applications la
            LEFT JOIN bank_details bd ON la.user_id = bd.user_id AND bd.verified = 1
            WHERE la.id = ?
        ");
        $stmt->execute([$application_id]);
        $app = $stmt->fetch();
        
        if (!$app) {
            return ['allowed' => false, 'reason' => 'Application not found'];
        }
        
        // Check application status
        if ($app['application_status'] !== 'approved') {
            return ['allowed' => false, 'reason' => 'Application not approved'];
        }
        
        // Check if bank verification is required
        if (getFundingSetting('require_bank_verification', true) && !$app['bank_verified']) {
            return ['allowed' => false, 'reason' => 'Bank account not verified'];
        }
        
        // Check daily funding limits
        $max_daily_amount = getFundingSetting('max_daily_funding_amount', 1000000);
        $today_funded = getFundingStatistics(date('Y-m-d'), date('Y-m-d'))['total_funded_amount'];
        
        if (($today_funded + $app['loan_amount']) > $max_daily_amount) {
            return ['allowed' => false, 'reason' => 'Daily funding limit exceeded'];
        }
        
        // Check business days only setting
        if (getFundingSetting('funding_business_days_only', true)) {
            $day_of_week = date('N'); // 1 (Monday) to 7 (Sunday)
            if ($day_of_week > 5) { // Weekend
                return ['allowed' => false, 'reason' => 'Funding only allowed on business days'];
            }
        }
        
        return ['allowed' => true, 'reason' => 'Funding allowed'];
        
    } catch (Exception $e) {
        error_log("Check funding allowed failed: " . $e->getMessage());
        return ['allowed' => false, 'reason' => 'System error'];
    }
}

/**
 * Get funding documents for application
 */
function getFundingDocuments($application_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT fd.*, u.first_name, u.last_name
            FROM funding_documents fd
            LEFT JOIN users u ON fd.uploaded_by = u.id
            WHERE fd.application_id = ?
            ORDER BY fd.created_at DESC
        ");
        $stmt->execute([$application_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get funding documents failed: " . $e->getMessage());
        return [];
    }
}

/**
 * Calculate estimated funding date
 */
function calculateEstimatedFundingDate($application_id) {
    try {
        $processing_hours = getFundingSetting('funding_processing_time_hours', 24);
        $business_days_only = getFundingSetting('funding_business_days_only', true);
        
        $estimated_date = new DateTime();
        $estimated_date->add(new DateInterval("PT{$processing_hours}H"));
        
        // If business days only, adjust for weekends
        if ($business_days_only) {
            while ($estimated_date->format('N') > 5) { // Weekend
                $estimated_date->add(new DateInterval('P1D'));
            }
        }
        
        return $estimated_date->format('Y-m-d H:i:s');
        
    } catch (Exception $e) {
        error_log("Calculate estimated funding date failed: " . $e->getMessage());
        return date('Y-m-d H:i:s', strtotime('+1 day'));
    }
}

/**
 * Process automated funding workflows
 */
function processAutomatedFundingWorkflows() {
    try {
        if (!getFundingSetting('auto_funding_enabled', false)) {
            return ['processed' => 0, 'message' => 'Auto-funding disabled'];
        }
        
        $db = getDB();
        
        // Get applications ready for auto-funding
        $stmt = $db->prepare("
            SELECT id FROM loan_applications 
            WHERE application_status = 'approved'
            AND funding_initiated_at IS NULL
            AND approval_date <= DATE_SUB(NOW(), INTERVAL 1 DAY)
            LIMIT 10
        ");
        $stmt->execute();
        $applications = $stmt->fetchAll();
        
        $processed = 0;
        foreach ($applications as $app) {
            $funding_check = isFundingAllowed($app['id']);
            if ($funding_check['allowed']) {
                if (initiateFunding($app['id'], 1)) { // System user ID = 1
                    $processed++;
                }
            }
        }
        
        return ['processed' => $processed, 'message' => "Processed $processed applications"];
        
    } catch (Exception $e) {
        error_log("Process automated funding workflows failed: " . $e->getMessage());
        return ['processed' => 0, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Replace placeholders in text
 */
function replacePlaceholders($text, $placeholders) {
    foreach ($placeholders as $key => $value) {
        $text = str_replace('{' . $key . '}', $value, $text);
    }
    return $text;
}

/**
 * Format funding amount with currency
 */
function formatFundingAmount($amount, $country = 'US') {
    $currency_symbols = [
        'US' => '$',
        'CA' => 'C$',
        'GB' => '£',
        'EU' => '€',
        'AU' => 'A$'
    ];
    
    $symbol = $currency_symbols[$country] ?? '$';
    return $symbol . number_format($amount, 2);
}

/**
 * Log funding action for audit
 */
function logFundingAction($application_id, $action, $old_values = null, $new_values = null, $performed_by = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO funding_audit_log 
            (application_id, action, old_values, new_values, performed_by, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $application_id,
            $action,
            $old_values ? json_encode($old_values) : null,
            $new_values ? json_encode($new_values) : null,
            $performed_by,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
    } catch (Exception $e) {
        error_log("Log funding action failed: " . $e->getMessage());
        return false;
    }
}

?>