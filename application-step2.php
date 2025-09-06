<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/language.php';
require_once 'includes/security.php';
require_once 'includes/captcha.php';
require_once 'includes/chatbot.php';
require_once 'config/countries.php';
require_once 'includes/email.php';

// Initialize language
initializeLanguage();

// Start session to get step 1 data
session_start();

// Check if step 1 data exists
if (!isset($_SESSION['application_step1'])) {
    header('Location: application-step1.php');
    exit;
}

// Get user's country and settings
$user_country = getUserCountry();
$country_settings = getCountrySettings($user_country);

// Get payment scheme
$payment_scheme = getActivePaymentScheme();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $errors = [];
        
        // Validate required fields
        if (empty($_POST['monthly_income'])) $errors[] = 'Monthly income is required';
        if (empty($_POST['password'])) $errors[] = 'Password is required';
        if (empty($_POST['agree_terms'])) $errors[] = 'You must agree to the terms and conditions';
        if (empty($_POST['consent_credit_check'])) $errors[] = 'You must consent to credit check';
        
        // Validate password length
        if (!empty($_POST['password']) && strlen($_POST['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        
        // Validate monthly income
        if (!empty($_POST['monthly_income']) && floatval($_POST['monthly_income']) < 0) {
            $errors[] = 'Monthly income must be a positive number';
        }
        
        // Validate CAPTCHA if enabled
        if (CaptchaManager::shouldProtectForm('loan_application')) {
            if (!CaptchaManager::validateCaptcha('loan_application_form', $_POST)) {
                $errors[] = 'Please complete the CAPTCHA verification';
            }
        }
        
        if (empty($errors)) {
            try {
                // Combine step 1 and step 2 data
                $application_data = array_merge($_SESSION['application_step1'], $_POST);
                
                // Remove CSRF token and submit button from data
                unset($application_data['csrf_token']);
                unset($application_data['submit_application']);
                unset($application_data['next_step']);
                
                // Hash the password
                $application_data['password'] = password_hash($application_data['password'], PASSWORD_DEFAULT);
                
                // Generate reference number
                $reference_number = generateReferenceNumber();
                
                // Insert into database
                $stmt = $pdo->prepare("
                    INSERT INTO loan_applications (
                        reference_number, first_name, last_name, email, phone, date_of_birth, sin_ssn,
                        address, city, state_province, postal_zip, loan_amount, loan_type, loan_purpose,
                        monthly_income, employment_status, employer_name, employment_duration, existing_debts,
                        password, status, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW()
                    )
                ");
                
                $stmt->execute([
                    $reference_number,
                    $application_data['first_name'],
                    $application_data['last_name'],
                    $application_data['email'],
                    $application_data['phone'],
                    $application_data['date_of_birth'],
                    $application_data['sin_ssn'] ?? null,
                    $application_data['address'] ?? null,
                    $application_data['city'] ?? null,
                    $application_data['state_province'] ?? null,
                    $application_data['postal_zip'] ?? null,
                    $application_data['loan_amount'],
                    $application_data['loan_type'],
                    $application_data['loan_purpose'] ?? null,
                    $application_data['monthly_income'],
                    $application_data['employment_status'] ?? null,
                    $application_data['employer_name'] ?? null,
                    $application_data['employment_duration'] ?? null,
                    $application_data['existing_debts'] ?? 0
                ]);
                
                // Send confirmation email
                $email_sent = sendApplicationConfirmationEmail(
                    $application_data['email'],
                    $application_data['first_name'],
                    $reference_number
                );
                
                // Clear session data
                unset($_SESSION['application_step1']);
                
                // Redirect to success page
                header('Location: application-success.php?ref=' . urlencode($reference_number));
                exit;
                
            } catch (Exception $e) {
                error_log('Application submission error: ' . $e->getMessage());
                $error = 'An error occurred while processing your application. Please try again.';
            }
        }
    }
}

// Handle back button
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['back_step'])) {
    // Store current form data in session temporarily
    $_SESSION['application_step2_temp'] = $_POST;
    header('Location: application-step1.php');
    exit;
}

// Get temporary step 2 data if returning from step 1
$step2_data = $_SESSION['application_step2_temp'] ?? [];
unset($_SESSION['application_step2_temp']);
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Application - Step 2 - QuickFunds</title>
    <link href="FrontEnd_Template/css/bootstrap.min.css" rel="stylesheet">
    <link href="FrontEnd_Template/css/style.css" rel="stylesheet">
    <link href="FrontEnd_Template/animation/aos.css" rel="stylesheet">
    <link href="FrontEnd_Template/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
  <div class="main-wrapper">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
      <div class="container">
        <a class="navbar-brand" href="index.php">
          <img src="FrontEnd_Template/images/logo.png" alt="QuickFunds Logo" height="40">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
          <ul class="navbar-nav ms-auto">
            <li class="nav-item">
              <a class="nav-link" href="index.php">Home</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="index.php#service">Services</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="index.php#howwework">How We Work</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="index.php#aboutus">About</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="login.php">Login</a>
            </li>
          </ul>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <div class="service-bg" style="padding-top: 120px;">
      <div class="container">
        <div class="row justify-content-center">
          <div class="col-lg-10">
            <!-- Progress Indicator -->
            <div class="mb-4">
              <div class="d-flex justify-content-between align-items-center">
                <div class="step-indicator completed">
                  <div class="step-number"><i class="fas fa-check"></i></div>
                  <div class="step-label">Personal & Loan Info</div>
                </div>
                <div class="progress-line completed"></div>
                <div class="step-indicator active">
                  <div class="step-number">2</div>
                  <div class="step-label">Employment & Security</div>
                </div>
              </div>
            </div>
            
            <div class="about-box">
              <div class="text-center mb-4">
                <h3 class="service-title">Loan Application - Step 2</h3>
                <p class="works-subtext">Please provide your employment information and create your account</p>
              </div>
              <div class="p-4">
                <?php if (isset($error)): ?>
                  <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                  </div>
                <?php endif; ?>

                <?php if (isset($errors) && !empty($errors)): ?>
                  <div class="alert alert-danger">
                    <h6>Please correct the following errors:</h6>
                    <ul class="mb-0">
                      <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endif; ?>

                <form method="POST" action="" id="applicationStep2Form">
                  <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                  
                  <!-- Employment Information -->
                  <div class="section-header">
                    <h5><i class="fas fa-briefcase me-2"></i>Employment Information</h5>
                  </div>
                  
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label class="form-label">Monthly Income *</label>
                      <div class="input-group">
                        <span class="input-group-text"><?= $country_settings['currency_symbol'] ?></span>
                        <input type="number" class="form-control" name="monthly_income" value="<?= htmlspecialchars($step2_data['monthly_income'] ?? $_POST['monthly_income'] ?? '') ?>" min="0" step="0.01" required>
                      </div>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label">Employment Status</label>
                      <select class="form-select" name="employment_status">
                        <option value="">Select...</option>
                        <option value="employed" <?= ($step2_data['employment_status'] ?? $_POST['employment_status'] ?? '') === 'employed' ? 'selected' : '' ?>>Employed</option>
                        <option value="self_employed" <?= ($step2_data['employment_status'] ?? $_POST['employment_status'] ?? '') === 'self_employed' ? 'selected' : '' ?>>Self-Employed</option>
                        <option value="unemployed" <?= ($step2_data['employment_status'] ?? $_POST['employment_status'] ?? '') === 'unemployed' ? 'selected' : '' ?>>Unemployed</option>
                        <option value="retired" <?= ($step2_data['employment_status'] ?? $_POST['employment_status'] ?? '') === 'retired' ? 'selected' : '' ?>>Retired</option>
                        <option value="student" <?= ($step2_data['employment_status'] ?? $_POST['employment_status'] ?? '') === 'student' ? 'selected' : '' ?>>Student</option>
                      </select>
                    </div>
                  </div>
                  
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label class="form-label">Employer Name</label>
                      <input type="text" class="form-control" name="employer_name" value="<?= htmlspecialchars($step2_data['employer_name'] ?? $_POST['employer_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label">Employment Duration</label>
                      <input type="text" class="form-control" name="employment_duration" value="<?= htmlspecialchars($step2_data['employment_duration'] ?? $_POST['employment_duration'] ?? '') ?>" placeholder="e.g., 2 years">
                    </div>
                  </div>
                  
                  <div class="mb-3">
                    <label class="form-label">Existing Monthly Debt Payments</label>
                    <div class="input-group">
                      <span class="input-group-text"><?= $country_settings['currency_symbol'] ?></span>
                      <input type="number" class="form-control" name="existing_debts" value="<?= htmlspecialchars($step2_data['existing_debts'] ?? $_POST['existing_debts'] ?? '0') ?>" min="0" step="0.01">
                    </div>
                    <small class="form-text text-muted">Include credit cards, other loans, etc.</small>
                  </div>
                  
                  <!-- Account Security -->
                  <div class="section-header mt-4">
                    <h5><i class="fas fa-lock me-2"></i>Account Security</h5>
                  </div>
                  
                  <div class="mb-3">
                    <label class="form-label">Password *</label>
                    <input type="password" class="form-control" name="password" required minlength="8">
                    <small class="form-text text-muted">Minimum 8 characters</small>
                  </div>
                  
                  <!-- Payment Information -->
                  <?php if ($payment_scheme): ?>
                  <div class="section-header mt-4">
                    <h5><i class="fas fa-credit-card me-2"></i>Service Fee Information</h5>
                  </div>
                  
                  <div class="alert alert-info">
                    <?php if ($payment_scheme['scheme_type'] === 'subscription'): ?>
                      <h6>Subscription Plan</h6>
                      <p class="mb-1">Monthly Service Fee: <strong><?= formatCurrency($payment_scheme['subscription_fee'], $user_country) ?></strong></p>
                      <p class="mb-1">Maximum Duration: <strong><?= $payment_scheme['max_subscription_months'] ?> months</strong></p>
                      <p class="mb-0">6-Month Funding Guarantee with <?= $payment_scheme['refund_policy_subscription'] ?>% refund policy</p>
                    <?php else: ?>
                      <h6>Percentage Fee Plan</h6>
                      <p class="mb-1">Service Fee: <strong><?= $payment_scheme['percentage_fee'] ?>% of loan amount</strong></p>
                      <p class="mb-0">One-time fee with <?= $payment_scheme['refund_policy_percentage'] ?>% refund policy</p>
                    <?php endif; ?>
                  </div>
                  <?php endif; ?>
                  
                  <!-- Terms and Conditions -->
                  <div class="section-header mt-4">
                    <h5><i class="fas fa-file-contract me-2"></i>Terms & Conditions</h5>
                  </div>
                  
                  <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="agree_terms" id="agreeTerms" required>
                    <label class="form-check-label" for="agreeTerms">
                      I agree to the <a href="terms.php" target="_blank">Terms and Conditions</a> and <a href="privacy.php" target="_blank">Privacy Policy</a> *
                    </label>
                  </div>
                  
                  <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="consent_credit_check" id="consentCredit" required>
                    <label class="form-check-label" for="consentCredit">
                      <?= __('consent_credit_check') ?> *
                    </label>
                  </div>
                  
                  <!-- CAPTCHA -->
                  <?php if (CaptchaManager::shouldProtectForm('loan_application')): ?>
                    <div class="mb-4">
                      <?= CaptchaManager::generateHTML('loan_application_form') ?>
                    </div>
                  <?php endif; ?>
                  
                  <div class="d-flex justify-content-between">
                    <button type="submit" name="back_step" class="btn btn-outline-secondary">
                      <i class="fas fa-arrow-left me-2"></i>Previous Step
                    </button>
                    <button type="submit" name="submit_application" class="btn btn-primary btn-lg">
                      <i class="fas fa-paper-plane me-2"></i><?= __('submit_application') ?>
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- AI Chatbot -->
  <?= ChatbotManager::generateWidget() ?>
  <?= ChatbotManager::generateCSS() ?>

  <script src="FrontEnd_Template/js/bootstrap.bundle.js"></script>
  <script src="FrontEnd_Template/js/bootstrap.min.js"></script>
  <?= CaptchaManager::generateJS('loan_application_form') ?>
  <script src="FrontEnd_Template/animation/aos.js"></script>
  <script>
    AOS.init({
      duration: 1000,
    });
    
    window.onscroll = function() {
      var navbar = document.querySelector('.navbar');
      
      if (window.scrollY > 100) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    };
  </script>
  <?= ChatbotManager::generateJS() ?>
  
  <style>
    .step-indicator {
      display: flex;
      flex-direction: column;
      align-items: center;
      position: relative;
    }
    
    .step-number {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background-color: #e9ecef;
      color: #6c757d;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      margin-bottom: 8px;
    }
    
    .step-indicator.active .step-number {
      background-color: #1B424C;
      color: white;
    }
    
    .step-indicator.completed .step-number {
      background-color: #1B424C;
      color: white;
    }
    
    .step-label {
      font-size: 14px;
      text-align: center;
      color: #6c757d;
    }
    
    .step-indicator.active .step-label {
      color: #1B424C;
      font-weight: 600;
    }
    
    .step-indicator.completed .step-label {
      color: #1B424C;
      font-weight: 600;
    }
    
    .progress-line {
      flex: 1;
      height: 2px;
      background-color: #e9ecef;
      margin: 0 20px;
      margin-top: -20px;
    }
    
    .progress-line.completed {
      background-color: #1B424C;
    }
    
    .section-header {
      border-bottom: 2px solid #f8f9fa;
      padding-bottom: 10px;
      margin-bottom: 20px;
    }
    
    .section-header h5 {
      color: #495057;
      margin: 0;
    }
  </style>
  
  <!-- Footer -->
  <div id="footer-placeholder"></div>
  
  <script>
    function loadFooter() {
        let isFileProtocol = window.location.protocol === 'file:';
        
        if (isFileProtocol) {
            // For file:// protocol, show a message about CORS limitations
            document.getElementById('footer-placeholder').innerHTML = `
                <div class="container text-center py-5">
                    <div class="alert alert-warning">
                        <h5>Footer Loading Limited</h5>
                        <p>When accessing via file:// protocol, the footer cannot load due to browser security restrictions (CORS).</p>
                        <p><strong>To see the complete website with footer:</strong></p>
                        <p>Please use: <code>http://localhost:8000/application-step2.php</code></p>
                    </div>
                </div>
            `;
            return;
        }
        
        fetch('footer.html')
            .then(response => response.text())
            .then(data => {
                document.getElementById('footer-placeholder').innerHTML = data;
            })
            .catch(error => console.error('Error loading footer:', error));
    }
    
    document.addEventListener('DOMContentLoaded', loadFooter);
  </script>
</body>
</html>