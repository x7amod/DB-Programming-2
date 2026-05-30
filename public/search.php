<?php
// PURPOSE: Search page for movies.
require_once __DIR__ . '/../includes/header.php';

$title = trim($_GET['title'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');
$creator = trim($_GET['creator'] ?? '');
$category = trim($_GET['category'] ?? '');
$sort = trim($_GET['sort'] ?? '');

$sql = "
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
";

$params = [];

if ($title !== '') {
    $sql .= " AND (
        MATCH(m.title, m.description) AGAINST(:search_term IN NATURAL LANGUAGE MODE)
        OR m.title LIKE :title_like
        OR m.description LIKE :description_like
    )";
    $params[':search_term'] = $title;
    $params[':title_like'] = '%' . $title . '%';
    $params[':description_like'] = '%' . $title . '%';
}

if ($startDate !== '') {
    $sql .= " AND DATE(m.createdon) >= :start_date";
    $params[':start_date'] = $startDate;
}

if ($endDate !== '') {
    $sql .= " AND DATE(m.createdon) <= :end_date";
    $params[':end_date'] = $endDate;
}

if ($creator !== '') {
    $sql .= " AND u.username LIKE :creator";
    $params[':creator'] = '%' . $creator . '%';
}

if ($category !== '') {
    $sql .= " AND c.name = :category";
    $params[':category'] = $category;
}

if ($sort === 'popular') {
    $sql .= " ORDER BY m.view_count DESC";
} else {
    $sql .= " ORDER BY m.createdon DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movies = $stmt->fetchAll();

$catStmt = $pdo->query("
    SELECT name 
    FROM dbProj_categories 
    WHERE inactive = FALSE 
    ORDER BY name ASC
");
$categories = $catStmt->fetchAll();
?>

<section class="hero-section">
    <h1>Search Movies</h1>
    <p>Search by title, date range, creator, category, or popularity.</p>
</section>

<section class="live-search-section">
    <div class="live-search-panel">
        <div>
            <p class="movie-detail-kicker">Live Search</p>
            <h2>Instant title search</h2>
        </div>
        <input
            id="live-search-input"
            type="text"
            value="<?= htmlspecialchars($title) ?>"
            placeholder="Type a movie title to search instantly"
            autocomplete="off"
        >
    </div>
    <div id="live-search-results" class="live-search-results">
        <p class="comment-empty">Type at least 2 characters to see live matches.</p>
    </div>
</section>

<section class="search-section">
    <form method="GET" action="/DB-Programming-2/public/search.php" class="advanced-search-form">
        <input 
            type="text" 
            name="title" 
            placeholder="Movie title or description"
            value="<?= htmlspecialchars($title) ?>"
        >

        <input 
            type="date" 
            name="start_date"
            value="<?= htmlspecialchars($startDate) ?>"
        >

        <input 
            type="date" 
            name="end_date"
            value="<?= htmlspecialchars($endDate) ?>"
        >

        <input 
            type="text" 
            name="creator" 
            placeholder="Creator username"
            value="<?= htmlspecialchars($creator) ?>"
        >

        <select name="category">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option 
                    value="<?= htmlspecialchars($cat['name']) ?>"
                    <?= $category === $cat['name'] ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="sort">
            <option value="">Newest First</option>
            <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>
                Most Popular
            </option>
        </select>

        <button type="submit">Search</button>
    </form>
</section>

<section class="movie-section">
    <h2>Search Results</h2>

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
    <?php else: ?>
        <p>No movies found.</p>
    <?php endif; ?>
</section>

<script>
(function () {
    const input = document.getElementById('live-search-input');
    const results = document.getElementById('live-search-results');

    if (!input || !results) {
        return;
    }

    let timer = null;

    function renderMessage(message, className) {
        results.innerHTML = '<p class="' + className + '">' + message + '</p>';
    }

    async function runSearch() {
        const term = input.value.trim();

        if (term.length < 2) {
            renderMessage('Type at least 2 characters to see live matches.', 'comment-empty');
            return;
        }

        renderMessage('Searching...', 'comment-status comment-status-info');

        try {
            const response = await fetch('/DB-Programming-2/public/ajax/search_live.php?q=' + encodeURIComponent(term), {
                headers: {
                    'Accept': 'text/html'
                }
            });

            results.innerHTML = await response.text();
        } catch (error) {
            renderMessage('Live search is temporarily unavailable.', 'comment-status comment-status-error');
        }
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(runSearch, 250);
    });

    if (input.value.trim().length >= 2) {
        runSearch();
    }
}());
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
