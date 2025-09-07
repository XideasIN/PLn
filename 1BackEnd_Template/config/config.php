<?php
// Configuration file for Consumer Loan Admin Template
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'consumer_loans');

// Application configuration
define('APP_NAME', 'Tability');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '/LBackEnd-2/');

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('UTC');

// Helper function to get base URL
function base_url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

// Helper function to include views
function include_view($view, $data = []) {
    extract($data);
    include "includes/{$view}.php";
}
?>