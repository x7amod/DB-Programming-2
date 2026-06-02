<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['Creator']);

$root_url = '/~u202302211/DB-Programming-2';
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
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="5" required></textarea>
                <div class="inline-error" id="error-description"></div>
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
            description: '',
            genre: '',
            poster: ''
        };

        const title = document.getElementById('title').value.trim();
        const description = document.getElementById('description').value.trim();
        const genre = document.getElementById('genre').value.trim();
        const posterFile = document.getElementById('poster').files;

        if (!title) {
            errors.title = 'Title is required.';
        }

        if (!description) {
            errors.description = 'Description is required.';
        }

        if (!genre) {
            errors.genre = 'Please select a genre.';
        }

        if (posterFile.length === 0) {
            errors.poster = 'Poster image is required.';
        }

        document.getElementById('error-title').textContent = errors.title;
        document.getElementById('error-description').textContent = errors.description;
        document.getElementById('error-genre').textContent = errors.genre;
        document.getElementById('error-poster').textContent = errors.poster;

        if (errors.title || errors.description || errors.genre || errors.poster) {
            event.preventDefault();
        }
    });
}());
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
