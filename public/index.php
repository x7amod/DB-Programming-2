<?php
// PURPOSE: Home page - shows latest published movies.
require_once __DIR__ . '/../includes/header.php';

// Pagination settings
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Count total published movies
$countStmt = $pdo->query("
    SELECT COUNT(*) 
    FROM dbProj_movies
    WHERE is_published = TRUE
      AND inactive = FALSE
");

$totalMovies = (int) $countStmt->fetchColumn();
$totalPages = (int) ceil($totalMovies / $limit);

// Get movies for current page
$stmt = $pdo->prepare("
    SELECT 
        m.id,
        m.title,
        m.description,
        m.image_url,
        m.createdon,
        m.view_count,
        c.name AS category_name,
        u.username AS creator_name
    FROM dbProj_movies m
    LEFT JOIN dbProj_categories c ON m.category_id = c.id
    LEFT JOIN dbProj_users u ON m.creator_id = u.id
    WHERE m.is_published = TRUE
      AND m.inactive = FALSE
    ORDER BY m.createdon DESC
    LIMIT :limit OFFSET :offset
");

$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$movies = $stmt->fetchAll();
?>

<section class="hero-section">
    <h1>Welcome to Movie Reviews</h1>
    <p>Discover the latest movies, reviews, ratings, and popular recommendations.</p>

    <form method="GET" action="search.php" class="search-bar">
        <input type="text" name="title" placeholder="Search for a movie..." required>
        <button type="submit">Search</button>
    </form>
</section>

<section class="movie-section">
    <h2>Latest Movies</h2>

    <?php if (count($movies) > 0): ?>
        <div class="movie-grid">
            <?php foreach ($movies as $movie): ?>
                <?php
                    $image = !empty($movie['image_url'])
                        ? $movie['image_url']
                        : '/DB-Programming-2/public/assets/images/default-movie.jpg';

                    $shortDescription = strlen($movie['description']) > 120
                        ? substr($movie['description'], 0, 120) . '...'
                        : $movie['description'];
                ?>

                <div class="movie-card">
                    <img src="<?= htmlspecialchars($image) ?>" alt="Movie poster">

                    <div class="movie-card-body">
                        <h3><?= htmlspecialchars($movie['title']) ?></h3>

                        <p class="movie-meta">
                            <?= htmlspecialchars($movie['category_name'] ?? 'Uncategorized') ?>
                            |
                            By <?= htmlspecialchars($movie['creator_name'] ?? 'Unknown') ?>
                        </p>

                        <p><?= htmlspecialchars($shortDescription) ?></p>

                        <p class="movie-views">
                            Views: <?= htmlspecialchars($movie['view_count']) ?>
                        </p>

                        <a class="btn-view" href="/DB-Programming-2/public/review.php?id=<?= urlencode($movie['id']) ?>">
                            View More
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="index.php?page=<?= $page - 1 ?>" class="page-link">Previous</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a 
                        href="index.php?page=<?= $i ?>" 
                        class="page-link <?= $i === $page ? 'active' : '' ?>"
                    >
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="index.php?page=<?= $page + 1 ?>" class="page-link">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <p>No movies available yet.</p>
    <?php endif; ?>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>