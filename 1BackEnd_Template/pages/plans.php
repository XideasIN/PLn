<?php
require_once '../config/config.php';
require_once '../data/dashboard_data.php';

$page_title = 'Plans';
?>

<?php include '../includes/header.php'; ?>

<div class="content">
    <div class="content-header">
        <h2 class="section-title">Strategic Plans</h2>
    </div>
    
    <div class="metrics-grid" style="grid-template-columns: repeat(3, 1fr);">
        <div class="metric-card">
            <div class="metric-category">ACTIVE PLANS</div>
            <div class="metric-value">
                <span class="metric-number">12</span>
            </div>
            <div style="font-size: 14px; color: var(--text-secondary);">Currently running strategic plans</div>
        </div>
        
        <div class="metric-card">
            <div class="metric-category">COMPLETION RATE</div>
            <div class="metric-value">
                <span class="metric-number">78%</span>
            </div>
            <div style="font-size: 14px; color: var(--text-secondary);">Average plan completion rate</div>
        </div>
        
        <div class="metric-card">
            <div class="metric-category">OVERDUE PLANS</div>
            <div class="metric-value">
                <span class="metric-number">3</span>
            </div>
            <div style="font-size: 14px; color: var(--text-secondary);">Plans requiring immediate attention</div>
        </div>
    </div>
    
    <div class="metric-card">
        <h3 style="margin-bottom: 20px; font-size: 18px; font-weight: 600;">Recent Plans</h3>
        
        <div style="display: grid; gap: 16px;">
            <div style="padding: 16px; border: 1px solid var(--border-light); border-radius: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                    <h4 style="font-weight: 600;">Q4 Marketing Campaign</h4>
                    <span style="background: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 16px; font-size: 12px;">Active</span>
                </div>
                <p style="color: var(--text-secondary); margin-bottom: 8px;">Launch comprehensive digital marketing strategy for Q4 objectives</p>
                <div style="font-size: 14px; color: var(--text-muted);">Due: December 31, 2024 • Progress: 65%</div>
            </div>
            
            <div style="padding: 16px; border: 1px solid var(--border-light); border-radius: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                    <h4 style="font-weight: 600;">Product Development Roadmap</h4>
                    <span style="background: #fef3cd; color: #92400e; padding: 4px 12px; border-radius: 16px; font-size: 12px;">In Review</span>
                </div>
                <p style="color: var(--text-secondary); margin-bottom: 8px;">Strategic roadmap for next year's product releases</p>
                <div style="font-size: 14px; color: var(--text-muted);">Due: January 15, 2025 • Progress: 45%</div>
            </div>
            
            <div style="padding: 16px; border: 1px solid var(--border-light); border-radius: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                    <h4 style="font-weight: 600;">Customer Success Initiative</h4>
                    <span style="background: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 16px; font-size: 12px;">Active</span>
                </div>
                <p style="color: var(--text-secondary); margin-bottom: 8px;">Improve customer satisfaction and retention rates</p>
                <div style="font-size: 14px; color: var(--text-muted);">Due: November 30, 2024 • Progress: 82%</div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>