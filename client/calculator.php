<?php
/**
 * Client Calculator - Loan Calculator and Financial Tools
 * LoanFlow Personal Loan Management System
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require client login
requireLogin();

$current_user = getCurrentUser();
$application = getApplicationByUserId($current_user['id']);

// Handle calculation save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_calculation'])) {
    try {
        $db = getDB();
        
        $calculation_type = $_POST['calculation_type'];
        $loan_amount = floatval($_POST['loan_amount']);
        $interest_rate = floatval($_POST['interest_rate']);
        $loan_term = intval($_POST['loan_term']);
        $monthly_payment = floatval($_POST['monthly_payment']);
        $total_interest = floatval($_POST['total_interest']);
        $total_amount = floatval($_POST['total_amount']);
        
        $stmt = $db->prepare("
            INSERT INTO loan_calculations 
            (user_id, calculation_type, loan_amount, interest_rate, loan_term, 
             monthly_payment, total_interest, total_amount, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $current_user['id'], $calculation_type, $loan_amount, $interest_rate, 
            $loan_term, $monthly_payment, $total_interest, $total_amount
        ]);
        
        setFlashMessage('Calculation saved successfully!', 'success');
        
    } catch (Exception $e) {
        error_log("Calculation save failed: " . $e->getMessage());
        setFlashMessage('Failed to save calculation. Please try again.', 'error');
    }
}

// Get saved calculations
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT * FROM loan_calculations 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$current_user['id']]);
    $saved_calculations = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Calculations fetch failed: " . $e->getMessage());
    $saved_calculations = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Calculator - QuickFunds</title>
    <link rel="stylesheet" href="../FrontEnd_Template/css/bootstrap.min.css">
    <link rel="stylesheet" href="../FrontEnd_Template/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/client.css" rel="stylesheet">
    <style>
        .calculator-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .calculator-tabs {
            border-bottom: 2px solid #f8f9fa;
            margin-bottom: 30px;
        }
        .calculator-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 15px 25px;
            border-radius: 10px 10px 0 0;
        }
        .calculator-tabs .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .result-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
        }
        .result-item {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .result-item:last-child {
            margin-bottom: 0;
        }
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .input-group-text {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .saved-calculation {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .saved-calculation:hover {
            background: #e9ecef;
            transform: translateY(-2px);
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
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?= htmlspecialchars($current_user['first_name']) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile.php">
                                    <i class="fas fa-user me-2"></i>Profile
                                </a></li>
                                <li><a class="dropdown-item active" href="calculator.php">
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
                <!-- Calculator Section -->
                <div class="col-lg-8">
                    <div class="calculator-card">
                        <h3 class="mb-4"><i class="fas fa-calculator me-2"></i>Loan Calculator</h3>
                        
                        <!-- Calculator Tabs -->
                        <ul class="nav nav-tabs calculator-tabs" id="calculatorTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="payment-tab" data-bs-toggle="tab" 
                                        data-bs-target="#payment" type="button" role="tab">
                                    <i class="fas fa-dollar-sign me-2"></i>Payment Calculator
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="affordability-tab" data-bs-toggle="tab" 
                                        data-bs-target="#affordability" type="button" role="tab">
                                    <i class="fas fa-chart-line me-2"></i>Affordability Calculator
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="comparison-tab" data-bs-toggle="tab" 
                                        data-bs-target="#comparison" type="button" role="tab">
                                    <i class="fas fa-balance-scale me-2"></i>Loan Comparison
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="calculatorTabsContent">
                            <!-- Payment Calculator -->
                            <div class="tab-pane fade show active" id="payment" role="tabpanel">
                                <form id="paymentCalculatorForm">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Loan Amount</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control" id="loanAmount" 
                                                       value="10000" min="1000" max="100000" step="100">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Interest Rate (Annual %)</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="interestRate" 
                                                       value="12.5" min="1" max="50" step="0.1">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Loan Term</label>
                                            <select class="form-select" id="loanTerm">
                                                <option value="12">12 months</option>
                                                <option value="24" selected>24 months</option>
                                                <option value="36">36 months</option>
                                                <option value="48">48 months</option>
                                                <option value="60">60 months</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Processing Fee</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control" id="processingFee" 
                                                       value="0" min="0" max="1000" step="10">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="button" class="btn btn-primary" onclick="calculatePayment()">
                                        <i class="fas fa-calculator me-1"></i>Calculate Payment
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Affordability Calculator -->
                            <div class="tab-pane fade" id="affordability" role="tabpanel">
                                <form id="affordabilityCalculatorForm">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Monthly Income</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control" id="monthlyIncome" 
                                                       value="5000" min="1000" step="100">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Monthly Expenses</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control" id="monthlyExpenses" 
                                                       value="3000" min="0" step="100">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Existing Debt Payments</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control" id="existingDebt" 
                                                       value="500" min="0" step="50">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Debt-to-Income Ratio (%)</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="dtiRatio" 
                                                       value="40" min="10" max="60" step="1">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="button" class="btn btn-success" onclick="calculateAffordability()">
                                        <i class="fas fa-chart-line me-1"></i>Calculate Affordability
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Loan Comparison -->
                            <div class="tab-pane fade" id="comparison" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Loan Option A</h6>
                                        <form id="loanAForm">
                                            <div class="mb-3">
                                                <label class="form-label">Amount</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" class="form-control" id="loanAmountA" 
                                                           value="10000" min="1000" step="100">
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Interest Rate (%)</label>
                                                <input type="number" class="form-control" id="interestRateA" 
                                                       value="12.5" min="1" step="0.1">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Term (months)</label>
                                                <input type="number" class="form-control" id="loanTermA" 
                                                       value="24" min="6" max="60">
                                            </div>
                                        </form>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h6>Loan Option B</h6>
                                        <form id="loanBForm">
                                            <div class="mb-3">
                                                <label class="form-label">Amount</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" class="form-control" id="loanAmountB" 
                                                           value="10000" min="1000" step="100">
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Interest Rate (%)</label>
                                                <input type="number" class="form-control" id="interestRateB" 
                                                       value="15.0" min="1" step="0.1">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Term (months)</label>
                                                <input type="number" class="form-control" id="loanTermB" 
                                                       value="36" min="6" max="60">
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                
                                <button type="button" class="btn btn-warning" onclick="compareLoans()">
                                    <i class="fas fa-balance-scale me-1"></i>Compare Loans
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Results Section -->
                <div class="col-lg-4">
                    <!-- Calculation Results -->
                    <div class="result-card" id="resultsCard" style="display: none;">
                        <h5><i class="fas fa-chart-pie me-2"></i>Calculation Results</h5>
                        <div id="resultsContent"></div>
                        
                        <form method="POST" id="saveCalculationForm" style="display: none;">
                            <input type="hidden" name="calculation_type" id="calculationType">
                            <input type="hidden" name="loan_amount" id="saveLoanAmount">
                            <input type="hidden" name="interest_rate" id="saveInterestRate">
                            <input type="hidden" name="loan_term" id="saveLoanTerm">
                            <input type="hidden" name="monthly_payment" id="saveMonthlyPayment">
                            <input type="hidden" name="total_interest" id="saveTotalInterest">
                            <input type="hidden" name="total_amount" id="saveTotalAmount">
                            <button type="submit" name="save_calculation" class="btn btn-light btn-sm mt-3">
                                <i class="fas fa-save me-1"></i>Save Calculation
                            </button>
                        </form>
                    </div>
                    
                    <!-- Payment Schedule Chart -->
                    <div class="chart-container mt-4" id="chartContainer" style="display: none;">
                        <h6><i class="fas fa-chart-bar me-2"></i>Payment Breakdown</h6>
                        <canvas id="paymentChart" width="400" height="200"></canvas>
                    </div>
                    
                    <!-- Saved Calculations -->
                    <?php if (!empty($saved_calculations)): ?>
                    <div class="calculator-card mt-4">
                        <h6><i class="fas fa-history me-2"></i>Recent Calculations</h6>
                        <?php foreach ($saved_calculations as $calc): ?>
                            <div class="saved-calculation" onclick="loadCalculation(<?= htmlspecialchars(json_encode($calc)) ?>)">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>$<?= number_format($calc['loan_amount']) ?></strong>
                                        <small class="text-muted d-block">
                                            <?= $calc['loan_term'] ?> months @ <?= $calc['interest_rate'] ?>%
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <strong class="text-primary">$<?= number_format($calc['monthly_payment'], 2) ?></strong>
                                        <small class="text-muted d-block">/month</small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="../FrontEnd_Template/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let paymentChart = null;
        
        function calculatePayment() {
            const loanAmount = parseFloat(document.getElementById('loanAmount').value);
            const interestRate = parseFloat(document.getElementById('interestRate').value) / 100 / 12;
            const loanTerm = parseInt(document.getElementById('loanTerm').value);
            const processingFee = parseFloat(document.getElementById('processingFee').value);
            
            // Calculate monthly payment using PMT formula
            const monthlyPayment = (loanAmount * interestRate * Math.pow(1 + interestRate, loanTerm)) / 
                                 (Math.pow(1 + interestRate, loanTerm) - 1);
            
            const totalAmount = monthlyPayment * loanTerm + processingFee;
            const totalInterest = totalAmount - loanAmount - processingFee;
            
            displayResults({
                type: 'payment',
                monthlyPayment: monthlyPayment,
                totalAmount: totalAmount,
                totalInterest: totalInterest,
                loanAmount: loanAmount,
                processingFee: processingFee
            });
            
            // Save calculation data
            document.getElementById('calculationType').value = 'payment';
            document.getElementById('saveLoanAmount').value = loanAmount;
            document.getElementById('saveInterestRate').value = document.getElementById('interestRate').value;
            document.getElementById('saveLoanTerm').value = loanTerm;
            document.getElementById('saveMonthlyPayment').value = monthlyPayment.toFixed(2);
            document.getElementById('saveTotalInterest').value = totalInterest.toFixed(2);
            document.getElementById('saveTotalAmount').value = totalAmount.toFixed(2);
            
            createPaymentChart(loanAmount, totalInterest);
        }
        
        function calculateAffordability() {
            const monthlyIncome = parseFloat(document.getElementById('monthlyIncome').value);
            const monthlyExpenses = parseFloat(document.getElementById('monthlyExpenses').value);
            const existingDebt = parseFloat(document.getElementById('existingDebt').value);
            const dtiRatio = parseFloat(document.getElementById('dtiRatio').value) / 100;
            
            const availableIncome = monthlyIncome - monthlyExpenses - existingDebt;
            const maxDebtPayment = monthlyIncome * dtiRatio - existingDebt;
            const affordablePayment = Math.min(availableIncome * 0.8, maxDebtPayment);
            
            // Estimate loan amount based on affordable payment (assuming 12.5% APR, 24 months)
            const estimatedRate = 0.125 / 12;
            const estimatedTerm = 24;
            const maxLoanAmount = affordablePayment * (Math.pow(1 + estimatedRate, estimatedTerm) - 1) / 
                                (estimatedRate * Math.pow(1 + estimatedRate, estimatedTerm));
            
            displayResults({
                type: 'affordability',
                availableIncome: availableIncome,
                affordablePayment: affordablePayment,
                maxLoanAmount: maxLoanAmount,
                dtiRatio: dtiRatio * 100
            });
        }
        
        function compareLoans() {
            // Loan A calculations
            const loanAmountA = parseFloat(document.getElementById('loanAmountA').value);
            const interestRateA = parseFloat(document.getElementById('interestRateA').value) / 100 / 12;
            const loanTermA = parseInt(document.getElementById('loanTermA').value);
            
            const monthlyPaymentA = (loanAmountA * interestRateA * Math.pow(1 + interestRateA, loanTermA)) / 
                                  (Math.pow(1 + interestRateA, loanTermA) - 1);
            const totalAmountA = monthlyPaymentA * loanTermA;
            const totalInterestA = totalAmountA - loanAmountA;
            
            // Loan B calculations
            const loanAmountB = parseFloat(document.getElementById('loanAmountB').value);
            const interestRateB = parseFloat(document.getElementById('interestRateB').value) / 100 / 12;
            const loanTermB = parseInt(document.getElementById('loanTermB').value);
            
            const monthlyPaymentB = (loanAmountB * interestRateB * Math.pow(1 + interestRateB, loanTermB)) / 
                                  (Math.pow(1 + interestRateB, loanTermB) - 1);
            const totalAmountB = monthlyPaymentB * loanTermB;
            const totalInterestB = totalAmountB - loanAmountB;
            
            displayResults({
                type: 'comparison',
                loanA: {
                    monthlyPayment: monthlyPaymentA,
                    totalAmount: totalAmountA,
                    totalInterest: totalInterestA
                },
                loanB: {
                    monthlyPayment: monthlyPaymentB,
                    totalAmount: totalAmountB,
                    totalInterest: totalInterestB
                }
            });
        }
        
        function displayResults(data) {
            const resultsCard = document.getElementById('resultsCard');
            const resultsContent = document.getElementById('resultsContent');
            const saveForm = document.getElementById('saveCalculationForm');
            
            let html = '';
            
            if (data.type === 'payment') {
                html = `
                    <div class="result-item">
                        <h6>Monthly Payment</h6>
                        <h4>$${data.monthlyPayment.toFixed(2)}</h4>
                    </div>
                    <div class="result-item">
                        <div class="row">
                            <div class="col-6">
                                <small>Total Interest</small><br>
                                <strong>$${data.totalInterest.toFixed(2)}</strong>
                            </div>
                            <div class="col-6">
                                <small>Total Amount</small><br>
                                <strong>$${data.totalAmount.toFixed(2)}</strong>
                            </div>
                        </div>
                    </div>
                `;
                saveForm.style.display = 'block';
            } else if (data.type === 'affordability') {
                html = `
                    <div class="result-item">
                        <h6>Affordable Payment</h6>
                        <h4>$${data.affordablePayment.toFixed(2)}</h4>
                    </div>
                    <div class="result-item">
                        <div class="row">
                            <div class="col-6">
                                <small>Available Income</small><br>
                                <strong>$${data.availableIncome.toFixed(2)}</strong>
                            </div>
                            <div class="col-6">
                                <small>Max Loan Amount</small><br>
                                <strong>$${data.maxLoanAmount.toFixed(0)}</strong>
                            </div>
                        </div>
                    </div>
                `;
                saveForm.style.display = 'none';
            } else if (data.type === 'comparison') {
                const betterOption = data.loanA.totalAmount < data.loanB.totalAmount ? 'A' : 'B';
                const savings = Math.abs(data.loanA.totalAmount - data.loanB.totalAmount);
                
                html = `
                    <div class="result-item">
                        <h6>Loan Comparison</h6>
                        <p>Option ${betterOption} saves you <strong>$${savings.toFixed(2)}</strong></p>
                    </div>
                    <div class="result-item">
                        <div class="row">
                            <div class="col-6 text-center">
                                <small>Option A</small><br>
                                <strong>$${data.loanA.monthlyPayment.toFixed(2)}/mo</strong><br>
                                <small>Total: $${data.loanA.totalAmount.toFixed(2)}</small>
                            </div>
                            <div class="col-6 text-center">
                                <small>Option B</small><br>
                                <strong>$${data.loanB.monthlyPayment.toFixed(2)}/mo</strong><br>
                                <small>Total: $${data.loanB.totalAmount.toFixed(2)}</small>
                            </div>
                        </div>
                    </div>
                `;
                saveForm.style.display = 'none';
            }
            
            resultsContent.innerHTML = html;
            resultsCard.style.display = 'block';
        }
        
        function createPaymentChart(principal, interest) {
            const ctx = document.getElementById('paymentChart').getContext('2d');
            
            if (paymentChart) {
                paymentChart.destroy();
            }
            
            paymentChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Principal', 'Interest'],
                    datasets: [{
                        data: [principal, interest],
                        backgroundColor: ['#28a745', '#dc3545'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            document.getElementById('chartContainer').style.display = 'block';
        }
        
        function loadCalculation(calc) {
            document.getElementById('loanAmount').value = calc.loan_amount;
            document.getElementById('interestRate').value = calc.interest_rate;
            document.getElementById('loanTerm').value = calc.loan_term;
            
            // Switch to payment calculator tab
            const paymentTab = new bootstrap.Tab(document.getElementById('payment-tab'));
            paymentTab.show();
            
            // Calculate and display results
            calculatePayment();
        }
        
        // Auto-calculate on input change
        document.addEventListener('DOMContentLoaded', function() {
            // Payment calculator inputs
            ['loanAmount', 'interestRate', 'loanTerm', 'processingFee'].forEach(id => {
                document.getElementById(id).addEventListener('input', function() {
                    if (document.getElementById('payment').classList.contains('active')) {
                        calculatePayment();
                    }
                });
            });
            
            // Affordability calculator inputs
            ['monthlyIncome', 'monthlyExpenses', 'existingDebt', 'dtiRatio'].forEach(id => {
                document.getElementById(id).addEventListener('input', function() {
                    if (document.getElementById('affordability').classList.contains('active')) {
                        calculateAffordability();
                    }
                });
            });
            
            // Comparison calculator inputs
            ['loanAmountA', 'interestRateA', 'loanTermA', 'loanAmountB', 'interestRateB', 'loanTermB'].forEach(id => {
                document.getElementById(id).addEventListener('input', function() {
                    if (document.getElementById('comparison').classList.contains('active')) {
                        compareLoans();
                    }
                });
            });
            
            // Initial calculation
            calculatePayment();
        });
    </script>
</body>
</html>