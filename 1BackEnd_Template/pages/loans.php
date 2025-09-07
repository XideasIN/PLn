<?php
require_once '../config/config.php';
require_once '../data/dashboard_data.php';

$page_title = 'Loan Management';

// Mock loan data
$loans = [
    ['id' => 'LN001', 'borrower' => 'John Smith', 'amount' => '$25,000', 'rate' => '5.5%', 'status' => 'Active', 'due_date' => '2024-12-15'],
    ['id' => 'LN002', 'borrower' => 'Sarah Johnson', 'amount' => '$18,500', 'rate' => '4.8%', 'status' => 'Pending', 'due_date' => '2024-11-30'],
    ['id' => 'LN003', 'borrower' => 'Mike Brown', 'amount' => '$32,000', 'rate' => '6.2%', 'status' => 'Active', 'due_date' => '2025-01-20'],
    ['id' => 'LN004', 'borrower' => 'Emily Davis', 'amount' => '$15,750', 'rate' => '5.0%', 'status' => 'Overdue', 'due_date' => '2024-10-15'],
];
?>

<?php include '../includes/header.php'; ?>

<div class="content">
    <div class="content-header">
        <h2 class="section-title">Consumer Loans</h2>
    </div>
    
    <div class="metric-card">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border);">
                    <th style="padding: 16px; text-align: left; font-weight: 600;">Loan ID</th>
                    <th style="padding: 16px; text-align: left; font-weight: 600;">Borrower</th>
                    <th style="padding: 16px; text-align: left; font-weight: 600;">Amount</th>
                    <th style="padding: 16px; text-align: left; font-weight: 600;">Interest Rate</th>
                    <th style="padding: 16px; text-align: left; font-weight: 600;">Status</th>
                    <th style="padding: 16px; text-align: left; font-weight: 600;">Due Date</th>
                    <th style="padding: 16px; text-align: left; font-weight: 600;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($loans as $loan): ?>
                <tr style="border-bottom: 1px solid var(--border-light);">
                    <td style="padding: 16px; font-weight: 500;"><?php echo $loan['id']; ?></td>
                    <td style="padding: 16px;"><?php echo $loan['borrower']; ?></td>
                    <td style="padding: 16px; font-weight: 600; color: var(--primary-teal);"><?php echo $loan['amount']; ?></td>
                    <td style="padding: 16px;"><?php echo $loan['rate']; ?></td>
                    <td style="padding: 16px;">
                        <span style="padding: 4px 12px; border-radius: 16px; font-size: 12px; font-weight: 500; 
                                     <?php 
                                     if($loan['status'] === 'Active') echo 'background: #dcfce7; color: #166534;';
                                     elseif($loan['status'] === 'Pending') echo 'background: #fef3cd; color: #92400e;';
                                     elseif($loan['status'] === 'Overdue') echo 'background: #fee2e2; color: #dc2626;';
                                     ?>">
                            <?php echo $loan['status']; ?>
                        </span>
                    </td>
                    <td style="padding: 16px;"><?php echo $loan['due_date']; ?></td>
                    <td style="padding: 16px;">
                        <button style="background: var(--primary-teal); color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer;">
                            View Details
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>