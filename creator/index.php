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
    "SELECT m.id, m.title, m.is_published, m.createdon, m.creator_id,
            c.name AS category_name,
            u.username AS uploader_name,
            myr.id AS my_review_id
     FROM dbProj_movies m
     LEFT JOIN dbProj_categories c ON m.category_id = c.id
     LEFT JOIN dbProj_users u ON m.creator_id = u.id
     LEFT JOIN dbProj_reviews myr
       ON myr.movie_id = m.id AND myr.user_id = :user_id AND myr.inactive = FALSE
     WHERE m.inactive = FALSE
     ORDER BY (myr.id IS NULL) DESC, m.createdon DESC"
);
$stmt->execute([':user_id' => $user_id]);
$movies = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';

$messages = [
    'movie_saved' => ['type' => 'success', 'text' => 'Movie saved successfully.'],
    'review_saved' => ['type' => 'success', 'text' => 'Review saved successfully.'],
    'published' => ['type' => 'success', 'text' => 'Movie published.'],
    'deleted' => ['type' => 'success', 'text' => 'Movie removed.'],
    'unauthorized' => ['type' => 'error', 'text' => 'You are not allowed to access this movie.'],
    'invalid' => ['type' => 'error', 'text' => 'Invalid request.'],
];

$msg_key = $_GET['msg'] ?? '';
$current_msg = $messages[$msg_key] ?? null;
?>

<section class="creator-wrap">
    <div class="creator-header">
        <div>
            <h1>Creator Dashboard</h1>
            <p class="creator-subtitle">Manage movies and write reviews.</p>
        </div>
        <div class="creator-actions">
            <a class="creator-btn creator-btn-primary" href="<?= $root_url ?>/creator/add_movie.php">Add Movie</a>
            <a class="creator-btn creator-btn-secondary" href="<?= $root_url ?>/creator/add_review.php">Add Review</a>
        </div>
    </div>

    <?php if ($current_msg): ?>
        <div class="alert alert-<?= sanitize($current_msg['type']) ?>">
            <?= sanitize($current_msg['text']) ?>
        </div>
    <?php endif; ?>

    <div class="creator-table-wrap">
        <table class="creator-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Uploaded By</th>
                    <th>Genre</th>
                    <th>Your Review</th>
                    <th>Status</th>
                    <th>Date Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$movies): ?>
                    <tr>
                        <td colspan="7">No movies yet. Click "Add Movie" to get started.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($movies as $movie): ?>
                        <?php
                            $is_published = (bool) $movie['is_published'];
                            $has_review = !empty($movie['my_review_id']);
                            $is_owner = ($movie['creator_id'] ?? '') === $user_id;
                        ?>
                        <tr>
                            <td><?= sanitize($movie['title']) ?></td>
                            <td><?= sanitize($movie['uploader_name'] ?? 'Unknown') ?></td>
                            <td><?= sanitize($movie['category_name'] ?? 'Uncategorized') ?></td>
                            <td>
                                <span class="review-pill <?= $has_review ? 'review-ready' : 'review-missing' ?>">
                                    <?= $has_review ? 'Reviewed By You' : 'Not Reviewed By You' ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-pill <?= $is_published ? 'status-published' : 'status-draft' ?>">
                                    <?= $is_published ? 'Published' : 'Draft' ?>
                                </span>
                            </td>
                            <td><?= sanitize(date('M d, Y', strtotime($movie['createdon']))) ?></td>
                            <td>
                                <div class="row-actions">
                                    <?php if (!$has_review): ?>
                                        <a class="row-btn" href="<?= $root_url ?>/creator/add_review.php?movie_id=<?= urlencode($movie['id']) ?>">
                                            Write Review
                                        </a>
                                    <?php else: ?>
                                        <a class="row-btn" href="<?= $root_url ?>/creator/edit_review.php?id=<?= urlencode($movie['id']) ?>">Edit Review</a>
                                    <?php endif; ?>

                                    <a class="row-btn" href="<?= $root_url ?>/review.php?id=<?= urlencode($movie['id']) ?>">View</a>

                                    <?php if ($is_owner && !$is_published): ?>
                                        <form method="post" action="<?= $root_url ?>/creator/index.php">
                                            <input type="hidden" name="action" value="publish">
                                            <input type="hidden" name="movie_id" value="<?= sanitize($movie['id']) ?>">
                                            <button class="row-btn" type="submit">Publish</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($is_owner): ?>
                                        <form method="post" action="<?= $root_url ?>/creator/index.php" onsubmit="return confirm('Delete this movie and its review content?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="movie_id" value="<?= sanitize($movie['id']) ?>">
                                            <button class="row-btn row-btn-danger" type="submit">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
