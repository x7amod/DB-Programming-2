<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['Creator']);

$root_url = '/DB-Programming-2';
$error_message = isset($_GET['error']) ? sanitize($_GET['error']) : '';
$user = current_user();
$user_id = $user['id'];

$stmt = $pdo->prepare(
    "SELECT
        m.id,
        m.title,
        c.name AS category_name,
        u.username AS uploader_name
     FROM dbProj_movies m
     LEFT JOIN dbProj_categories c ON m.category_id = c.id
     LEFT JOIN dbProj_users u ON m.creator_id = u.id
     LEFT JOIN dbProj_reviews myr
       ON myr.movie_id = m.id AND myr.user_id = :user_id AND myr.inactive = FALSE
     WHERE m.inactive = FALSE
       AND myr.id IS NULL
     ORDER BY m.createdon DESC"
);
$stmt->execute([':user_id' => $user_id]);
$movies = $stmt->fetchAll();

$selected_movie_id = trim($_GET['movie_id'] ?? '');

require_once __DIR__ . '/../includes/header.php';
?>

<section class="creator-form-shell">
    <h1>Add Review</h1>
    <p>Select any movie you have not reviewed yet and submit your rating + review.</p>

    <?php if (!$movies): ?>
        <div class="empty-note">
            No available movies left to review.
        </div>
    <?php else: ?>
        <div class="creator-form-card">
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?= $error_message ?></div>
            <?php endif; ?>

            <form id="review-form" method="post" action="<?= $root_url ?>/creator/upload_handler.php">
                <input type="hidden" name="action" value="add_review">

                <div class="form-group">
                    <label for="movie_id">Movie</label>
                    <select id="movie_id" name="movie_id" required>
                        <option value="">Select a movie</option>
                        <?php foreach ($movies as $movie): ?>
                            <option
                                value="<?= sanitize($movie['id']) ?>"
                                <?= $selected_movie_id === $movie['id'] ? 'selected' : '' ?>
                            >
                                <?= sanitize($movie['title']) ?>
                                <?= !empty($movie['category_name']) ? ' - ' . sanitize($movie['category_name']) : '' ?>
                                <?= !empty($movie['uploader_name']) ? ' - by ' . sanitize($movie['uploader_name']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="inline-error" id="error-movie"></div>
                </div>

                <div class="form-group">
                    <label>Rating</label>
                    <div class="rating-row">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <label>
                                <input type="radio" name="rating" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
                                <?= $i ?> star<?= $i > 1 ? 's' : '' ?>
                            </label>
                        <?php endfor; ?>
                    </div>
                    <div class="inline-error" id="error-rating"></div>
                </div>

                <div class="form-group">
                    <label for="comment">Review</label>
                    <textarea id="comment" name="comment" minlength="10" rows="7" required></textarea>
                    <div class="char-count" id="char-count">0 / 2000</div>
                    <div class="inline-error" id="error-comment"></div>
                </div>

                <button type="submit">Submit Review</button>
                <a class="btn-link" href="<?= $root_url ?>/creator/index.php">Cancel</a>
            </form>
        </div>
    <?php endif; ?>
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
        const errors = { movie: '', rating: '', comment: '' };
        const movieId = document.getElementById('movie_id').value.trim();
        const rating = form.querySelector('input[name="rating"]:checked');
        const text = comment.value.trim();

        if (!movieId) {
            errors.movie = 'Please select a movie.';
        }
        if (!rating) {
            errors.rating = 'Please choose a rating.';
        }
        if (text.length < 10) {
            errors.comment = 'Review must be at least 10 characters.';
        } else if (text.length > 2000) {
            errors.comment = 'Review must be 2000 characters or fewer.';
        }

        document.getElementById('error-movie').textContent = errors.movie;
        document.getElementById('error-rating').textContent = errors.rating;
        document.getElementById('error-comment').textContent = errors.comment;

        if (errors.movie || errors.rating || errors.comment) {
            event.preventDefault();
        }
    });
}());
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
