<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$root_url = '/DB-Programming-2';
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
<style>
    .portfolio-wrap { color: #f5f1e6; }
    .portfolio-header { margin-bottom: 20px; }
    .review-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
    .review-card { background: #141414; border-radius: 8px; overflow: hidden; border: 1px solid #242424; }
    .review-card img, .poster-placeholder { width: 100%; height: 320px; object-fit: cover; display: block; background: #1c1c1c; }
    .poster-placeholder { display: flex; align-items: center; justify-content: center; color: #888; font-size: 14px; }
    .review-body { padding: 14px; }
    .review-body h3 { margin: 0 0 6px; font-size: 18px; }
    .review-meta { font-size: 13px; color: #d9c97a; margin-bottom: 8px; }
    .review-desc { font-size: 13px; color: #d6d6d6; margin-bottom: 12px; }
    .stars { color: #f1c40f; font-size: 14px; letter-spacing: 1px; }
    .btn-link { color: #f1c40f; text-decoration: none; font-weight: 700; }
    .message-box { background: #1f2a38; color: #c0d4f2; padding: 12px; border-radius: 6px; border: 1px solid #2c4a6b; }
</style>

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
                                <img src="<?= sanitize($review['image_url']) ?>" alt="Poster">
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
