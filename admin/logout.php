<?php
/**
 * Logout Script
 * LoanFlow Personal Loan Management System
 */

require_once '../includes/functions.php';

// Log the logout if user is logged in
if (isLoggedIn()) {
    $user_id = getCurrentUserId();
    logAudit('user_logout', 'users', $user_id, null, [
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
}

// Clear all session data
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login with message
header('Location: ../login.php');
exit();
?>
