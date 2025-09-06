<?php
/**
 * Forgot Password
 * LoanFlow Personal Loan Management System
 */

require_once 'includes/functions.php';
require_once 'includes/captcha.php';
require_once 'includes/language.php';
require_once 'includes/email.php';

// Initialize language system
LanguageManager::init();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = __('invalid_csrf_token');
    } elseif (CaptchaManager::shouldProtectForm('password_reset') && !verifyCaptcha($_POST['captcha_response'] ?? $_POST['captcha_answer'] ?? null, 'password_reset')) {
        $error = CaptchaManager::getErrorMessage();
    } else {
        $email = sanitizeInput($_POST['email'] ?? '');
        
        if (empty($email)) {
            $error = __('email_required');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = __('invalid_email');
        } else {
            try {
                $db = getDB();
                
                // Check if user exists
                $stmt = $db->prepare("SELECT id, first_name, email FROM users WHERE email = ? AND status = 'active'");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Generate reset token
                    $reset_token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Store reset token
                    $stmt = $db->prepare("
                        UPDATE users 
                        SET reset_token = ?, reset_token_expires = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$reset_token, $expires_at, $user['id']]);
                    
                    // Send reset email
                    $reset_link = getBaseUrl() . '/reset-password.php?token=' . $reset_token;
                    
                    $email_data = [
                        'first_name' => $user['first_name'],
                        'reset_link' => $reset_link,
                        'expires_time' => '1 hour'
                    ];
                    
                    $email_sent = sendTemplateEmail(
                        $user['email'],
                        'password_reset',
                        $email_data
                    );
                    
                    if ($email_sent) {
                        // Log the action
                        logAudit('password_reset_requested', 'users', $user['id'], $user['id'], ['email' => $email]);
                        
                        $success = __('password_reset_email_sent');
                    } else {
                        $error = __('email_send_failed');
                    }
                } else {
                    // Don't reveal if email exists or not for security
                    $success = __('password_reset_email_sent');
                }
                
            } catch (Exception $e) {
                error_log("Password reset error: " . $e->getMessage());
                $error = __('system_error');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= LanguageManager::getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('forgot_password') ?> - QuickFunds</title>
    <link href="FrontEnd_Template/css/bootstrap.min.css" rel="stylesheet">
    <link href="FrontEnd_Template/css/style.css" rel="stylesheet">
    <link href="FrontEnd_Template/css/aos.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Header -->
    <header class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="FrontEnd_Template/images/logo.png" alt="QuickFunds" height="40">
            </a>
            <div class="navbar-nav ms-auto">
                <?= LanguageManager::getLanguageSelector() ?>
            </div>
        </div>
    </header>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h4 class="mb-0">
                            <i class="fas fa-key me-2"></i><?= __('forgot_password') ?>
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <?= htmlspecialchars($success) ?>
                            </div>
                            <div class="text-center">
                                <a href="login.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left me-2"></i><?= __('back_to_login') ?>
                                </a>
                            </div>
                        <?php else: ?>
                            
                            <div class="text-center mb-4">
                                <i class="fas fa-envelope fa-3x text-muted mb-3"></i>
                                <p class="text-muted">
                                    <?= __('forgot_password_instructions') ?>
                                </p>
                            </div>
                            
                            <form method="POST" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">
                                        <?= __('email') ?> <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-envelope"></i>
                                        </span>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                               required placeholder="<?= __('enter_email') ?>">
                                    </div>
                                </div>
                                
                                <!-- CAPTCHA -->
                                <?php if (CaptchaManager::shouldProtectForm('password_reset')): ?>
                                    <div class="mb-4">
                                        <?= CaptchaManager::generateHTML('password_reset_form') ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-paper-plane me-2"></i><?= __('send_reset_link') ?>
                                    </button>
                                </div>
                                
                                <div class="text-center">
                                    <a href="login.php" class="text-decoration-none">
                                        <i class="fas fa-arrow-left me-2"></i><?= __('back_to_login') ?>
                                    </a>
                                </div>
                            </form>
                        <?php endif; ?>
                        
                    </div>
                </div>
                
                <!-- Help Section -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-question-circle me-2"></i><?= __('need_help') ?>
                        </h6>
                        <p class="card-text small text-muted">
                            <?= __('password_reset_help') ?>
                        </p>
                        <ul class="small text-muted">
                            <li><?= __('reset_link_expires_1_hour') ?></li>
                            <li><?= __('check_spam_folder') ?></li>
                            <li><?= __('contact_support_if_issues') ?></li>
                        </ul>
                        <div class="text-center">
                            <a href="contact.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-headset me-2"></i><?= __('contact_support') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-3 mt-auto">
        <div class="container text-center">
            <p class="mb-0">&copy; 2025 QuickFunds. <?= __('all_rights_reserved') ?></p>
        </div>
    </footer>

    <script src="FrontEnd_Template/js/bootstrap.bundle.js"></script>
    <script src="FrontEnd_Template/js/bootstrap.min.js"></script>
    <script src="FrontEnd_Template/js/aos.js"></script>
    <script>
        AOS.init();
    </script>
    <?= CaptchaManager::generateJS('password_reset_form') ?>
    <script>
        // Language change function
        function changeLanguage(lang) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'change-language.php';
            
            const langInput = document.createElement('input');
            langInput.type = 'hidden';
            langInput.name = 'language';
            langInput.value = lang;
            
            const redirectInput = document.createElement('input');
            redirectInput.type = 'hidden';
            redirectInput.name = 'redirect';
            redirectInput.value = window.location.href;
            
            form.appendChild(langInput);
            form.appendChild(redirectInput);
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>
