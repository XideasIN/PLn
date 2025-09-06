<?php
/**
 * Loan Application Submission API
 * Handles loan application form submissions with validation and database storage
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

require_once '../includes/functions.php';
require_once '../config/database.php';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    $required_fields = [
        'first_name', 'last_name', 'email', 'phone', 'date_of_birth',
        'sin_ssn', 'address', 'city', 'province_state', 'postal_zip',
        'loan_amount', 'loan_purpose', 'employment_status', 'monthly_income'
    ];
    
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }
    
    // Sanitize input data
    $data = [];
    foreach ($input as $key => $value) {
        $data[$key] = sanitizeInput($value);
    }
    
    // Validate email format
    if (!validateEmail($data['email'])) {
        throw new Exception('Invalid email format');
    }
    
    // Validate loan amount
    $loan_amount = floatval($data['loan_amount']);
    if ($loan_amount < 1000 || $loan_amount > 50000) {
        throw new Exception('Loan amount must be between $1,000 and $50,000');
    }
    
    // Validate monthly income
    $monthly_income = floatval($data['monthly_income']);
    if ($monthly_income < 1000) {
        throw new Exception('Monthly income must be at least $1,000');
    }
    
    // Calculate debt-to-income ratio
    $existing_debts = floatval($data['existing_debts'] ?? 0);
    $estimated_payment = calculateMonthlyPayment($loan_amount, 9.75, 24); // Default 24 months at 9.75%
    $total_debt = $existing_debts + $estimated_payment;
    $debt_to_income = ($total_debt / $monthly_income) * 100;
    
    // Generate application reference number
    $reference_number = generateReferenceNumber();
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // First, create or get user
    $user_sql = "INSERT INTO users (
        reference_number, email, first_name, last_name, phone, date_of_birth,
        sin_ssn, address, city, state_province, postal_zip, country,
        password_hash, role, status, created_at, updated_at
    ) VALUES (
        :reference_number, :email, :first_name, :last_name, :phone, :date_of_birth,
        :sin_ssn, :address, :city, :state_province, :postal_zip, :country,
        :password_hash, 'client', 'active', NOW(), NOW()
    ) ON DUPLICATE KEY UPDATE
        first_name = VALUES(first_name),
        last_name = VALUES(last_name),
        phone = VALUES(phone),
        updated_at = NOW()";
    
    $user_stmt = $db->prepare($user_sql);
    $user_stmt->execute([
        ':reference_number' => $reference_number,
        ':email' => $data['email'],
        ':first_name' => $data['first_name'],
        ':last_name' => $data['last_name'],
        ':phone' => $data['phone'],
        ':date_of_birth' => $data['date_of_birth'],
        ':sin_ssn' => password_hash($data['sin_ssn'], PASSWORD_DEFAULT), // Hash sensitive data
        ':address' => $data['address'],
        ':city' => $data['city'],
        ':state_province' => $data['province_state'],
        ':postal_zip' => $data['postal_zip'],
        ':country' => $data['country'] ?? 'Canada',
        ':password_hash' => password_hash($data['sin_ssn'], PASSWORD_DEFAULT) // Temporary password
    ]);
    
    // Get user ID
    $user_id = $db->lastInsertId();
    if (!$user_id) {
        // User already exists, get their ID
        $get_user_sql = "SELECT id FROM users WHERE email = ?";
        $get_user_stmt = $db->prepare($get_user_sql);
        $get_user_stmt->execute([$data['email']]);
        $user_id = $get_user_stmt->fetchColumn();
    }
    
    // Insert loan application
    $sql = "INSERT INTO loan_applications (
        user_id, reference_number, loan_amount, loan_purpose, loan_term_months,
        employment_status, employer_name, monthly_income, existing_debts,
        application_status, created_at, updated_at
    ) VALUES (
        :user_id, :reference_number, :loan_amount, :loan_purpose, :loan_term_months,
        :employment_status, :employer_name, :monthly_income, :existing_debts,
        'pending', NOW(), NOW()
    )";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':user_id' => $user_id,
        ':reference_number' => $reference_number,
        ':loan_amount' => $loan_amount,
        ':loan_purpose' => $data['loan_purpose'],
        ':loan_term_months' => intval($data['loan_term'] ?? 24),
        ':employment_status' => $data['employment_status'],
        ':employer_name' => $data['employer_name'] ?? '',
        ':monthly_income' => $monthly_income,
        ':existing_debts' => $existing_debts,
    ]);
    
    $application_id = $db->lastInsertId();
    
    // Send confirmation email (if email functions are available)
    if (function_exists('send_application_confirmation')) {
        send_application_confirmation($data['email'], $reference_number, $data['first_name']);
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Application submitted successfully',
        'data' => [
            'application_id' => $application_id,
            'reference_number' => $reference_number,
            'status' => 'pending',
            'debt_to_income_ratio' => round($debt_to_income, 2)
        ]
    ]);
    
} catch (PDOException $e) {
    error_log('Database error in submit-application.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Calculate monthly payment for a loan
 */
function calculateMonthlyPayment($principal, $annual_rate, $months) {
    $monthly_rate = ($annual_rate / 100) / 12;
    if ($monthly_rate == 0) {
        return $principal / $months;
    }
    return $principal * ($monthly_rate * pow(1 + $monthly_rate, $months)) / (pow(1 + $monthly_rate, $months) - 1);
}
?>