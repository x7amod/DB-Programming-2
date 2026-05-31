<?php
// PURPOSE: Admin form for editing a review's comment text.
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db_helpers.php';

require_role(['Admin', 'Support']);

$root_url = '/DB-Programming-2'; // add app base URL

$review_id = $_GET['id'] ?? ($_POST['review_id'] ?? '');

if ($review_id === '') {
    set_flash('error', 'No review specified.');
    redirect($root_url . '/admin/manage_content.php');
}

$review = get_review_by_id($pdo, $review_id);

if (!$review) {
    set_flash('error', 'Review not found.');
    redirect($root_url . '/admin/manage_content.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $comment = trim($_POST['comment'] ?? '');
    $by_id   = current_user()['id'];

    if ($comment === '') {
        $errors['comment'] = 'Comment cannot be empty.';
    } elseif (strlen($comment) > 2000) {
        $errors['comment'] = 'Comment must be under 2000 characters.';
    }

    if (empty($errors)) {
        update_review_comment($pdo, $review_id, $comment, $by_id);
        set_flash('success', 'Review updated successfully.');
        redirect($root_url . '/admin/manage_content.php');
    }

    $review['comment'] = $comment; // re-populate textarea on error
}
?>

<div class="form-container form-container-wide">
    <div class="admin-section-header">
        <h1>Edit Review</h1>
        <a href="<?= $root_url ?>/admin/manage_content.php">&larr; Back to Reviews</a>
    </div>

    <div class="review-context">
        <p><strong>Movie:</strong> <?= sanitize($review['movie_title']) ?></p>
        <p><strong>Reviewer:</strong> <?= sanitize($review['reviewer_username']) ?></p>
        <p><strong>Rating:</strong>
            <span class="rating-stars">
                <?= str_repeat('★', (int)$review['rating']) ?><?= str_repeat('☆', 5 - (int)$review['rating']) ?>
            </span>
        </p>
    </div>

    <form method="POST" id="edit-review-form" novalidate>
        <input type="hidden" name="review_id" value="<?= sanitize($review_id) ?>">

        <div class="form-group">
            <label for="comment">Comment</label>
            <textarea
                id="comment"
                name="comment"
                rows="6"
                class="<?= isset($errors['comment']) ? 'is-invalid' : '' ?>"
                placeholder="Enter review comment..."
            ><?= sanitize($review['comment'] ?? '') ?></textarea>
            <?php if (isset($errors['comment'])): ?>
                <span class="field-error"><?= sanitize($errors['comment']) ?></span>
            <?php endif; ?>
            <small class="char-count"><span id="char-count">0</span> / 2000 characters</small>
        </div>

        <button type="submit">Save Changes</button>
        <a href="<?= $root_url ?>/admin/manage_content.php" class="btn-link">Cancel</a>
    </form>
</div>

<script>
(function () {
    const form    = document.getElementById('edit-review-form');
    const textarea = document.getElementById('comment');
    const counter  = document.getElementById('char-count');

    function updateCount() {
        counter.textContent = textarea.value.length;
        counter.style.color = textarea.value.length > 2000 ? '#dc3545' : '';
    }
    textarea.addEventListener('input', updateCount);
    updateCount();

    form.addEventListener('submit', function (e) {
        const comment = textarea.value.trim();
        clearErrors();
        const errors = [];

        if (comment === '') {
            errors.push({ field: 'comment', message: 'Comment cannot be empty.' });
        } else if (comment.length > 2000) {
            errors.push({ field: 'comment', message: 'Comment must be under 2000 characters.' });
        }

        if (errors.length > 0) {
            e.preventDefault();
            errors.forEach(function (err) {
                showError(err.field, err.message);
            });
        }
    });

    function showError(fieldId, message) {
        const el = document.getElementById(fieldId);
        el.classList.add('is-invalid');
        const span = document.createElement('span');
        span.className = 'field-error js-error';
        span.textContent = message;
        el.parentNode.appendChild(span);
    }

    function clearErrors() {
        document.querySelectorAll('.js-error').forEach(function (el) { el.remove(); });
        document.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
    }
}());
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
