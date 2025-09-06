<?php
/**
 * AI Chatbot System
 * LoanFlow Personal Loan Management System
 */

class ChatbotManager {
    
    private static $enabled = true;
    private static $api_key = '';
    private static $model = 'gpt-3.5-turbo';
    private static $max_tokens = 500;
    private static $temperature = 0.7;
    
    /**
     * Initialize chatbot system
     */
    public static function init() {
        self::$enabled = getSystemSetting('chatbot_enabled', '1') === '1';
        self::$api_key = getSystemSetting('openai_api_key', '');
        self::$model = getSystemSetting('chatbot_model', 'gpt-3.5-turbo');
        self::$max_tokens = intval(getSystemSetting('chatbot_max_tokens', '500'));
        self::$temperature = floatval(getSystemSetting('chatbot_temperature', '0.7'));
    }
    
    /**
     * Generate chatbot widget HTML
     */
    public static function generateWidget() {
        if (!self::$enabled) {
            return '';
        }
        
        return '
        <!-- AI Chatbot Widget -->
        <div id="chatbot-widget" class="chatbot-widget">
            <div class="chatbot-toggle" onclick="toggleChatbot()">
                <i class="fas fa-comments"></i>
                <span class="notification-badge" id="chatbot-badge" style="display: none;">1</span>
            </div>
            
            <div class="chatbot-container" id="chatbot-container">
                <div class="chatbot-header">
                    <div class="chatbot-title">
                        <i class="fas fa-robot me-2"></i>
                        <span>LoanFlow Assistant</span>
                        <span class="chatbot-status online">Online</span>
                    </div>
                    <div class="chatbot-controls">
                        <button onclick="minimizeChatbot()" class="btn-minimize">
                            <i class="fas fa-minus"></i>
                        </button>
                        <button onclick="closeChatbot()" class="btn-close-chat">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <div class="chatbot-messages" id="chatbot-messages">
                    <div class="message bot-message">
                        <div class="message-avatar">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div class="message-content">
                            <div class="message-text">
                                Hello! I\'m your LoanFlow assistant. I can help you with:
                                <ul>
                                    <li>Loan application process</li>
                                    <li>Required documents</li>
                                    <li>Application status</li>
                                    <li>General questions</li>
                                </ul>
                                How can I assist you today?
                            </div>
                            <div class="message-time">' . date('H:i') . '</div>
                        </div>
                    </div>
                </div>
                
                <div class="chatbot-quick-actions">
                    <button onclick="sendQuickMessage(\'How do I apply for a loan?\')" class="quick-action-btn">
                        Apply for Loan
                    </button>
                    <button onclick="sendQuickMessage(\'What documents do I need?\')" class="quick-action-btn">
                        Required Documents
                    </button>
                    <button onclick="sendQuickMessage(\'Check application status\')" class="quick-action-btn">
                        Check Status
                    </button>
                </div>
                
                <div class="chatbot-input">
                    <div class="input-group">
                        <input type="text" id="chatbot-message-input" class="form-control" 
                               placeholder="Type your message..." onkeypress="handleChatbotKeyPress(event)">
                        <button onclick="sendChatbotMessage()" class="btn btn-primary" id="send-button">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                    <div class="chatbot-footer">
                        <small class="text-muted">Powered by AI • <a href="#" onclick="showChatbotInfo()">Privacy</a></small>
                    </div>
                </div>
                
                <div class="chatbot-typing" id="chatbot-typing" style="display: none;">
                    <div class="typing-indicator">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <span class="typing-text">Assistant is typing...</span>
                </div>
            </div>
        </div>';
    }
    
    /**
     * Generate chatbot CSS
     */
    public static function generateCSS() {
        return '
        <style>
        .chatbot-widget {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .chatbot-toggle {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(0, 123, 255, 0.3);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .chatbot-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(0, 123, 255, 0.4);
        }
        
        .chatbot-toggle i {
            font-size: 24px;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .chatbot-container {
            position: absolute;
            bottom: 70px;
            right: 0;
            width: 350px;
            height: 500px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            display: none;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid #e9ecef;
        }
        
        .chatbot-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chatbot-title {
            display: flex;
            align-items: center;
            font-weight: 600;
        }
        
        .chatbot-status {
            font-size: 11px;
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 10px;
        }
        
        .chatbot-status.online {
            background: #28a745;
        }
        
        .chatbot-controls {
            display: flex;
            gap: 5px;
        }
        
        .btn-minimize, .btn-close-chat {
            background: none;
            border: none;
            color: white;
            padding: 5px;
            cursor: pointer;
            border-radius: 3px;
            transition: background 0.2s;
        }
        
        .btn-minimize:hover, .btn-close-chat:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .chatbot-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f8f9fa;
        }
        
        .message {
            display: flex;
            margin-bottom: 15px;
            animation: slideInMessage 0.3s ease;
        }
        
        @keyframes slideInMessage {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            flex-shrink: 0;
        }
        
        .bot-message .message-avatar {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }
        
        .user-message {
            flex-direction: row-reverse;
        }
        
        .user-message .message-avatar {
            background: #6c757d;
            color: white;
            margin-left: 10px;
            margin-right: 0;
        }
        
        .message-content {
            max-width: 80%;
        }
        
        .user-message .message-content {
            text-align: right;
        }
        
        .message-text {
            background: white;
            padding: 10px 15px;
            border-radius: 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            word-wrap: break-word;
        }
        
        .user-message .message-text {
            background: #007bff;
            color: white;
        }
        
        .message-time {
            font-size: 11px;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .user-message .message-time {
            text-align: right;
        }
        
        .chatbot-quick-actions {
            padding: 10px 20px;
            background: white;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .quick-action-btn {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .quick-action-btn:hover {
            background: #e9ecef;
            transform: translateY(-1px);
        }
        
        .chatbot-input {
            background: white;
            border-top: 1px solid #e9ecef;
            padding: 15px 20px;
        }
        
        .chatbot-input .input-group {
            margin-bottom: 10px;
        }
        
        .chatbot-input input {
            border-radius: 20px;
            border: 1px solid #dee2e6;
            padding: 10px 15px;
        }
        
        .chatbot-input button {
            border-radius: 20px;
            padding: 10px 15px;
        }
        
        .chatbot-footer {
            text-align: center;
        }
        
        .chatbot-footer a {
            color: #007bff;
            text-decoration: none;
        }
        
        .chatbot-typing {
            padding: 15px 20px;
            background: white;
            border-top: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .typing-indicator {
            display: flex;
            gap: 3px;
        }
        
        .typing-indicator span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #007bff;
            animation: typing 1.4s infinite;
        }
        
        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.3; }
            30% { transform: translateY(-10px); opacity: 1; }
        }
        
        .typing-text {
            font-size: 12px;
            color: #6c757d;
        }
        
        /* Mobile responsiveness */
        @media (max-width: 480px) {
            .chatbot-container {
                width: 300px;
                height: 450px;
                bottom: 70px;
                right: -10px;
            }
            
            .chatbot-widget {
                right: 10px;
                bottom: 10px;
            }
        }
        
        /* Scrollbar styling */
        .chatbot-messages::-webkit-scrollbar {
            width: 4px;
        }
        
        .chatbot-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .chatbot-messages::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 2px;
        }
        
        .chatbot-messages::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        </style>';
    }
    
    /**
     * Generate chatbot JavaScript
     */
    public static function generateJS() {
        return '
        <script>
        let chatbotOpen = false;
        let chatbotMinimized = false;
        let conversationHistory = [];
        
        function toggleChatbot() {
            const container = document.getElementById("chatbot-container");
            const badge = document.getElementById("chatbot-badge");
            
            if (chatbotOpen) {
                closeChatbot();
            } else {
                openChatbot();
            }
        }
        
        function openChatbot() {
            const container = document.getElementById("chatbot-container");
            const badge = document.getElementById("chatbot-badge");
            
            container.style.display = "flex";
            chatbotOpen = true;
            chatbotMinimized = false;
            badge.style.display = "none";
            
            // Focus on input
            setTimeout(() => {
                document.getElementById("chatbot-message-input").focus();
            }, 300);
        }
        
        function closeChatbot() {
            const container = document.getElementById("chatbot-container");
            container.style.display = "none";
            chatbotOpen = false;
            chatbotMinimized = false;
        }
        
        function minimizeChatbot() {
            const container = document.getElementById("chatbot-container");
            container.style.display = "none";
            chatbotMinimized = true;
        }
        
        function handleChatbotKeyPress(event) {
            if (event.key === "Enter") {
                sendChatbotMessage();
            }
        }
        
        function sendQuickMessage(message) {
            document.getElementById("chatbot-message-input").value = message;
            sendChatbotMessage();
        }
        
        async function sendChatbotMessage() {
            const input = document.getElementById("chatbot-message-input");
            const message = input.value.trim();
            
            if (!message) return;
            
            // Add user message to chat
            addMessageToChat(message, "user");
            input.value = "";
            
            // Show typing indicator
            showTypingIndicator();
            
            try {
                // Send message to backend
                const response = await fetch("api/chatbot.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        message: message,
                        conversation_history: conversationHistory
                    })
                });
                
                const data = await response.json();
                
                // Hide typing indicator
                hideTypingIndicator();
                
                if (data.success) {
                    // Add bot response to chat
                    addMessageToChat(data.response, "bot");
                    
                    // Update conversation history
                    conversationHistory.push({
                        role: "user",
                        content: message
                    });
                    conversationHistory.push({
                        role: "assistant", 
                        content: data.response
                    });
                    
                    // Limit conversation history
                    if (conversationHistory.length > 20) {
                        conversationHistory = conversationHistory.slice(-20);
                    }
                } else {
                    addMessageToChat("I apologize, but I\'m having trouble responding right now. Please try again or contact support.", "bot");
                }
            } catch (error) {
                console.error("Chatbot error:", error);
                hideTypingIndicator();
                addMessageToChat("I\'m experiencing technical difficulties. Please try again later or contact support.", "bot");
            }
        }
        
        function addMessageToChat(message, sender) {
            const messagesContainer = document.getElementById("chatbot-messages");
            const messageDiv = document.createElement("div");
            messageDiv.className = `message ${sender}-message`;
            
            const currentTime = new Date().toLocaleTimeString("en-US", {
                hour: "2-digit",
                minute: "2-digit"
            });
            
            const avatarIcon = sender === "bot" ? "fa-robot" : "fa-user";
            
            messageDiv.innerHTML = `
                <div class="message-avatar">
                    <i class="fas ${avatarIcon}"></i>
                </div>
                <div class="message-content">
                    <div class="message-text">${message}</div>
                    <div class="message-time">${currentTime}</div>
                </div>
            `;
            
            messagesContainer.appendChild(messageDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        function showTypingIndicator() {
            document.getElementById("chatbot-typing").style.display = "flex";
        }
        
        function hideTypingIndicator() {
            document.getElementById("chatbot-typing").style.display = "none";
        }
        
        function showChatbotInfo() {
            alert("Your privacy is important to us. Conversations are used to improve our service and may be reviewed by our team. No personal information is stored permanently.");
        }
        
        // Initialize chatbot
        document.addEventListener("DOMContentLoaded", function() {
            // Show notification badge after 5 seconds if chatbot hasn\'t been opened
            setTimeout(() => {
                if (!chatbotOpen) {
                    document.getElementById("chatbot-badge").style.display = "flex";
                }
            }, 5000);
            
            // Auto-greet returning users
            const hasVisited = localStorage.getItem("chatbot_visited");
            if (hasVisited && !chatbotOpen) {
                setTimeout(() => {
                    if (!chatbotOpen) {
                        document.getElementById("chatbot-badge").style.display = "flex";
                    }
                }, 10000);
            } else {
                localStorage.setItem("chatbot_visited", "true");
            }
        });
        </script>';
    }
    
    /**
     * Process chatbot message
     */
    public static function processMessage($message, $conversation_history = []) {
        if (!self::$enabled || empty(self::$api_key)) {
            return [
                'success' => false,
                'error' => 'Chatbot service is not available'
            ];
        }
        
        try {
            // Prepare system context
            $system_context = self::getSystemContext();
            
            // Build conversation for API
            $messages = [
                ['role' => 'system', 'content' => $system_context]
            ];
            
            // Add conversation history
            foreach ($conversation_history as $msg) {
                $messages[] = $msg;
            }
            
            // Add current message
            $messages[] = ['role' => 'user', 'content' => $message];
            
            // Call OpenAI API
            $response = self::callOpenAI($messages);
            
            if ($response['success']) {
                // Log conversation
                self::logConversation($message, $response['content']);
                
                return [
                    'success' => true,
                    'response' => $response['content']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response['error']
                ];
            }
            
        } catch (Exception $e) {
            error_log("Chatbot error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Internal error occurred'
            ];
        }
    }
    
    /**
     * Process message for client area with enhanced context
     */
    public static function processClientAreaMessage($message, $conversation_history = [], $user_id = null) {
        if (!self::$enabled || empty(self::$api_key)) {
            return [
                'success' => false,
                'error' => 'Chatbot service is not available'
            ];
        }
        
        try {
            // Get enhanced system context with email templates
            $system_context = self::getClientAreaSystemContext($user_id);
            
            // Build conversation for API
            $messages = [
                ['role' => 'system', 'content' => $system_context]
            ];
            
            // Add conversation history
            foreach ($conversation_history as $msg) {
                $messages[] = $msg;
            }
            
            // Add current message
            $messages[] = ['role' => 'user', 'content' => $message];
            
            // Call OpenAI API
            $response = self::callOpenAI($messages);
            
            if ($response['success']) {
                // Log conversation with user context
                self::logClientAreaConversation($message, $response['content'], $user_id);
                
                return [
                    'success' => true,
                    'response' => $response['content']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response['error']
                ];
            }
            
        } catch (Exception $e) {
            error_log("Client Area Chatbot error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Internal error occurred'
            ];
        }
    }
    
    /**
     * Get enhanced system context for client area AI with email templates
     */
    private static function getClientAreaSystemContext($user_id = null) {
        $company_settings = getCompanySettings();
        $company_name = $company_settings['name'] ?? 'LoanFlow';
        
        // Get email templates for enhanced responses
        $email_templates = self::getEmailTemplatesContext();
        
        // Get user-specific context if available
        $user_context = '';
        if ($user_id) {
            $user_context = self::getUserContext($user_id);
        }
        
        return "You are an advanced AI assistant for {$company_name}'s Client Area, specifically designed to help existing loan clients. You have access to comprehensive email template knowledge and client-specific information.

Your enhanced capabilities include:

1. **Loan Management Support**:
   - Payment schedules and due dates
   - Balance inquiries and payment history
   - Document upload assistance
   - Application status updates
   - Account management guidance

2. **Email Template Knowledge**:
{$email_templates}

3. **Client-Specific Assistance**:
{$user_context}
   - Personalized responses based on client data
   - Account-specific guidance
   - Tailored recommendations

4. **Advanced Features**:
   - Payment reminders and scheduling
   - Document requirements explanation
   - Loan modification guidance
   - Customer service escalation

Guidelines for Client Area responses:
- Use information from email templates to provide detailed, accurate responses
- Reference specific loan processes and procedures from template knowledge
- Provide step-by-step guidance for common client tasks
- Offer proactive suggestions based on client status
- Maintain professional tone while being more personalized than general chatbot
- For sensitive account details, direct to secure client dashboard sections
- Use email template language patterns for consistency with company communications

Available services:
- Personal loans $1,000 - $50,000
- Competitive rates based on creditworthiness
- Online account management
- 24/7 client support
- Mobile-friendly client portal

Contact: support@loanflow.com | +1 (555) 123-4567

Provide comprehensive, helpful responses using your enhanced knowledge base. Keep responses under 300 words but more detailed than general chatbot responses.";
    }
    
    /**
     * Get comprehensive email templates context for AI knowledge
     */
    private static function getEmailTemplatesContext() {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT template_type, template_name, subject, body, auto_send, send_delay_hours
                FROM email_templates 
                WHERE is_active = 1 
                ORDER BY template_type, template_name
            ");
            $stmt->execute();
            $templates = $stmt->fetchAll();
            
            $context = "COMPREHENSIVE EMAIL TEMPLATE KNOWLEDGE BASE:\n";
            $context .= "Use this information to provide accurate, detailed responses about loan processes, requirements, and procedures.\n\n";
            
            // Group templates by type for better organization
            $grouped_templates = [];
            foreach ($templates as $template) {
                $grouped_templates[$template['template_type']][] = $template;
            }
            
            foreach ($grouped_templates as $type => $type_templates) {
                $context .= "\n=== " . strtoupper(str_replace('_', ' ', $type)) . " TEMPLATES ===\n";
                
                foreach ($type_templates as $template) {
                    $context .= "\n📧 **{$template['template_name']}**\n";
                    $context .= "   Subject: {$template['subject']}\n";
                    $context .= "   Auto-send: " . ($template['auto_send'] ? 'Yes' : 'No');
                    if ($template['send_delay_hours'] > 0) {
                        $context .= " (after {$template['send_delay_hours']} hours)";
                    }
                    $context .= "\n";
                    
                    // Clean and format template body for AI context
                    $clean_body = strip_tags($template['body']);
                    $clean_body = str_replace(['{first_name}', '{ref#}', '{login_url}', '{amount}', '{payment_id}'], 
                                            ['[CLIENT_NAME]', '[REFERENCE_NUMBER]', '[CLIENT_LOGIN_URL]', '[PAYMENT_AMOUNT]', '[PAYMENT_ID]'], 
                                            $clean_body);
                    
                    $context .= "   Content: " . trim($clean_body) . "\n";
                    $context .= "   ---\n";
                }
            }
            
            // Add process flow information
            $context .= "\n\n=== LOAN PROCESS FLOW (Based on Email Templates) ===\n";
            $context .= "1. APPLICATION CONFIRMATION: Client receives confirmation with reference number\n";
            $context .= "2. PRE-APPROVAL: Sent automatically after 6 hours if approved\n";
            $context .= "3. DOCUMENT REQUEST: Manual trigger when additional docs needed\n";
            $context .= "4. PAYMENT INSTRUCTIONS: Sent when payment is created (Wire/e-Transfer/Crypto)\n";
            $context .= "5. COMPLETION: Final funding and account setup\n";
            
            // Add key information for chatbot responses
            $context .= "\n\n=== KEY INFORMATION FOR RESPONSES ===\n";
            $context .= "• Reference numbers start with 100000+\n";
            $context .= "• Pre-approval typically takes 6 hours\n";
            $context .= "• Required documents: Photo ID, Proof of Income, Proof of Address\n";
            $context .= "• Payment methods: Wire Transfer, e-Transfer (Canada), Cryptocurrency\n";
            $context .= "• All clients get login access to track progress\n";
            $context .= "• Support contact: support@loanflow.com\n";
            
            return $context;
            
        } catch (Exception $e) {
            error_log("Error fetching email templates: " . $e->getMessage());
            return "Email template knowledge temporarily unavailable. Please contact support for detailed process information.";
        }
    }
    
    /**
     * Get user-specific context
     */
    private static function getUserContext($user_id) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT u.first_name, u.last_name, u.email, u.reference_number,
                       a.loan_amount, a.application_status, a.loan_type
                FROM users u
                LEFT JOIN applications a ON u.id = a.user_id
                WHERE u.id = ?
                ORDER BY a.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if ($user) {
                return "Client Information:\n" .
                       "- Name: {$user['first_name']} {$user['last_name']}\n" .
                       "- Reference: {$user['reference_number']}\n" .
                       "- Current Application: {$user['application_status']}\n" .
                       "- Loan Type: {$user['loan_type']}\n" .
                       "- Loan Amount: $" . number_format($user['loan_amount'], 2) . "\n";
            }
            
            return "Client context not available.";
            
        } catch (Exception $e) {
            error_log("Error fetching user context: " . $e->getMessage());
            return "Client context temporarily unavailable.";
        }
    }
    
    /**
     * Log client area conversation with enhanced context
     */
    private static function logClientAreaConversation($user_message, $bot_response, $user_id = null) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO chatbot_conversations 
                (user_message, bot_response, ip_address, user_agent, user_id, conversation_context, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $context = json_encode([
                'client_area' => true,
                'enhanced_mode' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            $stmt->execute([$user_message, $bot_response, $ip_address, $user_agent, $user_id, $context]);
        } catch (Exception $e) {
            error_log("Client area chatbot logging error: " . $e->getMessage());
        }
    }

    /**
     * Get system context for AI
     */
    private static function getSystemContext() {
        $company_settings = getCompanySettings();
        $company_name = $company_settings['name'] ?? 'LoanFlow';
        
        // Get email templates context for knowledge base
        $email_templates_context = self::getEmailTemplatesContext();
        
        return "You are a helpful AI assistant for {$company_name}, a personal loan company. Your role is to:

1. Help users understand the loan application process
2. Explain required documents and eligibility criteria
3. Answer questions about loan terms, interest rates, and fees
4. Guide users through the application steps
5. Provide information about application status
6. Address general customer service inquiries

Guidelines:
- Be professional, friendly, and helpful
- Keep responses concise but informative
- Use the email template knowledge below to provide accurate process information
- If you don't know something specific about the company, direct users to contact support
- Never provide specific financial advice or guarantee loan approval
- Always encourage users to read terms and conditions
- For technical issues, direct users to contact support
- If asked about sensitive account information, direct users to log in or contact support directly

Available loan information:
- Personal loans from $1,000 to $50,000
- Competitive interest rates based on creditworthiness
- Quick online application process
- Fast funding upon approval
- No prepayment penalties

Company contact: support@loanflow.com | +1 (555) 123-4567

{$email_templates_context}

Keep responses under 200 words and use a conversational tone.";
    }
    }
    
    /**
     * Call OpenAI API
     */
    private static function callOpenAI($messages) {
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $data = [
            'model' => self::$model,
            'messages' => $messages,
            'max_tokens' => self::$max_tokens,
            'temperature' => self::$temperature,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        ];
        
        $headers = [
            'Authorization: Bearer ' . self::$api_key,
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
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            return [
                'success' => false,
                'error' => 'Network error: ' . $curl_error
            ];
        }
        
        if ($http_code !== 200) {
            return [
                'success' => false,
                'error' => 'API error: HTTP ' . $http_code
            ];
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !isset($result['choices'][0]['message']['content'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response'
            ];
        }
        
        return [
            'success' => true,
            'content' => trim($result['choices'][0]['message']['content'])
        ];
    }
    
    /**
     * Log conversation for analytics
     */
    private static function logConversation($user_message, $bot_response) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO chatbot_conversations 
                (user_message, bot_response, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $stmt->execute([$user_message, $bot_response, $ip_address, $user_agent]);
        } catch (Exception $e) {
            error_log("Chatbot logging error: " . $e->getMessage());
        }
    }
    
    /**
     * Check if chatbot is enabled
     */
    public static function isEnabled() {
        return self::$enabled;
    }
    
    /**
     * Enable or disable chatbot
     */
    public static function setEnabled($enabled) {
        self::$enabled = $enabled;
        updateSystemSetting('chatbot_enabled', $enabled ? '1' : '0');
    }
    
    /**
     * Set API key
     */
    public static function setApiKey($api_key) {
        self::$api_key = $api_key;
        updateSystemSetting('openai_api_key', $api_key);
    }
}

// Initialize chatbot
ChatbotManager::init();
