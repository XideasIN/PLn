<?php
require_once '../config/config.php';
require_once '../data/dashboard_data.php';

$page_title = 'Dashboards';

$dashboard_templates = [
    ['name' => 'Executive Dashboard', 'description' => 'High-level overview for senior management', 'type' => 'Executive', 'users' => 5],
    ['name' => 'Loan Performance', 'description' => 'Detailed loan portfolio analytics', 'type' => 'Operational', 'users' => 12],
    ['name' => 'Risk Management', 'description' => 'Credit risk and compliance metrics', 'type' => 'Risk', 'users' => 8],
    ['name' => 'Collections Dashboard', 'description' => 'Delinquency and recovery tracking', 'type' => 'Collections', 'users' => 15],
    ['name' => 'Customer Analytics', 'description' => 'Customer behavior and segmentation', 'type' => 'Analytics', 'users' => 7]
];
?>

<?php include '../includes/header.php'; ?>

<div class="content">
    <div class="content-header">
        <h2 class="section-title">Custom Dashboards</h2>
    </div>
    
    <div class="metrics-grid" style="grid-template-columns: repeat(3, 1fr);">
        <div class="metric-card">
            <div class="metric-category">TOTAL DASHBOARDS</div>
            <div class="metric-value">
                <span class="metric-number"><?php echo count($dashboard_templates); ?></span>
            </div>
            <div style="font-size: 14px; color: var(--text-secondary);">Active dashboard configurations</div>
        </div>
        
        <div class="metric-card">
            <div class="metric-category">ACTIVE USERS</div>
            <div class="metric-value">
                <span class="metric-number"><?php echo array_sum(array_column($dashboard_templates, 'users')); ?></span>
            </div>
            <div style="font-size: 14px; color: var(--text-secondary);">Using dashboard analytics</div>
        </div>
        
        <div class="metric-card">
            <div class="metric-category">REFRESH RATE</div>
            <div class="metric-value">
                <span class="metric-number">5m</span>
            </div>
            <div style="font-size: 14px; color: var(--text-secondary);">Auto-refresh interval</div>
        </div>
    </div>
    
    <div class="metric-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="font-size: 18px; font-weight: 600;">Available Dashboards</h3>
            <button style="background: var(--primary-teal); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i data-lucide="plus" style="width: 16px; height: 16px; margin-right: 8px;"></i>
                Create New Dashboard
            </button>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
            <?php foreach($dashboard_templates as $dashboard): ?>
            <div style="padding: 20px; border: 1px solid var(--border-light); border-radius: 12px; background: var(--background-gray);">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                    <h4 style="font-weight: 600; font-size: 16px;"><?php echo $dashboard['name']; ?></h4>
                    <span style="background: var(--primary-teal); color: white; padding: 4px 8px; border-radius: 12px; font-size: 10px; font-weight: 600;">
                        <?php echo $dashboard['type']; ?>
                    </span>
                </div>
                
                <p style="color: var(--text-secondary); margin-bottom: 16px; font-size: 14px;">
                    <?php echo $dashboard['description']; ?>
                </p>
                
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 14px; color: var(--text-muted);">
                        <i data-lucide="users" style="width: 16px; height: 16px;"></i>
                        <span><?php echo $dashboard['users']; ?> users</span>
                    </div>
                    
                    <div style="display: flex; gap: 8px;">
                        <button style="background: white; color: var(--primary-teal); border: 1px solid var(--primary-teal); padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer;">
                            Preview
                        </button>
                        <button style="background: var(--primary-teal); color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer;">
                            Open
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>