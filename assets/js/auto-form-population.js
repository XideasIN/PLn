/**
 * Auto Form Population based on IP Geolocation
 * Automatically detects client location and populates forms accordingly
 */

class AutoFormPopulation {
    constructor() {
        this.countryData = null;
        this.isInitialized = false;
        this.init();
    }
    
    /**
     * Initialize the auto-population system
     */
    async init() {
        try {
            // Get country data from server
            await this.fetchCountryData();
            
            // Auto-populate forms on page load
            this.populateExistingForms();
            
            // Set up observers for dynamically added forms
            this.setupFormObserver();
            
            this.isInitialized = true;
            console.log('Auto Form Population initialized for country:', this.countryData?.country_code);
        } catch (error) {
            console.error('Auto Form Population initialization failed:', error);
        }
    }
    
    /**
     * Fetch country data from server
     */
    async fetchCountryData() {
        try {
            const response = await fetch('/api/get-client-location.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.countryData = data.data;
            } else {
                throw new Error(data.message || 'Failed to fetch country data');
            }
        } catch (error) {
            console.error('Failed to fetch country data:', error);
            // Use fallback data
            this.countryData = this.getFallbackData();
        }
    }
    
    /**
     * Get fallback data when geolocation fails
     */
    getFallbackData() {
        return {
            country_code: 'USA',
            country_name: 'United States',
            currency: 'USD',
            currency_symbol: '$',
            phone_format: '(###) ###-####',
            postal_format: '#####-####',
            id_format: '###-##-####',
            id_label: 'SSN',
            state_label: 'State',
            js_config: {
                country_code: 'USA',
                phone_placeholder: '(555) 123-4567',
                postal_placeholder: '12345-6789',
                id_placeholder: '123-45-6789'
            }
        };
    }
    
    /**
     * Populate existing forms on the page
     */
    populateExistingForms() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => this.populateForm(form));
    }
    
    /**
     * Set up mutation observer for dynamically added forms
     */
    setupFormObserver() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        // Check if the added node is a form
                        if (node.tagName === 'FORM') {
                            this.populateForm(node);
                        }
                        // Check for forms within the added node
                        const forms = node.querySelectorAll ? node.querySelectorAll('form') : [];
                        forms.forEach(form => this.populateForm(form));
                    }
                });
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    /**
     * Populate a specific form with country data
     */
    populateForm(form) {
        if (!this.countryData || !form) return;
        
        // Country selection
        this.populateCountryField(form);
        
        // State/Province selection
        this.populateStateProvinceField(form);
        
        // Phone number formatting
        this.setupPhoneFormatting(form);
        
        // Postal code formatting
        this.setupPostalFormatting(form);
        
        // ID number formatting
        this.setupIdFormatting(form);
        
        // Currency display
        this.updateCurrencyDisplay(form);
        
        // Update field labels
        this.updateFieldLabels(form);
    }
    
    /**
     * Populate country field
     */
    populateCountryField(form) {
        const countryFields = form.querySelectorAll('select[name="country"], select[name="country_code"]');
        
        countryFields.forEach(field => {
            if (!field.value) { // Only populate if empty
                field.value = this.countryData.country_code;
                field.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }
    
    /**
     * Populate state/province field based on country
     */
    populateStateProvinceField(form) {
        const stateFields = form.querySelectorAll('select[name="state"], select[name="province"], select[name="state_province"]');
        
        stateFields.forEach(field => {
            // Clear existing options except the first (placeholder)
            const firstOption = field.querySelector('option');
            field.innerHTML = '';
            if (firstOption && firstOption.value === '') {
                field.appendChild(firstOption);
            }
            
            // Add state/province options
            if (this.countryData.states_provinces) {
                Object.entries(this.countryData.states_provinces).forEach(([code, name]) => {
                    const option = document.createElement('option');
                    option.value = code;
                    option.textContent = name;
                    field.appendChild(option);
                });
            }
        });
    }
    
    /**
     * Setup phone number formatting
     */
    setupPhoneFormatting(form) {
        const phoneFields = form.querySelectorAll('input[name="phone"], input[type="tel"]');
        
        phoneFields.forEach(field => {
            // Set placeholder
            if (this.countryData.js_config?.phone_placeholder) {
                field.placeholder = this.countryData.js_config.phone_placeholder;
            }
            
            // Add formatting on input
            field.addEventListener('input', (e) => {
                this.formatPhoneNumber(e.target);
            });
        });
    }
    
    /**
     * Format phone number based on country
     */
    formatPhoneNumber(field) {
        let value = field.value.replace(/\D/g, ''); // Remove non-digits
        
        switch (this.countryData.country_code) {
            case 'USA':
            case 'CAN':
                if (value.length >= 6) {
                    value = `(${value.slice(0, 3)}) ${value.slice(3, 6)}-${value.slice(6, 10)}`;
                } else if (value.length >= 3) {
                    value = `(${value.slice(0, 3)}) ${value.slice(3)}`;
                }
                break;
            case 'GBR':
                if (value.length > 10) {
                    value = `+44 ${value.slice(2, 6)} ${value.slice(6)}`;
                }
                break;
            case 'AUS':
                if (value.length >= 9) {
                    value = `+61 ${value.slice(1, 2)} ${value.slice(2, 6)} ${value.slice(6)}`;
                }
                break;
        }
        
        field.value = value;
    }
    
    /**
     * Setup postal code formatting
     */
    setupPostalFormatting(form) {
        const postalFields = form.querySelectorAll('input[name="postal_code"], input[name="zip_code"], input[name="postal_zip"]');
        
        postalFields.forEach(field => {
            // Set placeholder
            if (this.countryData.js_config?.postal_placeholder) {
                field.placeholder = this.countryData.js_config.postal_placeholder;
            }
            
            // Add formatting on input
            field.addEventListener('input', (e) => {
                this.formatPostalCode(e.target);
            });
        });
    }
    
    /**
     * Format postal code based on country
     */
    formatPostalCode(field) {
        let value = field.value.toUpperCase();
        
        switch (this.countryData.country_code) {
            case 'CAN':
                // Canadian postal code: A1A 1A1
                value = value.replace(/[^A-Z0-9]/g, '');
                if (value.length >= 3) {
                    value = `${value.slice(0, 3)} ${value.slice(3, 6)}`;
                }
                break;
            case 'GBR':
                // UK postal code: SW1A 1AA
                value = value.replace(/[^A-Z0-9]/g, '');
                if (value.length > 4) {
                    const firstPart = value.slice(0, -3);
                    const lastPart = value.slice(-3);
                    value = `${firstPart} ${lastPart}`;
                }
                break;
            case 'USA':
                // US ZIP code: 12345-6789
                value = value.replace(/\D/g, '');
                if (value.length > 5) {
                    value = `${value.slice(0, 5)}-${value.slice(5, 9)}`;
                }
                break;
            case 'AUS':
                // Australian postal code: 2000 (4 digits)
                value = value.replace(/\D/g, '').slice(0, 4);
                break;
        }
        
        field.value = value;
    }
    
    /**
     * Setup ID number formatting
     */
    setupIdFormatting(form) {
        const idFields = form.querySelectorAll('input[name="sin_ssn"], input[name="id_number"]');
        
        idFields.forEach(field => {
            // Set placeholder
            if (this.countryData.js_config?.id_placeholder) {
                field.placeholder = this.countryData.js_config.id_placeholder;
            }
            
            // Add formatting on input
            field.addEventListener('input', (e) => {
                this.formatIdNumber(e.target);
            });
        });
    }
    
    /**
     * Format ID number based on country
     */
    formatIdNumber(field) {
        let value = field.value.replace(/[^A-Z0-9]/g, ''); // Remove non-alphanumeric
        
        switch (this.countryData.country_code) {
            case 'USA':
                // SSN: 123-45-6789
                value = value.replace(/\D/g, '');
                if (value.length >= 5) {
                    value = `${value.slice(0, 3)}-${value.slice(3, 5)}-${value.slice(5, 9)}`;
                } else if (value.length >= 3) {
                    value = `${value.slice(0, 3)}-${value.slice(3)}`;
                }
                break;
            case 'CAN':
                // SIN: 123-456-789
                value = value.replace(/\D/g, '');
                if (value.length >= 6) {
                    value = `${value.slice(0, 3)}-${value.slice(3, 6)}-${value.slice(6, 9)}`;
                } else if (value.length >= 3) {
                    value = `${value.slice(0, 3)}-${value.slice(3)}`;
                }
                break;
            case 'GBR':
                // NINO: AB 12 34 56 C
                if (value.length >= 8) {
                    value = `${value.slice(0, 2)} ${value.slice(2, 4)} ${value.slice(4, 6)} ${value.slice(6, 8)} ${value.slice(8, 9)}`;
                }
                break;
            case 'AUS':
                // TFN: 123-456-789
                value = value.replace(/\D/g, '');
                if (value.length >= 6) {
                    value = `${value.slice(0, 3)}-${value.slice(3, 6)}-${value.slice(6, 9)}`;
                } else if (value.length >= 3) {
                    value = `${value.slice(0, 3)}-${value.slice(3)}`;
                }
                break;
        }
        
        field.value = value;
    }
    
    /**
     * Update currency display throughout the form
     */
    updateCurrencyDisplay(form) {
        const currencyElements = form.querySelectorAll('.currency-symbol, [data-currency]');
        
        currencyElements.forEach(element => {
            if (element.classList.contains('currency-symbol')) {
                element.textContent = this.countryData.currency_symbol;
            }
            if (element.hasAttribute('data-currency')) {
                element.setAttribute('data-currency', this.countryData.currency);
                element.textContent = this.countryData.currency;
            }
        });
    }
    
    /**
     * Update field labels based on country
     */
    updateFieldLabels(form) {
        // Update state/province label
        const stateLabels = form.querySelectorAll('label[for*="state"], label[for*="province"]');
        stateLabels.forEach(label => {
            if (label.textContent.includes('State') || label.textContent.includes('Province')) {
                label.textContent = label.textContent.replace(/State|Province/g, this.countryData.state_label);
            }
        });
        
        // Update ID number label
        const idLabels = form.querySelectorAll('label[for*="sin"], label[for*="ssn"], label[for*="id_number"]');
        idLabels.forEach(label => {
            if (this.countryData.id_label) {
                label.textContent = label.textContent.replace(/SSN|SIN|ID Number/g, this.countryData.id_label);
            }
        });
    }
    
    /**
     * Get current country data
     */
    getCountryData() {
        return this.countryData;
    }
    
    /**
     * Check if country is allowed for applications
     */
    async isCountryAllowed() {
        try {
            const response = await fetch('/api/check-country-allowed.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    country_code: this.countryData?.country_code
                })
            });
            
            const data = await response.json();
            return data.allowed || false;
        } catch (error) {
            console.error('Failed to check country allowance:', error);
            return true; // Default to allowed if check fails
        }
    }
    
    /**
     * Show country restriction message
     */
    showCountryRestrictionMessage() {
        const message = document.createElement('div');
        message.className = 'alert alert-warning country-restriction-alert';
        message.innerHTML = `
            <h5><i class="fas fa-exclamation-triangle"></i> Application Not Available</h5>
            <p>We're sorry, but loan applications are not currently available in ${this.countryData?.country_name || 'your country'}.</p>
            <p>Please contact our support team for more information.</p>
        `;
        
        // Insert at the top of the main content area
        const mainContent = document.querySelector('main, .container, .content');
        if (mainContent) {
            mainContent.insertBefore(message, mainContent.firstChild);
        }
        
        // Disable all form inputs
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            const inputs = form.querySelectorAll('input, select, textarea, button[type="submit"]');
            inputs.forEach(input => {
                input.disabled = true;
            });
        });
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.autoFormPopulation = new AutoFormPopulation();
    });
} else {
    window.autoFormPopulation = new AutoFormPopulation();
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AutoFormPopulation;
}