<?php
/**
 * Reset Password
 * LoanFlow Personal Loan Management System
 */

require_once 'includes/functions.php';
require_once 'includes/captcha.php';
require_once 'includes/language.php';

// Initialize language system
LanguageManager::init();

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$user = null;

// Validate token
if (empty($token)) {
    $error = __('invalid_reset_token');
} else {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, first_name, email, reset_token_expires 
            FROM users 
            WHERE reset_token = ? AND status = 'active'
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = __('invalid_reset_token');
        } elseif (strtotime($user['reset_token_expires']) < time()) {
            $error = __('reset_token_expired');
        }
    } catch (Exception $e) {
        error_log("Reset token validation error: " . $e->getMessage());
        $error = __('system_error');
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = __('invalid_csrf_token');
    } elseif (CaptchaManager::shouldProtectForm('password_reset') && !verifyCaptcha($_POST['captcha_response'] ?? $_POST['captcha_answer'] ?? null, 'password_reset')) {
        $error = CaptchaManager::getErrorMessage();
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($password)) {
            $error = __('password_required');
        } elseif (strlen($password) < 8) {
            $error = __('password_min_length');
        } elseif ($password !== $confirm_password) {
            $error = __('password_mismatch');
        } else {
            try {
                $db = getDB();
                
                // Update password and clear reset token
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("
                    UPDATE users 
                    SET password_hash = ?, 
                        reset_token = NULL, 
                        reset_token_expires = NULL,
                        password_changed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$password_hash, $user['id']]);
                
                // Log the action
                logAudit('password_reset_completed', 'users', $user['id'], $user['id']);
                
                $success = __('password_reset_success');
                
                // Clear user data to prevent reuse
                $user = null;
                
            } catch (Exception $e) {
                error_log("Password reset completion error: " . $e->getMessage());
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
    <title><?= __('reset_password') ?> - QuickFunds</title>
    <link href="FrontEnd_Template/css/bootstrap.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="FrontEnd_Template/css/main.css" rel="stylesheet">
    <link href="FrontEnd_Template/css/aos.css" rel="stylesheet">
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
                            <i class="fas fa-lock me-2"></i><?= __('reset_password') ?>
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?= htmlspecialchars($error) ?>
                            </div>
                            
                            <?php if (strpos($error, 'token') !== false): ?>
                                <div class="text-center">
                                    <a href="forgot-password.php" class="btn btn-primary">
                                        <i class="fas fa-key me-2"></i><?= __('request_new_reset') ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <?= htmlspecialchars($success) ?>
                            </div>
                            <div class="text-center">
                                <a href="login.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i><?= __('login_now') ?>
                                </a>
                            </div>
                        <?php elseif ($user): ?>
                            
                            <div class="text-center mb-4">
                                <i class="fas fa-user-shield fa-3x text-success mb-3"></i>
                                <h5><?= __('welcome_back') ?>, <?= htmlspecialchars($user['first_name']) ?>!</h5>
                                <p class="text-muted">
                                    <?= __('reset_password_instructions') ?>
                                </p>
                            </div>
                            
                            <form method="POST" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">
                                        <?= __('new_password') ?> <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="password" name="password" 
                                               required minlength="8" placeholder="<?= __('enter_new_password') ?>">
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">
                                        <?= __('password_requirements') ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">
                                        <?= __('confirm_password') ?> <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                               required minlength="8" placeholder="<?= __('confirm_new_password') ?>">
                                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Password Strength Indicator -->
                                <div class="mb-3">
                                    <label class="form-label"><?= __('password_strength') ?></label>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar" id="passwordStrength" role="progressbar" style="width: 0%"></div>
                                    </div>
                                    <div class="form-text">
                                        <small id="passwordStrengthText"><?= __('enter_password_to_check_strength') ?></small>
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
                                        <i class="fas fa-save me-2"></i><?= __('update_password') ?>
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
                
                <!-- Security Tips -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-shield-alt me-2"></i><?= __('security_tips') ?>
                        </h6>
                        <ul class="small text-muted mb-0">
                            <li><?= __('use_strong_password') ?></li>
                            <li><?= __('dont_share_password') ?></li>
                            <li><?= __('enable_2fa_recommended') ?></li>
                            <li><?= __('logout_shared_computers') ?></li>
                        </ul>
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
        // Password visibility toggle
        document.getElementById('togglePassword')?.addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                password.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });
        
        document.getElementById('toggleConfirmPassword')?.addEventListener('click', function() {
            const password = document.getElementById('confirm_password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                password.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });
        
        // Password strength checker
        document.getElementById('password')?.addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            const strengthText = document.getElementById('passwordStrengthText');
            
            let strength = 0;
            let text = '';
            let color = '';
            
            if (password.length >= 8) strength += 20;
            if (password.match(/[a-z]/)) strength += 20;
            if (password.match(/[A-Z]/)) strength += 20;
            if (password.match(/[0-9]/)) strength += 20;
            if (password.match(/[^a-zA-Z0-9]/)) strength += 20;
            
            if (strength === 0) {
                text = '<?= __('enter_password_to_check_strength') ?>';
                color = '';
            } else if (strength <= 40) {
                text = '<?= __('weak_password') ?>';
                color = 'bg-danger';
            } else if (strength <= 60) {
                text = '<?= __('fair_password') ?>';
                color = 'bg-warning';
            } else if (strength <= 80) {
                text = '<?= __('good_password') ?>';
                color = 'bg-info';
            } else {
                text = '<?= __('strong_password') ?>';
                color = 'bg-success';
            }
            
            strengthBar.style.width = strength + '%';
            strengthBar.className = 'progress-bar ' + color;
            strengthText.textContent = text;
        });
        
        // Password confirmation validation
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('<?= __('password_mismatch') ?>');
            } else {
                this.setCustomValidity('');
            }
        });
        
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
