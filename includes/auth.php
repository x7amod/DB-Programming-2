<?php
// PURPOSE: Session and authentication helpers for the website.
// NOTE: session_start() is called in header.php before this file is included.

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'       => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role'     => $_SESSION['role'],
    ];
}

function login_user(array $user): void {
    session_regenerate_id(true); // prevents session fixation
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role']     = $user['role'];
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function require_login(): void {
    if (!is_logged_in()) {
        set_flash('error', 'You must be logged in to access that page.');
        redirect('/DB-Programming-2/auth/login.php');
    }
}

function require_role(array $roles): void {
    require_login();
    $user = current_user();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        echo '<h1>403 — Access Denied</h1><p>You do not have permission to view this page.</p>';
        exit();
    }
}
