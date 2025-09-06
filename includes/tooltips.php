<?php
/**
 * Admin Tooltips and Help System
 * LoanFlow Personal Loan Management System
 */

class TooltipManager {
    
    private static $tooltips = [];
    private static $help_sections = [];
    
    /**
     * Initialize tooltip system
     */
    public static function init() {
        self::loadTooltips();
        self::loadHelpSections();
    }
    
    /**
     * Load all tooltip definitions
     */
    private static function loadTooltips() {
        self::$tooltips = [
            // General Settings
            'help_site_name' => 'The name of your website that appears in the browser title and throughout the system.',
            'help_site_email' => 'Default email address used for system notifications and automated emails.',
            'help_admin_email' => 'Email address where important admin notifications will be sent.',
            'help_timezone' => 'Default timezone for displaying dates and times throughout the system.',
            'help_date_format' => 'Format used for displaying dates. This affects how dates appear to users.',
            'help_currency' => 'Default currency for displaying monetary amounts.',
            'help_maintenance_mode' => 'When enabled, only administrators can access the site. Regular users will see a maintenance message.',
            
            // Email/SMTP Settings
            'help_smtp_host' => 'SMTP server hostname (e.g., smtp.gmail.com, mail.yourdomain.com).',
            'help_smtp_port' => 'SMTP server port. Common ports: 587 (TLS), 465 (SSL), 25 (unsecured).',
            'help_smtp_encryption' => 'Encryption method for secure email transmission. TLS is recommended.',
            'help_smtp_username' => 'Username for authenticating with the SMTP server (usually your email address).',
            'help_smtp_password' => 'Password or app-specific password for SMTP authentication.',
            'help_mail_from_name' => 'Display name that appears as the sender in outgoing emails.',
            'help_mail_from_address' => 'Email address that appears as the sender in outgoing emails.',
            
            // Security Settings
            'help_max_login_attempts' => 'Maximum number of failed login attempts before an account is temporarily locked.',
            'help_lockout_duration' => 'How long (in minutes) an account remains locked after exceeding login attempts.',
            'help_session_timeout' => 'How long (in minutes) a user session remains active without activity.',
            'help_password_min_length' => 'Minimum number of characters required for user passwords.',
            'help_require_2fa' => 'When enabled, all users must set up two-factor authentication.',
            'help_ip_whitelist' => 'List of IP addresses allowed to access admin areas. Leave empty to allow all IPs.',
            'help_audit_logging' => 'When enabled, all user actions are logged for security and compliance.',
            
            // Payment Settings
            'help_paypal_client_id' => 'Client ID from your PayPal developer dashboard.',
            'help_paypal_client_secret' => 'Client secret from your PayPal developer dashboard.',
            'help_paypal_sandbox' => 'Enable for testing with PayPal sandbox environment.',
            'help_stripe_publishable_key' => 'Publishable key from your Stripe dashboard (starts with pk_).',
            'help_stripe_secret_key' => 'Secret key from your Stripe dashboard (starts with sk_).',
            'help_stripe_webhook_secret' => 'Webhook endpoint secret for verifying Stripe webhook events.',
            
            // CAPTCHA Settings
            'help_captcha_enabled' => 'Enable CAPTCHA protection to prevent automated form submissions.',
            'help_captcha_provider' => 'Choose between custom math problems, Google reCAPTCHA, or hCaptcha.',
            'help_recaptcha_site_key' => 'Site key from Google reCAPTCHA console.',
            'help_recaptcha_secret_key' => 'Secret key from Google reCAPTCHA console.',
            'help_hcaptcha_site_key' => 'Site key from hCaptcha dashboard.',
            'help_hcaptcha_secret_key' => 'Secret key from hCaptcha dashboard.',
            'help_protected_forms' => 'Comma-separated list of forms to protect with CAPTCHA.',
            
            // Company Settings
            'help_company_name' => 'Your company name as it should appear on documents and emails.',
            'help_company_address' => 'Full business address including street, city, state, and postal code.',
            'help_company_phone' => 'Main business phone number for customer contact.',
            'help_company_email' => 'Main business email address for customer inquiries.',
            'help_company_website' => 'Your company website URL (include http:// or https://).',
            'help_company_registration' => 'Business registration or incorporation number.',
            'help_company_tax_id' => 'Tax identification number (EIN, VAT number, etc.).',
            'help_logo_upload' => 'Upload your company logo in PNG, JPG, or SVG format. Maximum size: 2MB.',
            'help_brand_colors' => 'Choose colors that match your brand identity. These will be used throughout the system.',
            
            // Fee Structure Settings
            'help_fee_structure_type' => 'Choose between transparent subscription pricing or hidden percentage-based fees.',
            'help_subscription_fee' => 'Monthly fee charged to users in the subscription model.',
            'help_max_months' => 'Maximum number of months a user can subscribe (typically 6 months).',
            'help_full_payment' => 'Allow users to pay the full subscription amount upfront instead of monthly.',
            'help_percentage_fee' => 'Percentage of the loan amount charged as a service fee.',
            'help_percentage_min_fee' => 'Minimum fee amount regardless of loan size.',
            'help_percentage_max_fee' => 'Maximum fee amount regardless of loan size.',
            'help_refund_policy' => 'Enable guaranteed refunds if funding is not secured for the client.',
            'help_refund_percentage' => 'Percentage of the fee to refund (typically 80%).',
            'help_payment_due_days' => 'Number of days clients have to pay fees after disclosure.',
            'help_pricing_page' => 'Show a dedicated pricing page for the subscription model.',
            'help_hide_fees_step' => 'For percentage model, choose at which step to disclose fees.',
            'help_fee_disclosure' => 'Legal text explaining fee structure and terms.',
            
            // User Management
            'help_user_status' => 'Active: Normal access, Inactive: Cannot login, Locked: Temporarily blocked, Cancelled: Permanently disabled.',
            'help_user_role' => 'Client: Regular user, Agent: Staff member, Admin: Full access, Super Admin: System administrator.',
            'help_email_verified' => 'Whether the user has verified their email address.',
            'help_2fa_status' => 'Shows if the user has enabled two-factor authentication.',
            'help_last_login' => 'Date and time of the user\'s last successful login.',
            'help_failed_attempts' => 'Number of recent failed login attempts.',
            
            // Loan Application Management
            'help_application_status' => 'Current status of the loan application in the processing workflow.',
            'help_loan_amount' => 'Amount requested by the applicant.',
            'help_loan_term' => 'Repayment period in months.',
            'help_interest_rate' => 'Annual percentage rate for the loan.',
            'help_monthly_payment' => 'Calculated monthly payment amount.',
            'help_application_priority' => 'Processing priority: High, Normal, or Low.',
            'help_assigned_agent' => 'Staff member responsible for processing this application.',
            'help_documents_required' => 'List of documents needed to complete the application.',
            'help_credit_score' => 'Applicant\'s credit score if available.',
            'help_debt_to_income' => 'Applicant\'s debt-to-income ratio.',
            
            // Document Management
            'help_document_type' => 'Category of document (ID, Income Proof, Address Proof, etc.).',
            'help_document_status' => 'Pending: Not reviewed, Approved: Accepted, Rejected: Needs replacement.',
            'help_document_notes' => 'Internal notes about the document review.',
            'help_upload_requirements' => 'Documents must be clear, readable, and in PDF, JPG, or PNG format.',
            
            // Payment Management
            'help_payment_status' => 'Current status of the payment transaction.',
            'help_payment_method' => 'How the payment was processed (PayPal, Stripe, etc.).',
            'help_payment_amount' => 'Total amount charged to the client.',
            'help_refund_amount' => 'Amount refunded if applicable.',
            'help_transaction_id' => 'Unique identifier from the payment processor.',
            
            // System Monitoring
            'help_system_health' => 'Overall system status and performance indicators.',
            'help_disk_space' => 'Available disk space on the server.',
            'help_memory_usage' => 'Current memory consumption.',
            'help_database_size' => 'Total size of the database.',
            'help_backup_frequency' => 'How often automatic backups are created.',
            'help_error_rate' => 'Percentage of requests resulting in errors.',
            'help_response_time' => 'Average server response time.',
            
            // Email Templates
            'help_email_template' => 'HTML template used for automated emails.',
            'help_email_variables' => 'Available placeholders: {first_name}, {last_name}, {reference_number}, etc.',
            'help_email_trigger' => 'Event that automatically sends this email.',
            'help_email_delay' => 'Time delay before sending the email after the trigger event.',
            
            // Reports and Analytics
            'help_date_range' => 'Select the time period for the report data.',
            'help_report_filters' => 'Narrow down the data by specific criteria.',
            'help_export_format' => 'Choose the file format for exporting report data.',
            'help_chart_type' => 'Visual representation style for the data.',
            
            // API and Integrations
            'help_api_key' => 'Unique identifier for API access authentication.',
            'help_webhook_url' => 'Endpoint URL for receiving webhook notifications.',
            'help_rate_limiting' => 'Maximum number of API requests per time period.',
            'help_ip_restrictions' => 'Limit API access to specific IP addresses.',
        ];
    }
    
    /**
     * Load help sections for comprehensive guidance
     */
    private static function loadHelpSections() {
        self::$help_sections = [
            'getting_started' => [
                'title' => 'Getting Started',
                'content' => [
                    'Welcome to LoanFlow Admin Panel! This guide will help you set up and manage your loan application system.',
                    'First, configure your company information and branding in the Company Settings section.',
                    'Next, set up your email server in System Settings > Email/SMTP to enable automated communications.',
                    'Configure your fee structure in Fee Settings to match your business model.',
                    'Finally, test the application process to ensure everything works correctly.'
                ]
            ],
            'user_management' => [
                'title' => 'Managing Users',
                'content' => [
                    'Users are automatically created when someone submits a loan application.',
                    'You can view and manage all users in the Users section of the admin panel.',
                    'User roles determine access levels: Client (applicants), Agent (staff), Admin (managers).',
                    'You can manually create users, change their status, reset passwords, and manage permissions.',
                    'Enable two-factor authentication for enhanced security.'
                ]
            ],
            'application_processing' => [
                'title' => 'Processing Applications',
                'content' => [
                    'New applications appear in the Applications section with "Pending" status.',
                    'Review application details, uploaded documents, and credit information.',
                    'Update the status as you progress: Under Review → Approved/Rejected → Funded.',
                    'Use the messaging system to communicate with applicants.',
                    'Generate and send required documents using the built-in templates.',
                    'Track payments and manage refunds through the Payment section.'
                ]
            ],
            'security_best_practices' => [
                'title' => 'Security Best Practices',
                'content' => [
                    'Always use strong passwords and enable two-factor authentication.',
                    'Regularly update the system and monitor the audit logs.',
                    'Limit admin access to trusted IP addresses when possible.',
                    'Keep SMTP credentials secure and use app-specific passwords.',
                    'Regular backup your database and store backups securely.',
                    'Monitor failed login attempts and investigate suspicious activity.'
                ]
            ],
            'troubleshooting' => [
                'title' => 'Troubleshooting Common Issues',
                'content' => [
                    'Email not sending: Check SMTP settings and test email functionality.',
                    'Users can\'t login: Verify account status and check for IP restrictions.',
                    'Payment issues: Ensure payment gateway credentials are correct.',
                    'Slow performance: Clear cache and check server resources.',
                    'Missing features: Verify user permissions and system settings.',
                    'For technical support, check the system logs and contact support with specific error messages.'
                ]
            ]
        ];
    }
    
    /**
     * Get tooltip text for a specific key
     */
    public static function getTooltip($key) {
        return self::$tooltips[$key] ?? '';
    }
    
    /**
     * Generate tooltip HTML
     */
    public static function tooltip($key, $icon = 'fas fa-question-circle') {
        $tooltip_text = self::getTooltip($key);
        if (empty($tooltip_text)) {
            return '';
        }
        
        return '<i class="' . $icon . ' ms-1 text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="' . htmlspecialchars($tooltip_text) . '"></i>';
    }
    
    /**
     * Get help section content
     */
    public static function getHelpSection($section) {
        return self::$help_sections[$section] ?? null;
    }
    
    /**
     * Generate contextual help panel
     */
    public static function generateHelpPanel($sections = []) {
        if (empty($sections)) {
            $sections = array_keys(self::$help_sections);
        }
        
        $html = '<div class="help-panel">';
        $html .= '<div class="accordion" id="helpAccordion">';
        
        foreach ($sections as $index => $section_key) {
            $section = self::getHelpSection($section_key);
            if (!$section) continue;
            
            $is_first = $index === 0;
            $html .= '<div class="accordion-item">';
            $html .= '<h2 class="accordion-header" id="heading' . $index . '">';
            $html .= '<button class="accordion-button' . ($is_first ? '' : ' collapsed') . '" type="button" data-bs-toggle="collapse" data-bs-target="#collapse' . $index . '">';
            $html .= '<i class="fas fa-info-circle me-2"></i>' . htmlspecialchars($section['title']);
            $html .= '</button>';
            $html .= '</h2>';
            $html .= '<div id="collapse' . $index . '" class="accordion-collapse collapse' . ($is_first ? ' show' : '') . '" data-bs-parent="#helpAccordion">';
            $html .= '<div class="accordion-body">';
            
            foreach ($section['content'] as $paragraph) {
                $html .= '<p>' . htmlspecialchars($paragraph) . '</p>';
            }
            
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate interactive help button
     */
    public static function helpButton($section = null) {
        $onclick = $section ? "showHelp('$section')" : "showHelp()";
        return '<button type="button" class="btn btn-outline-info btn-sm" onclick="' . $onclick . '">
                    <i class="fas fa-question-circle me-1"></i>Help
                </button>';
    }
    
    /**
     * Generate help modal HTML
     */
    public static function generateHelpModal() {
        return '
        <!-- Help Modal -->
        <div class="modal fade" id="helpModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-question-circle me-2"></i>Help & Documentation
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="helpModalBody">
                        ' . self::generateHelpPanel() . '
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <a href="mailto:support@loanflow.com" class="btn btn-primary">
                            <i class="fas fa-envelope me-1"></i>Contact Support
                        </a>
                    </div>
                </div>
            </div>
        </div>';
    }
    
    /**
     * Generate JavaScript for tooltip and help functionality
     */
    public static function generateJS() {
        return '
        <script>
            // Initialize tooltips
            document.addEventListener("DOMContentLoaded", function() {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll("[data-bs-toggle=\"tooltip\"]"));
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl, {
                        delay: { show: 500, hide: 100 },
                        placement: "top"
                    });
                });
            });
            
            // Show help modal
            function showHelp(section) {
                if (section) {
                    // Show specific section
                    var helpModal = new bootstrap.Modal(document.getElementById("helpModal"));
                    helpModal.show();
                    
                    // Expand specific accordion item
                    setTimeout(function() {
                        var targetAccordion = document.querySelector("#collapse" + section);
                        if (targetAccordion) {
                            var accordion = new bootstrap.Collapse(targetAccordion, {
                                show: true
                            });
                        }
                    }, 300);
                } else {
                    // Show general help
                    var helpModal = new bootstrap.Modal(document.getElementById("helpModal"));
                    helpModal.show();
                }
            }
            
            // Context-sensitive help
            function showContextHelp() {
                var currentPage = window.location.pathname;
                var helpSection = "getting_started";
                
                if (currentPage.includes("users")) helpSection = "user_management";
                else if (currentPage.includes("applications")) helpSection = "application_processing";
                else if (currentPage.includes("system-settings")) helpSection = "security_best_practices";
                
                showHelp(helpSection);
            }
            
            // Quick help tooltips
            function showQuickHelp(element, text) {
                var tooltip = new bootstrap.Tooltip(element, {
                    title: text,
                    trigger: "manual",
                    placement: "top"
                });
                tooltip.show();
                
                setTimeout(function() {
                    tooltip.hide();
                    tooltip.dispose();
                }, 3000);
            }
            
            // Add help keyboard shortcut (F1)
            document.addEventListener("keydown", function(e) {
                if (e.keyCode === 112) { // F1 key
                    e.preventDefault();
                    showContextHelp();
                }
            });
        </script>';
    }
    
    /**
     * Get all available tooltip keys
     */
    public static function getAvailableTooltips() {
        return array_keys(self::$tooltips);
    }
    
    /**
     * Add custom tooltip
     */
    public static function addTooltip($key, $text) {
        self::$tooltips[$key] = $text;
    }
    
    /**
     * Add custom help section
     */
    public static function addHelpSection($key, $title, $content) {
        self::$help_sections[$key] = [
            'title' => $title,
            'content' => is_array($content) ? $content : [$content]
        ];
    }
}

// Initialize the tooltip system
TooltipManager::init();

// Helper function for easy tooltip generation
function tooltip($key, $icon = 'fas fa-question-circle') {
    return TooltipManager::tooltip($key, $icon);
}

// Helper function for help buttons
function helpButton($section = null) {
    return TooltipManager::helpButton($section);
}
