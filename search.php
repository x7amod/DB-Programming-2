<?php
// PURPOSE: Search page for movies.
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db_helpers.php';

$root_url = app_base_url();

$title = trim($_GET['title'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');
$creator = trim($_GET['creator'] ?? '');
$category = trim($_GET['category'] ?? '');
$sort = trim($_GET['sort'] ?? '');

$filters = [
    'title' => $title,
    'start_date' => $startDate,
    'end_date' => $endDate,
    'creator' => $creator,
    'category' => $category,
    'sort' => $sort,
];

$movies = search_published_movies($pdo, $filters);

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

<section class="search-page-layout">
    <div class="search-info-card">
        <span class="section-kicker">Live Filter</span>
        <h2>Results update below</h2>
        <p>Use the main search form to filter the same results grid instantly without loading a separate block.</p>
        <p class="comment-empty">Title, creator, date, category, and sort changes all update the results section in place.</p>
    </div>

    <div class="search-form-card">
        <span class="section-kicker">Advanced Search</span>
        <h2>Refine your results</h2>
        <p>Filter by date range, creator, category, or sort by popularity.</p>

        <form id="search-form" method="GET" action="<?= $root_url ?>/search.php" class="advanced-search-form">
            <input 
                id="live-search-input"
                type="text" 
                name="title" 
                placeholder="Movie title or description"
                value="<?= htmlspecialchars($title) ?>"
                autocomplete="off"
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
    </div>
</section>

<section class="movie-section">
    <h2>Search Results</h2>
    <div id="search-results-content">
        <?php if (count($movies) > 0): ?>
            <div class="movie-grid">
                <?php foreach ($movies as $movie): ?>
                    <?php
                        $image = movie_poster_url($movie['image_url'] ?? null);

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

                            <a class="btn-view" href="<?= $root_url ?>/review.php?id=<?= urlencode($movie['id']) ?>">
                                View More
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>No movies found</h3>
                <p>Try adjusting your filters or searching with broader terms.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
(function () {
    const form = document.getElementById('search-form');
    const input = document.getElementById('live-search-input');
    const results = document.getElementById('search-results-content');
    let debounceId = null;
    let activeController = null;

    if (!form || !input || !results) {
        return;
    }

    function syncUrl(searchParams) {
        const query = searchParams.toString();
        const nextUrl = query ? `${form.action}?${query}` : form.action;
        window.history.replaceState(null, '', nextUrl);
    }

    function setLoadingState() {
        results.innerHTML = `
            <div class="empty-state">
                <h3>Updating results</h3>
                <p>Applying your filters...</p>
            </div>
        `;
    }

    async function fetchResults() {
        if (activeController) {
            activeController.abort();
        }

        activeController = new AbortController();
        const searchParams = new URLSearchParams(new FormData(form));

        try {
            setLoadingState();

            const response = await fetch(`<?= $root_url ?>/ajax/search_live.php?${searchParams.toString()}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: activeController.signal
            });

            if (!response.ok) {
                throw new Error('Unable to load live search results.');
            }

            results.innerHTML = await response.text();
            syncUrl(searchParams);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            results.innerHTML = `
                <div class="empty-state">
                    <h3>Unable to update results</h3>
                    <p>Please try again in a moment.</p>
                </div>
            `;
        }
    }

    function queueFetch(delay) {
        clearTimeout(debounceId);
        debounceId = setTimeout(function () {
            fetchResults();
        }, delay);
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        queueFetch(0);
    });

    input.addEventListener('input', function () {
        queueFetch(180);
    });

    form.querySelectorAll('input, select').forEach(function (field) {
        if (field === input) {
            return;
        }

        const eventName = field.tagName === 'SELECT' ? 'change' : 'input';
        field.addEventListener(eventName, function () {
            queueFetch(eventName === 'change' ? 0 : 180);
        });
    });

    syncUrl(new URLSearchParams(new FormData(form)));
}());
</script>
<?php
require_once __DIR__ . '/includes/footer.php';
?>
