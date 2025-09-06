<?php
/**
 * First Login Setup - Force Admin Password Change
 * LoanFlow Personal Loan Management System
 */

session_start();
require_once '../config/database.php';

// Check if this is the first login setup
function isFirstLoginRequired() {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND password_changed = 0");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['count'] > 0;
    } catch (Exception $e) {
        error_log("First login check failed: " . $e->getMessage());
        return false;
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $admin_id = $_POST['admin_id'] ?? '';
    
    $errors = [];
    
    // Validate inputs
    if (empty($current_password)) {
        $errors[] = "Current password is required";
    }
    
    if (empty($new_password)) {
        $errors[] = "New password is required";
    }
    
    if (strlen($new_password) < 8) {
        $errors[] = "New password must be at least 8 characters long";
    }
    
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $new_password)) {
        $errors[] = "New password must contain at least one uppercase letter, one lowercase letter, one number, and one special character";
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "New password and confirmation do not match";
    }
    
    if (empty($errors)) {
        try {
            $db = getDB();
            
            // Verify current password
            $stmt = $db->prepare("SELECT id, password FROM users WHERE id = ? AND role = 'admin'");
            $stmt->execute([$admin_id]);
            $admin = $stmt->fetch();
            
            if (!$admin || !password_verify($current_password, $admin['password'])) {
                $errors[] = "Current password is incorrect";
            } else {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ?, password_changed = 1, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashed_password, $admin_id]);
                
                // Log the password change
                $stmt = $db->prepare("INSERT INTO admin_logs (admin_id, action, details, ip_address, created_at) VALUES (?, 'password_change', 'First login password change completed', ?, NOW())");
                $stmt->execute([$admin_id, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                
                $_SESSION['password_changed'] = true;
                $_SESSION['success_message'] = "Password changed successfully. You can now access the admin panel.";
                header('Location: login.php');
                exit;
            }
        } catch (Exception $e) {
            error_log("Password change failed: " . $e->getMessage());
            $errors[] = "An error occurred while changing the password. Please try again.";
        }
    }
}

// Get admin users that need password change
$admin_users = [];
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, email FROM users WHERE role = 'admin' AND password_changed = 0");
    $stmt->execute();
    $admin_users = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch admin users: " . $e->getMessage());
}

if (empty($admin_users)) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>First Login Setup - LoanFlow Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .setup-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .setup-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .password-requirements {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 1rem;
            margin: 1rem 0;
        }
        .requirement {
            display: flex;
            align-items: center;
            margin: 0.5rem 0;
        }
        .requirement i {
            margin-right: 0.5rem;
            width: 16px;
        }
        .valid { color: #28a745; }
        .invalid { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="setup-card">
                    <div class="setup-header">
                        <i class="fas fa-shield-alt fa-3x mb-3"></i>
                        <h2>Security Setup Required</h2>
                        <p class="mb-0">Please change the default admin password before accessing the system</p>
                    </div>
                    
                    <div class="p-4">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="passwordForm">
                            <div class="mb-3">
                                <label for="admin_id" class="form-label">Admin Account</label>
                                <select class="form-select" id="admin_id" name="admin_id" required>
                                    <option value="">Select admin account</option>
                                    <?php foreach ($admin_users as $admin): ?>
                                        <option value="<?php echo $admin['id']; ?>">
                                            <?php echo htmlspecialchars($admin['username'] . ' (' . $admin['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Default password: admin123</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="password-requirements">
                                <h6><i class="fas fa-info-circle"></i> Password Requirements:</h6>
                                <div class="requirement" id="req-length">
                                    <i class="fas fa-times invalid"></i>
                                    <span>At least 8 characters long</span>
                                </div>
                                <div class="requirement" id="req-uppercase">
                                    <i class="fas fa-times invalid"></i>
                                    <span>At least one uppercase letter</span>
                                </div>
                                <div class="requirement" id="req-lowercase">
                                    <i class="fas fa-times invalid"></i>
                                    <span>At least one lowercase letter</span>
                                </div>
                                <div class="requirement" id="req-number">
                                    <i class="fas fa-times invalid"></i>
                                    <span>At least one number</span>
                                </div>
                                <div class="requirement" id="req-special">
                                    <i class="fas fa-times invalid"></i>
                                    <span>At least one special character (@$!%*?&)</span>
                                </div>
                                <div class="requirement" id="req-match">
                                    <i class="fas fa-times invalid"></i>
                                    <span>Passwords match</span>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100" id="submitBtn" disabled>
                                <i class="fas fa-key me-2"></i>Change Password & Continue
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        function validatePassword() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Check length
            const lengthValid = password.length >= 8;
            updateRequirement('req-length', lengthValid);
            
            // Check uppercase
            const uppercaseValid = /[A-Z]/.test(password);
            updateRequirement('req-uppercase', uppercaseValid);
            
            // Check lowercase
            const lowercaseValid = /[a-z]/.test(password);
            updateRequirement('req-lowercase', lowercaseValid);
            
            // Check number
            const numberValid = /\d/.test(password);
            updateRequirement('req-number', numberValid);
            
            // Check special character
            const specialValid = /[@$!%*?&]/.test(password);
            updateRequirement('req-special', specialValid);
            
            // Check match
            const matchValid = password === confirmPassword && password.length > 0;
            updateRequirement('req-match', matchValid);
            
            // Enable submit button if all requirements are met
            const allValid = lengthValid && uppercaseValid && lowercaseValid && numberValid && specialValid && matchValid;
            document.getElementById('submitBtn').disabled = !allValid;
        }
        
        function updateRequirement(reqId, isValid) {
            const req = document.getElementById(reqId);
            const icon = req.querySelector('i');
            
            if (isValid) {
                icon.classList.remove('fa-times', 'invalid');
                icon.classList.add('fa-check', 'valid');
            } else {
                icon.classList.remove('fa-check', 'valid');
                icon.classList.add('fa-times', 'invalid');
            }
        }
        
        // Add event listeners
        document.getElementById('new_password').addEventListener('input', validatePassword);
        document.getElementById('confirm_password').addEventListener('input', validatePassword);
    </script>
</body>
</html>