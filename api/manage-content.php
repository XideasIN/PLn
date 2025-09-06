<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

// Ensure only admin users can access this API
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';
$type = $_GET['type'] ?? ''; // 'faq' or 'testimonial'
$id = $_GET['id'] ?? null;

try {
    switch ($method) {
        case 'GET':
            handleGet($type, $id);
            break;
        case 'POST':
            handlePost($type, $input);
            break;
        case 'PUT':
            handlePut($type, $id, $input);
            break;
        case 'DELETE':
            handleDelete($type, $id);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

function handleGet($type, $id) {
    global $pdo;
    
    if ($type === 'faq') {
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM faqs WHERE id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->query("SELECT * FROM faqs ORDER BY display_order ASC, id ASC");
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } elseif ($type === 'testimonial') {
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM testimonials WHERE id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->query("SELECT * FROM testimonials ORDER BY display_order ASC, id ASC");
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid type specified']);
        return;
    }
    
    echo json_encode(['success' => true, 'data' => $result]);
}

function handlePost($type, $input) {
    global $pdo;
    
    if ($type === 'faq') {
        $errors = validateFaqInput($input);
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['error' => 'Validation failed', 'details' => $errors]);
            return;
        }
        
        $stmt = $pdo->prepare("INSERT INTO faqs (question, answer, display_order, is_active) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            sanitizeInput($input['question']),
            sanitizeInput($input['answer']),
            intval($input['display_order'] ?? 0),
            intval($input['is_active'] ?? 1)
        ]);
        
        $newId = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $newId, 'message' => 'FAQ created successfully']);
        
    } elseif ($type === 'testimonial') {
        $errors = validateTestimonialInput($input);
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['error' => 'Validation failed', 'details' => $errors]);
            return;
        }
        
        $stmt = $pdo->prepare("INSERT INTO testimonials (client_name, client_title, testimonial_text, client_image, rating, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            sanitizeInput($input['client_name']),
            sanitizeInput($input['client_title'] ?? ''),
            sanitizeInput($input['testimonial_text']),
            sanitizeInput($input['client_image'] ?? ''),
            intval($input['rating'] ?? 5),
            intval($input['display_order'] ?? 0),
            intval($input['is_active'] ?? 1)
        ]);
        
        $newId = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Testimonial created successfully']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid type specified']);
    }
}

function handlePut($type, $id, $input) {
    global $pdo;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID is required for updates']);
        return;
    }
    
    if ($type === 'faq') {
        $errors = validateFaqInput($input);
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['error' => 'Validation failed', 'details' => $errors]);
            return;
        }
        
        $stmt = $pdo->prepare("UPDATE faqs SET question = ?, answer = ?, display_order = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([
            sanitizeInput($input['question']),
            sanitizeInput($input['answer']),
            intval($input['display_order'] ?? 0),
            intval($input['is_active'] ?? 1),
            $id
        ]);
        
        echo json_encode(['success' => true, 'message' => 'FAQ updated successfully']);
        
    } elseif ($type === 'testimonial') {
        $errors = validateTestimonialInput($input);
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['error' => 'Validation failed', 'details' => $errors]);
            return;
        }
        
        $stmt = $pdo->prepare("UPDATE testimonials SET client_name = ?, client_title = ?, testimonial_text = ?, client_image = ?, rating = ?, display_order = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([
            sanitizeInput($input['client_name']),
            sanitizeInput($input['client_title'] ?? ''),
            sanitizeInput($input['testimonial_text']),
            sanitizeInput($input['client_image'] ?? ''),
            intval($input['rating'] ?? 5),
            intval($input['display_order'] ?? 0),
            intval($input['is_active'] ?? 1),
            $id
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Testimonial updated successfully']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid type specified']);
    }
}

function handleDelete($type, $id) {
    global $pdo;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID is required for deletion']);
        return;
    }
    
    if ($type === 'faq') {
        $stmt = $pdo->prepare("DELETE FROM faqs WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'FAQ deleted successfully']);
    } elseif ($type === 'testimonial') {
        $stmt = $pdo->prepare("DELETE FROM testimonials WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Testimonial deleted successfully']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid type specified']);
    }
}

function validateFaqInput($input) {
    $errors = [];
    
    if (empty($input['question']) || strlen(trim($input['question'])) < 5) {
        $errors[] = 'Question must be at least 5 characters long';
    }
    
    if (empty($input['answer']) || strlen(trim($input['answer'])) < 10) {
        $errors[] = 'Answer must be at least 10 characters long';
    }
    
    if (isset($input['display_order']) && (!is_numeric($input['display_order']) || $input['display_order'] < 0)) {
        $errors[] = 'Display order must be a non-negative number';
    }
    
    return $errors;
}

function validateTestimonialInput($input) {
    $errors = [];
    
    if (empty($input['client_name']) || strlen(trim($input['client_name'])) < 2) {
        $errors[] = 'Client name must be at least 2 characters long';
    }
    
    if (empty($input['testimonial_text']) || strlen(trim($input['testimonial_text'])) < 10) {
        $errors[] = 'Testimonial text must be at least 10 characters long';
    }
    
    if (isset($input['rating']) && (!is_numeric($input['rating']) || $input['rating'] < 1 || $input['rating'] > 5)) {
        $errors[] = 'Rating must be between 1 and 5';
    }
    
    if (isset($input['display_order']) && (!is_numeric($input['display_order']) || $input['display_order'] < 0)) {
        $errors[] = 'Display order must be a non-negative number';
    }
    
    return $errors;
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>