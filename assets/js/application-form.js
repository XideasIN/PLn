/**
 * Application Form JavaScript
 * LoanFlow Personal Loan Management System
 */

class ApplicationForm {
    constructor() {
        this.form = document.getElementById('applicationForm');
        this.currentStep = 1;
        this.totalSteps = 5;
        this.formData = {};
        
        this.init();
    }
    
    init() {
        if (this.form) {
            this.setupFormValidation();
            this.setupCountrySpecificFields();
            this.setupPhoneFormatting();
            this.setupTaxIdFormatting();
            this.setupPostalCodeFormatting();
            this.setupFormSubmission();
            this.setupAutoSave();
        }
    }
    
    setupFormValidation() {
        // Real-time validation for email
        const emailInput = this.form.querySelector('input[name="email"]');
        if (emailInput) {
            emailInput.addEventListener('blur', () => this.validateEmail(emailInput));
        }
        
        // Real-time validation for phone
        const phoneInput = this.form.querySelector('input[name="phone"]');
        if (phoneInput) {
            phoneInput.addEventListener('blur', () => this.validatePhone(phoneInput));
        }
        
        // Real-time validation for loan amount
        const loanAmountInput = this.form.querySelector('input[name="loan_amount"]');
        if (loanAmountInput) {
            loanAmountInput.addEventListener('input', () => this.validateLoanAmount(loanAmountInput));
        }
        
        // Age validation for date of birth
        const dobInput = this.form.querySelector('input[name="date_of_birth"]');
        if (dobInput) {
            dobInput.addEventListener('blur', () => this.validateAge(dobInput));
        }
        
        // Password strength indicator
        const passwordInput = this.form.querySelector('input[name="password"]');
        if (passwordInput) {
            passwordInput.addEventListener('input', () => this.checkPasswordStrength(passwordInput));
        }
    }
    
    validateEmail(input) {
        const email = input.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        this.clearFieldError(input);
        
        if (email && !emailRegex.test(email)) {
            this.showFieldError(input, 'Please enter a valid email address');
            return false;
        }
        
        // Check if email exists (you might want to implement this via AJAX)
        if (email) {
            this.checkEmailAvailability(email, input);
        }
        
        return true;
    }
    
    async checkEmailAvailability(email, input) {
        try {
            const response = await fetch('/api/check-email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email: email })
            });
            
            const data = await response.json();
            
            if (data.exists) {
                this.showFieldError(input, 'An account with this email already exists');
            } else {
                this.showFieldSuccess(input, 'Email is available');
            }
        } catch (error) {
            console.error('Email check failed:', error);
        }
    }
    
    validatePhone(input) {
        const phone = input.value.trim();
        const country = this.getSelectedCountry();
        
        this.clearFieldError(input);
        
        if (phone && !this.isValidPhoneFormat(phone, country)) {
            this.showFieldError(input, `Please enter a valid phone number for ${country}`);
            return false;
        }
        
        return true;
    }
    
    validateLoanAmount(input) {
        const amount = parseFloat(input.value);
        
        this.clearFieldError(input);
        
        if (amount < 1000) {
            this.showFieldError(input, 'Minimum loan amount is $1,000');
            return false;
        }
        
        if (amount > 150000) {
            this.showFieldError(input, 'Maximum loan amount is $150,000');
            return false;
        }
        
        this.showFieldSuccess(input, 'Amount is within acceptable range');
        return true;
    }
    
    validateAge(input) {
        const birthDate = new Date(input.value);
        const today = new Date();
        const age = Math.floor((today - birthDate) / (365.25 * 24 * 60 * 60 * 1000));
        
        this.clearFieldError(input);
        
        if (age < 18) {
            this.showFieldError(input, 'You must be at least 18 years old to apply');
            return false;
        }
        
        if (age > 80) {
            this.showFieldError(input, 'Maximum age for application is 80 years');
            return false;
        }
        
        return true;
    }
    
    checkPasswordStrength(input) {
        const password = input.value;
        const strength = this.calculatePasswordStrength(password);
        
        // Remove existing strength indicator
        const existingIndicator = input.parentNode.querySelector('.password-strength');
        if (existingIndicator) {
            existingIndicator.remove();
        }
        
        if (password.length > 0) {
            const indicator = document.createElement('div');
            indicator.className = `password-strength strength-${strength.level}`;
            indicator.innerHTML = `
                <div class="strength-bar">
                    <div class="strength-fill" style="width: ${strength.score}%"></div>
                </div>
                <small class="strength-text">${strength.message}</small>
            `;
            
            input.parentNode.appendChild(indicator);
        }
    }
    
    calculatePasswordStrength(password) {
        let score = 0;
        let level = 'weak';
        let message = 'Weak password';
        
        // Length check
        if (password.length >= 8) score += 20;
        if (password.length >= 12) score += 10;
        
        // Character variety checks
        if (/[a-z]/.test(password)) score += 15;
        if (/[A-Z]/.test(password)) score += 15;
        if (/[0-9]/.test(password)) score += 15;
        if (/[^A-Za-z0-9]/.test(password)) score += 25;
        
        // Determine level and message
        if (score >= 80) {
            level = 'strong';
            message = 'Strong password';
        } else if (score >= 60) {
            level = 'medium';
            message = 'Medium strength password';
        } else if (score >= 40) {
            level = 'fair';
            message = 'Fair password';
        }
        
        return { score: Math.min(score, 100), level, message };
    }
    
    setupCountrySpecificFields() {
        // This would be implemented to show/hide fields based on country
        // For now, we'll assume the server-side handles country detection
    }
    
    setupPhoneFormatting() {
        const phoneInput = this.form.querySelector('input[name="phone"]');
        if (phoneInput) {
            phoneInput.addEventListener('input', (e) => {
                const country = this.getSelectedCountry();
                e.target.value = this.formatPhoneNumber(e.target.value, country);
            });
        }
    }
    
    setupTaxIdFormatting() {
        const taxIdInput = this.form.querySelector('input[name="sin_ssn"]');
        if (taxIdInput) {
            taxIdInput.addEventListener('input', (e) => {
                const country = this.getSelectedCountry();
                e.target.value = this.formatTaxId(e.target.value, country);
            });
        }
    }
    
    setupPostalCodeFormatting() {
        const postalInput = this.form.querySelector('input[name="postal_zip"]');
        if (postalInput) {
            postalInput.addEventListener('input', (e) => {
                const country = this.getSelectedCountry();
                e.target.value = this.formatPostalCode(e.target.value, country);
            });
        }
    }
    
    formatPhoneNumber(value, country = 'USA') {
        // Remove all non-digit characters
        const digits = value.replace(/\D/g, '');
        
        switch (country) {
            case 'USA':
            case 'CAN':
                if (digits.length <= 3) return digits;
                if (digits.length <= 6) return `(${digits.slice(0, 3)}) ${digits.slice(3)}`;
                return `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6, 10)}`;
            
            case 'GBR':
                if (digits.length <= 4) return `+44 ${digits}`;
                return `+44 ${digits.slice(0, 4)} ${digits.slice(4, 10)}`;
            
            case 'AUS':
                if (digits.length <= 1) return `+61 ${digits}`;
                if (digits.length <= 5) return `+61 ${digits.slice(0, 1)} ${digits.slice(1)}`;
                return `+61 ${digits.slice(0, 1)} ${digits.slice(1, 5)} ${digits.slice(5, 9)}`;
            
            default:
                return value;
        }
    }
    
    formatTaxId(value, country = 'USA') {
        const digits = value.replace(/\D/g, '');
        
        switch (country) {
            case 'USA':
                if (digits.length <= 3) return digits;
                if (digits.length <= 5) return `${digits.slice(0, 3)}-${digits.slice(3)}`;
                return `${digits.slice(0, 3)}-${digits.slice(3, 5)}-${digits.slice(5, 9)}`;
            
            case 'CAN':
            case 'AUS':
                if (digits.length <= 3) return digits;
                if (digits.length <= 6) return `${digits.slice(0, 3)}-${digits.slice(3)}`;
                return `${digits.slice(0, 3)}-${digits.slice(3, 6)}-${digits.slice(6, 9)}`;
            
            case 'GBR':
                // UK NINO format: AB 123456 C
                const letters = value.replace(/[^A-Za-z]/g, '').toUpperCase();
                if (digits.length <= 6 && letters.length >= 2) {
                    return `${letters.slice(0, 2)} ${digits}`;
                }
                if (letters.length >= 3) {
                    return `${letters.slice(0, 2)} ${digits.slice(0, 6)} ${letters.slice(2, 3)}`;
                }
                return value;
            
            default:
                return value;
        }
    }
    
    formatPostalCode(value, country = 'USA') {
        switch (country) {
            case 'USA':
                const digits = value.replace(/\D/g, '');
                if (digits.length <= 5) return digits;
                return `${digits.slice(0, 5)}-${digits.slice(5, 9)}`;
            
            case 'CAN':
                const cleanValue = value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
                if (cleanValue.length <= 3) return cleanValue;
                return `${cleanValue.slice(0, 3)} ${cleanValue.slice(3, 6)}`;
            
            case 'GBR':
                // UK postal code format is complex, just ensure uppercase
                return value.toUpperCase();
            
            case 'AUS':
                return value.replace(/\D/g, '').slice(0, 4);
            
            default:
                return value;
        }
    }
    
    isValidPhoneFormat(phone, country = 'USA') {
        const patterns = {
            'USA': /^\(\d{3}\) \d{3}-\d{4}$/,
            'CAN': /^\(\d{3}\) \d{3}-\d{4}$/,
            'GBR': /^\+44 \d{4} \d{6}$/,
            'AUS': /^\+61 \d \d{4} \d{4}$/
        };
        
        return patterns[country] ? patterns[country].test(phone) : true;
    }
    
    getSelectedCountry() {
        // In a real implementation, this would get the country from a select field
        // or from server-side country detection
        return 'USA';
    }
    
    setupFormSubmission() {
        this.form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!this.validateForm()) {
                this.showFormErrors();
                return;
            }
            
            this.showSubmissionProgress();
            
            try {
                const formData = new FormData(this.form);
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
                    // Clear saved form data
                    localStorage.removeItem('loanApplicationData');
                    
                    // Show success message
                    this.showSuccessMessage(result.message, result.reference_number);
                    
                    // Redirect after delay
                    setTimeout(() => {
                        window.location.href = '/application-success.php?ref=' + result.reference_number;
                    }, 3000);
                } else {
                    this.showErrorMessage(result.message || 'Application submission failed. Please try again.');
                }
            } catch (error) {
                console.error('Form submission error:', error);
                this.showErrorMessage('Network error. Please check your connection and try again.');
            } finally {
                this.hideSubmissionProgress();
            }
        });
    }
    
    validateForm() {
        const requiredFields = this.form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                this.showFieldError(field, 'This field is required');
                isValid = false;
            }
        });
        
        // Additional custom validations
        const emailInput = this.form.querySelector('input[name="email"]');
        if (emailInput && !this.validateEmail(emailInput)) {
            isValid = false;
        }
        
        const phoneInput = this.form.querySelector('input[name="phone"]');
        if (phoneInput && !this.validatePhone(phoneInput)) {
            isValid = false;
        }
        
        const loanAmountInput = this.form.querySelector('input[name="loan_amount"]');
        if (loanAmountInput && !this.validateLoanAmount(loanAmountInput)) {
            isValid = false;
        }
        
        const dobInput = this.form.querySelector('input[name="date_of_birth"]');
        if (dobInput && !this.validateAge(dobInput)) {
            isValid = false;
        }
        
        return isValid;
    }
    
    showFormErrors() {
        // Scroll to first error
        const firstError = this.form.querySelector('.is-invalid');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus();
        }
    }
    
    showSubmissionProgress() {
        const submitButton = this.form.querySelector('button[type="submit"]');
        if (submitButton) {
            this.originalButtonText = submitButton.innerHTML;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing Application...';
            submitButton.disabled = true;
        }
    }
    
    hideSubmissionProgress() {
        const submitButton = this.form.querySelector('button[type="submit"]');
        if (submitButton && this.originalButtonText) {
            submitButton.innerHTML = this.originalButtonText;
            submitButton.disabled = false;
        }
    }
    
    showSuccessMessage(message, referenceNumber) {
        const alertHtml = `
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Success!</strong> ${message}
                ${referenceNumber ? `<br><small>Reference Number: <strong>${referenceNumber}</strong></small>` : ''}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        this.form.insertAdjacentHTML('beforebegin', alertHtml);
        this.scrollToTop();
    }
    
    showErrorMessage(message) {
        const alertHtml = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Error!</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        this.form.insertAdjacentHTML('beforebegin', alertHtml);
        this.scrollToTop();
    }
    
    scrollToTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    setupAutoSave() {
        // Auto-save form data to localStorage
        const inputs = this.form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('change', () => {
                this.saveFormData();
            });
        });
        
        // Load saved data on page load
        this.loadFormData();
    }
    
    saveFormData() {
        const formData = new FormData(this.form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            if (key !== 'password' && key !== 'csrf_token') {
                data[key] = value;
            }
        }
        
        localStorage.setItem('loanApplicationData', JSON.stringify(data));
    }
    
    loadFormData() {
        const savedData = localStorage.getItem('loanApplicationData');
        if (savedData) {
            try {
                const data = JSON.parse(savedData);
                Object.keys(data).forEach(key => {
                    const input = this.form.querySelector(`[name="${key}"]`);
                    if (input && input.type !== 'password') {
                        input.value = data[key];
                    }
                });
            } catch (error) {
                console.error('Error loading saved form data:', error);
            }
        }
    }
    
    clearSavedData() {
        localStorage.removeItem('loanApplicationData');
    }
    
    showFieldError(input, message) {
        this.clearFieldError(input);
        
        input.classList.add('is-invalid');
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        errorDiv.textContent = message;
        
        input.parentNode.appendChild(errorDiv);
    }
    
    showFieldSuccess(input, message) {
        this.clearFieldError(input);
        
        input.classList.add('is-valid');
        
        const successDiv = document.createElement('div');
        successDiv.className = 'valid-feedback';
        successDiv.textContent = message;
        
        input.parentNode.appendChild(successDiv);
    }
    
    clearFieldError(input) {
        input.classList.remove('is-invalid', 'is-valid');
        
        const existingFeedback = input.parentNode.querySelectorAll('.invalid-feedback, .valid-feedback');
        existingFeedback.forEach(el => el.remove());
    }
}

// Progress indicator for multi-step forms
class FormProgress {
    constructor(totalSteps = 5) {
        this.totalSteps = totalSteps;
        this.currentStep = 1;
        this.createProgressIndicator();
    }
    
    createProgressIndicator() {
        const progressHtml = `
            <div class="form-progress mb-4">
                <div class="progress mb-2">
                    <div class="progress-bar" role="progressbar" style="width: ${(this.currentStep / this.totalSteps) * 100}%">
                        Step ${this.currentStep} of ${this.totalSteps}
                    </div>
                </div>
                <div class="step-labels d-flex justify-content-between">
                    <small class="step-label ${this.currentStep >= 1 ? 'active' : ''}">Personal Info</small>
                    <small class="step-label ${this.currentStep >= 2 ? 'active' : ''}">Loan Details</small>
                    <small class="step-label ${this.currentStep >= 3 ? 'active' : ''}">Employment</small>
                    <small class="step-label ${this.currentStep >= 4 ? 'active' : ''}">Documents</small>
                    <small class="step-label ${this.currentStep >= 5 ? 'active' : ''}">Review</small>
                </div>
            </div>
        `;
        
        // Insert progress indicator at the top of the form
        const form = document.getElementById('applicationForm');
        if (form) {
            form.insertAdjacentHTML('afterbegin', progressHtml);
        }
    }
    
    updateProgress(step) {
        this.currentStep = step;
        const progressBar = document.querySelector('.form-progress .progress-bar');
        const stepLabels = document.querySelectorAll('.form-progress .step-label');
        
        if (progressBar) {
            progressBar.style.width = `${(this.currentStep / this.totalSteps) * 100}%`;
            progressBar.textContent = `Step ${this.currentStep} of ${this.totalSteps}`;
        }
        
        stepLabels.forEach((label, index) => {
            if (index < this.currentStep) {
                label.classList.add('active');
            } else {
                label.classList.remove('active');
            }
        });
    }
}

// Initialize application form when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    new ApplicationForm();
    
    // Clear saved data when application is successfully submitted
    if (window.location.href.includes('application-success')) {
        localStorage.removeItem('loanApplicationData');
    }
});

// Add CSS for password strength indicator
const style = document.createElement('style');
style.textContent = `
    .password-strength {
        margin-top: 5px;
    }
    
    .strength-bar {
        height: 4px;
        background: #e9ecef;
        border-radius: 2px;
        overflow: hidden;
    }
    
    .strength-fill {
        height: 100%;
        transition: width 0.3s ease;
        border-radius: 2px;
    }
    
    .strength-weak .strength-fill {
        background: #dc3545;
    }
    
    .strength-fair .strength-fill {
        background: #ffc107;
    }
    
    .strength-medium .strength-fill {
        background: #fd7e14;
    }
    
    .strength-strong .strength-fill {
        background: #198754;
    }
    
    .strength-text {
        display: block;
        margin-top: 2px;
        font-size: 0.75rem;
    }
    
    .step-label {
        color: #6c757d;
        font-weight: 500;
    }
    
    .step-label.active {
        color: #0d6efd;
        font-weight: 600;
    }
`;
document.head.appendChild(style);
