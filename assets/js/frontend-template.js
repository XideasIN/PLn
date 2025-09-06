// LoanFlow Frontend Template - Interactive JavaScript

// DOM Content Loaded Event
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

// Initialize Application
function initializeApp() {
    initializeNavbar();
    initializeLoanCalculator();
    initializeFormValidation();
    initializeAnimations();
    initializeSmoothScrolling();
    initializeTooltips();
}

// Navbar Functionality
function initializeNavbar() {
    const navbar = document.querySelector('.navbar');
    const navbarToggler = document.querySelector('.navbar-toggler');
    const navbarCollapse = document.querySelector('.navbar-collapse');
    
    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
            navbar.classList.add('navbar-scrolled');
        } else {
            navbar.classList.remove('navbar-scrolled');
        }
    });
    
    // Close mobile menu when clicking on links
    document.querySelectorAll('.navbar-nav .nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (navbarCollapse.classList.contains('show')) {
                navbarToggler.click();
            }
        });
    });
}

// Loan Calculator Functionality
function initializeLoanCalculator() {
    const loanAmountSlider = document.getElementById('loanAmount');
    const loanTermSlider = document.getElementById('loanTerm');
    const interestRateSlider = document.getElementById('interestRate');
    
    const loanAmountValue = document.getElementById('loanAmountValue');
    const loanTermValue = document.getElementById('loanTermValue');
    const interestRateValue = document.getElementById('interestRateValue');
    
    const monthlyPaymentElement = document.getElementById('monthlyPayment');
    const totalPaymentElement = document.getElementById('totalPayment');
    const totalInterestElement = document.getElementById('totalInterest');
    
    // Update slider values and calculate loan
    function updateCalculator() {
        const loanAmount = parseFloat(loanAmountSlider.value);
        const loanTerm = parseInt(loanTermSlider.value);
        const interestRate = parseFloat(interestRateSlider.value);
        
        // Update display values
        loanAmountValue.textContent = formatCurrency(loanAmount);
        loanTermValue.textContent = loanTerm + ' months';
        interestRateValue.textContent = interestRate + '%';
        
        // Calculate loan payments
        const monthlyRate = interestRate / 100 / 12;
        const monthlyPayment = calculateMonthlyPayment(loanAmount, monthlyRate, loanTerm);
        const totalPayment = monthlyPayment * loanTerm;
        const totalInterest = totalPayment - loanAmount;
        
        // Update results
        monthlyPaymentElement.textContent = formatCurrency(monthlyPayment);
        totalPaymentElement.textContent = formatCurrency(totalPayment);
        totalInterestElement.textContent = formatCurrency(totalInterest);
        
        // Update slider backgrounds
        updateSliderBackground(loanAmountSlider);
        updateSliderBackground(loanTermSlider);
        updateSliderBackground(interestRateSlider);
    }
    
    // Calculate monthly payment using loan formula
    function calculateMonthlyPayment(principal, monthlyRate, numberOfPayments) {
        if (monthlyRate === 0) {
            return principal / numberOfPayments;
        }
        
        const monthlyPayment = principal * 
            (monthlyRate * Math.pow(1 + monthlyRate, numberOfPayments)) / 
            (Math.pow(1 + monthlyRate, numberOfPayments) - 1);
        
        return monthlyPayment;
    }
    
    // Update slider background to show progress
    function updateSliderBackground(slider) {
        const value = (slider.value - slider.min) / (slider.max - slider.min) * 100;
        slider.style.background = `linear-gradient(to right, #0d6efd 0%, #0d6efd ${value}%, #ddd ${value}%, #ddd 100%)`;
    }
    
    // Format currency
    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(amount);
    }
    
    // Add event listeners
    if (loanAmountSlider) {
        loanAmountSlider.addEventListener('input', updateCalculator);
        loanTermSlider.addEventListener('input', updateCalculator);
        interestRateSlider.addEventListener('input', updateCalculator);
        
        // Initial calculation
        updateCalculator();
    }
}

// Form Validation
function initializeFormValidation() {
    const applicationForm = document.getElementById('applicationForm');
    
    if (applicationForm) {
        applicationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (validateForm()) {
                submitForm();
            }
        });
        
        // Real-time validation
        const inputs = applicationForm.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', () => validateField(input));
            input.addEventListener('input', () => clearFieldError(input));
        });
    }
}

// Validate entire form
function validateForm() {
    const form = document.getElementById('applicationForm');
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!validateField(input)) {
            isValid = false;
        }
    });
    
    return isValid;
}

// Validate individual field
function validateField(field) {
    const value = field.value.trim();
    const fieldType = field.type;
    const fieldName = field.name;
    
    // Clear previous errors
    clearFieldError(field);
    
    // Required field validation
    if (field.hasAttribute('required') && !value) {
        showFieldError(field, 'This field is required');
        return false;
    }
    
    // Email validation
    if (fieldType === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            showFieldError(field, 'Please enter a valid email address');
            return false;
        }
    }
    
    // Phone validation
    if (fieldName === 'phone' && value) {
        const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
        if (!phoneRegex.test(value.replace(/[\s\-\(\)]/g, ''))) {
            showFieldError(field, 'Please enter a valid phone number');
            return false;
        }
    }
    
    // SSN validation
    if (fieldName === 'ssn' && value) {
        const ssnRegex = /^\d{3}-?\d{2}-?\d{4}$/;
        if (!ssnRegex.test(value)) {
            showFieldError(field, 'Please enter a valid SSN (XXX-XX-XXXX)');
            return false;
        }
    }
    
    // Zip code validation
    if (fieldName === 'zipCode' && value) {
        const zipRegex = /^\d{5}(-\d{4})?$/;
        if (!zipRegex.test(value)) {
            showFieldError(field, 'Please enter a valid zip code');
            return false;
        }
    }
    
    // Income validation
    if (fieldName === 'monthlyIncome' && value) {
        const income = parseFloat(value);
        if (isNaN(income) || income < 0) {
            showFieldError(field, 'Please enter a valid income amount');
            return false;
        }
    }
    
    // Show success state
    field.classList.add('is-valid');
    return true;
}

// Show field error
function showFieldError(field, message) {
    field.classList.add('is-invalid');
    field.classList.remove('is-valid');
    
    // Remove existing error message
    const existingError = field.parentNode.querySelector('.invalid-feedback');
    if (existingError) {
        existingError.remove();
    }
    
    // Add new error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback';
    errorDiv.textContent = message;
    field.parentNode.appendChild(errorDiv);
}

// Clear field error
function clearFieldError(field) {
    field.classList.remove('is-invalid');
    const errorMessage = field.parentNode.querySelector('.invalid-feedback');
    if (errorMessage) {
        errorMessage.remove();
    }
}

// Submit form
async function submitForm() {
    const form = document.getElementById('applicationForm');
    const submitBtn = document.querySelector('#applicationForm button[type="submit"]');
    const originalText = submitBtn.textContent;
    
    // Show loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    
    try {
        const formData = new FormData(form);
        const data = {};
        
        // Convert FormData to regular object
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        const response = await fetch('/api/submit-application.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Show success message
            showNotification(`Application submitted successfully! Reference: ${result.reference_number}`, 'success');
            
            // Reset form
            form.reset();
            
            // Clear validation states
            const inputs = document.querySelectorAll('.is-valid, .is-invalid');
            inputs.forEach(input => {
                input.classList.remove('is-valid', 'is-invalid');
            });
            
            // Redirect after delay
            setTimeout(() => {
                window.location.href = '/application-success.php?ref=' + result.reference_number;
            }, 3000);
        } else {
            showNotification(result.message || 'Application submission failed. Please try again.', 'error');
        }
    } catch (error) {
        console.error('Form submission error:', error);
        showNotification('Network error. Please check your connection and try again.', 'error');
    } finally {
        // Reset button
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

// Show notification
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification
    const notification = document.createElement('div');
    notification.className = `notification alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Initialize Animations
function initializeAnimations() {
    // Intersection Observer for scroll animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in-up');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    // Observe elements with animation class
    const animatedElements = document.querySelectorAll('.service-card, .process-step, .calculator-card');
    animatedElements.forEach(el => observer.observe(el));
}

// Smooth Scrolling
function initializeSmoothScrolling() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                const offsetTop = target.offsetTop - 80; // Account for fixed navbar
                window.scrollTo({
                    top: offsetTop,
                    behavior: 'smooth'
                });
            }
        });
    });
}

// Initialize Tooltips
function initializeTooltips() {
    // Initialize Bootstrap tooltips if available
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
}

// Utility Functions

// Format phone number as user types
function formatPhoneNumber(input) {
    const value = input.value.replace(/\D/g, '');
    const formattedValue = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
    input.value = formattedValue;
}

// Format SSN as user types
function formatSSN(input) {
    const value = input.value.replace(/\D/g, '');
    const formattedValue = value.replace(/(\d{3})(\d{2})(\d{4})/, '$1-$2-$3');
    input.value = formattedValue;
}

// Format currency input
function formatCurrencyInput(input) {
    const value = input.value.replace(/[^\d.]/g, '');
    const numericValue = parseFloat(value);
    if (!isNaN(numericValue)) {
        input.value = numericValue.toLocaleString('en-US');
    }
}

// Debounce function for performance
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Add event listeners for formatted inputs
document.addEventListener('DOMContentLoaded', function() {
    // Phone number formatting
    const phoneInputs = document.querySelectorAll('input[name="phone"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', () => formatPhoneNumber(input));
    });
    
    // SSN formatting
    const ssnInputs = document.querySelectorAll('input[name="ssn"]');
    ssnInputs.forEach(input => {
        input.addEventListener('input', () => formatSSN(input));
    });
    
    // Currency formatting
    const currencyInputs = document.querySelectorAll('input[name="monthlyIncome"], input[name="requestedAmount"]');
    currencyInputs.forEach(input => {
        input.addEventListener('blur', () => formatCurrencyInput(input));
    });
});

// Export functions for external use
window.LoanFlowApp = {
    showNotification,
    validateForm,
    formatPhoneNumber,
    formatSSN,
    formatCurrencyInput
};