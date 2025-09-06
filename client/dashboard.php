<?php
/**
 * Client Dashboard
 * LoanFlow Personal Loan Management System
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require client login
requireLogin();

$current_user = getCurrentUser();
$application = getApplicationByUserId($current_user['id']);

// Get user's documents
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM documents WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$current_user['id']]);
    $documents = $stmt->fetchAll();
    
    // Get required documents count
    $required_docs = ['photo_id', 'proof_income', 'proof_address'];
    $uploaded_docs = array_column($documents, 'document_type');
    $missing_docs = array_diff($required_docs, $uploaded_docs);
    
    // Get signatures
    $stmt = $db->prepare("SELECT * FROM digital_signatures WHERE user_id = ? ORDER BY signed_at DESC");
    $stmt->execute([$current_user['id']]);
    $signatures = $stmt->fetchAll();
    
    // Get bank details
    $stmt = $db->prepare("SELECT * FROM bank_details WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$current_user['id']]);
    $bank_details = $stmt->fetch();
    
    // Get payments
    $stmt = $db->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$current_user['id']]);
    $payments = $stmt->fetchAll();
    
    // Get messages
    $stmt = $db->prepare("
        SELECT cm.*, u.first_name, u.last_name 
        FROM client_messages cm
        JOIN users u ON cm.sender_id = u.id
        WHERE cm.recipient_id = ? OR cm.sender_id = ?
        ORDER BY cm.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$current_user['id'], $current_user['id']]);
    $messages = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Dashboard data fetch failed: " . $e->getMessage());
    $documents = $signatures = $payments = $messages = [];
    $missing_docs = ['photo_id', 'proof_income', 'proof_address'];
    $bank_details = null;
}

// Calculate progress
$steps = [
    1 => 'Application Submitted',
    2 => 'Documents Uploaded', 
    3 => 'Agreements Signed',
    4 => 'Bank Details Provided',
    5 => 'Payment Completed',
    6 => 'Approved & Funded'
];

$current_step = $application['current_step'] ?? 1;
$progress_percentage = ($current_step / 6) * 100;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - QuickFunds</title>
    <link rel="stylesheet" href="../FrontEnd_Template/css/bootstrap.min.css">
    <link rel="stylesheet" href="../FrontEnd_Template/css/style.css">
    <link rel="stylesheet" href="../FrontEnd_Template/animation/aos.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/client.css" rel="stylesheet">
    <link href="../assets/css/client-chat.css" rel="stylesheet">
</head>
<body>
  <div class="main-wrapper">
    <!-- Client Header -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <img src="../FrontEnd_Template/images/logo.png" alt="QuickFunds" class="logo">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="documents.php">
                            <i class="fas fa-folder me-1"></i>Documents
                            <?php if (!empty($missing_docs)): ?>
                                <span class="badge bg-warning ms-1"><?= count($missing_docs) ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="agreements.php">
                            <i class="fas fa-file-signature me-1"></i>Agreements
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="banking.php">
                            <i class="fas fa-university me-1"></i>Banking
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="payments.php">
                            <i class="fas fa-credit-card me-1"></i>Payments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">
                            <i class="fas fa-envelope me-1"></i>Messages
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?= htmlspecialchars($current_user['first_name']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a></li>
                            <li><a class="dropdown-item" href="calculator.php">
                                <i class="fas fa-calculator me-2"></i>Loan Calculator
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid" style="padding-top: 100px;">
        <!-- Flash Messages -->
        <?php 
        $flash = getFlashMessage();
        if ($flash): 
        ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
                <?= htmlspecialchars($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="about-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="service-title" style="color: white;">Welcome back, <?= htmlspecialchars($current_user['first_name']) ?>!</h3>
                            <p class="works-subtext" style="color: white; opacity: 0.9;">Reference Number: <strong><?= htmlspecialchars($current_user['reference_number']) ?></strong></p>
                            <?php if ($application): ?>
                                <p class="works-subtext" style="color: white; opacity: 0.9;">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Your loan application for <?= formatCurrency($application['loan_amount'], $current_user['country']) ?> 
                                    is currently <strong><?= ucfirst(str_replace('_', ' ', $application['application_status'])) ?></strong>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="about-box" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
                                <div class="service-title" style="color: white; font-size: 2rem;"><?= $current_step ?>/6</div>
                                <div class="works-subtext" style="color: white; opacity: 0.9;">Steps Completed</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="about-box">
                    <div class="mb-3">
                        <h4 class="service-title"><i class="fas fa-tasks me-2"></i>Application Progress</h4>
                    </div>
                    <div>
                        <div class="progress mb-3" style="height: 12px;">
                            <div class="progress-bar" role="progressbar" style="width: <?= $progress_percentage ?>%" 
                                 aria-valuenow="<?= $progress_percentage ?>" aria-valuemin="0" aria-valuemax="100">
                                <?= round($progress_percentage) ?>%
                            </div>
                        </div>
                        
                        <div class="timeline">
                            <?php foreach ($steps as $step_num => $step_name): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker <?= $step_num <= $current_step ? 'completed' : '' ?>"></div>
                                    <div class="timeline-content">
                                        <h6 class="mb-1"><?= $step_name ?></h6>
                                        <?php if ($step_num <= $current_step): ?>
                                            <small class="text-success"><i class="fas fa-check me-1"></i>Completed</small>
                                        <?php elseif ($step_num == $current_step + 1): ?>
                                            <small class="text-primary"><i class="fas fa-arrow-right me-1"></i>Next Step</small>
                                        <?php else: ?>
                                            <small class="text-muted">Pending</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Quick Actions -->
            <div class="col-lg-8 mb-4">
                <div class="about-box">
                    <div class="mb-3">
                        <h4 class="service-title"><i class="fas fa-bolt me-2"></i>Quick Actions</h4>
                    </div>
                    <div>
                        <div class="row">
                            <?php if (!empty($missing_docs)): ?>
                            <div class="col-md-6 mb-3">
                                <a href="documents.php" class="btn btn-warning btn-lg w-100">
                                    <i class="fas fa-upload fa-2x mb-2 d-block"></i>
                                    Upload Missing Documents
                                    <small class="d-block"><?= count($missing_docs) ?> documents needed</small>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (empty($signatures) && $current_step >= 2): ?>
                            <div class="col-md-6 mb-3">
                                <a href="agreements.php" class="btn btn-info btn-lg w-100">
                                    <i class="fas fa-file-signature fa-2x mb-2 d-block"></i>
                                    Sign Agreements
                                    <small class="d-block">Digital signature required</small>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!$bank_details && $current_step >= 3): ?>
                            <div class="col-md-6 mb-3">
                                <a href="banking.php" class="btn btn-success btn-lg w-100">
                                    <i class="fas fa-university fa-2x mb-2 d-block"></i>
                                    Add Bank Details
                                    <small class="d-block">For loan deposit</small>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (empty($payments) && $current_step >= 4): ?>
                            <div class="col-md-6 mb-3">
                                <a href="payments.php" class="btn btn-payment btn-lg w-100 payment-cta-button">
                                    <i class="fas fa-credit-card fa-2x mb-2 d-block"></i>
                                    <span class="payment-btn-text">Make Payment</span>
                                    <small class="d-block payment-btn-subtitle">Service fee required</small>
                                    <div class="payment-btn-pulse"></div>
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="simple_payment_confirmation.php" class="btn btn-success btn-lg w-100">
                                    <i class="fas fa-check-circle fa-2x mb-2 d-block"></i>
                                    <span class="d-block">Payment Confirmation</span>
                                    <small class="d-block">Confirm payment sent</small>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Application Summary -->
            <div class="col-lg-4 mb-4">
                <div class="about-box">
                    <div class="mb-3">
                        <h4 class="service-title"><i class="fas fa-file-alt me-2"></i>Application Summary</h4>
                    </div>
                    <div>
                        <?php if ($application): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Loan Amount:</span>
                                <strong><?= formatCurrency($application['loan_amount'], $current_user['country']) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Loan Type:</span>
                                <span><?= ucfirst(str_replace('_', ' ', $application['loan_type'])) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Interest Rate:</span>
                                <span><?= $application['interest_rate'] ?>%</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Term:</span>
                                <span><?= $application['loan_term_months'] ?> months</span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span>Monthly Payment:</span>
                                <strong><?= formatCurrency($application['monthly_payment'], $current_user['country']) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Status:</span>
                                <span class="status-badge status-<?= $application['application_status'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $application['application_status'])) ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No application found.</p>
                            <a href="../index.php" class="btn btn-primary">Start Application</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="about-box">
                    <div class="mb-3">
                        <h4 class="service-title"><i class="fas fa-history me-2"></i>Recent Documents</h4>
                    </div>
                    <div>
                        <?php if (!empty($documents)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach (array_slice($documents, 0, 5) as $doc): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-file me-2"></i>
                                            <strong><?= ucfirst(str_replace('_', ' ', $doc['document_type'])) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= formatDateTime($doc['created_at']) ?></small>
                                        </div>
                                        <span class="status-badge status-<?= $doc['upload_status'] ?>">
                                            <?= ucfirst($doc['upload_status']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="documents.php" class="btn btn-outline-primary">View All Documents</a>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No documents uploaded yet.</p>
                            <div class="text-center">
                                <a href="documents.php" class="btn btn-primary">Upload Documents</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="about-box">
                    <div class="mb-3">
                        <h4 class="service-title"><i class="fas fa-envelope me-2"></i>Recent Messages</h4>
                    </div>
                    <div>
                        <?php if (!empty($messages)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($messages as $msg): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between">
                                            <strong><?= htmlspecialchars($msg['subject']) ?></strong>
                                            <small class="text-muted"><?= formatDateTime($msg['created_at']) ?></small>
                                        </div>
                                        <p class="mb-1"><?= htmlspecialchars(substr($msg['message'], 0, 100)) ?>...</p>
                                        <small class="text-muted">From: <?= htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']) ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="messages.php" class="btn btn-outline-primary">View All Messages</a>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No messages yet.</p>
                            <div class="text-center">
                                <a href="messages.php" class="btn btn-primary">Send Message</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Advanced AI Chat Box -->
    <div id="aiChatBox" class="ai-chat-container">
        <div class="chat-header">
            <div class="chat-header-info">
                <div class="chat-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="chat-title">
                    <h4>AI Assistant</h4>
                    <span class="chat-status" id="chatStatus">Online</span>
                </div>
            </div>
            <div class="chat-controls">
                <button class="chat-minimize" onclick="minimizeChat()">
                    <i class="fas fa-minus"></i>
                </button>
                <button class="chat-close" onclick="closeChat()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <div class="chat-messages" id="chatMessages">
            <div class="message bot-message">
                <div class="message-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-content">
                    <p>Hello! I'm your AI assistant. I can help you with questions about your loan, payments, documents, and more. How can I assist you today?</p>
                    <span class="message-time">Just now</span>
                </div>
            </div>
        </div>
        
        <div class="typing-indicator" id="typingIndicator" style="display: none;">
            <div class="typing-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <span>AI is typing...</span>
        </div>
        
        <div class="chat-input-container">
            <div class="chat-input-wrapper">
                <input type="text" id="chatInput" placeholder="Type your message..." maxlength="500">
                <button id="sendButton" onclick="sendMessage()">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
            <div class="chat-suggestions">
                <button class="suggestion-btn" onclick="sendSuggestion('What is my current loan balance?')">Loan Balance</button>
                <button class="suggestion-btn" onclick="sendSuggestion('When is my next payment due?')">Next Payment</button>
                <button class="suggestion-btn" onclick="sendSuggestion('How do I make a payment?')">Make Payment</button>
            </div>
        </div>
    </div>

    <!-- Chat Toggle Button -->
    <div id="chatToggle" class="chat-toggle" onclick="toggleChat()">
        <i class="fas fa-comments"></i>
        <span class="chat-notification" id="chatNotification" style="display: none;">1</span>
    </div>

    <script src="../FrontEnd_Template/js/bootstrap.bundle.js"></script>
    <script src="../FrontEnd_Template/js/bootstrap.min.js"></script>
    <script src="../FrontEnd_Template/animation/aos.js"></script>
    <script>
        // Initialize AOS animations
        AOS.init({
            duration: 1000,
        });
        
        // Navbar scroll effect
        window.onscroll = function() {
            var navbar = document.querySelector('.navbar');
            
            if (window.scrollY > 100) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        };
        
        // Auto-refresh progress every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
        
        // Advanced AI Chat Functionality
        let chatOpen = false;
        let conversationHistory = [];
        let isTyping = false;

        function toggleChat() {
            const chatBox = document.getElementById('aiChatBox');
            const chatToggle = document.getElementById('chatToggle');
            const notification = document.getElementById('chatNotification');
            
            if (chatOpen) {
                closeChat();
            } else {
                openChat();
            }
        }

        function openChat() {
            const chatBox = document.getElementById('aiChatBox');
            const chatToggle = document.getElementById('chatToggle');
            const notification = document.getElementById('chatNotification');
            
            chatBox.style.display = 'flex';
            chatToggle.style.display = 'none';
            notification.style.display = 'none';
            chatOpen = true;
            
            // Focus on input
            setTimeout(() => {
                document.getElementById('chatInput').focus();
            }, 300);
        }

        function closeChat() {
            const chatBox = document.getElementById('aiChatBox');
            const chatToggle = document.getElementById('chatToggle');
            
            chatBox.style.display = 'none';
            chatToggle.style.display = 'flex';
            chatOpen = false;
        }

        function minimizeChat() {
            closeChat();
        }

        function sendMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            
            if (message && !isTyping) {
                addMessage(message, 'user');
                input.value = '';
                
                // Show typing indicator
                showTypingIndicator();
                
                // Send to AI API
                sendToAI(message);
            }
        }

        function sendSuggestion(suggestion) {
            const input = document.getElementById('chatInput');
            input.value = suggestion;
            sendMessage();
        }

        function addMessage(content, sender) {
            const messagesContainer = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${sender}-message`;
            
            const currentTime = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            if (sender === 'user') {
                messageDiv.innerHTML = `
                    <div class="message-content">
                        <p>${escapeHtml(content)}</p>
                        <span class="message-time">${currentTime}</span>
                    </div>
                    <div class="message-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                `;
            } else {
                messageDiv.innerHTML = `
                    <div class="message-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="message-content">
                        <p>${content}</p>
                        <span class="message-time">${currentTime}</span>
                    </div>
                `;
            }
            
            messagesContainer.appendChild(messageDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            
            // Add to conversation history
            conversationHistory.push({
                role: sender === 'user' ? 'user' : 'assistant',
                content: content
            });
        }

        function showTypingIndicator() {
            const typingIndicator = document.getElementById('typingIndicator');
            typingIndicator.style.display = 'flex';
            isTyping = true;
            
            const messagesContainer = document.getElementById('chatMessages');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function hideTypingIndicator() {
            const typingIndicator = document.getElementById('typingIndicator');
            typingIndicator.style.display = 'none';
            isTyping = false;
        }

        async function sendToAI(message) {
            try {
                const response = await fetch('../api/chatbot.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        message: message,
                        conversation_history: conversationHistory.slice(-10), // Last 10 messages for context
                        client_area: true, // Flag for enhanced client area processing
                        user_id: <?= $current_user['id'] ?> // Current user ID for personalized responses
                    })
                });
                
                const data = await response.json();
                
                hideTypingIndicator();
                
                if (data.success) {
                    addMessage(data.response, 'bot');
                } else {
                    addMessage('I apologize, but I\'m having trouble processing your request right now. Please try again or contact our support team.', 'bot');
                }
                
            } catch (error) {
                console.error('Chat error:', error);
                hideTypingIndicator();
                addMessage('I\'m experiencing technical difficulties. Please try again later or contact support.', 'bot');
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Allow Enter key to send messages
        document.addEventListener('DOMContentLoaded', function() {
            const chatInput = document.getElementById('chatInput');
            if (chatInput) {
                chatInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        sendMessage();
                    }
                });
            }
        });
    </script>
</body>
</html>
