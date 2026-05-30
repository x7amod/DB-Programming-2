<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['Creator']);

$root_url = '/DB-Programming-2';
$current_year = (int) date('Y');
$error_message = isset($_GET['error']) ? sanitize($_GET['error']) : '';

require_once __DIR__ . '/../includes/header.php';
?>
<style>
    .form-wrap { max-width: 760px; margin: 0 auto; color: #f5f1e6; }
    .form-wrap h1 { margin-bottom: 10px; }
    .form-card { background: #141414; padding: 20px; border-radius: 8px; }
    .form-group { margin-bottom: 16px; }
    label { display: block; margin-bottom: 6px; font-weight: 600; }
    input, select, textarea { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #2c2c2c; background: #101010; color: #f5f1e6; }
    textarea { min-height: 160px; resize: vertical; }
    .inline-error { color: #f2b6b6; font-size: 13px; margin-top: 4px; }
    .note { font-size: 12px; color: #b7b7b7; }
    .char-count { font-size: 12px; color: #d9c97a; text-align: right; }
    .radio-group { display: flex; gap: 16px; }
    .btn { padding: 10px 16px; border-radius: 6px; border: none; cursor: pointer; font-weight: 700; }
    .btn-primary { background: #f1c40f; color: #1b1b1b; }
    .alert-error { background: #3b1b1b; color: #f2b6b6; padding: 10px; border-radius: 6px; margin-bottom: 12px; border: 1px solid #6b2b2b; }
</style>

<div class="form-wrap">
    <h1>Add New Review</h1>
    <div class="form-card">
        <?php if ($error_message): ?>
            <div class="alert-error"><?= $error_message ?></div>
        <?php endif; ?>

        <form id="review-form" method="post" action="<?= $root_url ?>/creator/upload_handler.php" enctype="multipart/form-data" data-require-poster="true">
            <input type="hidden" name="action" value="add">

            <div class="form-group">
                <label for="title">Movie Title</label>
                <input type="text" id="title" name="title" required>
                <div class="inline-error" id="error-title"></div>
            </div>

            <div class="form-group">
                <label for="genre">Genre</label>
                <select id="genre" name="genre" required>
                    <option value="">Select genre</option>
                    <option>Action</option>
                    <option>Comedy</option>
                    <option>Drama</option>
                    <option>Horror</option>
                    <option>Sci-Fi</option>
                    <option>Romance</option>
                    <option>Thriller</option>
                    <option>Animation</option>
                    <option>Documentary</option>
                    <option>Other</option>
                </select>
                <div class="inline-error" id="error-genre"></div>
            </div>

            <div class="form-group">
                <label for="release_year">Release Year</label>
                <input type="number" id="release_year" name="release_year" min="1888" max="<?= $current_year ?>" required>
                <div class="note">Enter a 4 digit year between 1888 and <?= $current_year ?>.</div>
                <div class="inline-error" id="error-year"></div>
            </div>

            <div class="form-group">
                <label for="description">Description / Review Body</label>
                <textarea id="description" name="description" minlength="50" required></textarea>
                <div class="char-count" id="char-count">0 / 50</div>
                <div class="inline-error" id="error-description"></div>
            </div>

            <div class="form-group">
                <label for="poster">Poster Image</label>
                <input type="file" id="poster" name="poster" accept=".jpg,.jpeg,.png,.webp" required>
                <div class="inline-error" id="error-poster"></div>
            </div>

            <div class="form-group">
                <label for="trailer">Trailer (optional)</label>
                <input type="file" id="trailer" name="trailer" accept=".mp4,.webm">
                <div class="inline-error" id="error-trailer"></div>
            </div>

            <div class="form-group">
                <label>Status</label>
                <div class="radio-group">
                    <label><input type="radio" name="status" value="draft" checked> Save as Draft</label>
                    <label><input type="radio" name="status" value="published"> Publish Now</label>
                </div>
            </div>

            <button class="btn btn-primary" type="submit">Save Review</button>
        </form>
    </div>
</div>

<script>
    const form = document.getElementById('review-form');
    const description = document.getElementById('description');
    const charCount = document.getElementById('char-count');

    function updateCharCount() {
        const count = description.value.trim().length;
        charCount.textContent = `${count} / 50`;
    }

    description.addEventListener('input', updateCharCount);
    updateCharCount();

    form.addEventListener('submit', (event) => {
        const errors = {
            title: '',
            genre: '',
            year: '',
            description: '',
            poster: ''
        };

        const title = document.getElementById('title').value.trim();
        const genre = document.getElementById('genre').value.trim();
        const yearValue = document.getElementById('release_year').value.trim();
        const posterFile = document.getElementById('poster').files;
        const requirePoster = form.dataset.requirePoster === 'true';

        if (!title) {
            errors.title = 'Title is required.';
        }

        if (!genre) {
            errors.genre = 'Please select a genre.';
        }

        if (yearValue) {
            const yearNumber = Number(yearValue);
            if (!Number.isInteger(yearNumber) || yearValue.length !== 4) {
                errors.year = 'Release year must be a 4 digit number.';
            } else if (yearNumber < 1888 || yearNumber > <?= $current_year ?>) {
                errors.year = 'Release year is out of range.';
            }
        } else {
            errors.year = 'Release year is required.';
        }

        if (description.value.trim().length < 50) {
            errors.description = 'Description must be at least 50 characters.';
        }

        if (requirePoster && posterFile.length === 0) {
            errors.poster = 'Poster image is required.';
        }

        document.getElementById('error-title').textContent = errors.title;
        document.getElementById('error-genre').textContent = errors.genre;
        document.getElementById('error-year').textContent = errors.year;
        document.getElementById('error-description').textContent = errors.description;
        document.getElementById('error-poster').textContent = errors.poster;

        if (errors.title || errors.genre || errors.year || errors.description || errors.poster) {
            event.preventDefault();
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
