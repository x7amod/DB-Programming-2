<?php
// PURPOSE: AJAX live search for movie titles.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_helpers.php';

$filters = [
    'title' => trim($_GET['title'] ?? ($_GET['q'] ?? '')),
    'start_date' => trim($_GET['start_date'] ?? ''),
    'end_date' => trim($_GET['end_date'] ?? ''),
    'creator' => trim($_GET['creator'] ?? ''),
    'category' => trim($_GET['category'] ?? ''),
    'sort' => trim($_GET['sort'] ?? ''),
];

$results = search_published_movies($pdo, $filters);

if (count($results) === 0) {
    echo '<div class="empty-state">';
    echo '<h3>No movies found</h3>';
    echo '<p>Try adjusting your filters or searching with broader terms.</p>';
    echo '</div>';
    exit;
}

echo '<div class="movie-grid">';

foreach ($results as $movie):
    $image = movie_poster_url($movie['image_url'] ?? null);
    $shortDescription = strlen($movie['description'] ?? '') > 120
        ? substr($movie['description'], 0, 120) . '...'
        : ($movie['description'] ?? '');
?>
    <article class="movie-card">
        <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($movie['title']) ?> poster">
        <div class="movie-card-body">
            <h3><?= htmlspecialchars($movie['title']) ?></h3>
            <p class="movie-meta">
                <?= htmlspecialchars($movie['category_name'] ?? 'Uncategorized') ?>
                |
                By <?= htmlspecialchars($movie['creator_name'] ?? 'Unknown') ?>
            </p>
            <p><?= htmlspecialchars($shortDescription) ?></p>
            <p class="movie-views">Views: <?= htmlspecialchars((string) $movie['view_count']) ?></p>
            <a class="btn-view" href="<?= app_base_url() ?>/review.php?id=<?= urlencode($movie['id']) ?>">View More</a>
        </div>
    </article>
<?php endforeach; ?>

<?php echo '</div>'; ?>
