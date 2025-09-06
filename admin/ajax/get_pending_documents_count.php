<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $db = getDB();
    
    // Get pending documents count
    $stmt = $db->query("SELECT COUNT(*) FROM documents WHERE upload_status = 'pending'");
    $count = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'count' => (int)$count
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>