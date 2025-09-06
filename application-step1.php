<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/language.php';
require_once 'includes/security.php';
require_once 'includes/captcha.php';
require_once 'includes/chatbot.php';
require_once 'config/countries.php';

// Initialize language
initializeLanguage();

// Get user's country and settings
$user_country = getUserCountry();
$country_settings = getCountrySettings($user_country);

// Get payment scheme
$payment_scheme = getActivePaymentScheme();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['next_step'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $errors = [];
        
        // Validate required fields
        if (empty($_POST['first_name'])) $errors[] = 'First name is required';
        if (empty($_POST['last_name'])) $errors[] = 'Last name is required';
        if (empty($_POST['email'])) $errors[] = 'Email address is required';
        if (empty($_POST['phone'])) $errors[] = 'Phone number is required';
        if (empty($_POST['date_of_birth'])) $errors[] = 'Date of birth is required';
        if (empty($_POST['loan_amount'])) $errors[] = 'Loan amount is required';
        if (empty($_POST['loan_type'])) $errors[] = 'Loan type is required';
        
        // Validate email format
        if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address';
        }
        
        // Validate loan amount range
        if (!empty($_POST['loan_amount'])) {
            $loan_amount = floatval($_POST['loan_amount']);
            if ($loan_amount < 1000 || $loan_amount > 150000) {
                $errors[] = 'Loan amount must be between ' . formatCurrency(1000, $user_country) . ' and ' . formatCurrency(150000, $user_country);
            }
        }
        
        if (empty($errors)) {
            // Store step 1 data in session
            session_start();
            $_SESSION['application_step1'] = $_POST;
            
            // Redirect to step 2
            header('Location: application-step2.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Application - Step 1 - QuickFunds</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link href="animation/aos.css" rel="stylesheet">
    <link href="bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
  <div class="main-wrapper">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
      <div class="container">
        <a class="navbar-brand" href="index.php">
          <img src="images/logo.png" alt="QuickFunds Logo" height="40">
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
                <div class="step-indicator active">
                  <div class="step-number">1</div>
                  <div class="step-label">Personal & Loan Info</div>
                </div>
                <div class="progress-line"></div>
                <div class="step-indicator">
                  <div class="step-number">2</div>
                  <div class="step-label">Employment & Security</div>
                </div>
              </div>
            </div>
            
            <div class="about-box">
              <div class="text-center mb-4">
                <h3 class="service-title">Loan Application - Step 1</h3>
                <p class="works-subtext">Please provide your personal information and loan details</p>
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

                <form method="POST" action="" id="applicationStep1Form">
                  <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                  
                  <!-- Personal Information -->
                  <div class="section-header">
                    <h5><i class="fas fa-user me-2"></i>Personal Information</h5>
                  </div>
                  
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label class="form-label">First Name *</label>
                      <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label">Last Name *</label>
                      <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
                    </div>
                  </div>
                  
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label class="form-label">Email Address *</label>
                      <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label">Phone Number *</label>
                      <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="<?= $country_settings['phone_format'] ?>" required>
                    </div>
                  </div>
                  
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label class="form-label">Date of Birth *</label>
                      <input type="date" class="form-control" name="date_of_birth" value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label"><?= getTaxIdLabel($user_country) ?></label>
                      <input type="text" class="form-control" name="sin_ssn" value="<?= htmlspecialchars($_POST['sin_ssn'] ?? '') ?>" placeholder="<?= $country_settings['tax_id_format'] ?>">
                    </div>
                  </div>
                  
                  <!-- Address Information -->
                  <div class="section-header mt-4">
                    <h5><i class="fas fa-map-marker-alt me-2"></i>Address Information</h5>
                  </div>
                  
                  <div class="mb-3">
                    <label class="form-label">Street Address</label>
                    <input type="text" class="form-control" name="address" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                  </div>
                  
                  <div class="row">
                    <div class="col-md-4 mb-3">
                      <label class="form-label">City</label>
                      <input type="text" class="form-control" name="city" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                      <label class="form-label"><?= $user_country === 'USA' ? 'State' : 'Province' ?></label>
                      <select class="form-select" name="state_province">
                        <option value="">Select...</option>
                        <?php foreach (getStatesProvinces($user_country) as $code => $name): ?>
                          <option value="<?= $code ?>" <?= ($_POST['state_province'] ?? '') === $code ? 'selected' : '' ?>>
                            <?= htmlspecialchars($name) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-4 mb-3">
                      <label class="form-label"><?= $user_country === 'USA' ? 'ZIP Code' : 'Postal Code' ?></label>
                      <input type="text" class="form-control" name="postal_zip" value="<?= htmlspecialchars($_POST['postal_zip'] ?? '') ?>" placeholder="<?= $country_settings['postal_format'] ?>">
                    </div>
                  </div>
                  
                  <!-- Loan Information -->
                  <div class="section-header mt-4">
                    <h5><i class="fas fa-money-bill-wave me-2"></i>Loan Information</h5>
                  </div>
                  
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label class="form-label">Loan Amount *</label>
                      <div class="input-group">
                        <span class="input-group-text"><?= $country_settings['currency_symbol'] ?></span>
                        <input type="number" class="form-control" name="loan_amount" value="<?= htmlspecialchars($_POST['loan_amount'] ?? '') ?>" min="1000" max="150000" required>
                      </div>
                      <small class="form-text text-muted">Amount between <?= formatCurrency(1000, $user_country) ?> and <?= formatCurrency(150000, $user_country) ?></small>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label">Loan Type *</label>
                      <select class="form-select" name="loan_type" required>
                        <option value="">Select loan type...</option>
                        <option value="personal" <?= ($_POST['loan_type'] ?? '') === 'personal' ? 'selected' : '' ?>>Personal Loan</option>
                        <option value="debt_consolidation" <?= ($_POST['loan_type'] ?? '') === 'debt_consolidation' ? 'selected' : '' ?>>Debt Consolidation</option>
                        <option value="home_repair" <?= ($_POST['loan_type'] ?? '') === 'home_repair' ? 'selected' : '' ?>>Home Repair</option>
                        <option value="automotive" <?= ($_POST['loan_type'] ?? '') === 'automotive' ? 'selected' : '' ?>>Automotive</option>
                        <option value="business" <?= ($_POST['loan_type'] ?? '') === 'business' ? 'selected' : '' ?>>Business</option>
                        <option value="medical" <?= ($_POST['loan_type'] ?? '') === 'medical' ? 'selected' : '' ?>>Medical</option>
                      </select>
                    </div>
                  </div>
                  
                  <div class="mb-4">
                    <label class="form-label">Loan Purpose</label>
                    <textarea class="form-control" name="loan_purpose" rows="3" placeholder="Please describe what you plan to use the loan for..."><?= htmlspecialchars($_POST['loan_purpose'] ?? '') ?></textarea>
                  </div>
                  
                  <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-outline-secondary">
                      <i class="fas fa-arrow-left me-2"></i>Back to Home
                    </a>
                    <button type="submit" name="next_step" class="btn btn-primary">
                      Next Step <i class="fas fa-arrow-right ms-2"></i>
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

  <script src="js/bootstrap.bundle.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="animation/aos.js"></script>
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
    
    .step-label {
      font-size: 14px;
      text-align: center;
      color: #6c757d;
    }
    
    .step-indicator.active .step-label {
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
                        <p>Please use: <code>http://localhost:8000/application-step1.php</code></p>
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