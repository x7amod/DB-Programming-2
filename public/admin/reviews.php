<?php
// PURPOSE: Admin review management — view, deactivate/reactivate reviews.
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../src/db_helpers.php';

require_role(['Admin', 'Support']);

$root_url = '/DB-Programming-2/public'; // added: app base URL

// Handle POST actions (PRG pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $review_id = $_POST['review_id'] ?? '';
    $by_id     = current_user()['id'];

    if ($action === 'toggle_active' && $review_id !== '') {
        toggle_review_active($pdo, $review_id, $by_id);
        set_flash('success', 'Review status updated.');
    }

    if ($action === 'delete_review' && $review_id !== '') {
        $stmt = $pdo->prepare('DELETE FROM dbProj_reviews WHERE id = ?');
        $stmt->execute([$review_id]);
        set_flash('success', 'Review deleted.');
    }

    redirect($root_url . '/admin/reviews.php'); // changed to use $root_url
}

$reviews = get_all_reviews_with_details($pdo);
?>

<div class="admin-section">
    <div class="admin-section-header">
        <h1>Manage Reviews</h1>
        <a href="<?= $root_url ?>/admin/index.php">&larr; Back to Dashboard</a> <!-- changed -->
    </div>

    <p><?= count($reviews) ?> review(s) total.</p>

    <?php if (empty($reviews)): ?>
        <p>No reviews found.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Movie</th>
                    <th>Reviewer</th>
                    <th>Rating</th>
                    <th>Comment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reviews as $r): ?>
                    <tr class="<?= $r['inactive'] ? 'row-inactive' : '' ?>">
                        <td><?= sanitize($r['movie_title']) ?></td>
                        <td><?= sanitize($r['reviewer_username']) ?></td>
                        <td>
                            <span class="rating-stars" title="<?= (int)$r['rating'] ?>/5">
                                <?= str_repeat('★', (int)$r['rating']) ?><?= str_repeat('☆', 5 - (int)$r['rating']) ?>
                            </span>
                        </td>
                        <td class="comment-excerpt" title="<?= sanitize($r['comment']) ?>">
                            <?= sanitize(mb_substr($r['comment'] ?? '', 0, 60)) ?>
                            <?= mb_strlen($r['comment'] ?? '') > 60 ? '…' : '' ?>
                        </td>
                        <td>
                            <span class="status-badge <?= $r['inactive'] ? 'status-inactive' : 'status-active' ?>">
                                <?= $r['inactive'] ? 'Removed' : 'Active' ?>
                            </span>
                        </td>
                        <td><?= sanitize(substr($r['createdon'], 0, 10)) ?></td>
                        <td class="actions">
                            <a href="<?= $root_url ?>/admin/edit_review.php?id=<?= urlencode($r['id']) ?>" class="btn-edit">Edit</a>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Permanently delete this review?');">
                                <input type="hidden" name="action" value="delete_review">
                                <input type="hidden" name="review_id" value="<?= sanitize($r['id']) ?>">
                                <button type="submit" class="btn-deactivate">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
