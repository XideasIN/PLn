<?php
/**
 * Memo Color Coding System
 * Provides visual indicators for client files based on memo activity
 */

class MemoColorCoding {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get memo status for a user
     * Returns color coding information based on recent memo activity
     */
    public function getMemoStatus($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    MAX(created_at) as last_memo_date,
                    COUNT(*) as total_memos,
                    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_memos,
                    COUNT(CASE WHEN memo_type = 'manual' AND DATE(created_at) = CURDATE() THEN 1 END) as today_manual_memos
                FROM client_memos 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result || !$result['last_memo_date']) {
                return [
                    'status' => 'no_memo',
                    'css_class' => '',
                    'indicator' => '',
                    'tooltip' => 'No memos recorded',
                    'days_since' => null
                ];
            }
            
            $lastMemoDate = new DateTime($result['last_memo_date']);
            $today = new DateTime();
            $daysSince = $today->diff($lastMemoDate)->days;
            
            // Today's manual memo (highest priority)
            if ($result['today_manual_memos'] > 0) {
                return [
                    'status' => 'memo_today',
                    'css_class' => 'memo-today',
                    'indicator' => '<i class="fas fa-sticky-note text-success"></i>',
                    'tooltip' => 'Manual memo added today (' . $result['today_manual_memos'] . ' memo' . ($result['today_manual_memos'] > 1 ? 's' : '') . ')',
                    'days_since' => 0
                ];
            }
            
            // System memos today (lower priority)
            if ($result['today_memos'] > 0) {
                return [
                    'status' => 'system_memo_today',
                    'css_class' => 'system-memo-today',
                    'indicator' => '<i class="fas fa-robot text-info"></i>',
                    'tooltip' => 'System memo added today (' . $result['today_memos'] . ' memo' . ($result['today_memos'] > 1 ? 's' : '') . ')',
                    'days_since' => 0
                ];
            }
            
            // Recent memos (1-7 days)
            if ($daysSince <= 7) {
                return [
                    'status' => 'memo_recent',
                    'css_class' => 'memo-recent',
                    'indicator' => '<span class="badge bg-warning text-dark">' . $daysSince . '</span>',
                    'tooltip' => 'Last memo ' . $daysSince . ' day' . ($daysSince > 1 ? 's' : '') . ' ago',
                    'days_since' => $daysSince
                ];
            }
            
            // Old memos (8-30 days)
            if ($daysSince <= 30) {
                return [
                    'status' => 'memo_old',
                    'css_class' => 'memo-old',
                    'indicator' => '<span class="badge bg-secondary">' . $daysSince . '</span>',
                    'tooltip' => 'Last memo ' . $daysSince . ' days ago',
                    'days_since' => $daysSince
                ];
            }
            
            // Very old memos (30+ days)
            return [
                'status' => 'memo_very_old',
                'css_class' => 'memo-very-old',
                'indicator' => '<span class="badge bg-danger">' . $daysSince . '+</span>',
                'tooltip' => 'Last memo ' . $daysSince . ' days ago - needs attention',
                'days_since' => $daysSince
            ];
            
        } catch (Exception $e) {
            error_log("MemoColorCoding::getMemoStatus Error: " . $e->getMessage());
            return [
                'status' => 'error',
                'css_class' => '',
                'indicator' => '<i class="fas fa-exclamation-triangle text-danger"></i>',
                'tooltip' => 'Error loading memo status',
                'days_since' => null
            ];
        }
    }
    
    /**
     * Get memo status for multiple users (bulk operation)
     */
    public function getBulkMemoStatus($userIds) {
        if (empty($userIds)) {
            return [];
        }
        
        try {
            $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
            $stmt = $this->db->prepare("
                SELECT 
                    user_id,
                    MAX(created_at) as last_memo_date,
                    COUNT(*) as total_memos,
                    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_memos,
                    COUNT(CASE WHEN memo_type = 'manual' AND DATE(created_at) = CURDATE() THEN 1 END) as today_manual_memos
                FROM client_memos 
                WHERE user_id IN ($placeholders)
                GROUP BY user_id
            ");
            $stmt->execute($userIds);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $memoStatuses = [];
            
            // Process results for users with memos
            foreach ($results as $result) {
                $userId = $result['user_id'];
                $lastMemoDate = new DateTime($result['last_memo_date']);
                $today = new DateTime();
                $daysSince = $today->diff($lastMemoDate)->days;
                
                if ($result['today_manual_memos'] > 0) {
                    $memoStatuses[$userId] = [
                        'status' => 'memo_today',
                        'css_class' => 'memo-today',
                        'indicator' => '<i class="fas fa-sticky-note text-success"></i>',
                        'tooltip' => 'Manual memo added today (' . $result['today_manual_memos'] . ' memo' . ($result['today_manual_memos'] > 1 ? 's' : '') . ')',
                        'days_since' => 0
                    ];
                } elseif ($result['today_memos'] > 0) {
                    $memoStatuses[$userId] = [
                        'status' => 'system_memo_today',
                        'css_class' => 'system-memo-today',
                        'indicator' => '<i class="fas fa-robot text-info"></i>',
                        'tooltip' => 'System memo added today (' . $result['today_memos'] . ' memo' . ($result['today_memos'] > 1 ? 's' : '') . ')',
                        'days_since' => 0
                    ];
                } elseif ($daysSince <= 7) {
                    $memoStatuses[$userId] = [
                        'status' => 'memo_recent',
                        'css_class' => 'memo-recent',
                        'indicator' => '<span class="badge bg-warning text-dark">' . $daysSince . '</span>',
                        'tooltip' => 'Last memo ' . $daysSince . ' day' . ($daysSince > 1 ? 's' : '') . ' ago',
                        'days_since' => $daysSince
                    ];
                } elseif ($daysSince <= 30) {
                    $memoStatuses[$userId] = [
                        'status' => 'memo_old',
                        'css_class' => 'memo-old',
                        'indicator' => '<span class="badge bg-secondary">' . $daysSince . '</span>',
                        'tooltip' => 'Last memo ' . $daysSince . ' days ago',
                        'days_since' => $daysSince
                    ];
                } else {
                    $memoStatuses[$userId] = [
                        'status' => 'memo_very_old',
                        'css_class' => 'memo-very-old',
                        'indicator' => '<span class="badge bg-danger">' . $daysSince . '+</span>',
                        'tooltip' => 'Last memo ' . $daysSince . ' days ago - needs attention',
                        'days_since' => $daysSince
                    ];
                }
            }
            
            // Add default status for users without memos
            foreach ($userIds as $userId) {
                if (!isset($memoStatuses[$userId])) {
                    $memoStatuses[$userId] = [
                        'status' => 'no_memo',
                        'css_class' => '',
                        'indicator' => '',
                        'tooltip' => 'No memos recorded',
                        'days_since' => null
                    ];
                }
            }
            
            return $memoStatuses;
            
        } catch (Exception $e) {
            error_log("MemoColorCoding::getBulkMemoStatus Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get CSS styles for memo color coding
     */
    public function getCSS() {
        return '
        <style>
        /* Memo Color Coding Styles */
        .memo-today {
            background-color: #d4edda !important;
            border-left: 4px solid #28a745 !important;
        }
        
        .memo-today:hover {
            background-color: #c3e6cb !important;
        }
        
        .system-memo-today {
            background-color: #d1ecf1 !important;
            border-left: 4px solid #17a2b8 !important;
        }
        
        .system-memo-today:hover {
            background-color: #bee5eb !important;
        }
        
        .memo-recent {
            background-color: #fff3cd !important;
            border-left: 4px solid #ffc107 !important;
        }
        
        .memo-recent:hover {
            background-color: #ffeaa7 !important;
        }
        
        .memo-old {
            background-color: #f8f9fa !important;
            border-left: 4px solid #6c757d !important;
        }
        
        .memo-old:hover {
            background-color: #e9ecef !important;
        }
        
        .memo-very-old {
            background-color: #f8d7da !important;
            border-left: 4px solid #dc3545 !important;
        }
        
        .memo-very-old:hover {
            background-color: #f5c6cb !important;
        }
        
        .memo-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .memo-tooltip {
            cursor: help;
        }
        
        /* Animation for new memo indicators */
        .memo-today .memo-indicator {
            animation: pulse-green 2s infinite;
        }
        
        @keyframes pulse-green {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        /* Table row highlighting */
        .table tbody tr.memo-today td {
            background-color: rgba(40, 167, 69, 0.1);
        }
        
        .table tbody tr.system-memo-today td {
            background-color: rgba(23, 162, 184, 0.1);
        }
        
        .table tbody tr.memo-recent td {
            background-color: rgba(255, 193, 7, 0.1);
        }
        
        .table tbody tr.memo-old td {
            background-color: rgba(108, 117, 125, 0.05);
        }
        
        .table tbody tr.memo-very-old td {
            background-color: rgba(220, 53, 69, 0.1);
        }
        </style>
        ';
    }
    
    /**
     * Get JavaScript for enhanced functionality
     */
    public function getJavaScript() {
        return '
        <script>
        // Initialize memo tooltips
        function initializeMemoTooltips() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll(\'[data-bs-toggle="tooltip"]\'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
        
        // Auto-refresh memo indicators every 5 minutes
        function refreshMemoIndicators() {
            // Only refresh if we\'re on a page with memo indicators
            if (document.querySelector(\'.memo-indicator\')) {
                location.reload();
            }
        }
        
        // Initialize on page load
        document.addEventListener(\'DOMContentLoaded\', function() {
            initializeMemoTooltips();
            
            // Set up auto-refresh (5 minutes)
            setInterval(refreshMemoIndicators, 300000);
        });
        
        // Function to manually refresh memo status
        function refreshMemoStatus() {
            location.reload();
        }
        </script>
        ';
    }
}

// Global function to get MemoColorCoding instance
function getMemoColorCoding($database = null) {
    static $instance = null;
    
    if ($instance === null && $database !== null) {
        $instance = new MemoColorCoding($database);
    }
    
    return $instance;
}

// Helper function for easy integration
function getMemoStatusForUser($userId, $database = null) {
    if ($database === null) {
        $database = getDB();
    }
    
    $memoColorCoding = getMemoColorCoding($database);
    return $memoColorCoding->getMemoStatus($userId);
}

// Helper function for bulk operations
function getMemoStatusForUsers($userIds, $database = null) {
    if ($database === null) {
        $database = getDB();
    }
    
    $memoColorCoding = getMemoColorCoding($database);
    return $memoColorCoding->getBulkMemoStatus($userIds);
}
?>