<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['Creator']);

$root_url = app_base_url();
$movie_id = trim($_GET['id'] ?? '');
$error_message = isset($_GET['error']) ? sanitize($_GET['error']) : '';
$user = current_user();
$user_id = $user['id'];

if ($movie_id === '') {
    redirect($root_url . '/creator/index.php?msg=invalid');
}

$stmt = $pdo->prepare(
    "SELECT
        m.id,
        m.title,
        c.name AS category_name,
        u.username AS uploader_name,
        r.id AS review_id,
        r.rating,
        r.comment
     FROM dbProj_movies m
     LEFT JOIN dbProj_categories c ON m.category_id = c.id
     LEFT JOIN dbProj_users u ON m.creator_id = u.id
     LEFT JOIN dbProj_reviews r
       ON r.movie_id = m.id AND r.user_id = :user_id AND r.inactive = FALSE
     WHERE m.id = :movie_id AND m.inactive = FALSE
     LIMIT 1"
);
$stmt->execute([
    ':user_id' => $user_id,
    ':movie_id' => $movie_id
]);
$movie = $stmt->fetch();

if (!$movie) {
    redirect($root_url . '/creator/index.php?msg=invalid');
}

if (empty($movie['review_id'])) {
    redirect($root_url . '/creator/add_review.php?movie_id=' . urlencode($movie_id));
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="creator-form-shell">
    <h1>Edit Review</h1>
    <p>Update your review for this movie.</p>

    <div class="creator-form-card">
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?= $error_message ?></div>
        <?php endif; ?>

        <div class="meta-box">
            <p><strong>Movie:</strong> <?= sanitize($movie['title']) ?></p>
            <p><strong>Genre:</strong> <?= sanitize($movie['category_name'] ?? 'Uncategorized') ?></p>
            <p><strong>Uploaded By:</strong> <?= sanitize($movie['uploader_name'] ?? 'Unknown') ?></p>
        </div>

        <form id="review-form" method="post" action="<?= $root_url ?>/creator/upload_handler.php">
            <input type="hidden" name="action" value="edit_review">
            <input type="hidden" name="movie_id" value="<?= sanitize($movie['id']) ?>">

            <div class="form-group">
                <label>Rating</label>
                <div class="rating-row">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <label>
                            <input type="radio" name="rating" value="<?= $i ?>" <?= (int) $movie['rating'] === $i ? 'checked' : '' ?>>
                            <?= $i ?> star<?= $i > 1 ? 's' : '' ?>
                        </label>
                    <?php endfor; ?>
                </div>
                <div class="inline-error" id="error-rating"></div>
            </div>

            <div class="form-group">
                <label for="comment">Review</label>
                <textarea id="comment" name="comment" minlength="10" rows="7" required><?= sanitize($movie['comment'] ?? '') ?></textarea>
                <div class="char-count" id="char-count">0 / 2000</div>
                <div class="inline-error" id="error-comment"></div>
            </div>

            <button type="submit">Update Review</button>
            <a class="btn-link" href="<?= $root_url ?>/creator/index.php">Back</a>
        </form>
    </div>
</section>

<script>
(function () {
    const form = document.getElementById('review-form');
    if (!form) {
        return;
    }

    const comment = document.getElementById('comment');
    const charCount = document.getElementById('char-count');

    function updateCharCount() {
        charCount.textContent = `${comment.value.length} / 2000`;
    }

    comment.addEventListener('input', updateCharCount);
    updateCharCount();

    form.addEventListener('submit', function (event) {
        const errors = { rating: '', comment: '' };
        const rating = form.querySelector('input[name="rating"]:checked');
        const text = comment.value.trim();

        if (!rating) {
            errors.rating = 'Please choose a rating.';
        }
        if (text.length < 10) {
            errors.comment = 'Review must be at least 10 characters.';
        } else if (text.length > 2000) {
            errors.comment = 'Review must be 2000 characters or fewer.';
        }

        document.getElementById('error-rating').textContent = errors.rating;
        document.getElementById('error-comment').textContent = errors.comment;

        if (errors.rating || errors.comment) {
            event.preventDefault();
        }
    });
}());
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
