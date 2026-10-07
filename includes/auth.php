<?php
/**
 * Organ Donation – Recipient Matching Portal
 * Authentication, Session Security & Access Control Helper
 */

require_once __DIR__ . '/../config.php';

// Safe session initialization
function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // Configure secure cookie parameters before starting session
        $lifetime = 60 * 60 * 24; // 24 hours
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path'     => '/',
                'domain'   => '',
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        } else {
            session_set_cookie_params($lifetime, '/; samesite=Lax', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
        }
        session_start();
    }
}

// Start session on include
init_session();

/**
 * Check if a user is currently logged in
 */
function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

/**
 * Check if the logged in user is an administrator
 */
function is_admin() {
    return is_logged_in() && ($_SESSION['user_role'] ?? '') === 'admin';
}

/**
 * Check if the logged in user is a registered donor
 */
function is_donor() {
    return is_logged_in() && ($_SESSION['user_role'] ?? '') === 'donor';
}

/**
 * Check if the logged in user is a registered recipient
 */
function is_recipient() {
    return is_logged_in() && ($_SESSION['user_role'] ?? '') === 'recipient';
}

/**
 * Get current authenticated user details
 */
function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => $_SESSION['user_role'] ?? 'guest'
    ];
}

/**
 * Require any logged in user, otherwise redirect to login page
 */
function require_login() {
    if (!is_logged_in()) {
        set_flash_message('error', 'Please log in to access this page.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * Require administrator role
 */
function require_admin() {
    require_login();
    if ($_SESSION['user_role'] !== 'admin') {
        set_flash_message('error', 'Access denied. Administrator privileges required.');
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

/**
 * Require donor role
 */
function require_donor() {
    require_login();
    if ($_SESSION['user_role'] !== 'donor' && $_SESSION['user_role'] !== 'admin') {
        set_flash_message('error', 'Access restricted to registered donors.');
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

/**
 * Require recipient role
 */
function require_recipient() {
    require_login();
    if ($_SESSION['user_role'] !== 'recipient' && $_SESSION['user_role'] !== 'admin') {
        set_flash_message('error', 'Access restricted to registered recipients.');
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

/**
 * CSRF Protection Token Generator
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF Token Verification
 */
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set flash alert notification
 */
function set_flash_message($type, $message) {
    $_SESSION['flash'] = [
        'type'    => $type, // 'success', 'error', 'info', 'warning'
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash alert
 */
function get_flash_message() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Log administrative actions into admin_logs
 */
function log_admin_action($admin_id, $action, $description) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, description, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$admin_id, $action, $description]);
        return true;
    } catch (Exception $e) {
        error_log("Failed to write admin log: " . $e->getMessage());
        return false;
    }
}
