-- Pre-Approval Automation Email Templates
-- LoanFlow Personal Loan Management System
-- Email templates for automated pre-approval workflows

-- Insert pre-approval notification template
INSERT INTO email_templates (template_name, template_type, subject, body_html, body_text, variables, is_active, created_at) VALUES
('Pre-Approval Notification', 'pre_approval_notification', 
'🎉 Congratulations! Your Loan Pre-Approval is Ready - {{reference_number}}',
'<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-Approval Notification</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
        .approval-box { background: white; border: 2px solid #28a745; border-radius: 10px; padding: 25px; margin: 20px 0; text-align: center; }
        .amount { font-size: 2.5em; font-weight: bold; color: #28a745; margin: 10px 0; }
        .terms { background: #e9ecef; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .terms-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .term-item { text-align: center; }
        .term-label { font-size: 0.9em; color: #6c757d; margin-bottom: 5px; }
        .term-value { font-size: 1.2em; font-weight: bold; color: #495057; }
        .cta-button { display: inline-block; background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
        .next-steps { background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .footer { text-align: center; color: #6c757d; font-size: 0.9em; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Congratulations {{client_name}}!</h1>
            <p>Your loan application has been pre-approved!</p>
        </div>
        
        <div class="content">
            <div class="approval-box">
                <h2>Pre-Approved Amount</h2>
                <div class="amount">{{approved_amount}}</div>
                <p><strong>Reference:</strong> {{reference_number}}</p>
            </div>
            
            <div class="terms">
                <h3>Your Pre-Approval Terms</h3>
                <div class="terms-grid">
                    <div class="term-item">
                        <div class="term-label">Interest Rate</div>
                        <div class="term-value">{{interest_rate}}</div>
                    </div>
                    <div class="term-item">
                        <div class="term-label">Loan Term</div>
                        <div class="term-value">{{loan_term}}</div>
                    </div>
                    <div class="term-item">
                        <div class="term-label">Monthly Payment</div>
                        <div class="term-value">{{monthly_payment}}</div>
                    </div>
                    <div class="term-item">
                        <div class="term-label">Processing Time</div>
                        <div class="term-value">24-48 hours</div>
                    </div>
                </div>
            </div>
            
            <div class="next-steps">
                <h3>📋 Next Steps</h3>
                <ol>
                    <li><strong>Upload Documents:</strong> Provide required documentation</li>
                    <li><strong>Verification:</strong> Our team will verify your documents</li>
                    <li><strong>Final Approval:</strong> Complete your loan agreement</li>
                    <li><strong>Funding:</strong> Receive your funds within 24 hours</li>
                </ol>
            </div>
            
            <div style="text-align: center;">
                <a href="{{login_url}}" class="cta-button">Continue Your Application</a>
            </div>
            
            <p><strong>Important:</strong> This pre-approval is valid for 7 days. Please complete your application within this timeframe to secure these terms.</p>
        </div>
        
        <div class="footer">
            <p>Best regards,<br>{{company_name}} Team</p>
            <p><small>This is an automated message. Please do not reply to this email.</small></p>
        </div>
    </div>
</body>
</html>',
'Congratulations {{client_name}}!

Your loan application ({{reference_number}}) has been pre-approved!

Pre-Approved Amount: {{approved_amount}}
Interest Rate: {{interest_rate}}
Loan Term: {{loan_term}}
Monthly Payment: {{monthly_payment}}

Next Steps:
1. Upload Documents: Provide required documentation
2. Verification: Our team will verify your documents
3. Final Approval: Complete your loan agreement
4. Funding: Receive your funds within 24 hours

Continue your application: {{login_url}}

Important: This pre-approval is valid for 7 days.

Best regards,
{{company_name}} Team',
'client_name,reference_number,approved_amount,interest_rate,loan_term,monthly_payment,login_url,company_name',
1, NOW()),

-- Insert document reminder template
('Document Upload Reminder', 'document_reminder',
'⏰ Action Required: Upload Your Documents - {{reference_number}}',
'<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Upload Reminder</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #ffc107 0%, #ff8f00 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
        .reminder-box { background: #fff3cd; border: 2px solid #ffc107; border-radius: 10px; padding: 25px; margin: 20px 0; text-align: center; }
        .documents-list { background: white; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .document-item { display: flex; align-items: center; padding: 10px 0; border-bottom: 1px solid #e9ecef; }
        .document-item:last-child { border-bottom: none; }
        .document-icon { color: #007bff; margin-right: 10px; }
        .cta-button { display: inline-block; background: #ffc107; color: #212529; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
        .urgency { background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; padding: 15px; margin: 20px 0; color: #721c24; }
        .footer { text-align: center; color: #6c757d; font-size: 0.9em; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⏰ Document Upload Required</h1>
            <p>Complete your pre-approved loan application</p>
        </div>
        
        <div class="content">
            <p>Dear {{client_name}},</p>
            
            <div class="reminder-box">
                <h3>Your Pre-Approval is Waiting!</h3>
                <p><strong>Reference:</strong> {{reference_number}}</p>
                <p>We need your documents to proceed with final approval.</p>
            </div>
            
            <div class="documents-list">
                <h3>📄 Required Documents</h3>
                <div class="document-item">
                    <span class="document-icon">📋</span>
                    <span>Government-issued ID (Driver\'s License or Passport)</span>
                </div>
                <div class="document-item">
                    <span class="document-icon">💰</span>
                    <span>Proof of Income (Pay stubs or Bank statements)</span>
                </div>
                <div class="document-item">
                    <span class="document-icon">🏠</span>
                    <span>Proof of Address (Utility bill or Lease agreement)</span>
                </div>
            </div>
            
            <div class="urgency">
                <strong>⚠️ Time Sensitive:</strong> Your pre-approval expires in a few days. Upload your documents now to secure your loan terms.
            </div>
            
            <div style="text-align: center;">
                <a href="{{login_url}}" class="cta-button">Upload Documents Now</a>
            </div>
            
            <p><strong>Need Help?</strong> Our support team is ready to assist you with the document upload process.</p>
        </div>
        
        <div class="footer">
            <p>Best regards,<br>{{company_name}} Team</p>
            <p><small>This is an automated reminder. Please do not reply to this email.</small></p>
        </div>
    </div>
</body>
</html>',
'Dear {{client_name}},

Your pre-approved loan application ({{reference_number}}) is waiting for document upload.

Required Documents:
- Government-issued ID (Driver\'s License or Passport)
- Proof of Income (Pay stubs or Bank statements)
- Proof of Address (Utility bill or Lease agreement)

Time Sensitive: Your pre-approval expires soon. Upload your documents now to secure your loan terms.

Upload documents: {{login_url}}

Need help? Contact our support team.

Best regards,
{{company_name}} Team',
'client_name,reference_number,login_url,company_name',
1, NOW()),

-- Insert agreement reminder template
('Agreement Signing Reminder', 'agreement_reminder',
'📝 Final Step: Sign Your Loan Agreement - {{reference_number}}',
'<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agreement Signing Reminder</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
        .progress-box { background: white; border: 2px solid #17a2b8; border-radius: 10px; padding: 25px; margin: 20px 0; text-align: center; }
        .progress-bar { background: #e9ecef; height: 10px; border-radius: 5px; margin: 15px 0; }
        .progress-fill { background: #17a2b8; height: 100%; width: 80%; border-radius: 5px; }
        .final-step { background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .cta-button { display: inline-block; background: #17a2b8; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
        .benefits { background: white; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .benefit-item { display: flex; align-items: center; padding: 10px 0; }
        .benefit-icon { color: #28a745; margin-right: 10px; font-size: 1.2em; }
        .footer { text-align: center; color: #6c757d; font-size: 0.9em; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 Almost There!</h1>
            <p>One final step to complete your loan</p>
        </div>
        
        <div class="content">
            <p>Dear {{client_name}},</p>
            
            <div class="progress-box">
                <h3>Application Progress</h3>
                <p><strong>Reference:</strong> {{reference_number}}</p>
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <p><strong>80% Complete</strong> - Just one step remaining!</p>
            </div>
            
            <div class="final-step">
                <h3>🎯 Final Step: Digital Signature</h3>
                <p>Your documents have been verified and approved. Now we just need your digital signature on the loan agreement to complete the process.</p>
            </div>
            
            <div class="benefits">
                <h3>✅ What Happens After Signing</h3>
                <div class="benefit-item">
                    <span class="benefit-icon">⚡</span>
                    <span>Instant loan activation</span>
                </div>
                <div class="benefit-item">
                    <span class="benefit-icon">💰</span>
                    <span>Funds transferred within 24 hours</span>
                </div>
                <div class="benefit-item">
                    <span class="benefit-icon">📱</span>
                    <span>Access to your loan management dashboard</span>
                </div>
                <div class="benefit-item">
                    <span class="benefit-icon">🔒</span>
                    <span>Secure, legally binding agreement</span>
                </div>
            </div>
            
            <div style="text-align: center;">
                <a href="{{login_url}}" class="cta-button">Sign Agreement Now</a>
            </div>
            
            <p><strong>Secure Process:</strong> Our digital signature process is bank-level secure and legally binding. The entire process takes less than 5 minutes.</p>
        </div>
        
        <div class="footer">
            <p>Best regards,<br>{{company_name}} Team</p>
            <p><small>This is an automated reminder. Please do not reply to this email.</small></p>
        </div>
    </div>
</body>
</html>',
'Dear {{client_name}},

Your loan application ({{reference_number}}) is 80% complete!

Final Step: Digital Signature
Your documents have been verified and approved. We just need your digital signature on the loan agreement.

What happens after signing:
- Instant loan activation
- Funds transferred within 24 hours
- Access to loan management dashboard
- Secure, legally binding agreement

Sign your agreement: {{login_url}}

The process is secure and takes less than 5 minutes.

Best regards,
{{company_name}} Team',
'client_name,reference_number,login_url,company_name',
1, NOW()),

-- Insert funding notification template
('Funding Notification', 'funding_notification',
'🚀 Great News! Your Loan Funding is Being Processed - {{reference_number}}',
'<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Funding Notification</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
        .funding-box { background: white; border: 2px solid #28a745; border-radius: 10px; padding: 25px; margin: 20px 0; text-align: center; }
        .amount { font-size: 2.5em; font-weight: bold; color: #28a745; margin: 10px 0; }
        .timeline { background: white; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .timeline-item { display: flex; align-items: center; padding: 15px 0; border-bottom: 1px solid #e9ecef; }
        .timeline-item:last-child { border-bottom: none; }
        .timeline-icon { background: #28a745; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px; font-size: 0.8em; }
        .timeline-content h4 { margin: 0 0 5px 0; color: #495057; }
        .timeline-content p { margin: 0; color: #6c757d; font-size: 0.9em; }
        .important-info { background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .cta-button { display: inline-block; background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
        .footer { text-align: center; color: #6c757d; font-size: 0.9em; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Funding in Progress!</h1>
            <p>Your loan has been approved and funding initiated</p>
        </div>
        
        <div class="content">
            <p>Dear {{client_name}},</p>
            
            <div class="funding-box">
                <h3>Funding Amount</h3>
                <div class="amount">{{loan_amount}}</div>
                <p><strong>Reference:</strong> {{reference_number}}</p>
                <p><strong>Status:</strong> <span style="color: #28a745; font-weight: bold;">Processing</span></p>
            </div>
            
            <div class="timeline">
                <h3>📅 Funding Timeline</h3>
                <div class="timeline-item">
                    <div class="timeline-icon">✓</div>
                    <div class="timeline-content">
                        <h4>Application Approved</h4>
                        <p>Your loan application has been fully approved</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-icon">⏳</div>
                    <div class="timeline-content">
                        <h4>Funding Processing</h4>
                        <p>Your funds are being prepared for transfer</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-icon">💰</div>
                    <div class="timeline-content">
                        <h4>Funds Transfer</h4>
                        <p>Expected within 24 hours</p>
                    </div>
                </div>
            </div>
            
            <div class="important-info">
                <h3>📋 Important Information</h3>
                <ul>
                    <li><strong>Transfer Method:</strong> Direct deposit to your verified bank account</li>
                    <li><strong>Expected Time:</strong> Funds will arrive within 24 hours</li>
                    <li><strong>Notification:</strong> You\'ll receive confirmation once funds are sent</li>
                    <li><strong>First Payment:</strong> Due 30 days from funding date</li>
                </ul>
            </div>
            
            <div style="text-align: center;">
                <a href="{{login_url}}" class="cta-button">View Loan Details</a>
            </div>
            
            <p><strong>What\'s Next?</strong> Monitor your bank account for the incoming transfer. You\'ll also receive a detailed loan schedule and payment information in your client portal.</p>
        </div>
        
        <div class="footer">
            <p>Congratulations and thank you for choosing us!<br>{{company_name}} Team</p>
            <p><small>This is an automated notification. Please do not reply to this email.</small></p>
        </div>
    </div>
</body>
</html>',
'Dear {{client_name}},

Great news! Your loan funding is being processed.

Funding Amount: {{loan_amount}}
Reference: {{reference_number}}
Status: Processing

Funding Timeline:
✓ Application Approved
⏳ Funding Processing (Current)
💰 Funds Transfer (Within 24 hours)

Important Information:
- Transfer Method: Direct deposit to your verified bank account
- Expected Time: Funds will arrive within 24 hours
- Notification: You\'ll receive confirmation once funds are sent
- First Payment: Due 30 days from funding date

View loan details: {{login_url}}

Monitor your bank account for the incoming transfer.

Congratulations and thank you for choosing us!
{{company_name}} Team',
'client_name,reference_number,loan_amount,login_url,company_name',
1, NOW());

-- Update existing email templates if they exist
UPDATE email_templates SET 
    subject = '🎉 Congratulations! Your Loan Pre-Approval is Ready - {{reference_number}}',
    updated_at = NOW()
WHERE template_type = 'pre_approval_notification' AND template_name != 'Pre-Approval Notification';

UPDATE email_templates SET 
    subject = '⏰ Action Required: Upload Your Documents - {{reference_number}}',
    updated_at = NOW()
WHERE template_type = 'document_reminder' AND template_name != 'Document Upload Reminder';

UPDATE email_templates SET 
    subject = '📝 Final Step: Sign Your Loan Agreement - {{reference_number}}',
    updated_at = NOW()
WHERE template_type = 'agreement_reminder' AND template_name != 'Agreement Signing Reminder';

UPDATE email_templates SET 
    subject = '🚀 Great News! Your Loan Funding is Being Processed - {{reference_number}}',
    updated_at = NOW()
WHERE template_type = 'funding_notification' AND template_name != 'Funding Notification';