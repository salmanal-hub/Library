<?php
require_once 'config.php';

// Log logout activity if user is logged in
if (isLoggedIn()) {
    logActivity('LOGOUT', "User {$_SESSION['username']} logged out");

    // Get user info for cleanup (if needed)
    $userId = $_SESSION['user_id'];
    $username = $_SESSION['username'];
}

// Clear all session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page with logout message
redirect('login.php?logout=1');
