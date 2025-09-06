<?php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$type = $_GET['type'] ?? '';
$limit = intval($_GET['limit'] ?? 10);

try {
    if ($type === 'faqs') {
        // Get active FAQs ordered by display_order, limited to specified number (default 7)
        $limit = min($limit, 20); // Maximum 20 FAQs
        $stmt = $pdo->prepare("SELECT id, question, answer FROM faqs WHERE is_active = 1 ORDER BY display_order ASC, id ASC LIMIT ?");
        $stmt->execute([$limit]);
        $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $faqs,
            'count' => count($faqs)
        ]);
        
    } elseif ($type === 'testimonials') {
        // Get active testimonials ordered by display_order, limited to specified number (default 5)
        $limit = min($limit, 10); // Maximum 10 testimonials
        $stmt = $pdo->prepare("SELECT id, client_name, client_title, testimonial_text, client_image, rating FROM testimonials WHERE is_active = 1 ORDER BY display_order ASC, id ASC LIMIT ?");
        $stmt->execute([$limit]);
        $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $testimonials,
            'count' => count($testimonials)
        ]);
        
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid type specified. Use "faqs" or "testimonials"'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>