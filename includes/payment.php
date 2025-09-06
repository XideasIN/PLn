<?php
/**
 * Payment Processing System
 * LoanFlow Personal Loan Management System
 */

// Get active payment scheme
function getActivePaymentScheme() {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM payment_schemes WHERE is_active = 1 LIMIT 1");
        $stmt->execute();
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Get active payment scheme failed: " . $e->getMessage());
        return null;
    }
}

// Calculate payment amount based on scheme
function calculatePaymentAmount($loan_amount, $scheme = null) {
    if (!$scheme) {
        $scheme = getActivePaymentScheme();
    }
    
    if (!$scheme) {
        return 0;
    }
    
    switch ($scheme['scheme_type']) {
        case 'subscription':
            return $scheme['subscription_fee'];
        case 'percentage':
            return ($loan_amount * $scheme['percentage_fee']) / 100;
        default:
            return 0;
    }
}

// Create payment record
function createPayment($user_id, $application_id, $amount, $payment_type, $payment_method = null) {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            INSERT INTO payments (
                user_id, application_id, payment_type, amount, currency, 
                payment_method, payment_status, due_date
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 7 DAY))
        ");
        
        $user = getUserById($user_id);
        $currency = getCountrySettings($user['country'])['currency'] ?? 'USD';
        
        $result = $stmt->execute([
            $user_id,
            $application_id,
            $payment_type,
            $amount,
            $currency,
            $payment_method
        ]);
        
        if ($result) {
            $payment_id = $db->lastInsertId();
            
            // Log audit
            logAudit('payment_created', 'payments', $payment_id, null, [
                'amount' => $amount,
                'payment_type' => $payment_type,
                'payment_method' => $payment_method
            ]);
            
            return $payment_id;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Create payment failed: " . $e->getMessage());
        return false;
    }
}

// Update payment status
function updatePaymentStatus($payment_id, $status, $transaction_id = null, $notes = null) {
    try {
        $db = getDB();
        
        $update_fields = ['payment_status = ?', 'updated_at = NOW()'];
        $params = [$status];
        
        if ($transaction_id) {
            $update_fields[] = 'transaction_id = ?';
            $params[] = $transaction_id;
        }
        
        if ($status === 'completed') {
            $update_fields[] = 'payment_date = NOW()';
        }
        
        if ($notes) {
            $update_fields[] = 'notes = ?';
            $params[] = $notes;
        }
        
        $params[] = $payment_id;
        
        $stmt = $db->prepare("
            UPDATE payments SET " . implode(', ', $update_fields) . " WHERE id = ?
        ");
        
        $result = $stmt->execute($params);
        
        if ($result && $status === 'completed') {
            // Get payment details
            $payment = getPaymentById($payment_id);
            if ($payment) {
                // Add memo
                addMemo($payment['user_id'], "Payment completed: " . formatCurrency($payment['amount']), 'system');
                
                // Check if this completes the payment step
                $application = getApplicationById($payment['application_id']);
                if ($application && $application['current_step'] <= 5) {
                    updateCurrentStep($payment['user_id'], 6);
                    updateApplicationStatus($payment['application_id'], 'approved');
                    
                    // Send approval email
                    sendApprovalEmail($payment['user_id'], [
                        'loan_amount' => $application['loan_amount'],
                        'reference_number' => $application['reference_number']
                    ]);
                }
            }
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Update payment status failed: " . $e->getMessage());
        return false;
    }
}

// Get payment by ID
function getPaymentById($payment_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM payments WHERE id = ? LIMIT 1");
        $stmt->execute([$payment_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Get payment by ID failed: " . $e->getMessage());
        return false;
    }
}

// Get payments by user ID
function getPaymentsByUserId($user_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get payments by user ID failed: " . $e->getMessage());
        return [];
    }
}

// Generate payment instructions
function generatePaymentInstructions($payment_id) {
    try {
        $payment = getPaymentById($payment_id);
        if (!$payment) {
            throw new Exception("Payment not found");
        }
        
        $user = getUserById($payment['user_id']);
        if (!$user) {
            throw new Exception("User not found");
        }
        
        $country_settings = getCountrySettings($user['country']);
        $payment_methods = getPaymentMethods($user['country']);
        
        $instructions = [
            'payment_id' => $payment_id,
            'amount' => $payment['amount'],
            'currency' => $payment['currency'],
            'formatted_amount' => formatCurrency($payment['amount'], $user['country']),
            'reference_number' => $user['reference_number'],
            'due_date' => $payment['due_date'],
            'methods' => []
        ];
        
        foreach ($payment_methods as $method) {
            switch ($method) {
                case 'wire_transfer':
                    $instructions['methods']['wire_transfer'] = [
                        'name' => 'Wire Transfer',
                        'instructions' => getWireTransferInstructions($user['country']),
                        'processing_time' => '1-3 business days'
                    ];
                    break;
                    
                case 'crypto':
                    $instructions['methods']['crypto'] = [
                        'name' => 'Cryptocurrency',
                        'instructions' => getCryptoInstructions(),
                        'processing_time' => '1-2 hours'
                    ];
                    break;
                    
                case 'e_transfer':
                    $instructions['methods']['e_transfer'] = [
                        'name' => 'E-Transfer',
                        'instructions' => getETransferInstructions(),
                        'processing_time' => '15-30 minutes'
                    ];
                    break;
            }
        }
        
        return $instructions;
        
    } catch (Exception $e) {
        error_log("Generate payment instructions failed: " . $e->getMessage());
        return null;
    }
}

// Get wire transfer instructions by country
function getWireTransferInstructions($country) {
    $instructions = [
        'USA' => [
            'bank_name' => 'LoanFlow Bank USA',
            'account_name' => 'LoanFlow Inc.',
            'account_number' => '1234567890',
            'routing_number' => '021000021',
            'swift_code' => 'LOANFLUS33',
            'address' => '123 Finance St, New York, NY 10001'
        ],
        'CAN' => [
            'bank_name' => 'LoanFlow Bank Canada',
            'account_name' => 'LoanFlow Inc.',
            'account_number' => '9876543210',
            'institution_number' => '001',
            'transit_number' => '12345',
            'swift_code' => 'LOANFLCA33',
            'address' => '456 Bay St, Toronto, ON M5H 2Y4'
        ],
        'GBR' => [
            'bank_name' => 'LoanFlow Bank UK',
            'account_name' => 'LoanFlow Ltd.',
            'account_number' => '12345678',
            'sort_code' => '12-34-56',
            'swift_code' => 'LOANFLGB33',
            'address' => '789 City Rd, London EC1V 1AA'
        ],
        'AUS' => [
            'bank_name' => 'LoanFlow Bank Australia',
            'account_name' => 'LoanFlow Pty Ltd.',
            'account_number' => '987654321',
            'bsb' => '123-456',
            'swift_code' => 'LOANFLAU33',
            'address' => '321 Collins St, Melbourne VIC 3000'
        ]
    ];
    
    return $instructions[$country] ?? $instructions['USA'];
}

// Get cryptocurrency payment instructions
function getCryptoInstructions() {
    return [
        'bitcoin' => [
            'name' => 'Bitcoin (BTC)',
            'address' => '1LoanFlowBitcoinAddressExample123456',
            'network' => 'Bitcoin Network'
        ],
        'ethereum' => [
            'name' => 'Ethereum (ETH)',
            'address' => '0xLoanFlowEthereumAddressExample123456789',
            'network' => 'Ethereum Network'
        ],
        'usdt' => [
            'name' => 'Tether (USDT)',
            'address' => '0xLoanFlowUSDTAddressExample123456789',
            'network' => 'Ethereum Network (ERC-20)'
        ]
    ];
}

// Get e-transfer instructions (Canada)
function getETransferInstructions() {
    return [
        'email' => 'payments@loanflow.com',
        'security_question' => 'What is the name of our company?',
        'security_answer' => 'LoanFlow',
        'memo' => 'Include your reference number in the message'
    ];
}

// Process refund
function processRefund($payment_id, $refund_amount = null, $reason = '') {
    try {
        $db = getDB();
        
        $payment = getPaymentById($payment_id);
        if (!$payment) {
            throw new Exception("Payment not found");
        }
        
        if ($payment['payment_status'] !== 'completed') {
            throw new Exception("Can only refund completed payments");
        }
        
        if (!$refund_amount) {
            $refund_amount = $payment['amount'];
        }
        
        // Create refund record
        $stmt = $db->prepare("
            INSERT INTO payments (
                user_id, application_id, payment_type, amount, currency,
                payment_method, payment_status, transaction_id, notes
            ) VALUES (?, ?, 'refund', ?, ?, ?, 'completed', ?, ?)
        ");
        
        $result = $stmt->execute([
            $payment['user_id'],
            $payment['application_id'],
            -abs($refund_amount), // Negative amount for refund
            $payment['currency'],
            $payment['payment_method'],
            'REFUND_' . $payment['transaction_id'],
            'Refund: ' . $reason
        ]);
        
        if ($result) {
            $refund_id = $db->lastInsertId();
            
            // Add memo
            addMemo($payment['user_id'], "Refund processed: " . formatCurrency($refund_amount) . ". Reason: $reason", 'system');
            
            // Log audit
            logAudit('refund_processed', 'payments', $refund_id, null, [
                'original_payment_id' => $payment_id,
                'refund_amount' => $refund_amount,
                'reason' => $reason
            ]);
            
            return $refund_id;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Process refund failed: " . $e->getMessage());
        return false;
    }
}

// Check if payment is overdue
function isPaymentOverdue($payment) {
    if ($payment['payment_status'] === 'completed') {
        return false;
    }
    
    return strtotime($payment['due_date']) < time();
}

// Get payment statistics
function getPaymentStats() {
    try {
        $db = getDB();
        
        $stats = [];
        
        // Total revenue (completed payments)
        $stmt = $db->query("
            SELECT SUM(amount) as total 
            FROM payments 
            WHERE payment_status = 'completed' AND amount > 0
        ");
        $stats['total_revenue'] = (float)($stmt->fetch()['total'] ?? 0);
        
        // Monthly revenue
        $stmt = $db->query("
            SELECT SUM(amount) as total 
            FROM payments 
            WHERE payment_status = 'completed' 
            AND amount > 0
            AND MONTH(payment_date) = MONTH(NOW()) 
            AND YEAR(payment_date) = YEAR(NOW())
        ");
        $stats['monthly_revenue'] = (float)($stmt->fetch()['total'] ?? 0);
        
        // Pending payments
        $stmt = $db->query("
            SELECT COUNT(*) as count, SUM(amount) as total 
            FROM payments 
            WHERE payment_status = 'pending'
        ");
        $pending = $stmt->fetch();
        $stats['pending_count'] = (int)$pending['count'];
        $stats['pending_amount'] = (float)($pending['total'] ?? 0);
        
        // Overdue payments
        $stmt = $db->query("
            SELECT COUNT(*) as count, SUM(amount) as total 
            FROM payments 
            WHERE payment_status = 'pending' AND due_date < NOW()
        ");
        $overdue = $stmt->fetch();
        $stats['overdue_count'] = (int)$overdue['count'];
        $stats['overdue_amount'] = (float)($overdue['total'] ?? 0);
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Get payment stats failed: " . $e->getMessage());
        return [
            'total_revenue' => 0,
            'monthly_revenue' => 0,
            'pending_count' => 0,
            'pending_amount' => 0,
            'overdue_count' => 0,
            'overdue_amount' => 0
        ];
    }
}

// Generate payment receipt
function generatePaymentReceipt($payment_id) {
    try {
        $payment = getPaymentById($payment_id);
        if (!$payment || $payment['payment_status'] !== 'completed') {
            throw new Exception("Payment not found or not completed");
        }
        
        $user = getUserById($payment['user_id']);
        if (!$user) {
            throw new Exception("User not found");
        }
        
        $application = getApplicationById($payment['application_id']);
        
        return [
            'receipt_number' => 'REC-' . str_pad($payment_id, 8, '0', STR_PAD_LEFT),
            'payment_id' => $payment_id,
            'reference_number' => $user['reference_number'],
            'client_name' => $user['first_name'] . ' ' . $user['last_name'],
            'amount' => $payment['amount'],
            'formatted_amount' => formatCurrency($payment['amount'], $user['country']),
            'currency' => $payment['currency'],
            'payment_method' => ucfirst(str_replace('_', ' ', $payment['payment_method'])),
            'transaction_id' => $payment['transaction_id'],
            'payment_date' => $payment['payment_date'],
            'payment_type' => ucfirst(str_replace('_', ' ', $payment['payment_type'])),
            'loan_amount' => $application ? $application['loan_amount'] : null,
            'formatted_loan_amount' => $application ? formatCurrency($application['loan_amount'], $user['country']) : null
        ];
        
    } catch (Exception $e) {
        error_log("Generate payment receipt failed: " . $e->getMessage());
        return null;
    }
}
?>