<?php
// PURPOSE: Destroys the current session and redirects to the login page.
// Intentionally does NOT include header.php — this page produces no HTML output.

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

logout_user();
set_flash('success', 'You have been logged out successfully.');
redirect(app_base_url() . '/auth/login.php');
