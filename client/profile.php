<?php
/**
 * Client Profile - User Profile Management
 * LoanFlow Personal Loan Management System
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require client login
requireLogin();

$current_user = getCurrentUser();
$application = getApplicationByUserId($current_user['id']);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $db = getDB();
        
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $date_of_birth = $_POST['date_of_birth'];
        $address = trim($_POST['address']);
        $city = trim($_POST['city']);
        $state = trim($_POST['state']);
        $zip_code = trim($_POST['zip_code']);
        $country = $_POST['country'];
        
        // Validation
        if (empty($first_name) || empty($last_name) || empty($email)) {
            throw new Exception('First name, last name, and email are required.');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }
        
        // Check if email is already taken by another user
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $current_user['id']]);
        if ($stmt->fetch()) {
            throw new Exception('This email address is already registered.');
        }
        
        // Update user profile
        $stmt = $db->prepare("
            UPDATE users SET 
                first_name = ?, last_name = ?, email = ?, phone = ?, 
                date_of_birth = ?, address = ?, city = ?, state = ?, 
                zip_code = ?, country = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $first_name, $last_name, $email, $phone,
            $date_of_birth ?: null, $address, $city, $state,
            $zip_code, $country, $current_user['id']
        ]);
        
        setFlashMessage('Profile updated successfully!', 'success');
        header('Location: profile.php');
        exit;
        
    } catch (Exception $e) {
        error_log("Profile update failed: " . $e->getMessage());
        setFlashMessage($e->getMessage(), 'error');
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    try {
        $db = getDB();
        
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validation
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            throw new Exception('All password fields are required.');
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception('New passwords do not match.');
        }
        
        if (strlen($new_password) < 8) {
            throw new Exception('New password must be at least 8 characters long.');
        }
        
        // Verify current password
        if (!password_verify($current_password, $current_user['password'])) {
            throw new Exception('Current password is incorrect.');
        }
        
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hashed_password, $current_user['id']]);
        
        setFlashMessage('Password changed successfully!', 'success');
        header('Location: profile.php');
        exit;
        
    } catch (Exception $e) {
        error_log("Password change failed: " . $e->getMessage());
        setFlashMessage($e->getMessage(), 'error');
    }
}

// Handle notification preferences
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notifications'])) {
    try {
        $db = getDB();
        
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $marketing_emails = isset($_POST['marketing_emails']) ? 1 : 0;
        
        // Update or insert notification preferences
        $stmt = $db->prepare("
            INSERT INTO user_preferences (user_id, email_notifications, sms_notifications, marketing_emails, updated_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                email_notifications = VALUES(email_notifications),
                sms_notifications = VALUES(sms_notifications),
                marketing_emails = VALUES(marketing_emails),
                updated_at = NOW()
        ");
        $stmt->execute([$current_user['id'], $email_notifications, $sms_notifications, $marketing_emails]);
        
        setFlashMessage('Notification preferences updated successfully!', 'success');
        header('Location: profile.php');
        exit;
        
    } catch (Exception $e) {
        error_log("Notification preferences update failed: " . $e->getMessage());
        setFlashMessage($e->getMessage(), 'error');
    }
}

// Get user preferences
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$current_user['id']]);
    $preferences = $stmt->fetch() ?: [
        'email_notifications' => 1,
        'sms_notifications' => 1,
        'marketing_emails' => 0
    ];
} catch (Exception $e) {
    error_log("Preferences fetch failed: " . $e->getMessage());
    $preferences = [
        'email_notifications' => 1,
        'sms_notifications' => 1,
        'marketing_emails' => 0
    ];
}

// Get fresh user data
$current_user = getCurrentUser();

// Countries list
$countries = [
    'US' => 'United States',
    'CA' => 'Canada',
    'GB' => 'United Kingdom',
    'AU' => 'Australia',
    'DE' => 'Germany',
    'FR' => 'France',
    'IT' => 'Italy',
    'ES' => 'Spain',
    'NL' => 'Netherlands',
    'BE' => 'Belgium',
    'CH' => 'Switzerland',
    'AT' => 'Austria',
    'SE' => 'Sweden',
    'NO' => 'Norway',
    'DK' => 'Denmark',
    'FI' => 'Finland'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - QuickFunds</title>
    <link rel="stylesheet" href="../FrontEnd_Template/css/bootstrap.min.css">
    <link rel="stylesheet" href="../FrontEnd_Template/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/client.css" rel="stylesheet">
    <style>
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            margin: 0 auto 20px;
        }
        .profile-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .section-title {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .verification-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 12px;
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
                            <a class="nav-link" href="messages.php">
                                <i class="fas fa-envelope me-1"></i>Messages
                            </a>
                        </li>
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?= htmlspecialchars($current_user['first_name']) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item active" href="profile.php">
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

            <div class="row">
                <!-- Profile Overview -->
                <div class="col-lg-4">
                    <div class="profile-section text-center">
                        <div class="profile-avatar">
                            <?= strtoupper(substr($current_user['first_name'], 0, 1) . substr($current_user['last_name'], 0, 1)) ?>
                        </div>
                        <h4><?= htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']) ?></h4>
                        <p class="text-muted"><?= htmlspecialchars($current_user['email']) ?></p>
                        
                        <div class="row text-center mt-4">
                            <div class="col-6">
                                <div class="border-end">
                                    <h5 class="text-primary mb-0"><?= $current_user['status'] === 'active' ? 'Active' : 'Inactive' ?></h5>
                                    <small class="text-muted">Account Status</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <h5 class="text-success mb-0"><?= date('M Y', strtotime($current_user['created_at'])) ?></h5>
                                <small class="text-muted">Member Since</small>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <div class="info-item">
                                <span><i class="fas fa-envelope me-2"></i>Email</span>
                                <span class="verification-badge bg-<?= $current_user['email_verified'] ? 'success' : 'warning' ?> text-white">
                                    <?= $current_user['email_verified'] ? 'Verified' : 'Unverified' ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span><i class="fas fa-phone me-2"></i>Phone</span>
                                <span class="verification-badge bg-<?= $current_user['phone_verified'] ? 'success' : 'warning' ?> text-white">
                                    <?= $current_user['phone_verified'] ? 'Verified' : 'Unverified' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="profile-section">
                        <h5 class="section-title"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                        <div class="d-grid gap-2">
                            <a href="dashboard.php" class="btn btn-outline-primary">
                                <i class="fas fa-tachometer-alt me-2"></i>View Dashboard
                            </a>
                            <a href="documents.php" class="btn btn-outline-secondary">
                                <i class="fas fa-folder me-2"></i>Manage Documents
                            </a>
                            <a href="messages.php" class="btn btn-outline-info">
                                <i class="fas fa-envelope me-2"></i>Contact Support
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Profile Forms -->
                <div class="col-lg-8">
                    <!-- Personal Information -->
                    <div class="profile-section">
                        <h5 class="section-title"><i class="fas fa-user me-2"></i>Personal Information</h5>
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" class="form-control" name="first_name" 
                                           value="<?= htmlspecialchars($current_user['first_name']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" name="last_name" 
                                           value="<?= htmlspecialchars($current_user['last_name']) ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?= htmlspecialchars($current_user['email']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone" 
                                           value="<?= htmlspecialchars($current_user['phone'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" name="date_of_birth" 
                                           value="<?= $current_user['date_of_birth'] ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Country</label>
                                    <select class="form-select" name="country">
                                        <option value="">Select Country</option>
                                        <?php foreach ($countries as $code => $name): ?>
                                            <option value="<?= $code ?>" <?= $current_user['country'] === $code ? 'selected' : '' ?>>
                                                <?= $name ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" name="address" 
                                       value="<?= htmlspecialchars($current_user['address'] ?? '') ?>">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" name="city" 
                                           value="<?= htmlspecialchars($current_user['city'] ?? '') ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">State/Province</label>
                                    <input type="text" class="form-control" name="state" 
                                           value="<?= htmlspecialchars($current_user['state'] ?? '') ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">ZIP/Postal Code</label>
                                    <input type="text" class="form-control" name="zip_code" 
                                           value="<?= htmlspecialchars($current_user['zip_code'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Update Profile
                            </button>
                        </form>
                    </div>
                    
                    <!-- Security Settings -->
                    <div class="profile-section">
                        <h5 class="section-title"><i class="fas fa-shield-alt me-2"></i>Security Settings</h5>
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Current Password *</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">New Password *</label>
                                    <input type="password" class="form-control" name="new_password" 
                                           minlength="8" required>
                                    <small class="text-muted">Minimum 8 characters</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm New Password *</label>
                                    <input type="password" class="form-control" name="confirm_password" 
                                           minlength="8" required>
                                </div>
                            </div>
                            
                            <button type="submit" name="change_password" class="btn btn-warning">
                                <i class="fas fa-key me-1"></i>Change Password
                            </button>
                        </form>
                    </div>
                    
                    <!-- Notification Preferences -->
                    <div class="profile-section">
                        <h5 class="section-title"><i class="fas fa-bell me-2"></i>Notification Preferences</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="email_notifications" 
                                           id="emailNotifications" <?= $preferences['email_notifications'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="emailNotifications">
                                        <strong>Email Notifications</strong><br>
                                        <small class="text-muted">Receive updates about your application via email</small>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sms_notifications" 
                                           id="smsNotifications" <?= $preferences['sms_notifications'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="smsNotifications">
                                        <strong>SMS Notifications</strong><br>
                                        <small class="text-muted">Receive important updates via text message</small>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="marketing_emails" 
                                           id="marketingEmails" <?= $preferences['marketing_emails'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="marketingEmails">
                                        <strong>Marketing Emails</strong><br>
                                        <small class="text-muted">Receive promotional offers and product updates</small>
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" name="update_notifications" class="btn btn-success">
                                <i class="fas fa-bell me-1"></i>Update Preferences
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../FrontEnd_Template/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password confirmation validation
            const newPassword = document.querySelector('input[name="new_password"]');
            const confirmPassword = document.querySelector('input[name="confirm_password"]');
            
            if (newPassword && confirmPassword) {
                function validatePasswords() {
                    if (newPassword.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Passwords do not match');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                }
                
                newPassword.addEventListener('input', validatePasswords);
                confirmPassword.addEventListener('input', validatePasswords);
            }
            
            // Phone number formatting
            const phoneInput = document.querySelector('input[name="phone"]');
            if (phoneInput) {
                phoneInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length >= 6) {
                        value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
                    } else if (value.length >= 3) {
                        value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
                    }
                    e.target.value = value;
                });
            }
        });
    </script>
</body>
</html>