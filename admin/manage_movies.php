<?php
// PURPOSE: Admin movie management — view, publish/unpublish, deactivate/reactivate movies.
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db_helpers.php';

require_role(['Admin']);

$root_url = '/DB-Programming-2';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $movie_id = $_POST['movie_id'] ?? '';
    $by_id = current_user()['id'];

    if ($movie_id !== '') {
        if ($action === 'toggle_active') {
            toggle_movie_active($pdo, $movie_id, $by_id);
            set_flash('success', 'Movie status updated.');
        }

        if ($action === 'toggle_published') {
            toggle_movie_published($pdo, $movie_id, $by_id);
            set_flash('success', 'Movie publication status updated.');
        }
    }

    redirect($root_url . '/admin/manage_movies.php');
}

$filters = [
    'status' => $_GET['status'] ?? '',
    'published' => $_GET['published'] ?? '',
];

$movies = get_all_movies_for_admin($pdo, $filters);
?>

<div class="admin-section">
    <div class="admin-section-header">
        <h1>Manage Movies</h1>
        <a href="<?= $root_url ?>/admin/index.php">&larr; Back to Dashboard</a>
    </div>

    <form method="GET" class="filter-form">
        <label for="status-filter">Status:</label>
        <select id="status-filter" name="status" onchange="this.form.submit()">
            <option value="">All</option>
            <option value="0" <?= $filters['status'] === '0' ? 'selected' : '' ?>>Active</option>
            <option value="1" <?= $filters['status'] === '1' ? 'selected' : '' ?>>Inactive</option>
        </select>

        <label for="published-filter">Visibility:</label>
        <select id="published-filter" name="published" onchange="this.form.submit()">
            <option value="">All</option>
            <option value="1" <?= $filters['published'] === '1' ? 'selected' : '' ?>>Published</option>
            <option value="0" <?= $filters['published'] === '0' ? 'selected' : '' ?>>Draft</option>
        </select>

        <button type="submit">Filter</button>
        <a href="<?= $root_url ?>/admin/manage_movies.php" class="btn-link">Clear</a>
    </form>

    <p><?= count($movies) ?> movie(s) found.</p>

    <?php if (empty($movies)): ?>
        <p>No movies match the current filter.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Creator</th>
                    <th>Genre</th>
                    <th>Views</th>
                    <th>Publish Status</th>
                    <th>Account Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movies as $movie): ?>
                    <tr class="<?= $movie['inactive'] ? 'row-inactive' : '' ?>">
                        <td><?= sanitize($movie['title']) ?></td>
                        <td><?= sanitize($movie['creator_name'] ?? 'Unknown') ?></td>
                        <td><?= sanitize($movie['category_name'] ?? 'Uncategorized') ?></td>
                        <td><?= (int) $movie['view_count'] ?></td>
                        <td>
                            <span class="status-badge <?= $movie['is_published'] ? 'status-published' : 'status-draft' ?>">
                                <?= $movie['is_published'] ? 'Published' : 'Draft' ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge <?= $movie['inactive'] ? 'status-inactive' : 'status-active' ?>">
                                <?= $movie['inactive'] ? 'Inactive' : 'Active' ?>
                            </span>
                        </td>
                        <td><?= sanitize(substr($movie['createdon'], 0, 10)) ?></td>
                        <td class="actions">
                            <?php if (!$movie['inactive'] && $movie['is_published']): ?>
                                <a href="<?= $root_url ?>/review.php?id=<?= urlencode($movie['id']) ?>" class="btn-view">View</a>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Change this movie\\'s publish status?');">
                                <input type="hidden" name="action" value="toggle_published">
                                <input type="hidden" name="movie_id" value="<?= sanitize($movie['id']) ?>">
                                <button type="submit" class="btn-edit">
                                    <?= $movie['is_published'] ? 'Unpublish' : 'Publish' ?>
                                </button>
                            </form>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Change this movie\\'s active status?');">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="movie_id" value="<?= sanitize($movie['id']) ?>">
                                <button type="submit" class="<?= $movie['inactive'] ? 'btn-activate' : 'btn-deactivate' ?>">
                                    <?= $movie['inactive'] ? 'Reactivate' : 'Deactivate' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
