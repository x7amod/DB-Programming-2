<?php
// PURPOSE: Admin dashboard for managing users and content.
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../src/db_helpers.php';

require_role(['Admin', 'Support']);

$stats = get_stats($pdo);
$user  = current_user();
?>

<div class="admin-dashboard">
    <h1>Admin Dashboard</h1>
    <p>Welcome back, <strong><?= sanitize($user['username']) ?></strong>.</p>

    <div class="stat-cards">
        <div class="stat-card">
            <span class="stat-number"><?= $stats['total_users'] ?></span>
            <span class="stat-label">Active Users</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?= $stats['total_movies'] ?></span>
            <span class="stat-label">Published Movies</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?= $stats['total_reviews'] ?></span>
            <span class="stat-label">Active Reviews</span>
        </div>
        <div class="stat-card stat-card-warning">
            <span class="stat-number"><?= $stats['recent_deactivations'] ?></span>
            <span class="stat-label">Deactivations (7 days)</span>
        </div>
    </div>

    <h2>Management</h2>
    <nav class="admin-nav">
        <?php if ($user['role'] === 'Admin'): ?>
            <a href="/admin/users.php">Manage Users</a>
        <?php endif; ?>
        <a href="/admin/reviews.php">Manage Reviews</a>
    </nav>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
