<?php
/**
 * Get Company Settings API
 * Returns company information for frontend display
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Include required files
require_once '../includes/functions.php';

try {
    // Get company settings from database
    $company_settings = getCompanySettings();
    
    // Format the response for frontend use
    $response = [
        'success' => true,
        'data' => [
            'company_name' => $company_settings['name'] ?? 'LoanFlow',
            'company_address' => $company_settings['address'] ?? '700 Well St. #308,<br> NV 89002',
            'company_phone' => $company_settings['phone'] ?? '1 888 489 8189',
            'company_email' => $company_settings['email'] ?? 'cs@pulse.online',
            'company_website' => $company_settings['website'] ?? 'https://www.loanflow.com'
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Return error response
    $response = [
        'success' => false,
        'error' => 'Failed to retrieve company settings',
        'data' => [
            'company_name' => 'LoanFlow',
            'company_address' => '700 Well St. #308,<br> NV 89002',
            'company_phone' => '1 888 489 8189',
            'company_email' => 'cs@pulse.online',
            'company_website' => 'https://www.loanflow.com'
        ]
    ];
    
    error_log('Company settings API error: ' . $e->getMessage());
    echo json_encode($response);
}
?>