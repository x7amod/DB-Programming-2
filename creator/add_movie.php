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
$genre_stmt = $pdo->query(
    "SELECT name
     FROM dbProj_categories
     WHERE inactive = FALSE
     ORDER BY name ASC"
);
$genres = $genre_stmt->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../includes/header.php';
?>

<section class="creator-form-shell">
    <h1>Add Movie</h1>

    <div class="creator-form-card">
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?= $error_message ?></div>
        <?php endif; ?>

        <form id="movie-form" method="post" action="<?= $root_url ?>/creator/upload_handler.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_movie">

            <div class="form-group">
                <label for="title">Movie Title</label>
                <input type="text" id="title" name="title" required>
                <div class="inline-error" id="error-title"></div>
            </div>

            <div class="form-group">
                <label for="genre">Genre</label>
                <select id="genre" name="genre" required>
                    <?php if (!$genres): ?>
                        <option value="">No active genres available</option>
                    <?php else: ?>
                        <option value="">Select genre</option>
                        <?php foreach ($genres as $genre_name): ?>
                            <option value="<?= sanitize($genre_name) ?>"><?= sanitize($genre_name) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
                <label for="poster">Poster Image</label>
                <input type="file" id="poster" name="poster" accept=".jpg,.jpeg,.png,.webp" required>
                <div class="inline-error" id="error-poster"></div>
            </div>

            <div class="form-group">
                <label for="trailer">Trailer (optional)</label>
                <input type="file" id="trailer" name="trailer" accept=".mp4,.webm">
            </div>

            <div class="form-group">
                <label>Status</label>
                <div class="status-row">
                    <label><input type="radio" name="status" value="draft" checked> Save as Draft</label>
                    <label><input type="radio" name="status" value="published"> Publish Now</label>
                </div>
            </div>

            <button type="submit">Save Movie</button>
            <a class="btn-link" href="<?= $root_url ?>/creator/index.php">Cancel</a>
        </form>
    </div>
</section>

<script>
(function () {
    const form = document.getElementById('movie-form');

    form.addEventListener('submit', function (event) {
        const errors = {
            title: '',
            genre: '',
            year: '',
            poster: ''
        };

        const title = document.getElementById('title').value.trim();
        const genre = document.getElementById('genre').value.trim();
        const yearValue = document.getElementById('release_year').value.trim();
        const posterFile = document.getElementById('poster').files;

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

        if (posterFile.length === 0) {
            errors.poster = 'Poster image is required.';
        }

        document.getElementById('error-title').textContent = errors.title;
        document.getElementById('error-genre').textContent = errors.genre;
        document.getElementById('error-year').textContent = errors.year;
        document.getElementById('error-poster').textContent = errors.poster;

        if (errors.title || errors.genre || errors.year || errors.poster) {
            event.preventDefault();
        }
    });
}());
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
