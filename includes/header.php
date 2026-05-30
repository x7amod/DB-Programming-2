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
$app_url  = '/DB-Programming-2';
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
            <a class="nav-action" href="<?= $base_url ?>/user/index.php">Movie Explorer</a>

            <?php if (in_array($current_user['role'], ['Admin', 'Support'], true)): ?>
                <a class="nav-action" href="<?= $base_url ?>/admin/index.php">
                    <?= $current_user['role'] === 'Support' ? 'Support Panel' : 'Admin Panel' ?>
                </a>
                <a class="nav-action" href="<?= $base_url ?>/admin/reviews.php">Review Queue</a>
            <?php endif; ?>

            <?php if ($current_user['role'] === 'Admin'): ?>
                <a class="nav-action" href="<?= $base_url ?>/admin/users.php">Users</a>
            <?php endif; ?>

            <?php if ($current_user['role'] === 'Creator'): ?>
                <a class="nav-action" href="<?= $app_url ?>/creator/index.php">Creator Dashboard</a>
                <a class="nav-action" href="<?= $app_url ?>/creator/add_review.php">Add Review</a>
            <?php endif; ?>

            <?php if ($current_user['role'] === 'Viewer'): ?>
                <a class="nav-action" href="<?= $base_url ?>/search.php">Browse Reviews</a>
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
