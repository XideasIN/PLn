<?php
/**
 * Language Change Handler
 * LoanFlow Personal Loan Management System
 */

require_once 'includes/functions.php';
require_once 'includes/language.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize language system
LanguageManager::init();

// Handle language change request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['language'])) {
    $language = sanitizeInput($_POST['language']);
    
    if (LanguageManager::setLanguage($language)) {
        // Return success response for AJAX
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'language' => $language]);
            exit;
        }
        
        // Redirect back to referring page or home
        $redirect = $_POST['redirect'] ?? $_SERVER['HTTP_REFERER'] ?? 'index.php';
        header('Location: ' . $redirect);
        exit;
    } else {
        // Return error response
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid language']);
            exit;
        }
        
        setFlashMessage('error', 'Invalid language selected');
        header('Location: index.php');
        exit;
    }
}

// If GET request, redirect to home
header('Location: index.php');
exit;
