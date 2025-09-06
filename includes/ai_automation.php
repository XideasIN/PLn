<?php
/**
 * AI Autonomous and Automated Features System
 * LoanFlow Personal Loan Management System
 */

class AIAutomationManager {
    
    private static $enabled = true;
    private static $openai_api_key = '';
    private static $auto_process_applications = true;
    private static $auto_generate_content = true;
    private static $auto_respond_emails = true;
    
    /**
     * Initialize AI automation system
     */
    public static function init() {
        self::$enabled = getSystemSetting('ai_automation_enabled', '1') === '1';
        self::$openai_api_key = getSystemSetting('openai_api_key', '');
        self::$auto_process_applications = getSystemSetting('auto_process_applications', '1') === '1';
        self::$auto_generate_content = getSystemSetting('auto_generate_content', '1') === '1';
        self::$auto_respond_emails = getSystemSetting('auto_respond_emails', '1') === '1';
    }
    
    /**
     * Automatically process loan applications using AI
     */
    public static function autoProcessApplications() {
        if (!self::$enabled || !self::$auto_process_applications) {
            return false;
        }
        
        try {
            $db = getDB();
            
            // Get pending applications
            $stmt = $db->prepare("
                SELECT la.*, u.first_name, u.last_name, u.email, u.phone 
                FROM loan_applications la 
                JOIN users u ON la.user_id = u.id 
                WHERE la.application_status = 'pending' 
                AND la.created_at <= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                ORDER BY la.created_at ASC 
                LIMIT 10
            ");
            $stmt->execute();
            $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $processed = 0;
            
            foreach ($applications as $application) {
                $decision = self::evaluateApplication($application);
                
                if ($decision) {
                    self::updateApplicationStatus($application['id'], $decision['status'], $decision['notes']);
                    self::sendApplicationDecisionEmail($application, $decision);
                    $processed++;
                    
                    // Log the AI decision
                    logAudit('ai_application_processed', 'loan_applications', $application['id'], null, $decision);
                }
            }
            
            return $processed;
            
        } catch (Exception $e) {
            error_log("AI auto-process applications error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * AI-powered application evaluation
     */
    private static function evaluateApplication($application) {
        if (empty(self::$openai_api_key)) {
            return self::basicApplicationEvaluation($application);
        }
        
        $prompt = self::generateApplicationEvaluationPrompt($application);
        
        $response = self::callOpenAI([
            ['role' => 'system', 'content' => 'You are a professional loan underwriter AI assistant. Evaluate loan applications based on standard lending criteria and provide clear decisions with reasoning.'],
            ['role' => 'user', 'content' => $prompt]
        ]);
        
        if ($response['success']) {
            return self::parseApplicationDecision($response['content']);
        } else {
            return self::basicApplicationEvaluation($application);
        }
    }
    
    /**
     * Generate application evaluation prompt
     */
    private static function generateApplicationEvaluationPrompt($application) {
        return "Please evaluate this loan application and provide a decision:

Application Details:
- Applicant: {$application['first_name']} {$application['last_name']}
- Loan Amount: $" . number_format($application['loan_amount']) . "
- Loan Purpose: {$application['loan_purpose']}
- Employment: {$application['employment_status']}
- Annual Income: $" . number_format($application['annual_income']) . "
- Monthly Income: $" . number_format($application['monthly_income']) . "
- Monthly Expenses: $" . number_format($application['monthly_expenses']) . "
- Credit Score: {$application['credit_score']}
- Debt-to-Income Ratio: " . number_format(($application['monthly_expenses'] / $application['monthly_income']) * 100, 2) . "%
- Existing Debts: $" . number_format($application['existing_debts']) . "
- Assets: $" . number_format($application['assets']) . "
- Housing Status: {$application['housing_status']}

Please provide:
1. Decision: APPROVED, REJECTED, or REVIEW_REQUIRED
2. Interest Rate: (if approved, suggest rate between 6-25%)
3. Loan Term: (suggest term in months)
4. Reasoning: Brief explanation of decision
5. Conditions: Any conditions if approved

Format your response as JSON:
{
  \"decision\": \"APPROVED|REJECTED|REVIEW_REQUIRED\",
  \"interest_rate\": 12.5,
  \"loan_term\": 36,
  \"reasoning\": \"Explanation here\",
  \"conditions\": \"Any conditions or empty string\"
}";
    }
    
    /**
     * Parse AI application decision
     */
    private static function parseApplicationDecision($ai_response) {
        try {
            $decision = json_decode($ai_response, true);
            
            if (!$decision) {
                return null;
            }
            
            $status_map = [
                'APPROVED' => 'approved',
                'REJECTED' => 'rejected',
                'REVIEW_REQUIRED' => 'under_review'
            ];
            
            return [
                'status' => $status_map[$decision['decision']] ?? 'under_review',
                'interest_rate' => $decision['interest_rate'] ?? null,
                'loan_term' => $decision['loan_term'] ?? null,
                'notes' => 'AI Decision: ' . ($decision['reasoning'] ?? 'No reasoning provided'),
                'conditions' => $decision['conditions'] ?? ''
            ];
            
        } catch (Exception $e) {
            error_log("Parse AI decision error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Basic application evaluation (fallback)
     */
    private static function basicApplicationEvaluation($application) {
        $score = 0;
        $notes = [];
        
        // Credit score evaluation
        if ($application['credit_score'] >= 750) {
            $score += 40;
            $notes[] = "Excellent credit score";
        } elseif ($application['credit_score'] >= 700) {
            $score += 30;
            $notes[] = "Good credit score";
        } elseif ($application['credit_score'] >= 650) {
            $score += 20;
            $notes[] = "Fair credit score";
        } else {
            $score += 5;
            $notes[] = "Poor credit score";
        }
        
        // Debt-to-income ratio
        $dti = ($application['monthly_expenses'] / $application['monthly_income']) * 100;
        if ($dti <= 30) {
            $score += 25;
            $notes[] = "Low debt-to-income ratio";
        } elseif ($dti <= 40) {
            $score += 15;
            $notes[] = "Moderate debt-to-income ratio";
        } else {
            $score += 5;
            $notes[] = "High debt-to-income ratio";
        }
        
        // Income stability
        if ($application['employment_status'] === 'full_time') {
            $score += 20;
            $notes[] = "Full-time employment";
        } elseif ($application['employment_status'] === 'part_time') {
            $score += 10;
            $notes[] = "Part-time employment";
        }
        
        // Loan amount vs income
        $income_ratio = ($application['loan_amount'] / $application['annual_income']) * 100;
        if ($income_ratio <= 20) {
            $score += 15;
            $notes[] = "Conservative loan amount";
        } elseif ($income_ratio <= 40) {
            $score += 10;
            $notes[] = "Moderate loan amount";
        } else {
            $score += 0;
            $notes[] = "High loan amount relative to income";
        }
        
        // Decision based on score
        if ($score >= 80) {
            $status = 'approved';
            $interest_rate = 8.5 + (100 - $score) * 0.1;
        } elseif ($score >= 60) {
            $status = 'under_review';
            $interest_rate = 12.5;
        } else {
            $status = 'rejected';
            $interest_rate = null;
        }
        
        return [
            'status' => $status,
            'interest_rate' => $interest_rate,
            'loan_term' => 36,
            'notes' => 'Automated Decision (Score: ' . $score . '): ' . implode(', ', $notes),
            'conditions' => $status === 'approved' ? 'Subject to final verification' : ''
        ];
    }
    
    /**
     * Update application status
     */
    private static function updateApplicationStatus($application_id, $status, $notes) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                UPDATE loan_applications 
                SET application_status = ?, 
                    admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n\n', ?),
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            return $stmt->execute([$status, $notes, $application_id]);
            
        } catch (Exception $e) {
            error_log("Update application status error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send application decision email
     */
    private static function sendApplicationDecisionEmail($application, $decision) {
        $template_name = $decision['status'] === 'approved' ? 'loan_approved' : 
                        ($decision['status'] === 'rejected' ? 'loan_rejected' : 'loan_under_review');
        
        $variables = [
            'first_name' => $application['first_name'],
            'last_name' => $application['last_name'],
            'reference_number' => $application['reference_number'],
            'loan_amount' => number_format($application['loan_amount']),
            'interest_rate' => $decision['interest_rate'] ? number_format($decision['interest_rate'], 2) . '%' : 'N/A',
            'loan_term' => $decision['loan_term'] ? $decision['loan_term'] . ' months' : 'N/A',
            'decision_notes' => $decision['notes']
        ];
        
        sendTemplatedEmail($application['email'], $template_name, $variables);
    }
    
    /**
     * Auto-generate content using AI
     */
    public static function autoGenerateContent($type, $parameters = []) {
        if (!self::$enabled || !self::$auto_generate_content || empty(self::$openai_api_key)) {
            return false;
        }
        
        $prompts = [
            'blog_post' => self::generateBlogPostPrompt($parameters),
            'email_template' => self::generateEmailTemplatePrompt($parameters),
            'faq_answer' => self::generateFAQPrompt($parameters),
            'product_description' => self::generateProductDescriptionPrompt($parameters),
            'social_media' => self::generateSocialMediaPrompt($parameters)
        ];
        
        if (!isset($prompts[$type])) {
            return false;
        }
        
        $response = self::callOpenAI([
            ['role' => 'system', 'content' => 'You are a professional content writer for a financial services company. Create engaging, accurate, and compliant content.'],
            ['role' => 'user', 'content' => $prompts[$type]]
        ]);
        
        if ($response['success']) {
            // Log content generation
            logAudit('ai_content_generated', 'content', null, null, ['type' => $type, 'parameters' => $parameters]);
            
            return [
                'success' => true,
                'content' => $response['content'],
                'type' => $type
            ];
        }
        
        return false;
    }
    
    /**
     * Generate blog post prompt
     */
    private static function generateBlogPostPrompt($parameters) {
        $topic = $parameters['topic'] ?? 'personal loans';
        $keywords = $parameters['keywords'] ?? [];
        $length = $parameters['length'] ?? 1000;
        
        return "Write a comprehensive blog post about '{$topic}' for a personal loan company website. 

Requirements:
- Length: approximately {$length} words
- Include these keywords naturally: " . implode(', ', $keywords) . "
- Write in a helpful, professional tone
- Include actionable advice
- Add a compelling introduction and conclusion
- Use subheadings to organize content
- Ensure content is SEO-friendly
- Include a call-to-action at the end

The blog post should be informative and help potential customers understand the topic while positioning our loan services as a solution.";
    }
    
    /**
     * Generate email template prompt
     */
    private static function generateEmailTemplatePrompt($parameters) {
        $purpose = $parameters['purpose'] ?? 'welcome';
        $tone = $parameters['tone'] ?? 'professional';
        
        return "Create a professional email template for a personal loan company for the purpose: '{$purpose}'.

Requirements:
- Tone: {$tone}
- Include placeholder variables like {first_name}, {loan_amount}, etc.
- Professional subject line
- Clear, concise content
- Appropriate call-to-action
- Company branding elements
- Compliance-friendly language

The email should be engaging while maintaining professionalism appropriate for financial services.";
    }
    
    /**
     * Generate FAQ prompt
     */
    private static function generateFAQPrompt($parameters) {
        $question = $parameters['question'] ?? '';
        $category = $parameters['category'] ?? 'general';
        
        return "Provide a comprehensive answer to this frequently asked question for a personal loan company:

Question: '{$question}'
Category: {$category}

Requirements:
- Clear, accurate answer
- Professional tone
- Address common concerns
- Include relevant details
- Mention company benefits where appropriate
- Ensure compliance with financial regulations
- Keep answer concise but complete";
    }
    
    /**
     * Auto-respond to emails using AI
     */
    public static function autoRespondEmails() {
        if (!self::$enabled || !self::$auto_respond_emails) {
            return false;
        }
        
        try {
            // This would integrate with email system to process incoming emails
            $unprocessed_emails = self::getUnprocessedEmails();
            $responses_sent = 0;
            
            foreach ($unprocessed_emails as $email) {
                $response = self::generateEmailResponse($email);
                
                if ($response && $response['confidence'] > 0.8) {
                    self::sendAutoResponse($email, $response);
                    self::markEmailProcessed($email['id']);
                    $responses_sent++;
                } else {
                    // Forward to human agent for complex queries
                    self::forwardToAgent($email);
                }
            }
            
            return $responses_sent;
            
        } catch (Exception $e) {
            error_log("Auto-respond emails error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate automated email response
     */
    private static function generateEmailResponse($email) {
        $prompt = "Analyze this customer email and generate an appropriate response:

From: {$email['from_email']}
Subject: {$email['subject']}
Message: {$email['body']}

Generate a helpful, professional response that:
1. Addresses the customer's question/concern
2. Provides accurate information about our loan services
3. Includes next steps or call-to-action
4. Maintains a friendly, professional tone
5. Includes appropriate disclaimers if needed

Also provide a confidence score (0-1) for how certain you are this response is appropriate.

Format as JSON:
{
  \"response\": \"Email response here\",
  \"confidence\": 0.95,
  \"requires_human\": false,
  \"suggested_subject\": \"Re: Original Subject\"
}";
        
        $ai_response = self::callOpenAI([
            ['role' => 'system', 'content' => 'You are a customer service AI for a personal loan company. Provide helpful, accurate responses while following financial service regulations.'],
            ['role' => 'user', 'content' => $prompt]
        ]);
        
        if ($ai_response['success']) {
            try {
                return json_decode($ai_response['content'], true);
            } catch (Exception $e) {
                return null;
            }
        }
        
        return null;
    }
    
    /**
     * Automated lead scoring
     */
    public static function scoreLeads() {
        if (!self::$enabled) {
            return false;
        }
        
        try {
            $db = getDB();
            
            // Get unscored leads
            $stmt = $db->prepare("
                SELECT * FROM leads 
                WHERE ai_score IS NULL 
                ORDER BY created_at DESC 
                LIMIT 50
            ");
            $stmt->execute();
            $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $scored = 0;
            
            foreach ($leads as $lead) {
                $score = self::calculateLeadScore($lead);
                self::updateLeadScore($lead['id'], $score);
                $scored++;
            }
            
            return $scored;
            
        } catch (Exception $e) {
            error_log("Lead scoring error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calculate lead score using AI and traditional methods
     */
    private static function calculateLeadScore($lead) {
        $score = 0;
        
        // Basic scoring factors
        if (!empty($lead['email'])) $score += 10;
        if (!empty($lead['phone'])) $score += 15;
        if (!empty($lead['loan_amount'])) $score += 20;
        if (!empty($lead['income'])) $score += 25;
        
        // Engagement scoring
        if ($lead['page_views'] > 3) $score += 15;
        if ($lead['time_on_site'] > 300) $score += 10; // 5 minutes
        
        // Source scoring
        $source_scores = [
            'organic' => 20,
            'paid' => 15,
            'referral' => 25,
            'direct' => 10,
            'social' => 5
        ];
        
        $score += $source_scores[$lead['source']] ?? 0;
        
        // AI enhancement (if available)
        if (!empty(self::$openai_api_key)) {
            $ai_score = self::getAILeadScore($lead);
            if ($ai_score) {
                $score = ($score + $ai_score) / 2; // Average traditional and AI scores
            }
        }
        
        return min(100, max(0, $score)); // Ensure score is between 0-100
    }
    
    /**
     * Get AI-powered lead score
     */
    private static function getAILeadScore($lead) {
        $prompt = "Analyze this lead and provide a quality score from 0-100:

Lead Data:
- Email: " . (!empty($lead['email']) ? 'Provided' : 'Not provided') . "
- Phone: " . (!empty($lead['phone']) ? 'Provided' : 'Not provided') . "
- Requested Amount: $" . number_format($lead['loan_amount'] ?? 0) . "
- Stated Income: $" . number_format($lead['income'] ?? 0) . "
- Source: {$lead['source']}
- Page Views: {$lead['page_views']}
- Time on Site: {$lead['time_on_site']} seconds
- Form Completion: " . ($lead['form_completed'] ? 'Yes' : 'No') . "

Provide just a numeric score from 0-100 representing the lead quality.";
        
        $response = self::callOpenAI([
            ['role' => 'system', 'content' => 'You are a lead scoring expert for financial services. Analyze leads and provide quality scores.'],
            ['role' => 'user', 'content' => $prompt]
        ]);
        
        if ($response['success']) {
            $score = intval(trim($response['content']));
            return ($score >= 0 && $score <= 100) ? $score : null;
        }
        
        return null;
    }
    
    /**
     * Update lead score in database
     */
    private static function updateLeadScore($lead_id, $score) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                UPDATE leads 
                SET ai_score = ?, scored_at = NOW() 
                WHERE id = ?
            ");
            
            return $stmt->execute([$score, $lead_id]);
            
        } catch (Exception $e) {
            error_log("Update lead score error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Call OpenAI API
     */
    private static function callOpenAI($messages, $model = 'gpt-3.5-turbo', $max_tokens = 1000) {
        if (empty(self::$openai_api_key)) {
            return ['success' => false, 'error' => 'API key not configured'];
        }
        
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $data = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $max_tokens,
            'temperature' => 0.7
        ];
        
        $headers = [
            'Authorization: Bearer ' . self::$openai_api_key,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            return ['success' => false, 'error' => 'API request failed'];
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !isset($result['choices'][0]['message']['content'])) {
            return ['success' => false, 'error' => 'Invalid API response'];
        }
        
        return [
            'success' => true,
            'content' => trim($result['choices'][0]['message']['content'])
        ];
    }
    
    /**
     * Generate automation report
     */
    public static function generateAutomationReport($days = 30) {
        try {
            $db = getDB();
            
            // Get AI automation statistics
            $stmt = $db->prepare("
                SELECT 
                    COUNT(CASE WHEN table_name = 'loan_applications' AND action = 'ai_application_processed' THEN 1 END) as applications_processed,
                    COUNT(CASE WHEN table_name = 'content' AND action = 'ai_content_generated' THEN 1 END) as content_generated,
                    COUNT(CASE WHEN action LIKE 'ai_%' THEN 1 END) as total_ai_actions
                FROM audit_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get lead scoring statistics
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_leads,
                    AVG(ai_score) as avg_score,
                    COUNT(CASE WHEN ai_score >= 80 THEN 1 END) as high_quality_leads
                FROM leads 
                WHERE scored_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $lead_stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'period_days' => $days,
                'applications_processed' => $stats['applications_processed'] ?? 0,
                'content_generated' => $stats['content_generated'] ?? 0,
                'total_ai_actions' => $stats['total_ai_actions'] ?? 0,
                'leads_scored' => $lead_stats['total_leads'] ?? 0,
                'average_lead_score' => round($lead_stats['avg_score'] ?? 0, 2),
                'high_quality_leads' => $lead_stats['high_quality_leads'] ?? 0,
                'efficiency_gain' => self::calculateEfficiencyGain($stats, $days)
            ];
            
        } catch (Exception $e) {
            error_log("Automation report error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Calculate efficiency gain from automation
     */
    private static function calculateEfficiencyGain($stats, $days) {
        $applications_processed = $stats['applications_processed'] ?? 0;
        $content_generated = $stats['content_generated'] ?? 0;
        
        // Estimate time saved (in hours)
        $time_saved = ($applications_processed * 0.5) + ($content_generated * 2); // 30 min per app, 2 hours per content
        
        // Calculate daily average
        $daily_average = $time_saved / $days;
        
        return [
            'total_hours_saved' => round($time_saved, 1),
            'daily_average_hours' => round($daily_average, 1),
            'estimated_cost_savings' => round($time_saved * 25, 2) // $25/hour
        ];
    }
    
    /**
     * Placeholder methods for email processing
     */
    private static function getUnprocessedEmails() {
        // This would integrate with email system
        return [];
    }
    
    private static function sendAutoResponse($email, $response) {
        // Send automated response
        return true;
    }
    
    private static function markEmailProcessed($email_id) {
        // Mark email as processed
        return true;
    }
    
    private static function forwardToAgent($email) {
        // Forward complex email to human agent
        return true;
    }
    
    /**
     * Check if system is enabled
     */
    public static function isEnabled() {
        return self::$enabled;
    }
    
    /**
     * Enable/disable automation
     */
    public static function setEnabled($enabled) {
        self::$enabled = $enabled;
        updateSystemSetting('ai_automation_enabled', $enabled ? '1' : '0');
    }
}

// Initialize AI automation system
AIAutomationManager::init();

/**
 * Generate AI-powered response based on contact form comment
 * @param string $comment The user's comment/question
 * @param string $name The user's name
 * @return string Generated response
 */
function generateAIResponse($comment, $name) {
    $comment = strtolower(trim($comment));
    
    // Loan-related keywords and responses
    $loanKeywords = ['loan', 'borrow', 'credit', 'finance', 'money', 'cash', 'fund'];
    $applicationKeywords = ['apply', 'application', 'process', 'how to', 'steps'];
    $rateKeywords = ['rate', 'interest', 'apr', 'percentage', 'cost'];
    $requirementKeywords = ['requirement', 'qualify', 'eligible', 'need', 'document'];
    $timeKeywords = ['time', 'fast', 'quick', 'when', 'how long', 'approval'];
    
    // Check for specific topics
    if (containsKeywords($comment, $loanKeywords)) {
        if (containsKeywords($comment, $applicationKeywords)) {
            return "Hi $name,\n\nThank you for your interest in LoanFlow! Our application process is simple and straightforward. You can apply online in just 2 easy steps, and most applications are processed within 24-48 hours.\n\nTo get started, simply click the 'APPLY' button on our website. You'll need basic information like your income, employment details, and the loan amount you're seeking.\n\nOur team will review your application and get back to you quickly with a decision.\n\nBest regards,\nLoanFlow Team";
        }
        
        if (containsKeywords($comment, $rateKeywords)) {
            return "Hi $name,\n\nThank you for contacting LoanFlow! Our interest rates are competitive and vary based on several factors including your credit score, loan amount, and repayment term.\n\nTypically, our rates range from 5.99% to 35.99% APR. The exact rate you qualify for will be determined during the application process based on your financial profile.\n\nWe encourage you to apply to see what rate you pre-qualify for - there's no obligation and it won't affect your credit score for the initial quote.\n\nBest regards,\nLoanFlow Team";
        }
        
        if (containsKeywords($comment, $requirementKeywords)) {
            return "Hi $name,\n\nThank you for your inquiry about LoanFlow's requirements! To qualify for a loan with us, you typically need:\n\n• Be at least 18 years old\n• Have a steady source of income\n• Provide valid identification\n• Have an active bank account\n• Meet our minimum credit requirements\n\nRequired documents usually include:\n• Government-issued ID\n• Proof of income (pay stubs, bank statements)\n• Employment verification\n\nOur application process will guide you through exactly what's needed for your specific situation.\n\nBest regards,\nLoanFlow Team";
        }
        
        if (containsKeywords($comment, $timeKeywords)) {
            return "Hi $name,\n\nGreat question! LoanFlow is designed for speed and efficiency:\n\n• Application: Takes just 5-10 minutes to complete\n• Initial Review: Within 1 hour during business hours\n• Approval Decision: Typically 24-48 hours\n• Funding: Same day or next business day after approval\n\nFor urgent needs, we also offer expedited processing. Our goal is to get you the funds you need as quickly as possible while maintaining responsible lending practices.\n\nReady to get started? Click 'APPLY' on our website!\n\nBest regards,\nLoanFlow Team";
        }
        
        // General loan inquiry
        return "Hi $name,\n\nThank you for contacting LoanFlow! We're here to help with all your lending needs.\n\nWe offer personal loans, business loans, and auto loans with competitive rates and flexible terms. Our streamlined application process makes it easy to get the funding you need quickly.\n\nKey benefits:\n• Fast approval process\n• Competitive rates\n• Flexible repayment terms\n• No hidden fees\n• Excellent customer service\n\nWould you like to start your application? Visit our website and click 'APPLY' to get started.\n\nBest regards,\nLoanFlow Team";
    }
    
    // Contact/support related
    if (containsKeywords($comment, ['contact', 'call', 'phone', 'speak', 'talk'])) {
        return "Hi $name,\n\nThank you for reaching out to LoanFlow! We're here to help.\n\nYou can contact us through several ways:\n• Phone: [Phone number from admin settings]\n• Email: [Email from admin settings]\n• Online chat: Available on our website\n• Contact form: Right here on our site\n\nOur customer service team is available Monday-Friday, 9 AM - 6 PM EST. We typically respond to emails within 2-4 hours during business hours.\n\nHow can we assist you today?\n\nBest regards,\nLoanFlow Team";
    }
    
    // Generic/fallback response
    return "Hi $name,\n\nThank you for contacting LoanFlow! We appreciate you taking the time to reach out to us.\n\nWe've received your message and our team will review it carefully. A member of our customer service team will get back to you within 24 hours with a personalized response.\n\nIn the meantime, feel free to explore our website to learn more about our loan products and services. If you're ready to apply, you can start your application anytime by clicking the 'APPLY' button.\n\nWe look forward to helping you achieve your financial goals!\n\nBest regards,\nLoanFlow Team";
}

/**
 * Check if comment contains any of the specified keywords
 * @param string $comment The comment to check
 * @param array $keywords Array of keywords to search for
 * @return bool True if any keyword is found
 */
function containsKeywords($comment, $keywords) {
    foreach ($keywords as $keyword) {
        if (strpos($comment, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Send automated email response to contact form submission
 * @param string $email Recipient email
 * @param string $name Recipient name
 * @param string $comment Original comment
 * @param string $response Generated AI response
 * @return bool Success status
 */
function sendContactAutoResponse($email, $name, $comment, $response) {
    try {
        $subject = "Thank you for contacting LoanFlow - We're here to help!";
        
        $htmlBody = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
                .highlight { background-color: #e7f3ff; padding: 15px; border-left: 4px solid #007bff; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>LoanFlow - Your Financial Partner</h2>
            </div>
            <div class='content'>
                <div class='highlight'>
                    <strong>Your Message:</strong><br>
                    " . htmlspecialchars($comment) . "
                </div>
                
                <div style='white-space: pre-line;'>" . htmlspecialchars($response) . "</div>
                
                <hr style='margin: 30px 0;'>
                
                <p><strong>Need immediate assistance?</strong></p>
                <p>• Visit our website: <a href='#'>www.loanflow.com</a></p>
                <p>• Call us: [Phone from admin settings]</p>
                <p>• Email: [Email from admin settings]</p>
                
                <p style='margin-top: 30px;'><a href='#' style='background-color: #007bff; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px;'>Apply Now</a></p>
            </div>
            <div class='footer'>
                <p>This is an automated response. If you need immediate assistance, please call us directly.</p>
                <p>&copy; 2024 LoanFlow. All rights reserved.</p>
            </div>
        </body>
        </html>
        ";
        
        // Use the existing email function
        return sendEmail($email, $subject, $htmlBody, $name);
        
    } catch (Exception $e) {
        error_log('Auto-response email error: ' . $e->getMessage());
        return false;
    }
}
