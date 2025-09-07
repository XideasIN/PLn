<?php
require_once '../config/config.php';
require_once '../data/dashboard_data.php';

$page_title = 'Strategy Map';
?>

<?php include '../includes/header.php'; ?>

<div class="content">
    <div class="content-header">
        <h2 class="section-title">Strategy Map</h2>
    </div>
    
    <div class="metric-card">
        <div style="text-align: center; padding: 60px 40px;">
            <div style="width: 80px; height: 80px; background: var(--background-gray); border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
                <i data-lucide="map" style="width: 32px; height: 32px; color: var(--text-muted);"></i>
            </div>
            <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 12px;">Strategy Map Coming Soon</h3>
            <p style="color: var(--text-secondary); margin-bottom: 24px;">Visual representation of your strategic objectives and their relationships will be available here.</p>
            <button style="background: var(--primary-teal); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 500; cursor: pointer;">
                Create Strategy Map
            </button>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>