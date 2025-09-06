<?php
/**
 * Email Configuration
 * LoanFlow Personal Loan Management System
 */

// Email settings - Environment variables take precedence
define('MAIL_HOST', $_ENV['MAIL_HOST'] ?? getenv('MAIL_HOST') ?: 'smtp.gmail.com');
define('MAIL_PORT', $_ENV['MAIL_PORT'] ?? getenv('MAIL_PORT') ?: 587);
define('MAIL_USERNAME', $_ENV['MAIL_USERNAME'] ?? getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', $_ENV['MAIL_PASSWORD'] ?? getenv('MAIL_PASSWORD') ?: '');
define('MAIL_ENCRYPTION', $_ENV['MAIL_ENCRYPTION'] ?? getenv('MAIL_ENCRYPTION') ?: 'tls');
define('MAIL_FROM_EMAIL', $_ENV['MAIL_FROM_EMAIL'] ?? getenv('MAIL_FROM_EMAIL') ?: 'noreply@loanflow.com');
define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?: 'LoanFlow Support');

// Validate SMTP credentials
if (empty(MAIL_USERNAME) || empty(MAIL_PASSWORD)) {
    error_log("WARNING: SMTP credentials not configured. Set MAIL_USERNAME and MAIL_PASSWORD environment variables.");
}

// Email sending limits
define('MAIL_DAILY_LIMIT', 500);
define('MAIL_HOURLY_LIMIT', 50);

// Email queue settings
define('MAIL_QUEUE_BATCH_SIZE', 10);
define('MAIL_MAX_RETRIES', 3);

// Email template variables
$email_variables = [
    '{first_name}' => 'First Name',
    '{last_name}' => 'Last Name',
    '{full_name}' => 'Full Name',
    '{email}' => 'Email Address',
    '{ref#}' => 'Reference Number',
    '{loan_amount}' => 'Loan Amount',
    '{loan_type}' => 'Loan Type',
    '{monthly_payment}' => 'Monthly Payment',
    '{interest_rate}' => 'Interest Rate',
    '{loan_term}' => 'Loan Term',
    '{application_status}' => 'Application Status',
    '{login_url}' => 'Client Login URL',
    '{current_date}' => 'Current Date',
    '{current_time}' => 'Current Time',
    '{company_name}' => 'Company Name',
    '{company_email}' => 'Company Email',
    '{company_phone}' => 'Company Phone',
    '{support_url}' => 'Support URL'
];

// Email template helper functions
function getEmailVariables() {
    global $email_variables;
    return $email_variables;
}

function replaceEmailVariables($content, $user_data = [], $application_data = []) {
    $replacements = [
        '{first_name}' => $user_data['first_name'] ?? '',
        '{last_name}' => $user_data['last_name'] ?? '',
        '{full_name}' => ($user_data['first_name'] ?? '') . ' ' . ($user_data['last_name'] ?? ''),
        '{email}' => $user_data['email'] ?? '',
        '{ref#}' => $user_data['reference_number'] ?? $application_data['reference_number'] ?? '',
        '{loan_amount}' => isset($application_data['loan_amount']) ? '$' . number_format($application_data['loan_amount'], 2) : '',
        '{loan_type}' => $application_data['loan_type'] ?? '',
        '{monthly_payment}' => isset($application_data['monthly_payment']) ? '$' . number_format($application_data['monthly_payment'], 2) : '',
        '{interest_rate}' => isset($application_data['interest_rate']) ? $application_data['interest_rate'] . '%' : '',
        '{loan_term}' => isset($application_data['loan_term_months']) ? $application_data['loan_term_months'] . ' months' : '',
        '{application_status}' => $application_data['application_status'] ?? '',
        '{login_url}' => getBaseUrl() . '/client/',
        '{current_date}' => date('F j, Y'),
        '{current_time}' => date('g:i A'),
        '{company_name}' => 'LoanFlow',
        '{company_email}' => MAIL_FROM_EMAIL,
        '{company_phone}' => $_ENV['COMPANY_PHONE'] ?? getenv('COMPANY_PHONE') ?: '(555) 123-4567',
        '{support_url}' => getBaseUrl() . '/support/'
    ];
    
    return str_replace(array_keys($replacements), array_values($replacements), $content);
}

// Get base URL helper
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'];
    return $protocol . $domainName;
}

// Email validation
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Email sending hours check
function isEmailSendingHours() {
    $current_hour = (int)date('H');
    $start_hour = 9; // 9 AM
    $end_hour = 17;  // 5 PM
    
    return $current_hour >= $start_hour && $current_hour < $end_hour;
}

// Check if today is a holiday
function isHoliday($country_code = 'USA') {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM holidays 
            WHERE country_code = ? 
            AND holiday_date = CURDATE()
        ");
        $stmt->execute([$country_code]);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    } catch (Exception $e) {
        error_log("Holiday check failed: " . $e->getMessage());
        return false;
    }
}

// Check if today is Sunday
function isSunday() {
    return date('w') == 0; // 0 = Sunday
}

// Check if email can be sent (not Sunday, not holiday, within hours)
function canSendEmail($country_code = 'USA') {
    if (isSunday()) {
        return false;
    }
    
    if (isHoliday($country_code)) {
        return false;
    }
    
    if (!isEmailSendingHours()) {
        return false;
    }
    
    return true;
}
?>
