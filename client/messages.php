<?php
/**
 * Client Messages - Communication Center
 * LoanFlow Personal Loan Management System
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require client login
requireLogin();

$current_user = getCurrentUser();
$application = getApplicationByUserId($current_user['id']);

// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    try {
        $db = getDB();
        
        $subject = trim($_POST['subject']);
        $message = trim($_POST['message']);
        $priority = $_POST['priority'] ?? 'normal';
        $category = $_POST['category'] ?? 'general';
        
        // Validation
        if (empty($subject) || empty($message)) {
            throw new Exception('Subject and message are required.');
        }
        
        // Insert message
        $stmt = $db->prepare("
            INSERT INTO messages (user_id, application_id, sender_type, subject, message, priority, category, status, created_at)
            VALUES (?, ?, 'client', ?, ?, ?, ?, 'unread', NOW())
        ");
        $stmt->execute([
            $current_user['id'],
            $application['id'] ?? null,
            $subject,
            $message,
            $priority,
            $category
        ]);
        
        setFlashMessage('Message sent successfully! We will respond within 24 hours.', 'success');
        header('Location: messages.php');
        exit;
        
    } catch (Exception $e) {
        error_log("Message sending failed: " . $e->getMessage());
        setFlashMessage($e->getMessage(), 'error');
    }
}

// Handle message reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    try {
        $db = getDB();
        
        $parent_id = $_POST['parent_id'];
        $reply_message = trim($_POST['reply_message']);
        
        if (empty($reply_message)) {
            throw new Exception('Reply message cannot be empty.');
        }
        
        // Get parent message for subject
        $stmt = $db->prepare("SELECT subject FROM messages WHERE id = ? AND user_id = ?");
        $stmt->execute([$parent_id, $current_user['id']]);
        $parent = $stmt->fetch();
        
        if (!$parent) {
            throw new Exception('Original message not found.');
        }
        
        $reply_subject = 'Re: ' . $parent['subject'];
        
        // Insert reply
        $stmt = $db->prepare("
            INSERT INTO messages (user_id, application_id, sender_type, subject, message, parent_id, status, created_at)
            VALUES (?, ?, 'client', ?, ?, ?, 'unread', NOW())
        ");
        $stmt->execute([
            $current_user['id'],
            $application['id'] ?? null,
            $reply_subject,
            $reply_message,
            $parent_id
        ]);
        
        setFlashMessage('Reply sent successfully!', 'success');
        header('Location: messages.php?view=' . $parent_id);
        exit;
        
    } catch (Exception $e) {
        error_log("Reply sending failed: " . $e->getMessage());
        setFlashMessage($e->getMessage(), 'error');
    }
}

// Get messages
$view_message_id = $_GET['view'] ?? null;

try {
    $db = getDB();
    
    if ($view_message_id) {
        // Get specific message thread
        $stmt = $db->prepare("
            SELECT m.*, u.first_name, u.last_name, u.email,
                   CASE WHEN m.sender_type = 'admin' THEN 'Support Team' 
                        ELSE CONCAT(u.first_name, ' ', u.last_name) END as sender_name
            FROM messages m
            LEFT JOIN users u ON m.user_id = u.id
            WHERE (m.id = ? OR m.parent_id = ?) AND m.user_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$view_message_id, $view_message_id, $current_user['id']]);
        $message_thread = $stmt->fetchAll();
        
        // Mark messages as read
        $stmt = $db->prepare("
            UPDATE messages SET status = 'read' 
            WHERE (id = ? OR parent_id = ?) AND user_id = ? AND sender_type = 'admin'
        ");
        $stmt->execute([$view_message_id, $view_message_id, $current_user['id']]);
    }
    
    // Get all messages (inbox)
    $stmt = $db->prepare("
        SELECT m.*, 
               CASE WHEN m.sender_type = 'admin' THEN 'Support Team' 
                    ELSE CONCAT(u.first_name, ' ', u.last_name) END as sender_name,
               (SELECT COUNT(*) FROM messages m2 WHERE m2.parent_id = m.id OR (m2.id = m.id AND m.parent_id IS NULL)) as thread_count,
               (SELECT COUNT(*) FROM messages m3 WHERE (m3.id = m.id OR m3.parent_id = m.id) AND m3.status = 'unread' AND m3.sender_type = 'admin') as unread_count
        FROM messages m
        LEFT JOIN users u ON m.user_id = u.id
        WHERE m.user_id = ? AND m.parent_id IS NULL
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$current_user['id']]);
    $messages = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Messages fetch failed: " . $e->getMessage());
    $messages = [];
    $message_thread = [];
}

// Message categories
$categories = [
    'general' => 'General Inquiry',
    'application' => 'Application Status',
    'documents' => 'Document Issues',
    'payment' => 'Payment Questions',
    'technical' => 'Technical Support',
    'complaint' => 'Complaint'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - QuickFunds</title>
    <link rel="stylesheet" href="../FrontEnd_Template/css/bootstrap.min.css">
    <link rel="stylesheet" href="../FrontEnd_Template/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/client.css" rel="stylesheet">
    <style>
        .message-list {
            max-height: 600px;
            overflow-y: auto;
        }
        .message-item {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .message-item:hover {
            background-color: #f8f9fa;
            border-color: #007bff;
        }
        .message-item.unread {
            background-color: #e3f2fd;
            border-left: 4px solid #007bff;
        }
        .message-thread {
            max-height: 500px;
            overflow-y: auto;
        }
        .message-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            margin-bottom: 10px;
        }
        .message-bubble.client {
            background-color: #007bff;
            color: white;
            margin-left: auto;
        }
        .message-bubble.admin {
            background-color: #f1f3f4;
            color: #333;
        }
        .priority-high {
            border-left: 4px solid #dc3545;
        }
        .priority-urgent {
            border-left: 4px solid #fd7e14;
        }
        .category-badge {
            font-size: 0.75rem;
            padding: 2px 8px;
        }
    </style>
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
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="documents.php">
                                <i class="fas fa-folder me-1"></i>Documents
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
                            <a class="nav-link active" href="messages.php">
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

            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="about-box">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="service-title"><i class="fas fa-envelope me-2"></i>Messages</h3>
                                <p class="works-subtext mb-0">Communicate with our support team and track your conversations.</p>
                            </div>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                                <i class="fas fa-plus me-1"></i>New Message
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Messages List -->
                <div class="col-lg-<?= $view_message_id ? '4' : '12' ?>">
                    <div class="about-box">
                        <h4 class="service-title">Inbox</h4>
                        
                        <?php if (empty($messages)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Messages</h5>
                                <p class="text-muted">Start a conversation with our support team.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                                    <i class="fas fa-plus me-1"></i>Send First Message
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="message-list">
                                <?php foreach ($messages as $message): ?>
                                    <div class="message-item <?= $message['unread_count'] > 0 ? 'unread' : '' ?> 
                                                <?= $message['priority'] !== 'normal' ? 'priority-' . $message['priority'] : '' ?>"
                                         onclick="window.location.href='messages.php?view=<?= $message['id'] ?>'">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-0"><?= htmlspecialchars($message['subject']) ?></h6>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if ($message['unread_count'] > 0): ?>
                                                    <span class="badge bg-primary"><?= $message['unread_count'] ?></span>
                                                <?php endif; ?>
                                                <small class="text-muted"><?= date('M j', strtotime($message['created_at'])) ?></small>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i><?= htmlspecialchars($message['sender_name']) ?>
                                            </small>
                                            <span class="badge bg-secondary category-badge">
                                                <?= $categories[$message['category']] ?? 'General' ?>
                                            </span>
                                        </div>
                                        
                                        <p class="mb-2 text-truncate"><?= htmlspecialchars(substr($message['message'], 0, 100)) ?>...</p>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <?php if ($message['thread_count'] > 1): ?>
                                                    <i class="fas fa-comments me-1"></i><?= $message['thread_count'] ?> messages
                                                <?php endif; ?>
                                            </small>
                                            <?php if ($message['priority'] !== 'normal'): ?>
                                                <span class="badge bg-<?= $message['priority'] === 'high' ? 'warning' : 'danger' ?>">
                                                    <?= ucfirst($message['priority']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Message Thread View -->
                <?php if ($view_message_id && !empty($message_thread)): ?>
                <div class="col-lg-8">
                    <div class="about-box">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="service-title mb-0"><?= htmlspecialchars($message_thread[0]['subject']) ?></h4>
                            <a href="messages.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>Back to Inbox
                            </a>
                        </div>
                        
                        <div class="message-thread">
                            <?php foreach ($message_thread as $msg): ?>
                                <div class="d-flex <?= $msg['sender_type'] === 'client' ? 'justify-content-end' : 'justify-content-start' ?> mb-3">
                                    <div class="message-bubble <?= $msg['sender_type'] ?>">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <small class="<?= $msg['sender_type'] === 'client' ? 'text-light' : 'text-muted' ?>">
                                                <strong><?= $msg['sender_type'] === 'client' ? 'You' : 'Support Team' ?></strong>
                                            </small>
                                            <small class="<?= $msg['sender_type'] === 'client' ? 'text-light' : 'text-muted' ?>">
                                                <?= date('M j, Y g:i A', strtotime($msg['created_at'])) ?>
                                            </small>
                                        </div>
                                        <div><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Reply Form -->
                        <div class="border-top pt-3 mt-3">
                            <form method="POST">
                                <input type="hidden" name="parent_id" value="<?= $message_thread[0]['id'] ?>">
                                <div class="mb-3">
                                    <label class="form-label">Reply:</label>
                                    <textarea class="form-control" name="reply_message" rows="4" 
                                              placeholder="Type your reply..." required></textarea>
                                </div>
                                <button type="submit" name="reply_message" class="btn btn-primary">
                                    <i class="fas fa-reply me-1"></i>Send Reply
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- New Message Modal -->
    <div class="modal fade" id="newMessageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>New Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category *</label>
                                <select class="form-select" name="category" required>
                                    <?php foreach ($categories as $key => $label): ?>
                                        <option value="<?= $key ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority</label>
                                <select class="form-select" name="priority">
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Subject *</label>
                            <input type="text" class="form-control" name="subject" 
                                   placeholder="Enter message subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Message *</label>
                            <textarea class="form-control" name="message" rows="6" 
                                      placeholder="Type your message here..." required></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Response Time:</strong> We typically respond within 24 hours during business days.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="send_message" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i>Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../FrontEnd_Template/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-scroll to bottom of message thread
            const messageThread = document.querySelector('.message-thread');
            if (messageThread) {
                messageThread.scrollTop = messageThread.scrollHeight;
            }
            
            // Auto-refresh for new messages (every 30 seconds)
            <?php if ($view_message_id): ?>
            setInterval(function() {
                // Only refresh if user is viewing a thread
                if (document.visibilityState === 'visible') {
                    window.location.reload();
                }
            }, 30000);
            <?php endif; ?>
        });
    </script>
</body>
</html>