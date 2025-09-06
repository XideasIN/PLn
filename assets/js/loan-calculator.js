/**
 * Loan Calculator JavaScript
 * LoanFlow Personal Loan Management System
 */

class LoanCalculator {
    constructor() {
        this.loanAmountSlider = document.getElementById('loanAmount');
        this.loanTermSelect = document.getElementById('loanTerm');
        this.loanAmountDisplay = document.getElementById('loanAmountDisplay');
        this.monthlyPaymentDisplay = document.getElementById('monthlyPayment');
        this.interestRateDisplay = document.getElementById('interestRate');
        
        this.interestRates = {
            'personal': 9.75,
            'debt_consolidation': 8.5,
            'home_repair': 8.25,
            'automotive': 8.25,
            'business': 7.95,
            'medical': 9.75
        };
        
        this.init();
    }
    
    init() {
        if (this.loanAmountSlider && this.loanTermSelect) {
            this.loanAmountSlider.addEventListener('input', () => this.updateCalculation());
            this.loanTermSelect.addEventListener('change', () => this.updateCalculation());
            
            // Initial calculation
            this.updateCalculation();
        }
        
        // Update calculation when loan type changes in the form
        const loanTypeSelect = document.querySelector('select[name="loan_type"]');
        if (loanTypeSelect) {
            loanTypeSelect.addEventListener('change', () => this.updateCalculation());
        }
    }
    
    updateCalculation() {
        const loanAmount = parseFloat(this.loanAmountSlider.value);
        const loanTerm = parseInt(this.loanTermSelect.value);
        const loanType = document.querySelector('select[name="loan_type"]')?.value || 'personal';
        
        // Update loan amount display
        this.loanAmountDisplay.textContent = this.formatCurrency(loanAmount);
        
        // Calculate interest rate based on loan type
        const interestRate = this.interestRates[loanType] || 9.75;
        this.interestRateDisplay.textContent = interestRate + '%';
        
        // Calculate monthly payment
        const monthlyPayment = this.calculateMonthlyPayment(loanAmount, interestRate, loanTerm);
        this.monthlyPaymentDisplay.textContent = this.formatCurrency(monthlyPayment);
        
        // Update form fields if they exist
        const loanAmountInput = document.querySelector('input[name="loan_amount"]');
        if (loanAmountInput) {
            loanAmountInput.value = loanAmount;
        }
    }
    
    calculateMonthlyPayment(principal, annualRate, months) {
        const monthlyRate = (annualRate / 100) / 12;
        
        if (monthlyRate === 0) {
            return principal / months;
        }
        
        const payment = principal * (monthlyRate * Math.pow(1 + monthlyRate, months)) / 
                       (Math.pow(1 + monthlyRate, months) - 1);
        
        return Math.round(payment * 100) / 100;
    }
    
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(amount);
    }
    
    // Static method for standalone calculations
    static calculate(principal, annualRate, months) {
        const monthlyRate = (annualRate / 100) / 12;
        
        if (monthlyRate === 0) {
            return principal / months;
        }
        
        const payment = principal * (monthlyRate * Math.pow(1 + monthlyRate, months)) / 
                       (Math.pow(1 + monthlyRate, months) - 1);
        
        return Math.round(payment * 100) / 100;
    }
}

// Advanced calculator for detailed loan information
class DetailedLoanCalculator extends LoanCalculator {
    constructor() {
        super();
        this.setupDetailedCalculator();
    }
    
    setupDetailedCalculator() {
        // Create detailed calculator modal if it doesn't exist
        this.createDetailedCalculatorModal();
        
        // Add event listener for detailed calculator button
        const detailedCalcBtn = document.getElementById('detailedCalculatorBtn');
        if (detailedCalcBtn) {
            detailedCalcBtn.addEventListener('click', () => this.showDetailedCalculator());
        }
    }
    
    createDetailedCalculatorModal() {
        const modalHtml = `
            <div class="modal fade" id="detailedCalculatorModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-calculator me-2"></i>Detailed Loan Calculator
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Loan Amount</label>
                                        <input type="number" class="form-control" id="detailedLoanAmount" 
                                               min="1000" max="150000" value="25000">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Interest Rate (%)</label>
                                        <input type="number" class="form-control" id="detailedInterestRate" 
                                               min="1" max="30" step="0.01" value="9.75">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Loan Term (months)</label>
                                        <select class="form-select" id="detailedLoanTerm">
                                            <option value="12">1 Year</option>
                                            <option value="24">2 Years</option>
                                            <option value="36">3 Years</option>
                                            <option value="48">4 Years</option>
                                            <option value="60" selected>5 Years</option>
                                            <option value="72">6 Years</option>
                                            <option value="84">7 Years</option>
                                            <option value="96">8 Years</option>
                                        </select>
                                    </div>
                                    <button type="button" class="btn btn-primary" id="calculateDetailedBtn">
                                        <i class="fas fa-calculator me-2"></i>Calculate
                                    </button>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0">Loan Summary</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row mb-2">
                                                <div class="col-6">Monthly Payment:</div>
                                                <div class="col-6 fw-bold" id="detailedMonthlyPayment">$0</div>
                                            </div>
                                            <div class="row mb-2">
                                                <div class="col-6">Total Interest:</div>
                                                <div class="col-6 fw-bold" id="detailedTotalInterest">$0</div>
                                            </div>
                                            <div class="row mb-2">
                                                <div class="col-6">Total Amount:</div>
                                                <div class="col-6 fw-bold" id="detailedTotalAmount">$0</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <canvas id="paymentChart" width="400" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h6>Payment Schedule</h6>
                                    <div class="table-responsive" style="max-height: 300px;">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Payment #</th>
                                                    <th>Principal</th>
                                                    <th>Interest</th>
                                                    <th>Balance</th>
                                                </tr>
                                            </thead>
                                            <tbody id="paymentScheduleBody">
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to body if it doesn't exist
        if (!document.getElementById('detailedCalculatorModal')) {
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Add event listeners for detailed calculator
            document.getElementById('calculateDetailedBtn').addEventListener('click', () => {
                this.calculateDetailed();
            });
            
            // Auto-calculate when inputs change
            ['detailedLoanAmount', 'detailedInterestRate', 'detailedLoanTerm'].forEach(id => {
                document.getElementById(id).addEventListener('input', () => this.calculateDetailed());
            });
        }
    }
    
    showDetailedCalculator() {
        const modal = new bootstrap.Modal(document.getElementById('detailedCalculatorModal'));
        modal.show();
        this.calculateDetailed();
    }
    
    calculateDetailed() {
        const loanAmount = parseFloat(document.getElementById('detailedLoanAmount').value);
        const interestRate = parseFloat(document.getElementById('detailedInterestRate').value);
        const loanTerm = parseInt(document.getElementById('detailedLoanTerm').value);
        
        if (!loanAmount || !interestRate || !loanTerm) return;
        
        const monthlyPayment = this.calculateMonthlyPayment(loanAmount, interestRate, loanTerm);
        const totalAmount = monthlyPayment * loanTerm;
        const totalInterest = totalAmount - loanAmount;
        
        // Update summary
        document.getElementById('detailedMonthlyPayment').textContent = this.formatCurrency(monthlyPayment);
        document.getElementById('detailedTotalInterest').textContent = this.formatCurrency(totalInterest);
        document.getElementById('detailedTotalAmount').textContent = this.formatCurrency(totalAmount);
        
        // Generate payment schedule
        this.generatePaymentSchedule(loanAmount, interestRate, loanTerm, monthlyPayment);
        
        // Update chart
        this.updatePaymentChart(loanAmount, totalInterest);
    }
    
    generatePaymentSchedule(principal, annualRate, months, monthlyPayment) {
        const monthlyRate = (annualRate / 100) / 12;
        let balance = principal;
        const tbody = document.getElementById('paymentScheduleBody');
        tbody.innerHTML = '';
        
        for (let i = 1; i <= months; i++) {
            const interestPayment = balance * monthlyRate;
            const principalPayment = monthlyPayment - interestPayment;
            balance -= principalPayment;
            
            if (balance < 0) balance = 0;
            
            const row = `
                <tr>
                    <td>${i}</td>
                    <td>${this.formatCurrency(principalPayment)}</td>
                    <td>${this.formatCurrency(interestPayment)}</td>
                    <td>${this.formatCurrency(balance)}</td>
                </tr>
            `;
            tbody.insertAdjacentHTML('beforeend', row);
        }
    }
    
    updatePaymentChart(principal, interest) {
        const canvas = document.getElementById('paymentChart');
        const ctx = canvas.getContext('2d');
        
        // Clear canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Simple pie chart
        const total = principal + interest;
        const principalAngle = (principal / total) * 2 * Math.PI;
        const centerX = canvas.width / 2;
        const centerY = canvas.height / 2;
        const radius = Math.min(centerX, centerY) - 20;
        
        // Draw principal slice
        ctx.beginPath();
        ctx.moveTo(centerX, centerY);
        ctx.arc(centerX, centerY, radius, 0, principalAngle);
        ctx.closePath();
        ctx.fillStyle = '#0d6efd';
        ctx.fill();
        
        // Draw interest slice
        ctx.beginPath();
        ctx.moveTo(centerX, centerY);
        ctx.arc(centerX, centerY, radius, principalAngle, 2 * Math.PI);
        ctx.closePath();
        ctx.fillStyle = '#dc3545';
        ctx.fill();
        
        // Add labels
        ctx.fillStyle = '#000';
        ctx.font = '12px Arial';
        ctx.fillText('Principal', centerX - 50, centerY + radius + 20);
        ctx.fillText('Interest', centerX + 10, centerY + radius + 20);
    }
}

// Affordability Calculator
class AffordabilityCalculator {
    static calculateMaxLoan(monthlyIncome, existingDebts, desiredTerm = 60) {
        // Use 28% debt-to-income ratio as maximum
        const maxMonthlyPayment = (monthlyIncome * 0.28) - existingDebts;
        
        if (maxMonthlyPayment <= 0) {
            return 0;
        }
        
        // Assume average interest rate of 9.75%
        const interestRate = 9.75;
        const monthlyRate = (interestRate / 100) / 12;
        
        // Calculate maximum loan amount
        const maxLoan = maxMonthlyPayment * (Math.pow(1 + monthlyRate, desiredTerm) - 1) / 
                       (monthlyRate * Math.pow(1 + monthlyRate, desiredTerm));
        
        return Math.floor(maxLoan);
    }
    
    static getAffordabilityAdvice(monthlyIncome, existingDebts, requestedLoan, requestedTerm = 60) {
        const maxLoan = this.calculateMaxLoan(monthlyIncome, existingDebts, requestedTerm);
        const monthlyPayment = LoanCalculator.calculate(requestedLoan, 9.75, requestedTerm);
        const totalMonthlyDebt = existingDebts + monthlyPayment;
        const debtToIncomeRatio = (totalMonthlyDebt / monthlyIncome) * 100;
        
        let advice = {
            maxLoan: maxLoan,
            requestedLoan: requestedLoan,
            monthlyPayment: monthlyPayment,
            debtToIncomeRatio: debtToIncomeRatio,
            recommendation: '',
            riskLevel: 'low'
        };
        
        if (debtToIncomeRatio <= 28) {
            advice.recommendation = 'Excellent! This loan fits comfortably within recommended debt-to-income ratios.';
            advice.riskLevel = 'low';
        } else if (debtToIncomeRatio <= 36) {
            advice.recommendation = 'Good. This loan is manageable but consider your budget carefully.';
            advice.riskLevel = 'medium';
        } else {
            advice.recommendation = 'Caution: This loan may strain your budget. Consider a smaller amount or longer term.';
            advice.riskLevel = 'high';
        }
        
        return advice;
    }
}

// Initialize calculator when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize basic calculator
    new LoanCalculator();
    
    // Initialize detailed calculator if on appropriate page
    if (document.getElementById('detailedCalculatorBtn')) {
        new DetailedLoanCalculator();
    }
    
    // Add affordability check to application form
    const monthlyIncomeInput = document.querySelector('input[name="monthly_income"]');
    const existingDebtsInput = document.querySelector('input[name="existing_debts"]');
    const loanAmountInput = document.querySelector('input[name="loan_amount"]');
    
    if (monthlyIncomeInput && existingDebtsInput && loanAmountInput) {
        const checkAffordability = () => {
            const monthlyIncome = parseFloat(monthlyIncomeInput.value) || 0;
            const existingDebts = parseFloat(existingDebtsInput.value) || 0;
            const requestedLoan = parseFloat(loanAmountInput.value) || 0;
            
            if (monthlyIncome > 0 && requestedLoan > 0) {
                const advice = AffordabilityCalculator.getAffordabilityAdvice(
                    monthlyIncome, existingDebts, requestedLoan
                );
                
                // Show affordability feedback
                showAffordabilityFeedback(advice);
            }
        };
        
        monthlyIncomeInput.addEventListener('blur', checkAffordability);
        existingDebtsInput.addEventListener('blur', checkAffordability);
        loanAmountInput.addEventListener('blur', checkAffordability);
    }
});

function showAffordabilityFeedback(advice) {
    // Remove existing feedback
    const existingFeedback = document.getElementById('affordabilityFeedback');
    if (existingFeedback) {
        existingFeedback.remove();
    }
    
    // Create feedback element
    const feedbackClass = advice.riskLevel === 'low' ? 'alert-success' : 
                         advice.riskLevel === 'medium' ? 'alert-warning' : 'alert-danger';
    
    const feedbackHtml = `
        <div id="affordabilityFeedback" class="alert ${feedbackClass} mt-3">
            <h6><i class="fas fa-chart-line me-2"></i>Affordability Check</h6>
            <p class="mb-2">${advice.recommendation}</p>
            <div class="row">
                <div class="col-sm-6">
                    <small><strong>Monthly Payment:</strong> $${advice.monthlyPayment.toFixed(2)}</small>
                </div>
                <div class="col-sm-6">
                    <small><strong>Debt-to-Income:</strong> ${advice.debtToIncomeRatio.toFixed(1)}%</small>
                </div>
            </div>
            ${advice.requestedLoan > advice.maxLoan ? 
                `<small class="d-block mt-2"><strong>Suggested Max:</strong> $${advice.maxLoan.toLocaleString()}</small>` : 
                ''
            }
        </div>
    `;
    
    // Insert feedback after loan amount input
    const loanAmountInput = document.querySelector('input[name="loan_amount"]');
    const loanAmountGroup = loanAmountInput.closest('.mb-3');
    loanAmountGroup.insertAdjacentHTML('afterend', feedbackHtml);
}
