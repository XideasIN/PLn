<?php
/**
 * Email System
 * LoanFlow Personal Loan Management System
 */

require_once __DIR__ . '/../config/email.php';

// Send payment instruction email
function sendPaymentInstructionEmail($user_id, $application_data = []) {
    try {
        $user = getUserById($user_id);
        if (!$user) {
            throw new Exception("User not found");
        }
        
        $db = getDB();
        
        // Get enabled payment methods with their admin-configured instructions
        $stmt = $db->prepare("
            SELECT method_name, config_data, instructions, email_template 
            FROM payment_method_config 
            WHERE is_enabled = 1 
            AND (allowed_countries IS NULL OR JSON_CONTAINS(allowed_countries, ?))
            ORDER BY method_name
        ");
        $stmt->execute([json_encode($user['country'])]);
        $payment_methods = $stmt->fetchAll();
        
        if (empty($payment_methods)) {
            throw new Exception("No payment methods available for user's country");
        }
        
        // Calculate payment amount (2% of loan amount)
        $payment_amount = isset($application_data['loan_amount']) ? $application_data['loan_amount'] * 0.02 : 0;
        
        // Build payment instructions content from admin configurations
        $payment_instructions_html = '';
        
        foreach ($payment_methods as $method) {
            $config = json_decode($method['config_data'], true);
            $method_name = ucwords(str_replace('_', ' ', $method['method_name']));
            
            $payment_instructions_html .= "<div style='margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>";
            $payment_instructions_html .= "<h3 style='color: #333; margin-top: 0;'>" . $method_name . "</h3>";
            
            // Use admin-configured instructions or email template
            if (!empty($method['email_template'])) {
                $payment_instructions_html .= $method['email_template'];
            } elseif (!empty($method['instructions'])) {
                $payment_instructions_html .= "<p>" . nl2br(htmlspecialchars($method['instructions'])) . "</p>";
            } else {
                // Generate instructions from config data
                switch ($method['method_name']) {
                    case 'wire_transfer':
                        if (!empty($config['bank_name'])) {
                            $payment_instructions_html .= "<p><strong>Bank Name:</strong> " . htmlspecialchars($config['bank_name']) . "</p>";
                        }
                        if (!empty($config['account_name'])) {
                            $payment_instructions_html .= "<p><strong>Account Name:</strong> " . htmlspecialchars($config['account_name']) . "</p>";
                        }
                        if (!empty($config['account_number'])) {
                            $payment_instructions_html .= "<p><strong>Account Number:</strong> " . htmlspecialchars($config['account_number']) . "</p>";
                        }
                        if (!empty($config['routing_number'])) {
                            $payment_instructions_html .= "<p><strong>Routing Number:</strong> " . htmlspecialchars($config['routing_number']) . "</p>";
                        }
                        if (!empty($config['swift_code'])) {
                            $payment_instructions_html .= "<p><strong>SWIFT Code:</strong> " . htmlspecialchars($config['swift_code']) . "</p>";
                        }
                        break;
                    case 'crypto':
                        if (!empty($config['wallet_address'])) {
                            $payment_instructions_html .= "<p><strong>Wallet Address:</strong> " . htmlspecialchars($config['wallet_address']) . "</p>";
                        }
                        if (!empty($config['currency_type'])) {
                            $payment_instructions_html .= "<p><strong>Currency:</strong> " . htmlspecialchars($config['currency_type']) . "</p>";
                        }
                        break;
                    case 'e_transfer':
                        if (!empty($config['email_address'])) {
                            $payment_instructions_html .= "<p><strong>Send e-Transfer to:</strong> " . htmlspecialchars($config['email_address']) . "</p>";
                        }
                        if (!empty($config['recipient_name'])) {
                            $payment_instructions_html .= "<p><strong>Recipient Name:</strong> " . htmlspecialchars($config['recipient_name']) . "</p>";
                        }
                        if (!empty($config['security_question'])) {
                            $payment_instructions_html .= "<p><strong>Security Question:</strong> " . htmlspecialchars($config['security_question']) . "</p>";
                        }
                        if (!empty($config['security_answer'])) {
                            $payment_instructions_html .= "<p><strong>Security Answer:</strong> " . htmlspecialchars($config['security_answer']) . "</p>";
                        }
                        break;
                }
            }
            
            $payment_instructions_html .= "</div>";
        }
        
        // Build complete email template
        $template_content = getPaymentInstructionEmailTemplate($payment_instructions_html);
        
        // Replace variables in template
        $variables = [
            '{{first_name}}' => $user['first_name'],
            '{{last_name}}' => $user['last_name'],
            '{{reference_number}}' => $user['reference_number'],
            '{{loan_amount}}' => number_format($application_data['loan_amount'] ?? 0, 2),
            '{{payment_amount}}' => number_format($payment_amount, 2),
            '{{company_name}}' => getSystemSetting('company_name', 'QuickFunds'),
            '{{support_email}}' => getSystemSetting('support_email', 'support@quickfunds.com'),
            '{{current_date}}' => date('F j, Y'),
            '{{payment_instructions}}' => $payment_instructions_html
        ];
        
        $email_body = str_replace(array_keys($variables), array_values($variables), $template_content);
        
        $subject = "Payment Instructions - Application #{$user['reference_number']}";
        
        // Send email
        $result = sendEmail($user['email'], $subject, $email_body);
        
        if ($result) {
            // Log the email sending
            logAudit('payment_instruction_email_sent', 'users', $user_id, null, [
                'email' => $user['email'],
                'reference_number' => $user['reference_number']
            ]);
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Payment instruction email failed: " . $e->getMessage());
        return false;
    }
}

// Get payment instruction email template with admin-configured payment methods
function getPaymentInstructionEmailTemplate($payment_instructions_html) {
    return '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;">
        <div style="background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h2 style="color: #333; text-align: center; margin-bottom: 30px;">Payment Instructions</h2>
            
            <p>Dear {{first_name}} {{last_name}},</p>
            
            <p>Congratulations! Your loan application has been approved and we are ready to proceed to the next step.</p>
            
            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3 style="color: #333; margin-top: 0;">Application Details:</h3>
                <p><strong>Reference Number:</strong> {{reference_number}}</p>
                <p><strong>Loan Amount:</strong> ${{loan_amount}}</p>
                <p><strong>Processing Fee (2%):</strong> ${{payment_amount}}</p>
            </div>
            
            <h3 style="color: #333;">Available Payment Methods:</h3>
            {{payment_instructions}}
            
            <div style="background-color: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <p style="margin: 0; font-weight: bold; color: #1976d2;">Next Steps:</p>
                <p style="margin: 5px 0 0 0;">Please log into your Client Area and click the "Make Payment" button to proceed with your fee submission.</p>
            </div>
            
            <p>If you have any questions, please contact our support team at {{support_email}}.</p>
            
            <p>Best regards,<br>{{company_name}} Team</p>
            
            <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
            <p style="font-size: 12px; color: #666; text-align: center;">This email was sent on {{current_date}}</p>
        </div>
    </div>
    ';
}

// Get default payment instruction template (fallback)
function getDefaultPaymentInstructionTemplate() {
    return '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;">
        <div style="background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h2 style="color: #333; text-align: center; margin-bottom: 30px;">Payment Instructions</h2>
            
            <p>Dear {{first_name}} {{last_name}},</p>
            
            <p>Congratulations! Your loan application has been approved and we are ready to proceed to the next step.</p>
            
            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3 style="color: #333; margin-top: 0;">Application Details:</h3>
                <p><strong>Reference Number:</strong> {{reference_number}}</p>
                <p><strong>Loan Amount:</strong> ${{loan_amount}}</p>
                <p><strong>Processing Fee (2%):</strong> ${{payment_amount}}</p>
            </div>
            
            <h3 style="color: #333;">Payment Methods Available:</h3>
            <ul style="line-height: 1.6;">
                <li><strong>Wire Transfer:</strong> Available for all countries</li>
                <li><strong>Cryptocurrency:</strong> Bitcoin, Ethereum accepted</li>
                <li><strong>e-Transfer:</strong> Canada only</li>
            </ul>
            
            <div style="background-color: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <p style="margin: 0; font-weight: bold; color: #1976d2;">Next Steps:</p>
                <p style="margin: 5px 0 0 0;">Please log into your Client Area and click the "Make Payment" button to proceed with your fee submission.</p>
            </div>
            
            <p>If you have any questions, please contact our support team at {{support_email}}.</p>
            
            <p>Best regards,<br>{{company_name}} Team</p>
            
            <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
            <p style="font-size: 12px; color: #666; text-align: center;">This email was sent on {{current_date}}</p>
        </div>
    </div>
    ';
}

// Send email function
function sendEmail($to, $subject, $body, $from_name = null, $from_email = null) {
    try {
        // Get email configuration
        $config = getEmailConfig();
        
        if (!$from_name) $from_name = $config['from_name'];
        if (!$from_email) $from_email = $config['from_email'];
        
        // Create headers
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $from_email,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        // Send email using PHP mail() function
        // In production, replace with PHPMailer or similar for better delivery
        $result = mail($to, $subject, $body, implode("\r\n", $headers));
        
        if ($result) {
            error_log("Email sent successfully to: $to");
            return true;
        } else {
            error_log("Email sending failed to: $to");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
        return false;
    }
}

// Process email queue
function processEmailQueue($limit = 50) {
    try {
        $db = getDB();
        
        // Get pending emails
        $stmt = $db->prepare("
            SELECT * FROM email_queue 
            WHERE status = 'pending' 
            AND send_at <= NOW() 
            AND attempts < max_attempts
            ORDER BY send_at ASC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $emails = $stmt->fetchAll();
        
        $sent_count = 0;
        $failed_count = 0;
        
        foreach ($emails as $email) {
            // Update attempts
            $update_stmt = $db->prepare("
                UPDATE email_queue 
                SET attempts = attempts + 1 
                WHERE id = ?
            ");
            $update_stmt->execute([$email['id']]);
            
            // Try to send email
            if (sendEmail($email['recipient_email'], $email['subject'], $email['body'])) {
                // Mark as sent
                $update_stmt = $db->prepare("
                    UPDATE email_queue 
                    SET status = 'sent', sent_at = NOW() 
                    WHERE id = ?
                ");
                $update_stmt->execute([$email['id']]);
                $sent_count++;
            } else {
                // Check if max attempts reached
                if ($email['attempts'] + 1 >= $email['max_attempts']) {
                    // Mark as failed
                    $update_stmt = $db->prepare("
                        UPDATE email_queue 
                        SET status = 'failed', error_message = 'Max attempts reached' 
                        WHERE id = ?
                    ");
                    $update_stmt->execute([$email['id']]);
                    $failed_count++;
                }
            }
        }
        
        return [
            'processed' => count($emails),
            'sent' => $sent_count,
            'failed' => $failed_count
        ];
        
    } catch (Exception $e) {
        error_log("Email queue processing failed: " . $e->getMessage());
        return false;
    }
}

// Queue email for sending
function queueEmail($user_id, $recipient_email, $subject, $body, $template_id = null, $send_at = null) {
    try {
        $db = getDB();
        
        if (!$send_at) {
            $send_at = date('Y-m-d H:i:s');
        }
        
        $stmt = $db->prepare("
            INSERT INTO email_queue (
                user_id, template_id, recipient_email, subject, body, send_at
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $user_id,
            $template_id,
            $recipient_email,
            $subject,
            $body,
            $send_at
        ]);
        
        if ($result) {
            return $db->lastInsertId();
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Queue email failed: " . $e->getMessage());
        return false;
    }
}

// Get email template
function getEmailTemplate($template_type, $country = null) {
    try {
        $db = getDB();
        
        $query = "
            SELECT * FROM email_templates 
            WHERE template_type = ? 
            AND is_active = 1
        ";
        $params = [$template_type];
        
        if ($country) {
            $query .= " AND (country_specific = ? OR country_specific IS NULL)";
            $params[] = $country;
            $query .= " ORDER BY country_specific DESC";
        }
        
        $query .= " LIMIT 1";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetch();
        
    } catch (Exception $e) {
        error_log("Get email template failed: " . $e->getMessage());
        return false;
    }
}

// Send templated email
function sendTemplatedEmail($user_id, $template_type, $variables = [], $send_at = null) {
    try {
        // Get user data
        $user = getUserById($user_id);
        if (!$user) {
            throw new Exception("User not found: $user_id");
        }
        
        // Get email template
        $template = getEmailTemplate($template_type, $user['country']);
        if (!$template) {
            throw new Exception("Email template not found: $template_type");
        }
        
        // Prepare variables for replacement
        $email_variables = array_merge([
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'reference_number' => $user['reference_number']
        ], $variables);
        
        // Replace variables in subject and body
        $subject = replaceEmailVariables($template['subject'], $email_variables);
        $body = replaceEmailVariables($template['body'], $email_variables);
        
        // Convert plain text to HTML if needed
        if (strpos($body, '<') === false) {
            $body = nl2br(htmlspecialchars($body));
        }
        
        // Add email wrapper
        $body = getEmailWrapper($body, $subject);
        
        // Calculate send time
        if (!$send_at && $template['send_delay_hours'] > 0) {
            $send_at = date('Y-m-d H:i:s', strtotime("+{$template['send_delay_hours']} hours"));
        }
        
        // Queue the email
        $queue_id = queueEmail($user_id, $user['email'], $subject, $body, $template['id'], $send_at);
        
        if ($queue_id) {
            // Add memo
            addMemo($user_id, "Email queued: $subject", 'email_sent', false);
            
            // Log audit
            logAudit('email_queued', 'email_queue', $queue_id, null, [
                'template_type' => $template_type,
                'recipient' => $user['email']
            ]);
            
            return $queue_id;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Send templated email failed: " . $e->getMessage());
        return false;
    }
}

// Get email wrapper/template
function getEmailWrapper($content, $subject = '') {
    $site_name = getSystemSetting('site_name', 'LoanFlow');
    $site_url = getSystemSetting('site_url', 'https://loanflow.com');
    $support_email = getSystemSetting('site_email', 'support@loanflow.com');
    
    return "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>$subject - $site_name</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0d6efd; color: white; padding: 20px; text-align: center; }
            .content { padding: 30px 20px; background: #f8f9fa; }
            .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            .btn { display: inline-block; background: #0d6efd; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>$site_name</h1>
            </div>
            <div class='content'>
                $content
            </div>
            <div class='footer'>
                <p>This email was sent by $site_name</p>
                <p>If you have questions, contact us at <a href='mailto:$support_email'>$support_email</a></p>
                <p><a href='$site_url'>Visit our website</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
}

// Send application confirmation email
function sendConfirmationEmail($user_id, $application_data = []) {
    return sendTemplatedEmail($user_id, 'confirmation', $application_data);
}

// Send pre-approval email
function sendPreApprovalEmail($user_id, $application_data = []) {
    return sendTemplatedEmail($user_id, 'pre_approval', $application_data);
}

// Send document request email
function sendDocumentRequestEmail($user_id, $missing_documents = []) {
    $variables = [
        'missing_documents' => implode(', ', $missing_documents)
    ];
    return sendTemplatedEmail($user_id, 'document_request', $variables);
}

// Send payment request email
function sendPaymentRequestEmail($user_id, $payment_data = []) {
    return sendTemplatedEmail($user_id, 'payment_request', $payment_data);
}

// Send approval email
function sendApprovalEmail($user_id, $application_data = []) {
    return sendTemplatedEmail($user_id, 'approval', $application_data);
}

// Send step completion email
function sendStepCompletionEmail($user_id, $step_name, $next_steps = []) {
    $variables = [
        'step_name' => $step_name,
        'next_steps' => implode("\n", $next_steps)
    ];
    return sendTemplatedEmail($user_id, 'step_completion', $variables);
}

// Check if it's appropriate time to send emails
function canSendEmails() {
    $sending_hours = getSystemSetting('email_sending_hours', ['start' => '09:00', 'end' => '17:00']);
    
    if (is_string($sending_hours)) {
        $sending_hours = json_decode($sending_hours, true);
    }
    
    $current_hour = date('H:i');
    return $current_hour >= $sending_hours['start'] && $current_hour <= $sending_hours['end'];
}

// Get email statistics
function getEmailStats() {
    try {
        $db = getDB();
        
        $stats = [];
        
        // Total emails in queue
        $stmt = $db->query("SELECT COUNT(*) as count FROM email_queue");
        $stats['total_queued'] = $stmt->fetch()['count'];
        
        // Pending emails
        $stmt = $db->query("SELECT COUNT(*) as count FROM email_queue WHERE status = 'pending'");
        $stats['pending'] = $stmt->fetch()['count'];
        
        // Sent emails (last 24 hours)
        $stmt = $db->query("
            SELECT COUNT(*) as count FROM email_queue 
            WHERE status = 'sent' AND sent_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stats['sent_24h'] = $stmt->fetch()['count'];
        
        // Failed emails
        $stmt = $db->query("SELECT COUNT(*) as count FROM email_queue WHERE status = 'failed'");
        $stats['failed'] = $stmt->fetch()['count'];
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Get email stats failed: " . $e->getMessage());
        return [
            'total_queued' => 0,
            'pending' => 0,
            'sent_24h' => 0,
            'failed' => 0
        ];
    }
}

// Clean old emails from queue
function cleanEmailQueue($days = 30) {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            DELETE FROM email_queue 
            WHERE (status = 'sent' OR status = 'failed') 
            AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        
        $result = $stmt->execute([$days]);
        
        if ($result) {
            $deleted_count = $stmt->rowCount();
            error_log("Cleaned $deleted_count old emails from queue");
            return $deleted_count;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Clean email queue failed: " . $e->getMessage());
        return false;
    }
}
?>