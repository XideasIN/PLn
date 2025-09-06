<?php
/**
 * Enhanced Payment System with Country Restrictions and User Forms
 * LoanFlow Personal Loan Management System
 */

require_once 'functions.php';
require_once '2fa.php';
require_once 'email.php';

class EnhancedPaymentManager {
    
    /**
     * Get user's assigned payment scheme
     */
    public static function getUserPaymentScheme($user_id) {
        try {
            $db = getDB();
            
            // Check if user has a specific scheme assigned
            $stmt = $db->prepare("
                SELECT ups.*, ps.* 
                FROM user_payment_schemes ups
                JOIN payment_schemes ps ON ps.scheme_type = ups.scheme_type AND ps.is_active = 1
                WHERE ups.user_id = ?
            ");
            $stmt->execute([$user_id]);
            $user_scheme = $stmt->fetch();
            
            if ($user_scheme) {
                return $user_scheme;
            }
            
            // Fall back to global default scheme (percentage)
            $stmt = $db->prepare("SELECT * FROM payment_schemes WHERE scheme_type = 'percentage' AND is_active = 1 LIMIT 1");
            $stmt->execute();
            return $stmt->fetch();
            
        } catch (Exception $e) {
            error_log("Get user payment scheme failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Assign subscription scheme to user (admin action requiring 2FA)
     */
    public static function assignSubscriptionScheme($user_id, $admin_id, $admin_2fa_code = null) {
        try {
            $db = getDB();
            
            // Verify admin 2FA if required
            if ($admin_2fa_code && !TwoFactorAuth::verifyCode($admin_id, $admin_2fa_code)) {
                return [
                    'success' => false,
                    'error' => 'Invalid 2FA code. Subscription assignment requires double authentication.'
                ];
            }
            
            $db->beginTransaction();
            
            // Remove existing scheme assignment
            $stmt = $db->prepare("DELETE FROM user_payment_schemes WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Assign subscription scheme
            $stmt = $db->prepare("
                INSERT INTO user_payment_schemes 
                (user_id, scheme_type, assigned_by, requires_2fa, created_at) 
                VALUES (?, 'subscription', ?, 1, NOW())
            ");
            $stmt->execute([$user_id, $admin_id]);
            
            $assignment_id = $db->lastInsertId();
            
            // Log the assignment
            logAudit('subscription_scheme_assigned', 'user_payment_schemes', $assignment_id, null, [
                'user_id' => $user_id,
                'assigned_by' => $admin_id,
                'requires_2fa' => true
            ]);
            
            $db->commit();
            
            // Send notification to user
            self::sendSchemeAssignmentNotification($user_id, 'subscription');
            
            return [
                'success' => true,
                'message' => 'Subscription scheme assigned successfully. User will need to verify with 2FA.',
                'assignment_id' => $assignment_id
            ];
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Assign subscription scheme failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to assign subscription scheme: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * User verifies subscription assignment with 2FA
     */
    public static function verifySubscriptionAssignment($user_id, $user_2fa_code) {
        try {
            $db = getDB();
            
            // Verify user 2FA
            if (!TwoFactorAuth::verifyCode($user_id, $user_2fa_code)) {
                return [
                    'success' => false,
                    'error' => 'Invalid 2FA code. Please try again.'
                ];
            }
            
            // Update verification status
            $stmt = $db->prepare("
                UPDATE user_payment_schemes 
                SET 2fa_verified = 1, verified_at = NOW() 
                WHERE user_id = ? AND requires_2fa = 1
            ");
            $result = $stmt->execute([$user_id]);
            
            if ($result && $stmt->rowCount() > 0) {
                logAudit('subscription_scheme_verified', 'user_payment_schemes', null, null, [
                    'user_id' => $user_id,
                    'verified_at' => date('Y-m-d H:i:s')
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Subscription scheme verified successfully.'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'No pending subscription assignment found.'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Verify subscription assignment failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Verification failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get available payment methods for user's country
     */
    public static function getAvailablePaymentMethods($user_country) {
        try {
            $db = getDB();
            
            $stmt = $db->prepare("
                SELECT method_name, config_data, instructions, email_template
                FROM payment_method_config 
                WHERE is_enabled = 1 
                AND JSON_CONTAINS(allowed_countries, ?)
            ");
            $stmt->execute(['"' . $user_country . '"']);
            
            $methods = [];
            while ($row = $stmt->fetch()) {
                $config = json_decode($row['config_data'], true) ?: [];
                
                // Only include methods that have been configured by admin
                if (self::isMethodConfigured($row['method_name'], $config)) {
                    $methods[$row['method_name']] = [
                        'config' => $config,
                        'instructions' => $row['instructions'],
                        'email_template' => $row['email_template']
                    ];
                }
            }
            
            return $methods;
            
        } catch (Exception $e) {
            error_log("Get available payment methods failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if payment method is properly configured by admin
     */
    private static function isMethodConfigured($method_name, $config) {
        // First check if the method is enabled by admin
        $payment_settings = getPaymentSettings();
        
        switch ($method_name) {
            case 'wire_transfer':
                return !empty($config['bank_name']) && 
                       !empty($config['account_number']) && 
                       !empty($config['account_name']);
            case 'crypto':
                return !empty($config['wallet_address']) && 
                       !empty($config['currency_type']);
            case 'e_transfer':
                return !empty($config['email_address']) && 
                       !empty($config['recipient_name']);
            case 'credit_card':
                // Check if Stripe is enabled and configured
                $stripe_enabled = ($payment_settings['stripe_enabled'] ?? '0') === '1' &&
                                 !empty($payment_settings['stripe_publishable_key']) && 
                                 !empty($payment_settings['stripe_secret_key']);
                
                // Check if PayPal is enabled and configured  
                $paypal_enabled = ($payment_settings['paypal_enabled'] ?? '0') === '1' &&
                                 !empty($payment_settings['paypal_client_id']) && 
                                 !empty($payment_settings['paypal_client_secret']);
                
                // At least one gateway must be enabled and configured
                return $stripe_enabled || $paypal_enabled;
            case 'stripe':
                // Direct Stripe method check
                return ($payment_settings['stripe_enabled'] ?? '0') === '1' &&
                       !empty($payment_settings['stripe_publishable_key']) && 
                       !empty($payment_settings['stripe_secret_key']);
            case 'paypal':
                // Direct PayPal method check
                return ($payment_settings['paypal_enabled'] ?? '0') === '1' &&
                       !empty($payment_settings['paypal_client_id']) && 
                       !empty($payment_settings['paypal_client_secret']);
            default:
                return false;
        }
    }
    
    /**
     * Create payment with enhanced features
     */
    public static function createEnhancedPayment($user_id, $application_id, $payment_method, $scheme = null) {
        try {
            $db = getDB();
            
            if (!$scheme) {
                $scheme = self::getUserPaymentScheme($user_id);
            }
            
            if (!$scheme) {
                throw new Exception('No payment scheme found for user');
            }
            
            // Get loan amount for percentage calculation
            $stmt = $db->prepare("SELECT loan_amount FROM loan_applications WHERE id = ?");
            $stmt->execute([$application_id]);
            $application = $stmt->fetch();
            
            if (!$application) {
                throw new Exception('Application not found');
            }
            
            // Calculate amount based on scheme
            if ($scheme['scheme_type'] === 'subscription') {
                $amount = $scheme['subscription_fee'];
            } else {
                $amount = ($application['loan_amount'] * $scheme['percentage_fee']) / 100;
                
                // Apply min/max limits if set
                if (isset($scheme['percentage_min_fee']) && $amount < $scheme['percentage_min_fee']) {
                    $amount = $scheme['percentage_min_fee'];
                }
                if (isset($scheme['percentage_max_fee']) && $amount > $scheme['percentage_max_fee']) {
                    $amount = $scheme['percentage_max_fee'];
                }
            }
            
            // Create payment record
            $stmt = $db->prepare("
                INSERT INTO payments (
                    user_id, application_id, payment_type, amount, currency, 
                    payment_method, payment_status, due_date, requires_2fa
                ) VALUES (?, ?, ?, ?, 'USD', ?, 'pending', DATE_ADD(NOW(), INTERVAL 7 DAY), ?)
            ");
            
            $requires_2fa = ($scheme['scheme_type'] === 'subscription') ? 1 : 0;
            
            $result = $stmt->execute([
                $user_id, 
                $application_id, 
                $scheme['scheme_type'], 
                $amount, 
                $payment_method,
                $requires_2fa
            ]);
            
            if ($result) {
                $payment_id = $db->lastInsertId();
                
                // Send payment instructions
                self::sendPaymentInstructions($user_id, $payment_id, $payment_method);
                
                logAudit('enhanced_payment_created', 'payments', $payment_id, null, [
                    'user_id' => $user_id,
                    'amount' => $amount,
                    'method' => $payment_method,
                    'scheme' => $scheme['scheme_type']
                ]);
                
                return [
                    'success' => true,
                    'payment_id' => $payment_id,
                    'amount' => $amount,
                    'payment_method' => $payment_method,
                    'requires_2fa' => $requires_2fa
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Failed to create payment record'
            ];
            
        } catch (Exception $e) {
            error_log("Create enhanced payment failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send payment instructions based on method and admin settings
     */
    private static function sendPaymentInstructions($user_id, $payment_id, $payment_method) {
        try {
            $user_data = getUserById($user_id);
            if (!$user_data) return false;
            
            $payment_config = self::getPaymentMethodConfig($payment_method);
            if (!$payment_config) return false;
            
            // Check if admin has enabled auto-email for this method
            $config = $payment_config['config'];
            if (!($config['auto_email_instructions'] ?? true)) {
                return true; // Admin disabled auto-email, return success without sending
            }
            
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM payments WHERE id = ?");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch();
            
            if (!$payment) return false;
            
            // Prepare email data with all config details
            $email_data = [
                'payment_id' => $payment_id,
                'amount' => formatCurrency($payment['amount'], $user_data['country']),
                'payment_method' => $payment_method,
                'instructions' => $payment_config['instructions'],
                'user_name' => $user_data['first_name'] . ' ' . $user_data['last_name'],
                'login_url' => getSystemSetting('base_url', '') . '/client/payments.php'
            ];
            
            // Add method-specific config data to email
            foreach ($config as $key => $value) {
                if (!is_array($value) && !is_object($value)) {
                    $email_data[$key] = $value;
                }
            }
            
            // Use custom email template if provided, otherwise use default
            $email_template = $payment_config['email_template'];
            if (!empty($email_template)) {
                // Send custom template
                $subject = "Payment Instructions - Payment #" . $payment_id;
                $body = self::processEmailTemplate($email_template, $email_data);
                return sendEmail($user_data['email'], $subject, $body, false);
            } else {
                // Send default template
                $email_manager = new EmailManager();
                $template_name = 'payment_instructions_' . $payment_method;
                return $email_manager->sendTemplateEmail($template_name, $user_id, $user_data, $email_data, true);
            }
            
        } catch (Exception $e) {
            error_log("Send payment instructions failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process email template with variable substitution
     */
    private static function processEmailTemplate($template, $data) {
        $processed = $template;
        
        foreach ($data as $key => $value) {
            $processed = str_replace('{' . $key . '}', $value, $processed);
        }
        
        return $processed;
    }
    
    /**
     * Get payment method configuration
     */
    public static function getPaymentMethodConfig($method_name) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT config_data, instructions, email_template 
                FROM payment_method_config 
                WHERE method_name = ? AND is_enabled = 1
            ");
            $stmt->execute([$method_name]);
            $config = $stmt->fetch();
            
            if ($config) {
                return [
                    'config' => json_decode($config['config_data'], true) ?: [],
                    'instructions' => $config['instructions'],
                    'email_template' => $config['email_template']
                ];
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Get payment method config failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get available payment gateways (Stripe/PayPal) based on admin configuration
     */
    public static function getAvailablePaymentGateways() {
        $payment_settings = getPaymentSettings();
        $available_gateways = [];
        
        // Check Stripe configuration
        if (($payment_settings['stripe_enabled'] ?? '0') === '1' && 
            !empty($payment_settings['stripe_publishable_key']) && 
            !empty($payment_settings['stripe_secret_key'])) {
            $available_gateways['stripe'] = [
                'name' => 'Stripe',
                'enabled' => true,
                'publishable_key' => $payment_settings['stripe_publishable_key'],
                'webhook_secret' => $payment_settings['stripe_webhook_secret'] ?? ''
            ];
        }
        
        // Check PayPal configuration
        if (($payment_settings['paypal_enabled'] ?? '0') === '1' && 
            !empty($payment_settings['paypal_client_id']) && 
            !empty($payment_settings['paypal_client_secret'])) {
            $available_gateways['paypal'] = [
                'name' => 'PayPal',
                'enabled' => true,
                'client_id' => $payment_settings['paypal_client_id'],
                'sandbox' => ($payment_settings['paypal_sandbox'] ?? '1') === '1'
            ];
        }
        
        return $available_gateways;
    }
    
    /**
     * Check if any payment gateways are available
     */
    public static function hasAvailableGateways() {
        $gateways = self::getAvailablePaymentGateways();
        return !empty($gateways);
    }
    
    /**
     * Get payment gateway status for admin interface
     */
    public static function getGatewayStatus() {
        $payment_settings = getPaymentSettings();
        
        return [
            'stripe' => [
                'enabled' => ($payment_settings['stripe_enabled'] ?? '0') === '1',
                'configured' => !empty($payment_settings['stripe_publishable_key']) && 
                               !empty($payment_settings['stripe_secret_key']),
                'available' => ($payment_settings['stripe_enabled'] ?? '0') === '1' && 
                              !empty($payment_settings['stripe_publishable_key']) && 
                              !empty($payment_settings['stripe_secret_key']),
                'missing_fields' => self::getMissingStripeFields($payment_settings)
            ],
            'paypal' => [
                'enabled' => ($payment_settings['paypal_enabled'] ?? '0') === '1',
                'configured' => !empty($payment_settings['paypal_client_id']) && 
                               !empty($payment_settings['paypal_client_secret']),
                'available' => ($payment_settings['paypal_enabled'] ?? '0') === '1' && 
                              !empty($payment_settings['paypal_client_id']) && 
                              !empty($payment_settings['paypal_client_secret']),
                'missing_fields' => self::getMissingPayPalFields($payment_settings)
            ]
        ];
    }
    
    /**
     * Get missing Stripe configuration fields
     */
    private static function getMissingStripeFields($settings) {
        $missing = [];
        if (empty($settings['stripe_publishable_key'])) {
            $missing[] = 'Publishable Key';
        }
        if (empty($settings['stripe_secret_key'])) {
            $missing[] = 'Secret Key';
        }
        return $missing;
    }
    
    /**
     * Get missing PayPal configuration fields
     */
    private static function getMissingPayPalFields($settings) {
        $missing = [];
        if (empty($settings['paypal_client_id'])) {
            $missing[] = 'Client ID';
        }
        if (empty($settings['paypal_client_secret'])) {
            $missing[] = 'Client Secret';
        }
        return $missing;
    }
    
    /**
     * Submit payment confirmation (for wire transfer and e-transfer)
     */
    public static function submitPaymentConfirmation($payment_id, $user_id, $confirmation_data, $uploaded_file = null) {
        try {
            $db = getDB();
            
            // Verify payment belongs to user
            $stmt = $db->prepare("SELECT p.*, pmc.config_data FROM payments p 
                                 JOIN payment_method_config pmc ON pmc.method_name = p.payment_method 
                                 WHERE p.id = ? AND p.user_id = ?");
            $stmt->execute([$payment_id, $user_id]);
            $payment = $stmt->fetch();
            
            if (!$payment) {
                return [
                    'success' => false,
                    'error' => 'Payment not found or access denied'
                ];
            }
            
            $method_config = json_decode($payment['config_data'], true) ?: [];
            
            // Check if admin requires confirmation for this method
            if (!($method_config['require_confirmation'] ?? true)) {
                return [
                    'success' => false,
                    'error' => 'Payment confirmation not required for this method'
                ];
            }
            
            $confirmation_image_path = null;
            
            // Check if image upload is required by admin
            $require_image = $method_config['require_image_upload'] ?? true;
            $allow_manual = $method_config['allow_manual_details'] ?? true;
            
            if ($require_image && (!$uploaded_file || $uploaded_file['error'] !== UPLOAD_ERR_OK)) {
                return [
                    'success' => false,
                    'error' => 'Image upload is required for this payment method'
                ];
            }
            
            // For e-Transfer, check if manual details are allowed when no image provided
            if ($payment['payment_method'] === 'e_transfer' && !$uploaded_file && !$allow_manual) {
                return [
                    'success' => false,
                    'error' => 'Either image upload or manual details entry is required'
                ];
            }
            
            // Handle file upload if provided
            if ($uploaded_file && $uploaded_file['error'] === UPLOAD_ERR_OK) {
                $upload_result = self::handleConfirmationFileUpload($uploaded_file, $payment_id, $method_config);
                if ($upload_result['success']) {
                    $confirmation_image_path = $upload_result['file_path'];
                } else {
                    return $upload_result;
                }
            }
            
            // Check confirmation timeout
            $timeout_hours = $method_config['confirmation_timeout'] ?? 72;
            $due_date = date('Y-m-d H:i:s', strtotime($payment['created_at'] . ' +' . $timeout_hours . ' hours'));
            
            if (date('Y-m-d H:i:s') > $due_date) {
                return [
                    'success' => false,
                    'error' => 'Confirmation period has expired. Please contact support.'
                ];
            }
            
            // Update payment with confirmation
            $stmt = $db->prepare("
                UPDATE payments 
                SET confirmation_details = ?, confirmation_image = ?, payment_status = 'processing', updated_at = NOW()
                WHERE id = ?
            ");
            
            $details_json = json_encode($confirmation_data);
            $result = $stmt->execute([$details_json, $confirmation_image_path, $payment_id]);
            
            if ($result) {
                // Notify admin of confirmation submission
                self::notifyAdminOfConfirmation($payment_id, $user_id);
                
                logAudit('payment_confirmation_submitted', 'payments', $payment_id, null, [
                    'user_id' => $user_id,
                    'has_image' => !empty($confirmation_image_path),
                    'details_provided' => !empty($confirmation_data),
                    'method' => $payment['payment_method']
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Payment confirmation submitted successfully. Your payment is being processed.'
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Failed to submit confirmation'
            ];
            
        } catch (Exception $e) {
            error_log("Submit payment confirmation failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Confirmation submission failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Handle confirmation file upload with admin-defined restrictions
     */
    private static function handleConfirmationFileUpload($file, $payment_id, $method_config = []) {
        try {
            // Get admin-defined settings or use defaults
            $max_size_mb = $method_config['max_file_size'] ?? 10;
            $max_size = $max_size_mb * 1024 * 1024; // Convert MB to bytes
            
            $allowed_extensions = explode(',', $method_config['allowed_file_types'] ?? 'jpg,jpeg,png,pdf');
            $allowed_extensions = array_map('trim', $allowed_extensions);
            $allowed_extensions = array_map('strtolower', $allowed_extensions);
            
            // Map extensions to MIME types
            $mime_map = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'pdf' => 'application/pdf'
            ];
            
            $allowed_mime_types = [];
            foreach ($allowed_extensions as $ext) {
                if (isset($mime_map[$ext])) {
                    $allowed_mime_types[] = $mime_map[$ext];
                }
            }
            
            // Validate file type
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($file_extension, $allowed_extensions)) {
                return [
                    'success' => false,
                    'error' => 'Invalid file type. Allowed types: ' . implode(', ', $allowed_extensions)
                ];
            }
            
            if (!in_array($file['type'], $allowed_mime_types)) {
                return [
                    'success' => false,
                    'error' => 'Invalid file type. Please upload only allowed file formats.'
                ];
            }
            
            // Validate file size
            if ($file['size'] > $max_size) {
                return [
                    'success' => false,
                    'error' => "File too large. Maximum size is {$max_size_mb}MB."
                ];
            }
            
            // Create upload directory if it doesn't exist
            $upload_dir = dirname(__DIR__) . '/uploads/payment_confirmations';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate secure filename
            $filename = 'payment_' . $payment_id . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
            $file_path = $upload_dir . '/' . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                return [
                    'success' => true,
                    'file_path' => 'uploads/payment_confirmations/' . $filename
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to upload file'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Handle confirmation file upload failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'File upload failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send scheme assignment notification
     */
    private static function sendSchemeAssignmentNotification($user_id, $scheme_type) {
        try {
            $user_data = getUserById($user_id);
            if (!$user_data) return false;
            
            $email_data = [
                'scheme_type' => $scheme_type,
                'user_name' => $user_data['first_name'] . ' ' . $user_data['last_name']
            ];
            
            $email_manager = new EmailManager();
            return $email_manager->sendTemplateEmail('scheme_assignment_notification', $user_id, $user_data, $email_data, true);
            
        } catch (Exception $e) {
            error_log("Send scheme assignment notification failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Notify admin of payment confirmation submission
     */
    private static function notifyAdminOfConfirmation($payment_id, $user_id) {
        try {
            $admin_email = getSystemSetting('admin_email', '');
            if (empty($admin_email)) return false;
            
            $user_data = getUserById($user_id);
            $payment_data = [
                'payment_id' => $payment_id,
                'user_name' => $user_data['first_name'] . ' ' . $user_data['last_name'],
                'user_email' => $user_data['email']
            ];
            
            $subject = 'Payment Confirmation Submitted - Payment #' . $payment_id;
            $message = "A payment confirmation has been submitted by {$user_data['first_name']} {$user_data['last_name']} for Payment ID: {$payment_id}. Please review and approve in the admin panel.";
            
            return sendEmail($admin_email, $subject, $message, false);
            
        } catch (Exception $e) {
            error_log("Notify admin of confirmation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get payment statistics for admin
     */
    public static function getPaymentStatistics() {
        try {
            $db = getDB();
            
            // Total payments by status
            $stmt = $db->query("
                SELECT payment_status, COUNT(*) as count, SUM(amount) as total_amount
                FROM payments 
                GROUP BY payment_status
            ");
            $status_stats = $stmt->fetchAll();
            
            // Payments by method
            $stmt = $db->query("
                SELECT payment_method, COUNT(*) as count, SUM(amount) as total_amount
                FROM payments 
                WHERE payment_method IS NOT NULL
                GROUP BY payment_method
            ");
            $method_stats = $stmt->fetchAll();
            
            // Scheme distribution
            $stmt = $db->query("
                SELECT payment_type, COUNT(*) as count, SUM(amount) as total_amount
                FROM payments 
                GROUP BY payment_type
            ");
            $scheme_stats = $stmt->fetchAll();
            
            return [
                'status_breakdown' => $status_stats,
                'method_breakdown' => $method_stats,
                'scheme_breakdown' => $scheme_stats
            ];
            
        } catch (Exception $e) {
            error_log("Get payment statistics failed: " . $e->getMessage());
            return [];
        }
    }
}

// Helper function to format currency based on country
function formatCurrency($amount, $country = 'USA') {
    $currencies = [
        'USA' => ['symbol' => '$', 'format' => 'before'],
        'CAN' => ['symbol' => 'C$', 'format' => 'before'],
        'GBR' => ['symbol' => '£', 'format' => 'before'],
        'AUS' => ['symbol' => 'A$', 'format' => 'before']
    ];
    
    $currency = $currencies[$country] ?? $currencies['USA'];
    $formatted_amount = number_format($amount, 2);
    
    if ($currency['format'] === 'before') {
        return $currency['symbol'] . $formatted_amount;
    } else {
        return $formatted_amount . $currency['symbol'];
    }
}

// Helper function to format file size
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>
