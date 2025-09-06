<?php
/**
 * Document Review Email Templates
 * LoanFlow Personal Loan Management System
 */

/**
 * Get pre-formatted email templates for document rejection
 */
function getDocumentRejectionTemplates() {
    return [
        'photo_id_missing' => [
            'subject' => 'Document Required: Photo ID',
            'template' => '
Dear {client_name},

Thank you for submitting your loan application. We have reviewed your submitted documents and need additional information to proceed.

**Required Document Missing: Photo ID**

Please submit a clear, government-issued photo identification such as:
• Driver\'s License
• Passport
• State ID Card
• Military ID

The document must:
• Be current and not expired
• Show your full name matching your application
• Have a clear, readable photo
• Include all four corners of the document

You can upload your document by logging into your client area at: {login_url}

If you have any questions, please don\'t hesitate to contact our support team.

Best regards,
{company_name} Team
Reference: {reference_number}'
        ],
        
        'proof_income_missing' => [
            'subject' => 'Document Required: Proof of Income',
            'template' => '
Dear {client_name},

Thank you for submitting your loan application. We have reviewed your submitted documents and need additional information to proceed.

**Required Document Missing: Proof of Income**

Please submit one of the following documents:
• Recent pay stubs (last 2-3 pay periods)
• Employment letter on company letterhead
• Tax return (T4 or T1 for Canada, W-2 or 1040 for USA)
• Bank statements showing regular income deposits
• Self-employment income documentation

The document must:
• Be recent (within the last 3 months for pay stubs)
• Show your full name
• Display your income amount clearly
• Be from an official source

You can upload your document by logging into your client area at: {login_url}

If you have any questions, please don\'t hesitate to contact our support team.

Best regards,
{company_name} Team
Reference: {reference_number}'
        ],
        
        'proof_address_missing' => [
            'subject' => 'Document Required: Proof of Address',
            'template' => '
Dear {client_name},

Thank you for submitting your loan application. We have reviewed your submitted documents and need additional information to proceed.

**Required Document Missing: Proof of Address**

Please submit one of the following documents:
• Utility bill (electricity, gas, water, internet)
• Bank statement
• Government correspondence
• Lease agreement or mortgage statement
• Insurance statement

The document must:
• Be recent (within the last 3 months)
• Show your full name matching your application
• Display your current address clearly
• Be from an official source

Note: Your driver\'s license can serve as both Photo ID and Proof of Address if it shows your current address.

You can upload your document by logging into your client area at: {login_url}

If you have any questions, please don\'t hesitate to contact our support team.

Best regards,
{company_name} Team
Reference: {reference_number}'
        ],
        
        'multiple_documents_missing' => [
            'subject' => 'Documents Required: Multiple Items Missing',
            'template' => '
Dear {client_name},

Thank you for submitting your loan application. We have reviewed your submitted documents and need additional information to proceed.

**Required Documents Missing:**
{missing_documents_list}

**Document Requirements:**

**Photo ID:** Government-issued photo identification (Driver\'s License, Passport, State ID)
• Must be current and not expired
• Show your full name matching your application
• Have a clear, readable photo

**Proof of Income:** Recent pay stubs, employment letter, or tax documents
• Must be recent (within last 3 months for pay stubs)
• Show your full name and income amount clearly
• From an official source

**Proof of Address:** Utility bill, bank statement, or government correspondence
• Must be recent (within last 3 months)
• Show your full name and current address
• From an official source

You can upload your documents by logging into your client area at: {login_url}

If you have any questions, please don\'t hesitate to contact our support team.

Best regards,
{company_name} Team
Reference: {reference_number}'
        ],
        
        'document_quality_poor' => [
            'subject' => 'Document Resubmission Required: Quality Issues',
            'template' => '
Dear {client_name},

Thank you for submitting your documents. We have reviewed your submission and need you to resubmit due to quality issues.

**Document Quality Issues Identified:**
• Image is blurry or unclear
• Document is partially cut off
• Text is not readable
• File quality is too low

**Please resubmit with:**
• Clear, high-resolution images
• All four corners of the document visible
• Good lighting with no shadows
• Text that is clearly readable
• File size under 10MB

**Accepted formats:** PDF, JPG, PNG

You can upload your corrected documents by logging into your client area at: {login_url}

If you need assistance with document scanning or have technical questions, please contact our support team.

Best regards,
{company_name} Team
Reference: {reference_number}'
        ],
        
        'document_expired' => [
            'subject' => 'Document Resubmission Required: Expired Document',
            'template' => '
Dear {client_name},

Thank you for submitting your documents. We have reviewed your submission and found that one or more documents have expired.

**Issue:** The submitted document is expired and no longer valid.

**Required Action:** Please submit a current, non-expired version of the document.

**Document Requirements:**
• Must be current and not expired
• Government-issued documents must be valid
• Utility bills and statements must be within the last 3 months
• All information must be clearly visible

You can upload your updated documents by logging into your client area at: {login_url}

If you have any questions about acceptable documents, please contact our support team.

Best regards,
{company_name} Team
Reference: {reference_number}'
        ],
        
        'document_mismatch' => [
            'subject' => 'Document Resubmission Required: Information Mismatch',
            'template' => '
Dear {client_name},

Thank you for submitting your documents. We have reviewed your submission and found discrepancies that need to be resolved.

**Issue:** The information on your submitted document does not match your application details.

**Common mismatches:**
• Name spelling differences
• Address variations
• Date of birth discrepancies

**Required Action:** Please either:
1. Submit a document with information that exactly matches your application, OR
2. Contact us to update your application information

**Important:** All documents must show the exact same name and information as provided in your loan application.

You can upload corrected documents by logging into your client area at: {login_url}

For assistance with updating your application information, please contact our support team.

Best regards,
{company_name} Team
Reference: {reference_number}'
        ],
        
        'all_documents_approved' => [
            'subject' => 'Documents Approved - Application Moving Forward',
            'template' => '
Dear {client_name},

Great news! We have successfully reviewed and approved all your submitted documents.

**Documents Approved:**
• Photo ID ✓
• Proof of Income ✓
• Proof of Address ✓

**Next Steps:**
Your application is now moving to the next stage of our approval process. You will receive an update within 24-48 hours regarding your loan approval status.

**What happens next:**
1. Final underwriting review
2. Loan approval decision
3. Funding arrangements (if approved)

You can track your application progress by logging into your client area at: {login_url}

Thank you for choosing {company_name} for your financial needs.

Best regards,
{company_name} Team
Reference: {reference_number}'
        ]
    ];
}

/**
 * Send document rejection email
 */
function sendDocumentRejectionEmail($user, $application, $missing_documents = [], $rejection_reason = 'multiple_documents_missing', $custom_notes = '') {
    $templates = getDocumentRejectionTemplates();
    
    if (!isset($templates[$rejection_reason])) {
        $rejection_reason = 'multiple_documents_missing';
    }
    
    $template = $templates[$rejection_reason];
    
    // Prepare missing documents list
    $missing_docs_text = '';
    if (!empty($missing_documents)) {
        $doc_names = [
            'photo_id' => '• Photo ID (Driver\'s License, Passport, etc.)',
            'proof_income' => '• Proof of Income (Pay stubs, Employment letter, etc.)',
            'proof_address' => '• Proof of Address (Utility bill, Bank statement, etc.)',
            'bank_statement' => '• Bank Statement'
        ];
        
        foreach ($missing_documents as $doc_type) {
            if (isset($doc_names[$doc_type])) {
                $missing_docs_text .= $doc_names[$doc_type] . "\n";
            }
        }
    }
    
    // Get system settings
    $settings = getSystemSettings();
    $company_name = $settings['company_name'] ?? 'LoanFlow';
    $base_url = $settings['base_url'] ?? 'https://yourdomain.com';
    
    // Replace template variables
    $replacements = [
        '{client_name}' => $user['first_name'] . ' ' . $user['last_name'],
        '{company_name}' => $company_name,
        '{reference_number}' => $user['reference_number'],
        '{login_url}' => $base_url . '/client/',
        '{missing_documents_list}' => $missing_docs_text,
        '{custom_notes}' => $custom_notes
    ];
    
    $subject = str_replace(array_keys($replacements), array_values($replacements), $template['subject']);
    $body = str_replace(array_keys($replacements), array_values($replacements), $template['template']);
    
    // Add custom notes if provided
    if ($custom_notes) {
        $body .= "\n\n**Additional Notes:**\n" . $custom_notes;
    }
    
    // Send email
    return sendEmail($user['email'], $subject, $body, 'document_rejection');
}

/**
 * Send document approval email
 */
function sendDocumentApprovalEmail($user, $application) {
    $templates = getDocumentRejectionTemplates();
    $template = $templates['all_documents_approved'];
    
    // Get system settings
    $settings = getSystemSettings();
    $company_name = $settings['company_name'] ?? 'LoanFlow';
    $base_url = $settings['base_url'] ?? 'https://yourdomain.com';
    
    // Replace template variables
    $replacements = [
        '{client_name}' => $user['first_name'] . ' ' . $user['last_name'],
        '{company_name}' => $company_name,
        '{reference_number}' => $user['reference_number'],
        '{login_url}' => $base_url . '/client/'
    ];
    
    $subject = str_replace(array_keys($replacements), array_values($replacements), $template['subject']);
    $body = str_replace(array_keys($replacements), array_values($replacements), $template['template']);
    
    // Send email
    return sendEmail($user['email'], $subject, $body, 'document_approval');
}

/**
 * Get missing document types for a user
 */
function getMissingDocuments($user_id) {
    try {
        $db = getDB();
        
        // Get uploaded document types
        $stmt = $db->prepare("SELECT DISTINCT document_type FROM documents WHERE user_id = ? AND upload_status != 'rejected'");
        $stmt->execute([$user_id]);
        $uploaded_types = array_column($stmt->fetchAll(), 'document_type');
        
        // Required document types
        $required_types = ['photo_id', 'proof_income', 'proof_address'];
        
        // Find missing types
        $missing_types = array_diff($required_types, $uploaded_types);
        
        return array_values($missing_types);
        
    } catch (Exception $e) {
        error_log("Get missing documents failed: " . $e->getMessage());
        return ['photo_id', 'proof_income', 'proof_address']; // Return all as missing if error
    }
}

/**
 * Auto-send document approval email
 */
function autoSendDocumentApprovalEmail($user_id) {
    try {
        $db = getDB();
        
        // Get user and application details
        $stmt = $db->prepare("
            SELECT u.*, la.id as application_id
            FROM users u
            LEFT JOIN loan_applications la ON u.id = la.user_id
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        return sendDocumentApprovalEmail($user, ['id' => $user['application_id']]);
        
    } catch (Exception $e) {
        error_log("Auto send document approval email failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Auto-send rejection emails based on missing documents
 */
function autoSendDocumentRejectionEmail($user_id, $custom_notes = '') {
    try {
        $db = getDB();
        
        // Get user and application details
        $stmt = $db->prepare("
            SELECT u.*, la.id as application_id
            FROM users u
            LEFT JOIN loan_applications la ON u.id = la.user_id
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        // Get missing documents
        $missing_docs = getMissingDocuments($user_id);
        
        if (empty($missing_docs)) {
            // All documents present, send approval email
            return sendDocumentApprovalEmail($user, ['id' => $user['application_id']]);
        }
        
        // Determine email template based on missing documents
        $template_key = 'multiple_documents_missing';
        if (count($missing_docs) === 1) {
            $template_key = $missing_docs[0] . '_missing';
        }
        
        return sendDocumentRejectionEmail($user, ['id' => $user['application_id']], $missing_docs, $template_key, $custom_notes);
        
    } catch (Exception $e) {
        error_log("Auto send document rejection email failed: " . $e->getMessage());
        return false;
    }
}
?>