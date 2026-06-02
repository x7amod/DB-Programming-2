<?php
// PURPOSE: AJAX live search for movie titles.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../src/db_helpers.php';

$term = trim($_GET['q'] ?? '');
$results = search_movies_live($pdo, $term);

if (strlen($term) < 2) {
    echo '<p class="comment-empty">Type at least 2 characters to see live matches.</p>';
    exit;
}

if (count($results) === 0) {
    echo '<p class="comment-empty">No live matches found.</p>';
    exit;
}

foreach ($results as $movie):
    $image = !empty($movie['image_url'])
        ? '/~u202302211' . $movie['image_url']
        : '/~u202302211/DB-Programming-2/public/assets/images/default-movie.jpg';
    $shortDescription = strlen($movie['description'] ?? '') > 140
        ? substr($movie['description'], 0, 140) . '...'
        : ($movie['description'] ?? '');
?>
    <article class="live-result-card">
        <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($movie['title']) ?> poster">
        <div class="live-result-card-body">
            <h3><?= htmlspecialchars($movie['title']) ?></h3>
            <p class="movie-meta">
                <?= htmlspecialchars($movie['category_name'] ?? 'Uncategorized') ?>
                |
                By <?= htmlspecialchars($movie['creator_name'] ?? 'Unknown') ?>
            </p>
            <p><?= htmlspecialchars($shortDescription) ?></p>
            <p class="movie-views">Views: <?= htmlspecialchars((string) $movie['view_count']) ?></p>
            <a class="btn-view" href="/~u202302211/DB-Programming-2/public/review.php?id=<?= urlencode($movie['id']) ?>">View More</a>
        </div>
    </article>
<?php endforeach; ?>
