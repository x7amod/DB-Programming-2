<?php
// PURPOSE: Admin reports for popular content and content created by a specific user.
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db_helpers.php';

require_role(['Admin']);

$root_url = app_base_url();

$today = new DateTimeImmutable('today');
$defaultStart = $today->modify('-30 days')->format('Y-m-d');
$defaultEnd = $today->format('Y-m-d');

$popularStart = trim($_GET['popular_start'] ?? $defaultStart);
$popularEnd = trim($_GET['popular_end'] ?? $defaultEnd);
$reportUserId = trim($_GET['report_user_id'] ?? '');

$activeUsers = get_active_users_for_reports($pdo);

if ($reportUserId === '' && !empty($activeUsers)) {
    $reportUserId = $activeUsers[0]['id'];
}

$popularReport = [];
$userContentReport = [];

if ($popularStart !== '' && $popularEnd !== '') {
    $popularReport = get_popular_movies_report($pdo, $popularStart, $popularEnd);
}

if ($reportUserId !== '') {
    $userContentReport = get_content_by_user_report($pdo, $reportUserId);
}

$selectedUser = null;
foreach ($activeUsers as $userRow) {
    if ($userRow['id'] === $reportUserId) {
        $selectedUser = $userRow;
        break;
    }
}
?>

<div class="admin-section reports-page">
    <div class="admin-section-header">
        <h1>Reports</h1>
        <a href="<?= $root_url ?>/admin/index.php">&larr; Back to Dashboard</a>
    </div>

    <form method="GET" class="filter-form report-filter-form">
        <label>
            Start Date
            <input type="date" name="popular_start" value="<?= htmlspecialchars($popularStart) ?>">
        </label>
        <label>
            End Date
            <input type="date" name="popular_end" value="<?= htmlspecialchars($popularEnd) ?>">
        </label>
        <label>
            Content by User
            <select name="report_user_id">
                <?php foreach ($activeUsers as $userRow): ?>
                    <option value="<?= htmlspecialchars($userRow['id']) ?>" <?= $reportUserId === $userRow['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($userRow['username']) ?> (<?= htmlspecialchars($userRow['role']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="report-filter-button">Generate Reports</button>
    </form>

    <div class="reports-grid">
        <section class="page-card report-section">
            <div class="section-heading">
                <div>
                    <p class="movie-detail-kicker">Report 1</p>
                    <h2>Most popular content within a date range</h2>
                </div>
            </div>

            <?php if (empty($popularReport)): ?>
                <div class="empty-state">
                    <h3>No content found</h3>
                    <p>No content was found for the selected date range.</p>
                </div>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Creator</th>
                            <th>Created</th>
                            <th>Views</th>
                            <th>Reviews</th>
                            <th>Avg. Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($popularReport as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= htmlspecialchars($row['creator_name'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars(substr($row['createdon'], 0, 10)) ?></td>
                                <td><?= htmlspecialchars((string) $row['view_count']) ?></td>
                                <td><?= htmlspecialchars((string) $row['review_count']) ?></td>
                                <td><?= htmlspecialchars((string) $row['avg_rating']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="page-card report-section">
            <div class="section-heading">
                <div>
                    <p class="movie-detail-kicker">Report 2</p>
                    <h2>Content created by a specific user</h2>
                </div>
            </div>

            <div class="report-summary">
                <strong>Selected user:</strong>
                <?= $selectedUser ? htmlspecialchars($selectedUser['username']) . ' (' . htmlspecialchars($selectedUser['role']) . ')' : 'No active user available' ?>
            </div>

            <?php if (empty($userContentReport)): ?>
                <div class="empty-state">
                    <h3>No content found</h3>
                    <p>No content was found for the selected user.</p>
                </div>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Created</th>
                            <th>Views</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userContentReport as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></td>
                                <td><?= htmlspecialchars(substr($row['createdon'], 0, 10)) ?></td>
                                <td><?= htmlspecialchars((string) $row['view_count']) ?></td>
                                <td>
                                    <span class="status-badge <?= $row['is_published'] ? 'status-published' : 'status-draft' ?>">
                                        <?= $row['is_published'] ? 'Published' : 'Draft' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
