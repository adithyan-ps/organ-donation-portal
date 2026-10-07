<?php
/**
 * Organ Donation – Recipient Matching Portal
 * Session Logout Handler
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

// Unset all session variables
$_SESSION = [];

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Start fresh session for flash message
session_start();
set_flash_message('info', 'You have been signed out safely.');

header('Location: ' . BASE_URL . '/login.php');
exit;
