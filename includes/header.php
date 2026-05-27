<?php
// PURPOSE: HTML header template with session bootstrap and dynamic navigation.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$current_user = current_user();
$flash        = get_flash();

$base_url = '/DB-Programming-2/public';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movie Review System</title>
    <link rel="stylesheet" href="<?= $base_url ?>/assets/css/style.css">
</head>
<body>
<nav>
    <div class="nav-brand">
        <a href="<?= $base_url ?>/index.php">🎬 Movie Reviews</a>
    </div>

    <div class="nav-links">
        <a href="<?= $base_url ?>/index.php">Home</a>
        <a href="<?= $base_url ?>/search.php">Search</a>

        <?php if ($current_user): ?>
            <?php if ($current_user['role'] === 'Admin'): ?>
                <a href="<?= $base_url ?>/admin/index.php">Admin Panel</a>
            <?php endif; ?>

            <?php if ($current_user['role'] === 'Creator'): ?>
                <a href="<?= $base_url ?>/user/index.php">My Content</a>
            <?php endif; ?>

            <span class="nav-user">
                <?= sanitize($current_user['username']) ?>
                <span class="role-badge role-<?= strtolower(sanitize($current_user['role'])) ?>">
                    <?= sanitize($current_user['role']) ?>
                </span>
            </span>

            <a href="<?= $base_url ?>/auth/logout.php">Logout</a>
        <?php else: ?>
            <a href="<?= $base_url ?>/auth/login.php">Login</a>
            <a href="<?= $base_url ?>/auth/signup.php">Sign Up</a>
        <?php endif; ?>
    </div>
</nav>

<main>
<?php if ($flash): ?>
<div class="alert alert-<?= sanitize($flash['type']) ?>">
    <?= sanitize($flash['message']) ?>
</div>
<?php endif; ?>