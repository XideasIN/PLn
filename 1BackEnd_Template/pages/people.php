<?php
require_once '../config/config.php';
require_once '../data/dashboard_data.php';

$page_title = 'People';

$team_members = [
    ['name' => 'Sarah Johnson', 'role' => 'Loan Officer', 'department' => 'Lending', 'status' => 'Active', 'loans' => 45],
    ['name' => 'Michael Chen', 'role' => 'Credit Analyst', 'department' => 'Risk Management', 'status' => 'Active', 'loans' => 32],
    ['name' => 'Emily Rodriguez', 'role' => 'Collections Specialist', 'department' => 'Collections', 'status' => 'Active', 'loans' => 67],
    ['name' => 'David Thompson', 'role' => 'Branch Manager', 'department' => 'Operations', 'status' => 'Active', 'loans' => 28],
    ['name' => 'Lisa Wong', 'role' => 'Compliance Officer', 'department' => 'Compliance', 'status' => 'Away', 'loans' => 18]
];
?>

<?php include '../includes/header.php'; ?>

<div class="content">
    <div class="content-header">
        <h2 class="section-title">Team Members</h2>
    </div>
    
    <div class="metrics-grid" style="grid-template-columns: repeat(4, 1fr);">
        <div class="metric-card">
            <div class="metric-category">TOTAL STAFF</div>
            <div class="metric-value">
                <span class="metric-number">42</span>
            </div>
            <div style="font-size: 14px; color: var(--text-secondary);">Active team members</div>
        </div>
        
        <div class="metric-card">
            <div class="metric-category">LOAN OFFICERS</div>
            <div class="metric-value">
                <span class="metric-number">15</span>
            </div>
            <div style="font-size: 14px; color: var(--text-secondary);">Front-line staff</div>
        </div>
        
        <div class="metric-card">
            <div class="metric-category">CREDIT ANALYSTS</div>
            <div class="metric-value">
                <span class="metric-number">8</span>
            </div>
            <div style="font-size: 14px; color: var(--text-secondary);">Risk assessment team</div>
        </div>
        
        <div class="metric-card">
            <div class="metric-category">DEPARTMENTS</div>
            <div class="metric-value">
                <span class="metric-number">6</span>
            </div>
            <div style="font-size: 14px; color: var(--text-secondary);">Active departments</div>
        </div>
    </div>
    
    <div class="metric-card">
        <h3 style="margin-bottom: 20px; font-size: 18px; font-weight: 600;">Team Directory</h3>
        
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border);">
                    <th style="padding: 16px; text-align: left; font-weight: 600;">Name</th>
                    <th style="padding: 16px; text-align: left; font-weight: 600;">Role</th>
                    <th style="padding: 16px; text-align: left; font-weight: 600;">Department</th>
                    <th style="padding: 16px; text-align: left; font-weight: 600;">Status</th>
                    <th style="padding: 16px; text-align: left; font-weight: 600;">Active Loans</th>
                    <th style="padding: 16px; text-align: left; font-weight: 600;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($team_members as $member): ?>
                <tr style="border-bottom: 1px solid var(--border-light);">
                    <td style="padding: 16px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 40px; height: 40px; background: var(--primary-teal); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                <?php echo strtoupper(substr($member['name'], 0, 1)); ?>
                            </div>
                            <span style="font-weight: 500;"><?php echo $member['name']; ?></span>
                        </div>
                    </td>
                    <td style="padding: 16px;"><?php echo $member['role']; ?></td>
                    <td style="padding: 16px;"><?php echo $member['department']; ?></td>
                    <td style="padding: 16px;">
                        <span style="padding: 4px 12px; border-radius: 16px; font-size: 12px; font-weight: 500; 
                                     <?php 
                                     if($member['status'] === 'Active') echo 'background: #dcfce7; color: #166534;';
                                     elseif($member['status'] === 'Away') echo 'background: #fef3cd; color: #92400e;';
                                     ?>">
                            <?php echo $member['status']; ?>
                        </span>
                    </td>
                    <td style="padding: 16px; text-align: center; font-weight: 600; color: var(--primary-teal);">
                        <?php echo $member['loans']; ?>
                    </td>
                    <td style="padding: 16px;">
                        <button style="background: var(--primary-teal); color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; margin-right: 8px;">
                            View Profile
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>