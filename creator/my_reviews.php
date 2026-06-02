<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$root_url = '/~u202302211/DB-Programming-2';
$user_id = trim($_GET['user_id'] ?? '');

function extract_release_year(string $description): array {
    $year = '';
    $body = $description;
    if (preg_match('/^\[Release Year: (\d{4})\]\s*/', $description, $matches)) {
        $year = $matches[1];
        $body = preg_replace('/^\[Release Year: \d{4}\]\s*/', '', $description);
    }
    return [$year, $body];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="portfolio-wrap">
    <?php if ($user_id === ''): ?>
        <div class="message-box">Creator not found.</div>
    <?php else: ?>
        <?php
            $user_stmt = $pdo->prepare('SELECT username FROM dbProj_users WHERE id = ?');
            $user_stmt->execute([$user_id]);
            $user = $user_stmt->fetch();
        ?>

        <?php if (!$user): ?>
            <div class="message-box">Creator not found.</div>
        <?php else: ?>
            <?php
                $review_stmt = $pdo->prepare(
                    "SELECT m.id, m.title, m.description, m.image_url, m.createdon, c.name AS category_name,
                            COALESCE(AVG(r.rating), 0) AS avg_rating
                     FROM dbProj_movies m
                     LEFT JOIN dbProj_categories c ON m.category_id = c.id
                     LEFT JOIN dbProj_reviews r ON r.movie_id = m.id AND r.inactive = FALSE
                     WHERE m.creator_id = ? AND m.is_published = TRUE AND m.inactive = FALSE
                     GROUP BY m.id
                     ORDER BY m.createdon DESC"
                );
                $review_stmt->execute([$user_id]);
                $reviews = $review_stmt->fetchAll();
                $review_count = count($reviews);
            ?>

            <div class="portfolio-header">
                <h1><?= sanitize($user['username']) ?>'s Reviews</h1>
                <div><?= $review_count ?> published review<?= $review_count === 1 ? '' : 's' ?></div>
            </div>

            <?php if (!$reviews): ?>
                <div class="message-box">No published reviews yet. Check back soon.</div>
            <?php else: ?>
                <div class="review-grid">
                    <?php foreach ($reviews as $review): ?>
                        <?php
                            $avg = (float) $review['avg_rating'];
                            $filled = (int) round($avg);
                            $stars = str_repeat('★', $filled) . str_repeat('☆', 5 - $filled);
                            list($release_year, $clean_description) = extract_release_year($review['description'] ?? '');
                            $excerpt = substr(trim($clean_description), 0, 120);
                        ?>
                        <div class="review-card">
                            <?php if (!empty($review['image_url'])): ?>
                                <img src="/~u202302211<?= sanitize($review['image_url']) ?>" alt="Poster">
                            <?php else: ?>
                                <div class="poster-placeholder">No poster</div>
                            <?php endif; ?>
                            <div class="review-body">
                                <h3><?= sanitize($review['title']) ?></h3>
                                <div class="review-meta">
                                    <?= sanitize($review['category_name'] ?? 'Uncategorized') ?>
                                    <?php if ($release_year !== ''): ?>
                                        · <?= sanitize($release_year) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="stars" aria-label="Average rating"><?= $stars ?></div>
                                <div class="review-desc"><?= sanitize($excerpt) ?>...</div>
                                <a class="btn-link" href="<?= $root_url ?>/review.php?id=<?= urlencode($review['id']) ?>">Read Review</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
