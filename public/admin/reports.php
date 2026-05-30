<?php
// PURPOSE: Admin reports for popular content and content created by a specific user.
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../src/db_helpers.php';

require_role(['Admin']);

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
        <a href="/admin/index.php">&larr; Back to Dashboard</a>
    </div>

    <section class="report-section">
        <div class="section-heading">
            <div>
                <p class="movie-detail-kicker">Report 1</p>
                <h2>Most popular content within a date range</h2>
            </div>
        </div>

        <form method="GET" class="report-filter-form">
            <label>
                Start Date
                <input type="date" name="popular_start" value="<?= htmlspecialchars($popularStart) ?>">
            </label>
            <label>
                End Date
                <input type="date" name="popular_end" value="<?= htmlspecialchars($popularEnd) ?>">
            </label>
            <label>
                Creator Report User
                <select name="report_user_id">
                    <?php foreach ($activeUsers as $userRow): ?>
                        <option value="<?= htmlspecialchars($userRow['id']) ?>" <?= $reportUserId === $userRow['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($userRow['username']) ?> (<?= htmlspecialchars($userRow['role']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Generate Reports</button>
        </form>

        <?php if (empty($popularReport)): ?>
            <p class="report-empty">No content was found for the selected date range.</p>
        <?php else: ?>
            <table class="report-table">
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
                            <td data-label="Title"><?= htmlspecialchars($row['title']) ?></td>
                            <td data-label="Creator"><?= htmlspecialchars($row['creator_name'] ?? 'Unknown') ?></td>
                            <td data-label="Created"><?= htmlspecialchars(substr($row['createdon'], 0, 10)) ?></td>
                            <td data-label="Views"><?= htmlspecialchars((string) $row['view_count']) ?></td>
                            <td data-label="Reviews"><?= htmlspecialchars((string) $row['review_count']) ?></td>
                            <td data-label="Avg. Rating"><?= htmlspecialchars((string) $row['avg_rating']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="report-section">
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
            <p class="report-empty">No content was found for the selected user.</p>
        <?php else: ?>
            <table class="report-table">
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
                            <td data-label="Title"><?= htmlspecialchars($row['title']) ?></td>
                            <td data-label="Category"><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></td>
                            <td data-label="Created"><?= htmlspecialchars(substr($row['createdon'], 0, 10)) ?></td>
                            <td data-label="Views"><?= htmlspecialchars((string) $row['view_count']) ?></td>
                            <td data-label="Status"><?= $row['is_published'] ? 'Published' : 'Draft' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
