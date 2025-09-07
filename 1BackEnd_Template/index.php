<?php
require_once 'config/config.php';
require_once 'data/dashboard_data.php';

$page_title = 'Workspace Insights';
?>

<?php include 'includes/header.php'; ?>

<div class="content">
    <!-- Workspace Audit Section -->
    <div class="content-header">
        <h2 class="section-title">Workspace Audit</h2>
    </div>
    
    <!-- Metrics Grid -->
    <div class="metrics-grid">
        <?php foreach($workspace_metrics as $index => $metric): ?>
        <div class="metric-card" style="animation-delay: <?php echo $index * 0.1; ?>s;">
            <div class="metric-header">
                <div class="metric-category"><?php echo $metric['category']; ?></div>
            </div>
            
            <div class="metric-value">
                <span class="metric-number"><?php echo $metric['value']; ?></span>
                <span class="metric-unit"><?php echo $metric['unit']; ?></span>
            </div>
            
            <div class="metric-warning">
                <i data-lucide="alert-triangle" class="warning-icon"></i>
                <div>
                    <div style="font-weight: 600;">Warning</div>
                    <div><?php echo $metric['warning_text']; ?></div>
                </div>
                <span style="margin-left: auto; font-weight: 600;"><?php echo $metric['warning_count']; ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Audit Score Section -->
    <div class="audit-section">
        <div class="audit-overview">
            <h3 class="section-title">OVERALL AUDIT SCORE</h3>
            
            <div class="audit-score-circle">
                <svg width="120" height="120" viewBox="0 0 120 120">
                    <circle cx="60" cy="60" r="52" class="circle-bg"></circle>
                    <circle cx="60" cy="60" r="52" class="circle-progress" 
                            stroke-dasharray="326.7256637168141" 
                            stroke-dashoffset="81.68141592920353"></circle>
                </svg>
                <div class="score-text">75%</div>
            </div>
            
            <div class="audit-title">Overall Audit Score</div>
            <div class="audit-subtitle">Last updated 2 hours ago</div>
        </div>
        
        <div class="audit-details">
            <?php foreach($audit_scores as $audit): ?>
            <div class="audit-item">
                <div class="audit-item-title"><?php echo $audit['title']; ?></div>
                <div class="audit-progress">
                    <span class="audit-score"><?php echo $audit['score']; ?>%</span>
                    <div class="progress-bar">
                        <div class="progress-fill" data-percentage="<?php echo $audit['score']; ?>" style="width: 0%;"></div>
                    </div>
                    <span class="audit-percentage"><?php echo $audit['percentage']; ?>%</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Recent Activities -->
    <div class="activities-section">
        <h3 class="section-title">Recent Activities</h3>
        
        <div class="activities-grid">
            <div class="activity-chart check-in-chart">
                <div class="chart-title">CHECK IN CREATED</div>
                <div class="chart-container"></div>
            </div>
            
            <div class="activity-chart key-results-chart">
                <div class="chart-title">KEY RESULTS VIEWED</div>
                <div class="chart-container"></div>
            </div>
            
            <div class="activity-chart tasks-chart">
                <div class="chart-title">TASKS UPDATED</div>
                <div class="chart-container"></div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>