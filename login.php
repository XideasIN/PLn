<?php
/**
 * Login Page
 * LoanFlow Personal Loan Management System
 */

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $user = getCurrentUser();
    if (hasRole('admin')) {
        header('Location: admin/');
    } else {
        header('Location: client/');
    }
    exit();
}

$error = '';
$login_attempts = 0;

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password.";
        } else {
            // Get user by email
            $user = getUserByEmail($email);
            
            if ($user) {
                // Check if account is locked
                if (isAccountLocked($user)) {
                    $error = "Account is temporarily locked due to too many failed login attempts. Please try again later.";
                } else {
                    // Verify password
                    if (verifyPassword($password, $user['password_hash'])) {
                        // Successful login
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['login_time'] = time();
                        
                        // Update last login
                        updateLastLogin($user['id']);
                        
                        // Log the login
                        logAudit('user_login', 'users', $user['id'], null, [
                            'email' => $email,
                            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                        ]);
                        
                        // Set remember me cookie if requested
                        if ($remember) {
                            $token = generateToken();
                            setcookie('remember_token', $token, time() + (86400 * 30), '/'); // 30 days
                            // In production, store this token in database for validation
                        }
                        
                        // Redirect based on role
                        if (hasRole('admin')) {
                            redirectWithMessage('admin/', 'Welcome back, ' . $user['first_name'] . '!', 'success');
                        } else {
                            redirectWithMessage('client/', 'Welcome back, ' . $user['first_name'] . '!', 'success');
                        }
                    } else {
                        // Failed login
                        $result = handleFailedLogin($email);
                        
                        if ($result === 'locked') {
                            $error = "Too many failed attempts. Account has been locked for 30 minutes.";
                        } else {
                            $login_attempts = $user['failed_login_attempts'] + 1;
                            $remaining = 5 - $login_attempts;
                            $error = "Invalid email or password. " . ($remaining > 0 ? "$remaining attempts remaining." : "");
                        }
                        
                        // Log failed login attempt
                        logAudit('failed_login', 'users', $user['id'] ?? null, null, [
                            'email' => $email,
                            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                        ]);
                    }
                }
            } else {
                $error = "Invalid email or password.";
                
                // Log failed login attempt for non-existent user
                logAudit('failed_login', null, null, null, [
                    'email' => $email,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'reason' => 'user_not_found'
                ]);
            }
        }
    }
}

// Check for remember me cookie
if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
    // In production, validate remember token from database
    // For now, we'll skip this implementation
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - QuickFunds</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link href="css/aos.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .login-container {
            max-width: 400px;
            margin: 0 auto;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .login-header {
            text-align: center;
            padding: 2rem 2rem 1rem;
        }
        
        .login-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .login-body {
            padding: 0 2rem 2rem;
        }
        
        .form-floating {
            margin-bottom: 1rem;
        }
        
        .form-floating .form-control {
            border: 2px solid rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.8);
        }
        
        .form-floating .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background: white;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 12px;
            padding: 0.8rem 2rem;
            font-weight: 600;
            width: 100%;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(0, 0, 0, 0.1);
        }
        
        .divider span {
            background: rgba(255, 255, 255, 0.95);
            padding: 0 1rem;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .login-links {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .login-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-links a:hover {
            text-decoration: underline;
        }
        
        .demo-accounts {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.85rem;
        }
        
        .demo-accounts strong {
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="card login-card">
                <div class="login-header">
                    <div class="login-logo">
                        <i class="fas fa-coins fa-2x text-white"></i>
                    </div>
                    <h3 class="mb-1">Welcome Back</h3>
                    <p class="text-muted mb-0">Sign in to your LoanFlow account</p>
                </div>
                
                <div class="login-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php 
                    $flash = getFlashMessage();
                    if ($flash): 
                    ?>
                        <div class="alert alert-<?= $flash['type'] ?>">
                            <?= htmlspecialchars($flash['message']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="loginForm">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="form-floating">
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="name@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            <label for="email"><i class="fas fa-envelope me-2"></i>Email address</label>
                        </div>
                        
                        <div class="form-floating">
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Password" required>
                            <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember">
                            <label class="form-check-label" for="remember">
                                Remember me for 30 days
                            </label>
                        </div>
                        
                        <button type="submit" name="login" class="btn btn-primary btn-login">
                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                        </button>
                    </form>
                    
                    <div class="divider">
                        <span>New to LoanFlow?</span>
                    </div>
                    
                    <a href="index.php" class="btn btn-outline-primary w-100">
                        <i class="fas fa-user-plus me-2"></i>Create Account
                    </a>
                    
                    <div class="login-links">
                        <a href="forgot-password.php">Forgot your password?</a>
                    </div>
                    
                    <!-- Demo Accounts for Testing -->
                    <div class="demo-accounts">
                        <strong>Demo Accounts:</strong><br>
                        <small>
                            <strong>Admin:</strong> admin@loanflow.com / admin123<br>
                            <strong>Client:</strong> client@loanflow.com / client123
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <a href="index.php" class="text-white text-decoration-none">
                    <i class="fas fa-arrow-left me-2"></i>Back to Homepage
                </a>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/aos.js"></script>
    <script>
        AOS.init();
    </script>
    <script>
        // Auto-focus email field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('email').focus();
        });
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Please enter both email and password.');
                return false;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Signing In...';
            submitBtn.disabled = true;
            
            // Re-enable button after timeout (in case of errors)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 10000);
        });
        
        // Demo account quick login
        function quickLogin(email, password) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
            document.getElementById('loginForm').submit();
        }
        
        // Add click handlers to demo accounts
        document.addEventListener('DOMContentLoaded', function() {
            const demoAccounts = document.querySelector('.demo-accounts');
            if (demoAccounts) {
                demoAccounts.addEventListener('click', function(e) {
                    if (e.target.textContent.includes('admin@loanflow.com')) {
                        quickLogin('admin@loanflow.com', 'admin123');
                    } else if (e.target.textContent.includes('client@loanflow.com')) {
                        quickLogin('client@loanflow.com', 'client123');
                    }
                });
            }
        });
    </script>
</body>
</html>
