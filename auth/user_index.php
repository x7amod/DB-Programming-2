<?php
// PURPOSE: Logged-in user movie explorer with advanced text search.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

require_once __DIR__ . '/../includes/header.php';

$root_url = '/DB-Programming-2';
$current_user = current_user();

function selected_attr(string $value, string $current): string {
    return $value === $current ? 'selected' : '';
}

function clean_year_from_description(?string $description): array {
    $description = $description ?? '';
    $year = '';
    $body = $description;

    if (preg_match('/^\[Release Year: (\d{4})\]\s*/', $description, $matches)) {
        $year = $matches[1];
        $body = preg_replace('/^\[Release Year: \d{4}\]\s*/', '', $description);
    }

    return [$year, trim($body)];
}

function build_boolean_query(string $query, string $mode): string {
    $clean_query = preg_replace('/[+\-><()~*"@]+/', ' ', $query);
    $words = preg_split('/\s+/', trim((string) $clean_query));
    $words = array_values(array_filter($words, static function ($word) {
        return $word !== '';
    }));

    if ($mode === 'phrase') {
        return '"' . str_replace('"', '\"', trim($query)) . '"';
    }

    if ($mode === 'all') {
        return implode(' ', array_map(static function ($word) {
            return '+' . $word . '*';
        }, $words));
    }

    return implode(' ', array_map(static function ($word) {
        return $word . '*';
    }, $words));
}

$query = trim($_GET['q'] ?? '');
$field = $_GET['field'] ?? 'all';
$match = $_GET['match'] ?? 'any';
$category = trim($_GET['category'] ?? '');
$creator = trim($_GET['creator'] ?? '');
$year = trim($_GET['year'] ?? '');
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');
$min_rating = trim($_GET['min_rating'] ?? '');
$sort = $_GET['sort'] ?? 'relevance';

$allowed_fields = ['all', 'title', 'description', 'creator', 'category'];
$allowed_matches = ['any', 'all', 'phrase'];
$allowed_sorts = ['relevance', 'newest', 'popular', 'rating', 'title'];

if (!in_array($field, $allowed_fields, true)) {
    $field = 'all';
}
if (!in_array($match, $allowed_matches, true)) {
    $match = 'any';
}
if (!in_array($sort, $allowed_sorts, true)) {
    $sort = 'relevance';
}

$sql = "
    SELECT
        m.id,
        m.title,
        m.description,
        m.image_url,
        m.createdon,
        m.view_count,
        c.name AS category_name,
        u.username AS creator_name,
        COALESCE(AVG(r.rating), 0) AS avg_rating,
        COUNT(r.id) AS rating_count
    FROM dbProj_movies m
    LEFT JOIN dbProj_categories c ON m.category_id = c.id
    LEFT JOIN dbProj_users u ON m.creator_id = u.id
    LEFT JOIN dbProj_reviews r ON r.movie_id = m.id AND r.inactive = FALSE
    WHERE m.is_published = TRUE
      AND m.inactive = FALSE
";

$params = [];
$having = [];
$has_text_search = $query !== '';

if ($has_text_search) {
    $boolean_query = build_boolean_query($query, $match);
    $like_query = $match === 'phrase' ? '%' . $query . '%' : '%' . str_replace(' ', '%', $query) . '%';

    if ($field === 'title') {
        $sql .= ' AND m.title LIKE :query_like';
        $params[':query_like'] = $like_query;
    } elseif ($field === 'description') {
        $sql .= ' AND m.description LIKE :query_like';
        $params[':query_like'] = $like_query;
    } elseif ($field === 'creator') {
        $sql .= ' AND u.username LIKE :query_like';
        $params[':query_like'] = $like_query;
    } elseif ($field === 'category') {
        $sql .= ' AND c.name LIKE :query_like';
        $params[':query_like'] = $like_query;
    } else {
        $sql .= " AND (
            MATCH(m.title, m.description) AGAINST(:boolean_query_match IN BOOLEAN MODE)
            OR m.title LIKE :query_like_title
            OR m.description LIKE :query_like_description
            OR u.username LIKE :query_like_creator
            OR c.name LIKE :query_like_category
        )";
        $params[':boolean_query_match'] = $boolean_query;
        $params[':query_like_title'] = $like_query;
        $params[':query_like_description'] = $like_query;
        $params[':query_like_creator'] = $like_query;
        $params[':query_like_category'] = $like_query;
    }
}

if ($category !== '') {
    $sql .= ' AND c.name = :category';
    $params[':category'] = $category;
}

if ($creator !== '') {
    $sql .= ' AND u.username LIKE :creator';
    $params[':creator'] = '%' . $creator . '%';
}

if ($year !== '' && preg_match('/^\d{4}$/', $year)) {
    $sql .= ' AND m.description LIKE :release_year';
    $params[':release_year'] = '[Release Year: ' . $year . ']%';
}

if ($start_date !== '') {
    $sql .= ' AND DATE(m.createdon) >= :start_date';
    $params[':start_date'] = $start_date;
}

if ($end_date !== '') {
    $sql .= ' AND DATE(m.createdon) <= :end_date';
    $params[':end_date'] = $end_date;
}

$sql .= ' GROUP BY m.id, m.title, m.description, m.image_url, m.createdon, m.view_count, c.name, u.username';

if ($min_rating !== '' && is_numeric($min_rating)) {
    $having[] = 'avg_rating >= :min_rating';
    $params[':min_rating'] = (float) $min_rating;
}

if ($having) {
    $sql .= ' HAVING ' . implode(' AND ', $having);
}

if ($sort === 'popular') {
    $sql .= ' ORDER BY m.view_count DESC, m.createdon DESC';
} elseif ($sort === 'rating') {
    $sql .= ' ORDER BY avg_rating DESC, rating_count DESC, m.createdon DESC';
} elseif ($sort === 'title') {
    $sql .= ' ORDER BY m.title ASC';
} elseif ($sort === 'newest' || !$has_text_search || $field !== 'all') {
    $sql .= ' ORDER BY m.createdon DESC';
} else {
    $sql .= ' ORDER BY MATCH(m.title, m.description) AGAINST(:boolean_query_order IN BOOLEAN MODE) DESC, m.createdon DESC';
    $params[':boolean_query_order'] = $boolean_query;
}

$stmt = $pdo->prepare($sql);
foreach ($params as $name => $value) {
    $stmt->bindValue($name, $value);
}
$stmt->execute();
$movies = $stmt->fetchAll();

$cat_stmt = $pdo->query("
    SELECT name
    FROM dbProj_categories
    WHERE inactive = FALSE
    ORDER BY name ASC
");
$categories = $cat_stmt->fetchAll();
?>

<section class="user-search-header">
    <div>
        <h1>Movie Explorer</h1>
        <p>Welcome, <?= sanitize($current_user['username']) ?>. Search across published reviews with text, rating, date, category, creator, and year filters.</p>
    </div>

    <?php if ($current_user['role'] === 'Creator'): ?>
        <a class="btn-view" href="<?= $root_url ?>/creator/index.php">Creator Dashboard</a>
    <?php elseif (in_array($current_user['role'], ['Admin', 'Support'], true)): ?>
        <a class="btn-view" href="<?= $root_url ?>/admin/index.php">Admin Dashboard</a>
    <?php endif; ?>
</section>

<section class="user-search-panel">
    <form method="GET" action="<?= $root_url ?>/auth/user_index.php" class="user-search-form">
        <div class="form-group span-2">
            <label for="q">Text Search</label>
            <input
                type="text"
                id="q"
                name="q"
                value="<?= sanitize($query) ?>"
                placeholder="Search title, description, creator, or category"
            >
        </div>

        <div class="form-group">
            <label for="field">Search In</label>
            <select id="field" name="field">
                <option value="all" <?= selected_attr('all', $field) ?>>Everything</option>
                <option value="title" <?= selected_attr('title', $field) ?>>Title</option>
                <option value="description" <?= selected_attr('description', $field) ?>>Description</option>
                <option value="creator" <?= selected_attr('creator', $field) ?>>Creator</option>
                <option value="category" <?= selected_attr('category', $field) ?>>Category</option>
            </select>
        </div>

        <div class="form-group">
            <label for="match">Text Mode</label>
            <select id="match" name="match">
                <option value="any" <?= selected_attr('any', $match) ?>>Any word</option>
                <option value="all" <?= selected_attr('all', $match) ?>>All words</option>
                <option value="phrase" <?= selected_attr('phrase', $match) ?>>Exact phrase</option>
            </select>
        </div>

        <div class="form-group">
            <label for="category">Category</label>
            <select id="category" name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= sanitize($cat['name']) ?>" <?= selected_attr($cat['name'], $category) ?>>
                        <?= sanitize($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="creator">Creator</label>
            <input type="text" id="creator" name="creator" value="<?= sanitize($creator) ?>" placeholder="Username">
        </div>

        <div class="form-group">
            <label for="year">Release Year</label>
            <input type="number" id="year" name="year" value="<?= sanitize($year) ?>" min="1888" max="<?= date('Y') ?>" placeholder="2024">
        </div>

        <div class="form-group">
            <label for="min_rating">Minimum Rating</label>
            <select id="min_rating" name="min_rating">
                <option value="" <?= selected_attr('', $min_rating) ?>>Any rating</option>
                <?php foreach (['1', '2', '3', '4', '5'] as $rating): ?>
                    <option value="<?= $rating ?>" <?= selected_attr($rating, $min_rating) ?>><?= $rating ?>+ stars</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="start_date">From</label>
            <input type="date" id="start_date" name="start_date" value="<?= sanitize($start_date) ?>">
        </div>

        <div class="form-group">
            <label for="end_date">To</label>
            <input type="date" id="end_date" name="end_date" value="<?= sanitize($end_date) ?>">
        </div>

        <div class="form-group">
            <label for="sort">Sort</label>
            <select id="sort" name="sort">
                <option value="relevance" <?= selected_attr('relevance', $sort) ?>>Relevance</option>
                <option value="newest" <?= selected_attr('newest', $sort) ?>>Newest</option>
                <option value="popular" <?= selected_attr('popular', $sort) ?>>Most viewed</option>
                <option value="rating" <?= selected_attr('rating', $sort) ?>>Highest rated</option>
                <option value="title" <?= selected_attr('title', $sort) ?>>Title A-Z</option>
            </select>
        </div>

        <div class="user-search-actions">
            <button type="submit">Search</button>
            <a class="btn-link" href="<?= $root_url ?>/auth/user_index.php">Clear</a>
        </div>
    </form>
</section>

<section class="movie-section">
    <h2><?= count($movies) ?> Result<?= count($movies) === 1 ? '' : 's' ?></h2>

    <?php if ($movies): ?>
        <div class="movie-grid">
            <?php foreach ($movies as $movie): ?>
                <?php
                    $image = !empty($movie['image_url'])
                        ? $movie['image_url']
                        : '/DB-Programming-2/assets/images/default-movie.jpg';
                    [$release_year, $clean_description] = clean_year_from_description($movie['description'] ?? '');
                    $short_description = strlen($clean_description) > 130
                        ? substr($clean_description, 0, 130) . '...'
                        : $clean_description;
                    $avg_rating = number_format((float) $movie['avg_rating'], 1);
                ?>

                <div class="movie-card">
                    <img src="<?= sanitize($image) ?>" alt="Movie poster">

                    <div class="movie-card-body">
                        <h3><?= sanitize($movie['title']) ?></h3>
                        <p class="movie-meta">
                            <?= sanitize($movie['category_name'] ?? 'Uncategorized') ?>
                            <?php if ($release_year !== ''): ?>
                                | <?= sanitize($release_year) ?>
                            <?php endif; ?>
                            | By <?= sanitize($movie['creator_name'] ?? 'Unknown') ?>
                        </p>
                        <p><?= sanitize($short_description) ?></p>
                        <p class="movie-views">
                            <?= sanitize($avg_rating) ?>/5 from <?= (int) $movie['rating_count'] ?> rating<?= (int) $movie['rating_count'] === 1 ? '' : 's' ?>
                            | Views: <?= (int) $movie['view_count'] ?>
                        </p>
                        <a class="btn-view" href="<?= $root_url ?>/review.php?id=<?= urlencode($movie['id']) ?>">
                            View Review
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>No published reviews match your filters.</p>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
