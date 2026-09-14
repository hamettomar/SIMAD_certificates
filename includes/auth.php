<?php
/**
 * Authentication & Session Guard Helpers
 * CertificateHub - Organizer Workspace
 */

// Start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if an admin is currently logged in
 *
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Return current logged in admin data
 *
 * @return array|null
 */
function currentAdmin(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'    => $_SESSION['admin_id'] ?? null,
        'name'  => $_SESSION['admin_name'] ?? 'Organizer',
        'email' => $_SESSION['admin_email'] ?? '',
        'role'  => $_SESSION['admin_role'] ?? 'Organizer',
    ];
}

/**
 * Enforce authentication on protected admin pages.
 * Redirects to organizer_sign_in.php if user is not logged in.
 */
function requireAdminAuth(): void {
    if (!isLoggedIn()) {
        header('Location: organizer_sign_in.php');
        exit;
    }
}
