<?php
require_once '../config/config.php';
require_once '../data/dashboard_data.php';

$page_title = 'Insights';

$insights_data = [
    ['metric' => 'Loan Applications', 'current' => 245, 'previous' => 198, 'trend' => 'up'],
    ['metric' => 'Approval Rate', 'current' => 78.5, 'previous' => 82.1, 'trend' => 'down'],
    ['metric' => 'Average Loan Amount', 'current' => 24750, 'previous' => 23500, 'trend' => 'up'],
    ['metric' => 'Default Rate', 'current' => 2.3, 'previous' => 2.8, 'trend' => 'down']
];
?>

<?php include '../includes/header.php'; ?>

<div class="content">
    <div class="content-header">
        <h2 class="section-title">Business Insights</h2>
    </div>
    
    <div class="metrics-grid">
        <?php foreach($insights_data as $insight): ?>
        <div class="metric-card">
            <div class="metric-category"><?php echo strtoupper($insight['metric']); ?></div>
            <div class="metric-value">
                <span class="metric-number">
                    <?php 
                    if(strpos($insight['metric'], 'Rate') !== false) {
                        echo $insight['current'] . '%';
                    } elseif(strpos($insight['metric'], 'Amount') !== false) {
                        echo '$' . number_format($insight['current']);
                    } else {
                        echo number_format($insight['current']);
                    }
                    ?>
                </span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 14px;">
                <i data-lucide="<?php echo $insight['trend'] === 'up' ? 'trending-up' : 'trending-down'; ?>" 
                   style="width: 16px; height: 16px; color: <?php echo $insight['trend'] === 'up' ? 'var(--success)' : 'var(--danger)'; ?>;"></i>
                <span style="color: <?php echo $insight['trend'] === 'up' ? 'var(--success)' : 'var(--danger)'; ?>;">
                    <?php 
                    $change = abs($insight['current'] - $insight['previous']);
                    $percentage = ($change / $insight['previous']) * 100;
                    echo ($insight['trend'] === 'up' ? '+' : '-') . number_format($percentage, 1) . '%';
                    ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="metric-card">
        <h3 style="margin-bottom: 20px; font-size: 18px; font-weight: 600;">Key Performance Indicators</h3>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
            <div style="padding: 20px; background: var(--background-gray); border-radius: 12px;">
                <h4 style="font-weight: 600; margin-bottom: 12px;">Loan Portfolio Health</h4>
                <div style="margin-bottom: 8px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <span>Performing Loans</span>
                        <span style="font-weight: 600;">94.2%</span>
                    </div>
                    <div style="background: var(--border-light); height: 6px; border-radius: 3px;">
                        <div style="background: var(--success); width: 94.2%; height: 100%; border-radius: 3px;"></div>
                    </div>
                </div>
                <div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <span>Non-performing Loans</span>
                        <span style="font-weight: 600;">5.8%</span>
                    </div>
                    <div style="background: var(--border-light); height: 6px; border-radius: 3px;">
                        <div style="background: var(--warning); width: 5.8%; height: 100%; border-radius: 3px;"></div>
                    </div>
                </div>
            </div>
            
            <div style="padding: 20px; background: var(--background-gray); border-radius: 12px;">
                <h4 style="font-weight: 600; margin-bottom: 12px;">Monthly Trends</h4>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span>New Applications</span>
                    <span style="color: var(--success); font-weight: 600;">↗ +15.3%</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span>Loan Disbursements</span>
                    <span style="color: var(--success); font-weight: 600;">↗ +8.7%</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Collections</span>
                    <span style="color: var(--danger); font-weight: 600;">↘ -2.1%</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>