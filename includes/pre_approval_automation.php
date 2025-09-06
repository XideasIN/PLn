<?php
/**
 * Pre-Approval Automation System
 * LoanFlow Personal Loan Management System
 * Handles timed pre-approval workflows and automated status transitions
 */

class PreApprovalAutomation {
    
    private static $enabled = true;
    private static $auto_pre_approve = true;
    private static $pre_approval_delay_hours = 2;
    private static $document_review_timeout_hours = 48;
    private static $agreement_timeout_hours = 72;
    private static $funding_timeout_hours = 168; // 7 days
    
    /**
     * Initialize pre-approval automation system
     */
    public static function init() {
        self::$enabled = getSystemSetting('pre_approval_automation_enabled', '1') === '1';
        self::$auto_pre_approve = getSystemSetting('auto_pre_approve_enabled', '1') === '1';
        self::$pre_approval_delay_hours = (int)getSystemSetting('pre_approval_delay_hours', '2');
        self::$document_review_timeout_hours = (int)getSystemSetting('document_review_timeout_hours', '48');
        self::$agreement_timeout_hours = (int)getSystemSetting('agreement_timeout_hours', '72');
        self::$funding_timeout_hours = (int)getSystemSetting('funding_timeout_hours', '168');
    }
    
    /**
     * Process all pre-approval workflows
     */
    public static function processPreApprovalWorkflows() {
        if (!self::$enabled) {
            return ['processed' => 0, 'message' => 'Pre-approval automation disabled'];
        }
        
        $results = [
            'pre_approved' => 0,
            'document_reminders' => 0,
            'agreement_reminders' => 0,
            'funding_processed' => 0,
            'expired_applications' => 0,
            'total_processed' => 0
        ];
        
        try {
            // 1. Process automatic pre-approvals
            $results['pre_approved'] = self::processAutoPreApprovals();
            
            // 2. Send document upload reminders
            $results['document_reminders'] = self::sendDocumentReminders();
            
            // 3. Send agreement signing reminders
            $results['agreement_reminders'] = self::sendAgreementReminders();
            
            // 4. Process funding workflows
            $results['funding_processed'] = self::processFundingWorkflows();
            
            // 5. Handle expired applications
            $results['expired_applications'] = self::handleExpiredApplications();
            
            $results['total_processed'] = array_sum(array_slice($results, 0, -1));
            
            return $results;
            
        } catch (Exception $e) {
            error_log('Pre-approval automation error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Process automatic pre-approvals based on criteria
     */
    private static function processAutoPreApprovals() {
        if (!self::$auto_pre_approve) {
            return 0;
        }
        
        $db = getDB();
        $processed = 0;
        
        // Get applications eligible for auto pre-approval
        $stmt = $db->prepare("
            SELECT la.*, u.first_name, u.last_name, u.email, u.phone
            FROM loan_applications la
            JOIN users u ON la.user_id = u.id
            WHERE la.application_status = 'pending'
            AND la.created_at <= DATE_SUB(NOW(), INTERVAL ? HOUR)
            AND la.loan_amount <= 50000
            AND la.credit_score >= 650
            AND (la.monthly_income - la.monthly_expenses) >= (la.loan_amount * 0.05)
            ORDER BY la.created_at ASC
            LIMIT 20
        ");
        $stmt->execute([self::$pre_approval_delay_hours]);
        $applications = $stmt->fetchAll();
        
        foreach ($applications as $app) {
            if (self::evaluateForPreApproval($app)) {
                // Update to pre-approved status
                $update_stmt = $db->prepare("
                    UPDATE loan_applications 
                    SET application_status = 'pre_approved',
                        current_step = 2,
                        pre_approved_at = NOW(),
                        pre_approval_amount = ?,
                        pre_approval_rate = ?,
                        pre_approval_term = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $pre_approval_data = self::calculatePreApprovalTerms($app);
                $update_stmt->execute([
                    $pre_approval_data['amount'],
                    $pre_approval_data['rate'],
                    $pre_approval_data['term'],
                    $app['id']
                ]);
                
                // Send pre-approval email
                self::sendPreApprovalEmail($app, $pre_approval_data);
                
                // Log the pre-approval
                logAudit('auto_pre_approved', 'loan_applications', $app['id'], null, [
                    'amount' => $pre_approval_data['amount'],
                    'rate' => $pre_approval_data['rate'],
                    'term' => $pre_approval_data['term']
                ]);
                
                $processed++;
            }
        }
        
        return $processed;
    }
    
    /**
     * Evaluate application for pre-approval
     */
    private static function evaluateForPreApproval($app) {
        // Basic pre-approval criteria
        $criteria = [
            'credit_score' => $app['credit_score'] >= 650,
            'debt_to_income' => ($app['monthly_expenses'] / $app['monthly_income']) <= 0.4,
            'income_stability' => in_array($app['employment_status'], ['employed', 'self_employed']),
            'loan_amount' => $app['loan_amount'] <= 50000,
            'disposable_income' => ($app['monthly_income'] - $app['monthly_expenses']) >= ($app['loan_amount'] * 0.05)
        ];
        
        // Calculate score
        $score = 0;
        $total_criteria = count($criteria);
        
        foreach ($criteria as $met) {
            if ($met) $score++;
        }
        
        // Require at least 80% of criteria to be met
        return ($score / $total_criteria) >= 0.8;
    }
    
    /**
     * Calculate pre-approval terms
     */
    private static function calculatePreApprovalTerms($app) {
        $base_rate = 12.0;
        $credit_adjustment = 0;
        $income_adjustment = 0;
        
        // Adjust rate based on credit score
        if ($app['credit_score'] >= 750) {
            $credit_adjustment = -2.0;
        } elseif ($app['credit_score'] >= 700) {
            $credit_adjustment = -1.0;
        } elseif ($app['credit_score'] < 650) {
            $credit_adjustment = 3.0;
        }
        
        // Adjust rate based on debt-to-income ratio
        $dti = $app['monthly_expenses'] / $app['monthly_income'];
        if ($dti <= 0.2) {
            $income_adjustment = -1.0;
        } elseif ($dti >= 0.35) {
            $income_adjustment = 2.0;
        }
        
        $final_rate = max(6.0, min(25.0, $base_rate + $credit_adjustment + $income_adjustment));
        
        // Determine loan term based on amount
        $term = 36; // Default 3 years
        if ($app['loan_amount'] >= 25000) {
            $term = 60; // 5 years for larger loans
        } elseif ($app['loan_amount'] <= 10000) {
            $term = 24; // 2 years for smaller loans
        }
        
        return [
            'amount' => $app['loan_amount'],
            'rate' => round($final_rate, 2),
            'term' => $term
        ];
    }
    
    /**
     * Send document upload reminders
     */
    private static function sendDocumentReminders() {
        $db = getDB();
        $sent = 0;
        
        // Get pre-approved applications without complete documents
        $stmt = $db->query("
            SELECT la.*, u.first_name, u.last_name, u.email,
                   COUNT(d.id) as doc_count,
                   COUNT(CASE WHEN d.upload_status = 'verified' THEN 1 END) as verified_count
            FROM loan_applications la
            JOIN users u ON la.user_id = u.id
            LEFT JOIN documents d ON la.user_id = d.user_id
            WHERE la.application_status = 'pre_approved'
            AND la.pre_approved_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND la.pre_approved_at >= DATE_SUB(NOW(), INTERVAL 72 HOUR)
            GROUP BY la.id
            HAVING doc_count < 3 OR verified_count < 3
        ");
        $applications = $stmt->fetchAll();
        
        foreach ($applications as $app) {
            // Check if reminder already sent today
            $reminder_check = $db->prepare("
                SELECT COUNT(*) as count FROM email_queue 
                WHERE recipient_email = ? 
                AND template_type = 'document_reminder'
                AND DATE(created_at) = CURDATE()
            ");
            $reminder_check->execute([$app['email']]);
            
            if ($reminder_check->fetch()['count'] == 0) {
                self::sendDocumentReminderEmail($app);
                $sent++;
            }
        }
        
        return $sent;
    }
    
    /**
     * Send agreement signing reminders
     */
    private static function sendAgreementReminders() {
        $db = getDB();
        $sent = 0;
        
        // Get applications with verified documents but no signed agreement
        $stmt = $db->query("
            SELECT la.*, u.first_name, u.last_name, u.email,
                   COUNT(d.id) as doc_count,
                   COUNT(CASE WHEN d.upload_status = 'verified' THEN 1 END) as verified_count,
                   COUNT(ds.id) as signature_count
            FROM loan_applications la
            JOIN users u ON la.user_id = u.id
            LEFT JOIN documents d ON la.user_id = d.user_id
            LEFT JOIN digital_signatures ds ON la.user_id = ds.user_id AND ds.document_type = 'loan_agreement'
            WHERE la.application_status IN ('pre_approved', 'document_review')
            AND la.updated_at <= DATE_SUB(NOW(), INTERVAL 48 HOUR)
            GROUP BY la.id
            HAVING doc_count >= 3 AND verified_count >= 3 AND signature_count = 0
        ");
        $applications = $stmt->fetchAll();
        
        foreach ($applications as $app) {
            // Check if reminder already sent today
            $reminder_check = $db->prepare("
                SELECT COUNT(*) as count FROM email_queue 
                WHERE recipient_email = ? 
                AND template_type = 'agreement_reminder'
                AND DATE(created_at) = CURDATE()
            ");
            $reminder_check->execute([$app['email']]);
            
            if ($reminder_check->fetch()['count'] == 0) {
                self::sendAgreementReminderEmail($app);
                $sent++;
            }
        }
        
        return $sent;
    }
    
    /**
     * Process funding workflows
     */
    private static function processFundingWorkflows() {
        $db = getDB();
        $processed = 0;
        
        // Get approved applications ready for funding
        $stmt = $db->query("
            SELECT la.*, u.first_name, u.last_name, u.email,
                   COUNT(ds.id) as signature_count,
                   COUNT(bd.id) as bank_details_count
            FROM loan_applications la
            JOIN users u ON la.user_id = u.id
            LEFT JOIN digital_signatures ds ON la.user_id = ds.user_id AND ds.document_type = 'loan_agreement'
            LEFT JOIN bank_details bd ON la.user_id = bd.user_id AND bd.is_primary = 1
            WHERE la.application_status = 'approved'
            AND la.updated_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY la.id
            HAVING signature_count > 0 AND bank_details_count > 0
        ");
        $applications = $stmt->fetchAll();
        
        foreach ($applications as $app) {
            // Move to funding status
            $update_stmt = $db->prepare("
                UPDATE loan_applications 
                SET application_status = 'funding',
                    current_step = 5,
                    funding_initiated_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $update_stmt->execute([$app['id']]);
            
            // Send funding notification
            self::sendFundingNotificationEmail($app);
            
            // Log the funding initiation
            logAudit('funding_initiated', 'loan_applications', $app['id'], null, [
                'automated' => true,
                'funding_amount' => $app['loan_amount']
            ]);
            
            $processed++;
        }
        
        return $processed;
    }
    
    /**
     * Handle expired applications
     */
    private static function handleExpiredApplications() {
        $db = getDB();
        $expired = 0;
        
        // Expire applications that have been in pre_approved status too long without documents
        $stmt = $db->prepare("
            UPDATE loan_applications la
            LEFT JOIN (
                SELECT user_id, COUNT(*) as doc_count,
                       COUNT(CASE WHEN upload_status = 'verified' THEN 1 END) as verified_count
                FROM documents 
                GROUP BY user_id
            ) d ON la.user_id = d.user_id
            SET la.application_status = 'expired',
                la.expiry_reason = 'Documents not submitted within required timeframe',
                la.updated_at = NOW()
            WHERE la.application_status = 'pre_approved'
            AND la.pre_approved_at <= DATE_SUB(NOW(), INTERVAL ? HOUR)
            AND (d.doc_count IS NULL OR d.doc_count < 3 OR d.verified_count < 3)
        ");
        $stmt->execute([self::$document_review_timeout_hours]);
        $expired += $stmt->rowCount();
        
        // Expire applications that have been in document_review too long without agreement
        $stmt = $db->prepare("
            UPDATE loan_applications la
            LEFT JOIN digital_signatures ds ON la.user_id = ds.user_id AND ds.document_type = 'loan_agreement'
            SET la.application_status = 'expired',
                la.expiry_reason = 'Agreement not signed within required timeframe',
                la.updated_at = NOW()
            WHERE la.application_status = 'document_review'
            AND la.updated_at <= DATE_SUB(NOW(), INTERVAL ? HOUR)
            AND ds.id IS NULL
        ");
        $stmt->execute([self::$agreement_timeout_hours]);
        $expired += $stmt->rowCount();
        
        return $expired;
    }
    
    /**
     * Send pre-approval email
     */
    private static function sendPreApprovalEmail($app, $terms) {
        $template_data = [
            'client_name' => $app['first_name'] . ' ' . $app['last_name'],
            'reference_number' => $app['reference_number'],
            'approved_amount' => '$' . number_format($terms['amount'], 2),
            'interest_rate' => $terms['rate'] . '%',
            'loan_term' => $terms['term'] . ' months',
            'monthly_payment' => '$' . number_format(self::calculateMonthlyPayment($terms['amount'], $terms['rate'], $terms['term']), 2),
            'login_url' => getSystemSetting('site_url', '') . '/client/dashboard.php',
            'company_name' => getSystemSetting('company_name', 'LoanFlow')
        ];
        
        queueEmail(
            $app['email'],
            'Congratulations! Your Loan Pre-Approval is Ready',
            'pre_approval_notification',
            $template_data
        );
    }
    
    /**
     * Send document reminder email
     */
    private static function sendDocumentReminderEmail($app) {
        $template_data = [
            'client_name' => $app['first_name'] . ' ' . $app['last_name'],
            'reference_number' => $app['reference_number'],
            'login_url' => getSystemSetting('site_url', '') . '/client/dashboard.php',
            'company_name' => getSystemSetting('company_name', 'LoanFlow')
        ];
        
        queueEmail(
            $app['email'],
            'Action Required: Upload Your Documents',
            'document_reminder',
            $template_data
        );
    }
    
    /**
     * Send agreement reminder email
     */
    private static function sendAgreementReminderEmail($app) {
        $template_data = [
            'client_name' => $app['first_name'] . ' ' . $app['last_name'],
            'reference_number' => $app['reference_number'],
            'login_url' => getSystemSetting('site_url', '') . '/client/dashboard.php',
            'company_name' => getSystemSetting('company_name', 'LoanFlow')
        ];
        
        queueEmail(
            $app['email'],
            'Action Required: Sign Your Loan Agreement',
            'agreement_reminder',
            $template_data
        );
    }
    
    /**
     * Send funding notification email
     */
    private static function sendFundingNotificationEmail($app) {
        $template_data = [
            'client_name' => $app['first_name'] . ' ' . $app['last_name'],
            'reference_number' => $app['reference_number'],
            'loan_amount' => '$' . number_format($app['loan_amount'], 2),
            'login_url' => getSystemSetting('site_url', '') . '/client/dashboard.php',
            'company_name' => getSystemSetting('company_name', 'LoanFlow')
        ];
        
        queueEmail(
            $app['email'],
            'Great News! Your Loan Funding is Being Processed',
            'funding_notification',
            $template_data
        );
    }
    
    /**
     * Calculate monthly payment
     */
    private static function calculateMonthlyPayment($principal, $annual_rate, $term_months) {
        $monthly_rate = ($annual_rate / 100) / 12;
        if ($monthly_rate == 0) {
            return $principal / $term_months;
        }
        
        return $principal * ($monthly_rate * pow(1 + $monthly_rate, $term_months)) / (pow(1 + $monthly_rate, $term_months) - 1);
    }
    
    /**
     * Get automation statistics
     */
    public static function getAutomationStats() {
        $db = getDB();
        
        $stats = [];
        
        // Pre-approvals today
        $stmt = $db->query("
            SELECT COUNT(*) as count 
            FROM loan_applications 
            WHERE application_status = 'pre_approved' 
            AND DATE(pre_approved_at) = CURDATE()
        ");
        $stats['pre_approvals_today'] = $stmt->fetch()['count'];
        
        // Total automated pre-approvals this month
        $stmt = $db->query("
            SELECT COUNT(*) as count 
            FROM loan_applications 
            WHERE application_status IN ('pre_approved', 'approved', 'funding', 'funded') 
            AND pre_approved_at IS NOT NULL
            AND MONTH(pre_approved_at) = MONTH(CURDATE())
            AND YEAR(pre_approved_at) = YEAR(CURDATE())
        ");
        $stats['pre_approvals_this_month'] = $stmt->fetch()['count'];
        
        // Applications in workflow
        $stmt = $db->query("
            SELECT application_status, COUNT(*) as count 
            FROM loan_applications 
            WHERE application_status IN ('pre_approved', 'document_review', 'approved', 'funding')
            GROUP BY application_status
        ");
        $workflow_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $stats['workflow_counts'] = $workflow_stats;
        
        // Average processing time
        $stmt = $db->query("
            SELECT 
                AVG(TIMESTAMPDIFF(HOUR, created_at, pre_approved_at)) as avg_pre_approval_hours,
                AVG(TIMESTAMPDIFF(HOUR, pre_approved_at, updated_at)) as avg_completion_hours
            FROM loan_applications 
            WHERE pre_approved_at IS NOT NULL
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $timing_stats = $stmt->fetch();
        $stats['avg_pre_approval_hours'] = round($timing_stats['avg_pre_approval_hours'] ?? 0, 1);
        $stats['avg_completion_hours'] = round($timing_stats['avg_completion_hours'] ?? 0, 1);
        
        return $stats;
    }
}

// Initialize the pre-approval automation system
PreApprovalAutomation::init();
?>