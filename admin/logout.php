<?php
/**
 * Admin Sign Out Handler
 * CertificateHub - Organizer Workspace
 */
require_once __DIR__ . '/../includes/auth.php';

// Unset all session variables
$_SESSION = [];

// Delete the session cookie if present
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy the session
session_destroy();

// Redirect back to sign-in page with notice
header('Location: organizer_sign_in.php?logged_out=1');
exit;
