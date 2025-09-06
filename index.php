<?php
/**
 * Homepage - QuickFunds Loan Application
 * Using FrontEnd_Template Design
 */

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/captcha.php';
require_once 'includes/language.php';
require_once 'includes/chatbot.php';

// Initialize language system
LanguageManager::init();

// Get user's country based on IP (or default to USA)
$user_country = getCountryFromIP();
$country_settings = getCountrySettings($user_country);

// Get active payment scheme
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM payment_schemes WHERE is_active = 1 LIMIT 1");
    $stmt->execute();
    $payment_scheme = $stmt->fetch();
} catch (Exception $e) {
    $payment_scheme = null;
}
?>
<!DOCTYPE html>
<html lang="<?= LanguageManager::getCurrentLanguage() ?>">
<head>
    <meta charset="utf-8">
    <title>QuickFunds | Home</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" type="text/css" href="css/bootstrap.css">
    <link href="bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="animation/aos.css">

    <link rel="stylesheet" type="text/css" href="css/style.css">
    <link rel="stylesheet" type="text/css" href="css/responsive.css">
</head>
<body>
  <div class="wrapper">
    <header class="header">
      <nav class="navbar navbar-expand-lg">
        <div class="container">
          <a class="navbar-brand" href="index.php"><img src="images/logo.png" alt="logo"></a>
          <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarText" aria-controls="navbarText" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
          </button>
          <div class="collapse navbar-collapse justify-content-between" id="navbarText">
            <ul class="navbar-nav w-100 justify-content-center">
              <li class="nav-item">
                <a class="nav-link" href="index.php">Home</a>
              </li>
              <li class="nav-item">
                <div class="dropdown">
                  <a class="nav-link" href="#service">Service</a>
                </div>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="#howwework">How We Work?</a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="#aboutus">About Us</a>
              </li>
            </ul>
            <a href="application-step1.php" class="headertop-btn">Apply Now</a>
          </div>
        </div>
      </nav>
    </header>
    <div class="main">
      <div class="container">
        <div class="row justify-content-between align-items-center mt-100">
          <div class="col-lg-6 col-md-12" data-aos="fade-up-right">
            <h1 class="maintitle-text">Quick and Easy Loans for Your Financial Needs.</h1>
            <p class="subtitle-text">Our loan services offer a hassle-free and streamlined borrowing experience, providing you with the funds you need in a timely manner to meet your financial requirements.</p>
            <a href="application-step1.php" class="getstart-btn">Get started</a>
          </div>
          <div class="col-lg-6 col-md-12" data-aos="fade-up-left">
            <img src="images/hero-img.png" class="img-box">
          </div>
        </div>
      </div>
      <div class="service-bg mt-100" data-aos="fade-up" id="service">
        <div class="container">
          <h3 class="service-title mb-3">Our Services</h3>
          <div class="row">
            <div class="col-lg-4 mt-3">
              <div class="service-box">
                <img src="images/service-img-1.png" alt="">
                <h4>Personal loan</h4>
                <p>Personal loans provide borrowers with flexibility in how they use the funds.</p>
                <a href="application-step1.php">Apply now</a>
              </div>
            </div>
            <div class="col-lg-4 mt-3">
              <div class="service-box">
                <img src="images/service-img-2.png" alt="">
                <h4>Business loan</h4>
                <p>Business Loan Services provide financial assistance to businesses for various purposes..</p>
                <a href="application-step1.php">Apply now</a>
              </div>
            </div>
            <div class="col-lg-4 mt-3">
              <div class="service-box">
                <img src="images/service-img-3.png" alt="">
                <h4>Auto loan</h4>
                <p>Auto Loan Services provide financing options for individuals businesses to purchase a vehicle.</p>
                <a href="application-step1.php">Apply now</a>
              </div>
            </div>
          </div>
          <div class="text-center mt-4">
            <a href="application-step1.php" class="view-btn">Apply for Loan</a>
          </div>
        </div>
      </div>
      <div class="mt-100" id="howwework">
        <div class="container">
          <div class="service-title">How we works ?</div>
          <p class="works-subtext text-center">This is a process, how you can get loan for your self.</p>
          <div class="row justify-content-around align-items-center mt-50 mobile-column">
            <div class="col-lg-5" data-aos="fade-up-right">
              <img src="images/works-img-1.png" alt="" class="img-box">
            </div>
            <div class="col-lg-6 position-relative" data-aos="fade-up-left">
              <h4 class="process-number">01</h4>
              <h3 class="works-text">Application</h3>
              <p class="works-subtext">The borrower submits a loan application to the bank, either in person, online, or through other channels. The application includes personal and financial information, such as income, employment history, credit score, and the purpose of the loan.</p>
            </div>
          </div>
          <div class="row justify-content-around align-items-center mt-50 pad-left">
            <div class="col-lg-6 position-relative" data-aos="fade-up-right">
              <h4 class="process-number">02</h4>
              <h3 class="works-text">Documentation and Verification</h3>
              <p class="works-subtext">The bank requests supporting documents from the borrower, such as identification proof, income statements, bank statements, and collateral details (if applicable). The bank verifies the information provided to assess the borrower's creditworthiness and eligibility for the loan.</p>
            </div>
            <div class="col-lg-5" data-aos="fade-up-left">
              <img src="images/works-img-2.png" alt="" class="img-box">
            </div>
          </div>
          <div class="row justify-content-around align-items-center mt-50 mobile-column">
            <div class="col-lg-5" data-aos="fade-up-right">
              <img src="images/works-img-3.png" alt="" class="img-box">
            </div>
            <div class="col-lg-6 position-relative" data-aos="fade-up-left">
              <h4 class="process-number">03</h4>
              <h3 class="works-text">Credit Assessment</h3>
              <p class="works-subtext">The bank conducts a credit assessment to evaluate the borrower's creditworthiness and ability to repay the loan. This process involves analyzing the borrower's credit history, income stability, debt-to-income ratio, and other factors.</p>
            </div>
          </div>
          <div class="row justify-content-around align-items-center mt-50 pad-left">
            <div class="col-lg-6 position-relative" data-aos="fade-up-right">
              <h4 class="process-number">04</h4>
              <h3 class="works-text">Loan Approval</h3>
              <p class="works-subtext">If the borrower meets the bank's lending criteria and passes the credit assessment, the loan is approved. The bank determines the loan amount, interest rate, repayment term, and any associated fees.</p>
            </div>
            <div class="col-lg-5" data-aos="fade-up-left">
              <img src="images/work-img-4.png" alt="" class="img-box">
            </div>
          </div>
        </div>
      </div>
      <div class="mt-100" id="faq" data-aos="fade-up">
        <div class="container">
          <div class="service-title">FAQ</div>
          <div class="accordion mt-50" id="accordionExample">
            <div class="accordion-item">
              <h2 class="accordion-header" id="headingOne">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">What types of loans do you offer?</button>
              </h2>
              <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                <div class="accordion-body">
                  <p class="mb-0">We offer personal loans, business loans, and auto loans with competitive interest rates and flexible repayment terms to meet your financial needs.</p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header" id="headingTwo">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">How quickly can I get approved?</button>
              </h2>
              <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#accordionExample">
                <div class="accordion-body">
                  <p class="mb-0">Our streamlined application process allows for quick approval, often within 24-48 hours of submitting your complete application with all required documents.</p>
                </div>
              </div>
            </div>
            <div class="accordion-item">
              <h2 class="accordion-header" id="headingThree">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">What are the eligibility requirements?</button>
              </h2>
              <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#accordionExample">
                <div class="accordion-body">
                  <p class="mb-0">You must be 18+ years old, have a steady income, valid identification, and meet our credit requirements. Specific requirements may vary by loan type and amount.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="mt-100" id="aboutus" data-aos="fade-up">
        <div class="container">
          <div class="row justify-content-between align-items-center">
            <div class="col-lg-6">
              <img src="images/about-us.png" alt="" class="img-box">
            </div>
            <div class="col-lg-6">
              <div class="about-content">
                <h3 class="about-title">About Us</h3>
                <p class="about-text">QuickFunds is dedicated to providing fast, reliable, and transparent loan services to help you achieve your financial goals. With years of experience in the lending industry, we understand the importance of quick access to funds when you need them most.</p>
                <form class="about-form">
                  <div class="row">
                    <div class="col-lg-6">
                      <input type="text" class="form-control" placeholder="Your Name" required>
                    </div>
                    <div class="col-lg-6">
                      <input type="email" class="form-control" placeholder="Your Email" required>
                    </div>
                    <div class="col-lg-12 mt-3">
                      <textarea class="form-control" rows="4" placeholder="Your Message" required></textarea>
                    </div>
                    <div class="col-lg-12 mt-3">
                      <a href="#" class="about-btn">SEND</a>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <footer class="footer-box" data-aos="fade-up">
      <div class="container">
        <div class="row justify-content-between">
          <div class="col-lg-4">
            <h3 class="footer-title">Our Office</h3>
              <ul class="footer-ul-contact">
                <li><a href="#"><i class="bi bi-geo-alt-fill footer-contact-icons"></i>700 Well St. #308,<br> NV 89002</a></li>
                <li><a href="#"><i class="bi bi-telephone-fill footer-contact-icons"></i>1 888 489 8189</a></li>
                <li><a href="#"><i class="bi bi-envelope-fill footer-contact-icons"></i>support@quickfunds.com</a></li>
              </ul>
          </div>
          <div class="col-lg-4">
            <h3 class="footer-title">Quick Links</h3>
            <ul>
              <li><a href="#faq"><i class="bi bi-chevron-right pe-2"></i>FAQ's</a></li>
              <li><a href="#aboutus"><i class="bi bi-chevron-right pe-2"></i>About Us</a></li>
              <li><a href="#aboutus"><i class="bi bi-chevron-right pe-2"></i>Contact Us</a></li>
              <li><a href="terms.php"><i class="bi bi-chevron-right pe-2"></i>Terms & Condition</a></li>
              <li><a href="privacy.php"><i class="bi bi-chevron-right pe-2"></i>Privacy</a></li>
            </ul>
          </div>
          <div class="col-lg-4">
            <h3 class="footer-title">Business Hours</h3>
            <div class="position-relative">
              <p class="workday-title">Monday - Friday</p>
              <p class="mb-0">9:00am - 05:00pm</p>
            </div>
            <div class="position-relative">
              <p class="workday-title">Saturday</p>
              <p class="mb-0">09:00am - 02:00pm</p>
            </div>
          </div>
        </div>
      </div>
    </footer>
  </div>

  <script src="js/bootstrap.bundle.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script>
    window.onscroll = function() {
        var navbar = document.querySelector('.navbar');
        
        if (window.scrollY > 100) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    };
  </script>
  <script src="animation/aos.js"></script>
  <script>
    AOS.init(({
      duration: 1000,
    }))
  </script>

  <?= ChatbotManager::renderChatbot() ?>
</body>
</html>
