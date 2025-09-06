-- Document Management System Database Schema
-- LoanFlow Personal Loan Management System
-- This schema supports client area document visibility, admin controls, and PDF generation

-- Document categories table
CREATE TABLE IF NOT EXISTS document_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sort_order (sort_order),
    INDEX idx_active (is_active)
);

-- Editable documents table (for admin-managed content)
CREATE TABLE IF NOT EXISTS editable_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_key VARCHAR(100) NOT NULL UNIQUE, -- e.g., 'company_declaration', 'loan_guarantee'
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category_id INT,
    content LONGTEXT NOT NULL, -- Markdown content
    template_variables JSON, -- Available variables for this document
    is_client_visible BOOLEAN DEFAULT FALSE,
    requires_download_permission BOOLEAN DEFAULT TRUE,
    version VARCHAR(20) DEFAULT '1.0',
    last_modified_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES document_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (last_modified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_document_key (document_key),
    INDEX idx_client_visible (is_client_visible),
    INDEX idx_category (category_id)
);

-- Document versions history
CREATE TABLE IF NOT EXISTS document_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    version VARCHAR(20) NOT NULL,
    content LONGTEXT NOT NULL,
    change_summary TEXT,
    modified_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES editable_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (modified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_document_version (document_id, version),
    INDEX idx_created_at (created_at)
);

-- Client document access permissions
CREATE TABLE IF NOT EXISTS client_document_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_id INT NOT NULL,
    can_view BOOLEAN DEFAULT FALSE,
    can_download BOOLEAN DEFAULT FALSE,
    granted_by INT,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES editable_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_document (user_id, document_id),
    INDEX idx_user_permissions (user_id),
    INDEX idx_document_permissions (document_id),
    INDEX idx_expires_at (expires_at)
);

-- Document download log
CREATE TABLE IF NOT EXISTS document_download_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_id INT NOT NULL,
    download_type ENUM('view', 'pdf_download') NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    file_size INT, -- Size in bytes
    download_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES editable_documents(id) ON DELETE CASCADE,
    INDEX idx_user_downloads (user_id, download_time),
    INDEX idx_document_downloads (document_id, download_time),
    INDEX idx_download_type (download_type)
);

-- Document personalization for users
CREATE TABLE IF NOT EXISTS document_personalizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_id INT NOT NULL,
    personalized_content LONGTEXT, -- Content with user-specific variables replaced
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_current BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES editable_documents(id) ON DELETE CASCADE,
    INDEX idx_user_document (user_id, document_id),
    INDEX idx_current (is_current)
);

-- Document approval workflow
CREATE TABLE IF NOT EXISTS document_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    version VARCHAR(20) NOT NULL,
    submitted_by INT NOT NULL,
    approved_by INT NULL,
    status ENUM('pending', 'approved', 'rejected', 'revision_required') DEFAULT 'pending',
    approval_notes TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (document_id) REFERENCES editable_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_document_status (document_id, status),
    INDEX idx_submitted_at (submitted_at)
);

-- Insert default document categories
INSERT INTO document_categories (name, description, sort_order) VALUES
('Legal Documents', 'Legal agreements and declarations', 1),
('Authorization Forms', 'User authorization and consent forms', 2),
('Company Policies', 'Company policies and procedures', 3),
('Loan Agreements', 'Loan-specific agreements and terms', 4),
('Marketing Materials', 'Marketing and promotional documents', 5)
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Insert default editable documents
INSERT INTO editable_documents (document_key, title, description, category_id, content, template_variables, is_client_visible, requires_download_permission) VALUES

('company_declaration', 'Company Statement of Declaration', 'Official company declaration and statement', 
(SELECT id FROM document_categories WHERE name = 'Legal Documents'),
'# Company Statement of Declaration\n\n## {{company_name}}\n\n**Date:** {{current_date}}\n\n### Declaration\n\nWe, {{company_name}}, hereby declare that:\n\n1. **Company Information**\n   - Legal Name: {{company_legal_name}}\n   - Registration Number: {{company_registration}}\n   - Address: {{company_address}}\n   - Phone: {{company_phone}}\n   - Email: {{company_email}}\n\n2. **Business Operations**\n   - We are a licensed financial services provider\n   - We operate in compliance with all applicable laws and regulations\n   - We maintain appropriate insurance and bonding\n\n3. **Client Commitment**\n   - We are committed to providing transparent and fair lending services\n   - We protect client information in accordance with privacy laws\n   - We provide clear terms and conditions for all loan products\n\n4. **Regulatory Compliance**\n   - We comply with all federal and state lending regulations\n   - We maintain required licenses and registrations\n   - We submit required reports to regulatory authorities\n\n### Authorization\n\nThis declaration is made under penalty of perjury and is true and correct to the best of our knowledge.\n\n**Authorized Signature:**\n\n_________________________\n{{authorized_signatory_name}}\n{{authorized_signatory_title}}\n{{company_name}}\n\n**Date:** {{current_date}}\n\n---\n\n*This document is confidential and proprietary to {{company_name}}. Unauthorized distribution is prohibited.*',
JSON_OBJECT('company_name', 'Company name', 'company_legal_name', 'Legal company name', 'company_registration', 'Registration number', 'company_address', 'Company address', 'company_phone', 'Company phone', 'company_email', 'Company email', 'current_date', 'Current date', 'authorized_signatory_name', 'Authorized signatory name', 'authorized_signatory_title', 'Authorized signatory title'),
TRUE, TRUE),

('loan_guarantee_agreement', 'Loan Guarantee Agreement', 'Comprehensive loan guarantee agreement', 
(SELECT id FROM document_categories WHERE name = 'Loan Agreements'),
'# Loan Guarantee Agreement\n\n**Agreement Number:** {{agreement_number}}\n**Date:** {{current_date}}\n\n## Parties\n\n**Lender:** {{company_name}}\n**Borrower:** {{client_full_name}}\n**Loan Amount:** ${{loan_amount}}\n\n## Terms and Conditions\n\n### 1. Loan Details\n- **Principal Amount:** ${{loan_amount}}\n- **Interest Rate:** {{interest_rate}}% per annum\n- **Term:** {{loan_term}} months\n- **Monthly Payment:** ${{monthly_payment}}\n\n### 2. Guarantee Provisions\n\nThe borrower guarantees that:\n\na) All information provided is true and accurate\nb) The loan will be used for the stated purpose\nc) All payments will be made on time\nd) The borrower will notify the lender of any changes in financial circumstances\n\n### 3. Default Provisions\n\nDefault occurs when:\n- Payment is more than {{grace_period}} days late\n- Borrower provides false information\n- Borrower files for bankruptcy\n- Borrower violates any term of this agreement\n\n### 4. Remedies\n\nUpon default, the lender may:\n- Declare the entire balance due immediately\n- Pursue collection through legal means\n- Report to credit bureaus\n- Exercise any other legal remedies\n\n### 5. Governing Law\n\nThis agreement is governed by the laws of {{state_jurisdiction}}.\n\n## Signatures\n\n**Borrower:**\n\n_________________________\n{{client_full_name}}\nDate: {{current_date}}\n\n**Lender:**\n\n_________________________\n{{lender_representative}}\n{{company_name}}\nDate: {{current_date}}\n\n---\n\n*This is a legally binding agreement. Please read carefully before signing.*',
JSON_OBJECT('agreement_number', 'Agreement number', 'current_date', 'Current date', 'company_name', 'Company name', 'client_full_name', 'Client full name', 'loan_amount', 'Loan amount', 'interest_rate', 'Interest rate', 'loan_term', 'Loan term in months', 'monthly_payment', 'Monthly payment amount', 'grace_period', 'Grace period in days', 'state_jurisdiction', 'State jurisdiction', 'lender_representative', 'Lender representative name'),
TRUE, TRUE),

('marketing_authorization', 'Marketing Authorization Agreement', 'Authorization for marketing communications', 
(SELECT id FROM document_categories WHERE name = 'Authorization Forms'),
'# Marketing Authorization Agreement\n\n**Client:** {{client_full_name}}\n**Date:** {{current_date}}\n\n## Marketing Communication Authorization\n\n### 1. Consent to Marketing\n\nI, {{client_full_name}}, hereby authorize {{company_name}} to contact me for marketing purposes through the following channels:\n\n- [ ] Email to: {{client_email}}\n- [ ] Phone calls to: {{client_phone}}\n- [ ] Text messages to: {{client_phone}}\n- [ ] Direct mail to: {{client_address}}\n- [ ] Social media platforms\n\n### 2. Types of Marketing Communications\n\nI consent to receive information about:\n\n- [ ] New loan products and services\n- [ ] Special offers and promotions\n- [ ] Company news and updates\n- [ ] Educational content about financial services\n- [ ] Customer satisfaction surveys\n\n### 3. Frequency\n\nI understand that marketing communications may be sent:\n- Up to {{max_emails_per_month}} emails per month\n- Up to {{max_calls_per_month}} phone calls per month\n- Up to {{max_texts_per_month}} text messages per month\n\n### 4. Opt-Out Rights\n\nI understand that I can:\n- Unsubscribe from emails by clicking the unsubscribe link\n- Opt out of calls by requesting to be placed on the do-not-call list\n- Stop text messages by replying "STOP"\n- Contact customer service at {{company_phone}} to update preferences\n\n### 5. Data Usage\n\nI understand that {{company_name}} will:\n- Use my information only for authorized marketing purposes\n- Protect my information according to the privacy policy\n- Not sell my information to third parties without consent\n- Honor my opt-out requests promptly\n\n### 6. Agreement Term\n\nThis authorization remains in effect until:\n- I withdraw consent\n- My account is closed\n- {{company_name}} discontinues marketing programs\n\n## Client Acknowledgment\n\nBy signing below, I acknowledge that:\n- I have read and understand this authorization\n- I voluntarily consent to marketing communications\n- I understand my rights to opt out at any time\n\n**Client Signature:**\n\n_________________________\n{{client_full_name}}\nDate: {{current_date}}\n\n**Witness:**\n\n_________________________\n{{witness_name}}\n{{company_name}}\nDate: {{current_date}}\n\n---\n\n*Your privacy is important to us. See our full privacy policy at {{privacy_policy_url}}*',
JSON_OBJECT('client_full_name', 'Client full name', 'current_date', 'Current date', 'company_name', 'Company name', 'client_email', 'Client email', 'client_phone', 'Client phone', 'client_address', 'Client address', 'max_emails_per_month', 'Maximum emails per month', 'max_calls_per_month', 'Maximum calls per month', 'max_texts_per_month', 'Maximum texts per month', 'company_phone', 'Company phone', 'witness_name', 'Witness name', 'privacy_policy_url', 'Privacy policy URL'),
TRUE, TRUE),

('soft_credit_authorization', 'Soft Credit Pull Authorization', 'Authorization for soft credit inquiry', 
(SELECT id FROM document_categories WHERE name = 'Authorization Forms'),
'# Soft Credit Pull Authorization\n\n**Applicant:** {{client_full_name}}\n**Date:** {{current_date}}\n**Application ID:** {{application_id}}\n\n## Credit Inquiry Authorization\n\n### 1. Authorization to Obtain Credit Report\n\nI, {{client_full_name}}, hereby authorize {{company_name}} to obtain my credit report and credit score from one or more consumer reporting agencies for the purpose of evaluating my loan application.\n\n### 2. Type of Credit Inquiry\n\nI understand that this will be a **SOFT CREDIT INQUIRY** which:\n- Will NOT affect my credit score\n- Will NOT appear on my credit report as visible to other lenders\n- Is used solely for pre-qualification purposes\n- May be converted to a hard inquiry only with my additional consent\n\n### 3. Information to be Obtained\n\nThe credit report may include:\n- Payment history\n- Current account balances\n- Length of credit history\n- Types of credit accounts\n- Recent credit inquiries\n- Public records (bankruptcies, liens, judgments)\n\n### 4. Use of Credit Information\n\n{{company_name}} will use this information to:\n- Determine loan eligibility\n- Calculate loan terms and interest rates\n- Verify identity and prevent fraud\n- Comply with lending regulations\n\n### 5. Information Security\n\nI understand that {{company_name}} will:\n- Protect my credit information according to federal privacy laws\n- Use the information only for authorized lending purposes\n- Maintain secure storage of all credit data\n- Dispose of credit information securely when no longer needed\n\n### 6. Retention Period\n\nCredit information will be retained for:\n- Active applications: Until loan is funded or declined\n- Approved loans: Duration of loan plus {{retention_years}} years\n- Declined applications: {{declined_retention_months}} months\n\n### 7. Consumer Rights\n\nI understand my rights under the Fair Credit Reporting Act (FCRA):\n- Right to obtain a copy of my credit report\n- Right to dispute inaccurate information\n- Right to know if adverse action is taken based on credit report\n- Right to receive adverse action notices\n\n### 8. Contact Information for Credit Bureaus\n\n**Experian:** 1-888-397-3742 | www.experian.com\n**Equifax:** 1-800-685-1111 | www.equifax.com\n**TransUnion:** 1-800-916-8800 | www.transunion.com\n\n## Applicant Certification\n\nBy signing below, I certify that:\n- I am {{client_age}} years of age or older\n- The information provided is true and accurate\n- I understand this is a soft credit inquiry\n- I authorize {{company_name}} to obtain my credit report\n- I have read and understand my rights under the FCRA\n\n**Applicant Signature:**\n\n_________________________\n{{client_full_name}}\nSSN: XXX-XX-{{ssn_last_four}}\nDate: {{current_date}}\n\n**Company Representative:**\n\n_________________________\n{{company_representative}}\n{{company_name}}\nDate: {{current_date}}\n\n---\n\n*For questions about this authorization, contact us at {{company_phone}} or {{company_email}}*',
JSON_OBJECT('client_full_name', 'Client full name', 'current_date', 'Current date', 'application_id', 'Application ID', 'company_name', 'Company name', 'retention_years', 'Credit info retention years', 'declined_retention_months', 'Declined app retention months', 'client_age', 'Client age', 'ssn_last_four', 'Last 4 digits of SSN', 'company_representative', 'Company representative', 'company_phone', 'Company phone', 'company_email', 'Company email'),
TRUE, TRUE)

ON DUPLICATE KEY UPDATE 
    title = VALUES(title),
    description = VALUES(description),
    content = VALUES(content),
    template_variables = VALUES(template_variables);

-- Add system settings for document management
INSERT INTO system_settings (setting_key, setting_value, description, category) VALUES
('document_pdf_generation_enabled', '1', 'Enable PDF generation for documents', 'documents'),
('document_watermark_enabled', '1', 'Add watermark to generated PDFs', 'documents'),
('document_download_logging', '1', 'Log all document downloads', 'documents'),
('document_retention_days', '2555', 'Days to retain document download logs (7 years)', 'documents'),
('max_document_downloads_per_day', '10', 'Maximum document downloads per user per day', 'documents'),
('document_approval_required', '0', 'Require approval for document changes', 'documents')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Create indexes for better performance
CREATE INDEX idx_client_permissions_active ON client_document_permissions(user_id, can_view, can_download);
CREATE INDEX idx_download_log_user_date ON document_download_log(user_id, DATE(download_time));
CREATE INDEX idx_document_personalization_current ON document_personalizations(user_id, document_id, is_current);

-- Create view for client document access
CREATE VIEW client_document_access AS
SELECT 
    u.id as user_id,
    u.first_name,
    u.last_name,
    u.email,
    ed.id as document_id,
    ed.document_key,
    ed.title,
    ed.description,
    dc.name as category_name,
    ed.is_client_visible,
    ed.requires_download_permission,
    COALESCE(cdp.can_view, FALSE) as can_view,
    COALESCE(cdp.can_download, FALSE) as can_download,
    cdp.granted_at,
    cdp.expires_at,
    (SELECT COUNT(*) FROM document_download_log ddl 
     WHERE ddl.user_id = u.id AND ddl.document_id = ed.id) as download_count
FROM users u
CROSS JOIN editable_documents ed
LEFT JOIN document_categories dc ON ed.category_id = dc.id
LEFT JOIN client_document_permissions cdp ON u.id = cdp.user_id AND ed.id = cdp.document_id
WHERE ed.is_client_visible = TRUE
ORDER BY u.id, dc.sort_order, ed.title;

-- Create stored procedure for granting document access
DELIMITER //
CREATE PROCEDURE GrantDocumentAccess(
    IN p_user_id INT,
    IN p_document_id INT,
    IN p_can_view BOOLEAN,
    IN p_can_download BOOLEAN,
    IN p_granted_by INT,
    IN p_expires_at TIMESTAMP,
    IN p_notes TEXT
)
BEGIN
    INSERT INTO client_document_permissions 
    (user_id, document_id, can_view, can_download, granted_by, expires_at, notes)
    VALUES (p_user_id, p_document_id, p_can_view, p_can_download, p_granted_by, p_expires_at, p_notes)
    ON DUPLICATE KEY UPDATE
        can_view = p_can_view,
        can_download = p_can_download,
        granted_by = p_granted_by,
        granted_at = CURRENT_TIMESTAMP,
        expires_at = p_expires_at,
        notes = p_notes;
END//
DELIMITER ;

-- Create function to check document access
DELIMITER //
CREATE FUNCTION CheckDocumentAccess(
    p_user_id INT,
    p_document_id INT,
    p_access_type VARCHAR(20)
) RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE access_granted BOOLEAN DEFAULT FALSE;
    
    SELECT 
        CASE 
            WHEN p_access_type = 'view' THEN COALESCE(can_view, FALSE)
            WHEN p_access_type = 'download' THEN COALESCE(can_download, FALSE)
            ELSE FALSE
        END INTO access_granted
    FROM client_document_permissions
    WHERE user_id = p_user_id 
    AND document_id = p_document_id
    AND (expires_at IS NULL OR expires_at > NOW());
    
    RETURN access_granted;
END//
DELIMITER ;

COMMIT;