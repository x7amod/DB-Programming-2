<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['Creator']);

$root_url = '/DB-Programming-2';
$current_user = current_user();
$user_id = $current_user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $movie_id = trim($_POST['movie_id'] ?? '');

    if ($movie_id === '' || $user_id === '') {
        redirect($root_url . '/creator/index.php?msg=invalid');
    }

    if ($action === 'publish') {
        $stmt = $pdo->prepare(
            "UPDATE dbProj_movies
             SET is_published = TRUE, modifiedon = NOW(), modifiedby = ?
             WHERE id = ? AND creator_id = ?"
        );
        $stmt->execute([$user_id, $movie_id, $user_id]);
        redirect($root_url . '/creator/index.php?msg=published');
    }

    if ($action === 'delete') {
        $stmt = $pdo->prepare(
            "UPDATE dbProj_movies
             SET inactive = TRUE, modifiedon = NOW(), modifiedby = ?
             WHERE id = ? AND creator_id = ?"
        );
        $stmt->execute([$user_id, $movie_id, $user_id]);
        redirect($root_url . '/creator/index.php?msg=deleted');
    }

    redirect($root_url . '/creator/index.php?msg=invalid');
}

$stmt = $pdo->prepare(
    "SELECT m.id, m.title, m.is_published, m.createdon, c.name AS category_name
     FROM dbProj_movies m
     LEFT JOIN dbProj_categories c ON m.category_id = c.id
     WHERE m.creator_id = ? AND m.inactive = FALSE
     ORDER BY m.createdon DESC"
);
$stmt->execute([$user_id]);
$movies = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';

$messages = [
    'saved' => ['type' => 'success', 'text' => 'Review saved successfully.'],
    'published' => ['type' => 'success', 'text' => 'Review published.'],
    'deleted' => ['type' => 'success', 'text' => 'Review removed.'],
    'unauthorized' => ['type' => 'error', 'text' => 'You are not allowed to access that review.'],
    'invalid' => ['type' => 'error', 'text' => 'Invalid request.'],
];

$msg_key = $_GET['msg'] ?? '';
$current_msg = $messages[$msg_key] ?? null;
?>
<style>
    .creator-wrap { color: #f5f1e6; }
    .creator-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .creator-header h1 { margin: 0; font-size: 26px; }
    .btn { display: inline-block; padding: 10px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; }
    .btn-primary { background: #f1c40f; color: #1b1b1b; }
    .btn-outline { border: 1px solid #f1c40f; color: #f1c40f; background: transparent; }
    .alert-msg { padding: 12px 14px; border-radius: 6px; margin-bottom: 18px; }
    .alert-success { background: #163c2a; color: #bff0d2; border: 1px solid #2b6b45; }
    .alert-error { background: #3b1b1b; color: #f2b6b6; border: 1px solid #6b2b2b; }
    table { width: 100%; border-collapse: collapse; background: #141414; }
    th, td { padding: 12px; border-bottom: 1px solid #2a2a2a; text-align: left; }
    th { color: #f1c40f; font-weight: 700; }
    .status-pill { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 700; }
    .status-draft { background: #2f2f2f; color: #e0e0e0; }
    .status-published { background: #143d2d; color: #a6f0c6; }
    .actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .btn-small { padding: 6px 10px; font-size: 13px; }
    .btn-danger { background: #6b1f1f; color: #f3c2c2; border: none; }
    form { margin: 0; }
</style>

<div class="creator-wrap">
    <div class="creator-header">
        <h1>Creator Dashboard</h1>
        <a class="btn btn-primary" href="<?= $root_url ?>/creator/add_review.php">Add New Review</a>
    </div>

    <?php if ($current_msg): ?>
        <div class="alert-msg alert-<?= sanitize($current_msg['type']) ?>">
            <?= sanitize($current_msg['text']) ?>
        </div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Genre</th>
                <th>Status</th>
                <th>Date Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$movies): ?>
                <tr>
                    <td colspan="5">No reviews yet. Click "Add New Review" to get started.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($movies as $movie): ?>
                    <?php
                        $status_label = (bool) $movie['is_published'] ? 'Published' : 'Draft';
                    ?>
                    <tr>
                        <td><?= sanitize($movie['title']) ?></td>
                        <td><?= sanitize($movie['category_name'] ?? 'Uncategorized') ?></td>
                        <td>
                            <span class="status-pill status-<?= sanitize($status_label) === 'Published' ? 'published' : 'draft' ?>">
                                <?= $status_label ?>
                            </span>
                        </td>
                        <td><?= sanitize(date('M d, Y', strtotime($movie['createdon']))) ?></td>
                        <td>
                            <div class="actions">
                                <a class="btn btn-outline btn-small" href="<?= $root_url ?>/creator/edit_review.php?id=<?= urlencode($movie['id']) ?>">Edit</a>

                                <?php if (!(bool) $movie['is_published']): ?>
                                    <form method="post" action="<?= $root_url ?>/creator/index.php">
                                        <input type="hidden" name="action" value="publish">
                                        <input type="hidden" name="movie_id" value="<?= sanitize($movie['id']) ?>">
                                        <button class="btn btn-primary btn-small" type="submit">Publish</button>
                                    </form>
                                <?php endif; ?>

                                <form method="post" action="<?= $root_url ?>/creator/index.php" onsubmit="return confirm('Delete this review?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="movie_id" value="<?= sanitize($movie['id']) ?>">
                                    <button class="btn btn-danger btn-small" type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
