<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$root_url = '/DB-Programming-2';
$movie_id = trim($_GET['id'] ?? '');

function extract_release_year(string $description): array {
    $year = '';
    $body = $description;
    if (preg_match('/^\[Release Year: (\d{4})\]\s*/', $description, $matches)) {
        $year = $matches[1];
        $body = preg_replace('/^\[Release Year: \d{4}\]\s*/', '', $description);
    }
    return [$year, $body];
}

require_once __DIR__ . '/includes/header.php';
?>
<style>
    .review-wrap { color: #f5f1e6; }
    .review-hero { display: grid; grid-template-columns: 1fr 1.3fr; gap: 20px; margin-bottom: 24px; }
    .review-hero img { width: 100%; border-radius: 8px; border: 1px solid #2c2c2c; }
    .review-meta { color: #d9c97a; margin-bottom: 8px; }
    .review-stats { display: flex; gap: 16px; margin: 10px 0; color: #b7b7b7; font-size: 14px; }
    .rating-display { display: flex; align-items: center; gap: 8px; }
    .stars { color: #f1c40f; font-size: 18px; letter-spacing: 1px; }
    .rating-widget { margin-top: 12px; }
    .rating-widget .star { cursor: pointer; font-size: 22px; color: #555; }
    .rating-widget .star.active { color: #f1c40f; }
    .rating-message { font-size: 13px; color: #d9c97a; margin-top: 6px; }
    .review-body { background: #141414; padding: 18px; border-radius: 8px; border: 1px solid #242424; }
    .trailer { margin-top: 20px; }
    .tooltip { font-size: 12px; color: #f2b6b6; margin-top: 6px; display: none; }
    @media (max-width: 900px) {
        .review-hero { grid-template-columns: 1fr; }
    }
</style>

<div class="review-wrap">
<?php if ($movie_id === ''): ?>
    <div class="review-body">Review not found.</div>
<?php else: ?>
    <?php
        $update_stmt = $pdo->prepare(
            'UPDATE dbProj_movies SET view_count = view_count + 1 WHERE id = ?'
        );
        $update_stmt->execute([$movie_id]);

        $stmt = $pdo->prepare(
            "SELECT m.*, c.name AS category_name, u.username AS creator_name
             FROM dbProj_movies m
             LEFT JOIN dbProj_categories c ON m.category_id = c.id
             LEFT JOIN dbProj_users u ON m.creator_id = u.id
             WHERE m.id = ? AND m.is_published = TRUE AND m.inactive = FALSE"
        );
        $stmt->execute([$movie_id]);
        $movie = $stmt->fetch();
    ?>

    <?php if (!$movie): ?>
        <div class="review-body">Review not found.</div>
    <?php else: ?>
        <?php
            $rating_stmt = $pdo->prepare(
                'SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_ratings
                 FROM dbProj_reviews
                 WHERE movie_id = ? AND inactive = FALSE'
            );
            $rating_stmt->execute([$movie_id]);
            $rating_stats = $rating_stmt->fetch();

            $avg_rating = $rating_stats ? (float) $rating_stats['avg_rating'] : 0.0;
            $total_ratings = $rating_stats ? (int) $rating_stats['total_ratings'] : 0;
            $filled = (int) round($avg_rating);
            $stars = str_repeat('★', $filled) . str_repeat('☆', 5 - $filled);

            list($release_year, $clean_description) = extract_release_year($movie['description'] ?? '');

            $user_rating = null;
            $can_rate = false;

            if (is_logged_in()) {
                $current_user = current_user();
                if ($current_user && $current_user['role'] !== 'Admin') {
                    $can_rate = true;
                    $user_rating_stmt = $pdo->prepare(
                        'SELECT rating FROM dbProj_reviews WHERE movie_id = ? AND user_id = ? AND inactive = FALSE LIMIT 1'
                    );
                    $user_rating_stmt->execute([$movie_id, $current_user['id']]);
                    $rating = $user_rating_stmt->fetch();
                    if ($rating) {
                        $user_rating = (int) $rating['rating'];
                    }
                }
            }
        ?>

        <div class="review-hero">
            <div>
                <?php if (!empty($movie['image_url'])): ?>
                    <img src="<?= sanitize($movie['image_url']) ?>" alt="Poster">
                <?php else: ?>
                    <div class="review-body">Poster not available.</div>
                <?php endif; ?>
            </div>
            <div>
                <h1><?= sanitize($movie['title']) ?></h1>
                <div class="review-meta">
                    <?= sanitize($movie['category_name'] ?? 'Uncategorized') ?>
                    <?php if ($release_year !== ''): ?>
                        · <?= sanitize($release_year) ?>
                    <?php endif; ?>
                </div>
                <div class="rating-display">
                    <div class="stars" id="avg-stars" aria-label="Average rating"><?= $stars ?></div>
                    <div id="avg-score"><?= number_format($avg_rating, 1) ?>/5</div>
                </div>
                <div class="review-stats">
                    <div><span id="rating-count"><?= $total_ratings ?></span> ratings</div>
                    <div><?= (int) $movie['view_count'] ?> views</div>
                </div>
                <div>
                    Reviewed by
                    <a href="<?= $root_url ?>/creator/my_reviews.php?user_id=<?= urlencode($movie['creator_id']) ?>">
                        <?= sanitize($movie['creator_name'] ?? 'Unknown') ?>
                    </a>
                </div>

                <?php if ($can_rate): ?>
                    <div class="rating-widget" data-movie-id="<?= sanitize($movie['id']) ?>">
                        <div id="star-row">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star <?= $user_rating !== null && $i <= $user_rating ? 'active' : '' ?>" data-score="<?= $i ?>">★</span>
                            <?php endfor; ?>
                        </div>
                        <div class="rating-message" id="user-rating-msg">
                            <?= $user_rating ? 'You rated this ' . $user_rating . '/5' : 'Rate this review' ?>
                        </div>
                        <div class="tooltip" id="rating-tooltip">Please log in to rate</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="review-body">
            <p><?= nl2br(sanitize($clean_description)) ?></p>
        </div>

        <?php if (!empty($movie['media_url'])): ?>
            <div class="trailer">
                <h2>Trailer</h2>
                <video controls width="100%">
                    <source src="<?= sanitize($movie['media_url']) ?>">
                    Your browser does not support the video tag.
                </video>
            </div>
        <?php endif; ?>

        <!-- COMMENTS SECTION: built by Member 5, include comments.php here -->

        <?php if ($can_rate): ?>
            <script>
                const ratingWidget = document.querySelector('.rating-widget');
                const starRow = document.getElementById('star-row');
                const tooltip = document.getElementById('rating-tooltip');
                const avgStars = document.getElementById('avg-stars');
                const avgScore = document.getElementById('avg-score');
                const ratingCount = document.getElementById('rating-count');
                const userRatingMsg = document.getElementById('user-rating-msg');

                function setStarHighlight(score) {
                    starRow.querySelectorAll('.star').forEach((star) => {
                        const starScore = Number(star.dataset.score);
                        if (starScore <= score) {
                            star.classList.add('active');
                        } else {
                            star.classList.remove('active');
                        }
                    });
                }

                function renderAverageStars(avg) {
                    const rounded = Math.round(avg);
                    avgStars.textContent = '★'.repeat(rounded) + '☆'.repeat(5 - rounded);
                }

                starRow.addEventListener('click', async (event) => {
                    const target = event.target;
                    if (!target.classList.contains('star')) {
                        return;
                    }

                    const score = Number(target.dataset.score);
                    const movieId = ratingWidget.dataset.movieId;
                    tooltip.style.display = 'none';

                    try {
                        const response = await fetch('<?= $root_url ?>/ajax/submit_rating.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `movie_id=${encodeURIComponent(movieId)}&score=${score}`
                        });

                        const result = await response.json();
                        if (!result.success) {
                            tooltip.textContent = result.message || 'Please log in to rate';
                            tooltip.style.display = 'block';
                            return;
                        }

                        setStarHighlight(score);
                        renderAverageStars(result.new_avg);
                        avgScore.textContent = `${result.new_avg.toFixed(1)}/5`;
                        ratingCount.textContent = result.total_ratings;
                        userRatingMsg.textContent = `You rated this ${score}/5`;
                    } catch (error) {
                        tooltip.textContent = 'Could not submit rating.';
                        tooltip.style.display = 'block';
                    }
                });
            </script>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
