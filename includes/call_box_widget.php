<?php
/**
 * CALL BOX Widget
 * LoanFlow Personal Loan Management System
 * 
 * Reusable widget that displays pending calls count and provides quick access
 * to the call list. Can be included on any admin page.
 */

if (!defined('CALL_BOX_INCLUDED')) {
    define('CALL_BOX_INCLUDED', true);
    
    // Get pending calls count
    function getPendingCallsCount() {
        try {
            $db = getDB();
            $stmt = $db->query("
                SELECT COUNT(*) as total,
                       SUM(CASE WHEN list_type = 'new_application' THEN 1 ELSE 0 END) as new_apps,
                       SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent,
                       SUM(CASE WHEN callback_date IS NOT NULL AND callback_date <= NOW() THEN 1 ELSE 0 END) as overdue
                FROM call_lists 
                WHERE status IN ('pending', 'contacted')
            ");
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Call Box Widget Error: " . $e->getMessage());
            return ['total' => 0, 'new_apps' => 0, 'urgent' => 0, 'overdue' => 0];
        }
    }
    
    $call_stats = getPendingCallsCount();
    
    // Determine widget color based on priority
    $widget_color = 'primary';
    if ($call_stats['urgent'] > 0 || $call_stats['overdue'] > 0) {
        $widget_color = 'danger';
    } elseif ($call_stats['new_apps'] > 0) {
        $widget_color = 'warning';
    }
    
    // Only show widget if there are pending calls
    if ($call_stats['total'] > 0):
?>

<!-- CALL BOX Widget Styles -->
<style>
    .call-box-widget {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 1050;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .call-box-widget:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 20px rgba(0,0,0,0.25);
    }
    .call-box-widget .btn {
        border: none;
        font-size: 1.2rem;
        padding: 15px;
        position: relative;
    }
    .call-box-widget .call-details {
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 10px;
        min-width: 200px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        display: none;
        margin-top: 5px;
    }
    .call-box-widget:hover .call-details {
        display: block;
    }
    .call-detail-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 3px 0;
        font-size: 0.85rem;
    }
    .call-detail-item:not(:last-child) {
        border-bottom: 1px solid #eee;
        margin-bottom: 3px;
        padding-bottom: 6px;
    }
    .pulse-animation {
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
</style>

<!-- CALL BOX Widget -->
<div class="call-box-widget <?= $call_stats['urgent'] > 0 ? 'pulse-animation' : '' ?>" 
     onclick="window.location.href='<?= basename($_SERVER['PHP_SELF']) === 'call-list.php' ? '#callListSection' : 'call-list.php' ?>'" 
     title="Click to view call list">
    
    <div class="btn btn-<?= $widget_color ?> btn-lg rounded-circle position-relative">
        <i class="fas fa-phone"></i>
        
        <!-- Total Count Badge -->
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-light text-dark">
            <?= $call_stats['total'] ?>
        </span>
        
        <!-- Urgent Indicator -->
        <?php if ($call_stats['urgent'] > 0): ?>
            <span class="position-absolute bottom-0 start-0 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                !
            </span>
        <?php endif; ?>
    </div>
    
    <!-- Hover Details -->
    <div class="call-details">
        <div class="fw-bold text-center mb-2 text-primary">
            <i class="fas fa-phone me-1"></i>Pending Calls
        </div>
        
        <div class="call-detail-item">
            <span><i class="fas fa-exclamation-triangle text-danger me-1"></i>New Applications:</span>
            <span class="badge bg-danger"><?= $call_stats['new_apps'] ?></span>
        </div>
        
        <div class="call-detail-item">
            <span><i class="fas fa-clock text-warning me-1"></i>Urgent Calls:</span>
            <span class="badge bg-warning"><?= $call_stats['urgent'] ?></span>
        </div>
        
        <div class="call-detail-item">
            <span><i class="fas fa-calendar-times text-danger me-1"></i>Overdue:</span>
            <span class="badge bg-danger"><?= $call_stats['overdue'] ?></span>
        </div>
        
        <div class="call-detail-item">
            <span><i class="fas fa-list text-info me-1"></i>Total Pending:</span>
            <span class="badge bg-info"><?= $call_stats['total'] ?></span>
        </div>
        
        <div class="text-center mt-2">
            <small class="text-muted">Click to manage calls</small>
        </div>
    </div>
</div>

<!-- Auto-refresh script for real-time updates -->
<script>
    // Auto-refresh call box every 2 minutes
    setInterval(function() {
        // Only refresh if not on call-list page to avoid conflicts
        if (!window.location.pathname.includes('call-list.php')) {
            fetch('<?= dirname($_SERVER['PHP_SELF']) ?>/call-list.php?ajax=get_stats')
                .then(response => response.json())
                .then(data => {
                    if (data.total !== <?= $call_stats['total'] ?>) {
                        // Reload page if call count changed
                        location.reload();
                    }
                })
                .catch(error => console.log('Call box refresh error:', error));
        }
    }, 120000); // 2 minutes
    
    // Add click sound effect (optional)
    document.querySelector('.call-box-widget').addEventListener('click', function() {
        // Create a subtle click sound using Web Audio API
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.1);
        } catch (e) {
            // Ignore audio errors
        }
    });
</script>

<?php endif; // End if pending calls > 0 ?>

<?php
    // AJAX endpoint for stats refresh
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_stats') {
        header('Content-Type: application/json');
        echo json_encode($call_stats);
        exit;
    }
}
?>