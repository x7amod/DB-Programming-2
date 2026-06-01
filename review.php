<?php
// PURPOSE: Movie review and details page.
require_once __DIR__ . '/includes/header.php';

require_once __DIR__ . '/includes/db_helpers.php';

$movieId = trim($_GET['id'] ?? ($_POST['movie_id'] ?? ''));

if ($movieId === '') {
	set_flash('error', 'No movie selected.');
	redirect('/DB-Programming-2/index.php');
}

$movie = get_movie_by_id($pdo, $movieId);

if (!$movie || (int) $movie['inactive'] === 1 || (int) $movie['is_published'] !== 1) {
	set_flash('error', 'Movie not found.');
	redirect('/DB-Programming-2/index.php');
}

increment_movie_view_count($pdo, $movieId);

$comments = get_movie_reviews($pdo, $movieId);
$currentUser = current_user();
$myReview = $currentUser ? get_user_movie_review($pdo, $movieId, $currentUser['id']) : null;

$ratingTotal = 0;
foreach ($comments as $commentRow) {
	$ratingTotal += (int) $commentRow['rating'];
}
$commentCount = count($comments);
$averageRating = $commentCount > 0 ? round($ratingTotal / $commentCount, 1) : 0;

$image = !empty($movie['image_url'])
	? $movie['image_url']
	: '/DB-Programming-2/assets/images/default-movie.jpg';
?>

<section class="movie-detail-hero">
	<div class="movie-detail-poster">
		<img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($movie['title']) ?> poster">
	</div>

	<div class="movie-detail-content">
		<p class="movie-detail-kicker">Movie Details</p>
		<h1><?= htmlspecialchars($movie['title']) ?></h1>
		<p class="movie-meta">
			<?= htmlspecialchars($movie['category_name'] ?? 'Uncategorized') ?>
			|
			By <?= htmlspecialchars($movie['creator_name'] ?? 'Unknown') ?>
		</p>

		<div class="movie-stats">
			<span><?= htmlspecialchars((string) $movie['view_count']) ?> views</span>
			<span><?= htmlspecialchars((string) $commentCount) ?> comment(s)</span>
			<span><?= htmlspecialchars((string) $averageRating) ?>/5 average</span>
		</div>

		<p class="movie-description"><?= nl2br(htmlspecialchars($movie['description'] ?? '')) ?></p>
	</div>
</section>

<section class="comments-section">
	<div class="section-heading">
		<div>
			<p class="movie-detail-kicker">Comments & Ratings</p>
			<h2>Viewer feedback</h2>
		</div>
		<span class="rating-stars" aria-label="Average rating <?= htmlspecialchars((string) $averageRating) ?> out of 5">
			<?= str_repeat('★', (int) round($averageRating)) ?><?= str_repeat('☆', 5 - (int) round($averageRating)) ?>
		</span>
	</div>

	<div id="comments-list" class="comments-list">
		<?php if ($commentCount === 0): ?>
			<p class="comment-empty">No comments yet. Be the first viewer to leave a rating.</p>
		<?php else: ?>
			<?php foreach ($comments as $commentRow): ?>
				<article class="comment-card">
					<div class="comment-card-header">
						<strong><?= htmlspecialchars($commentRow['reviewer_username']) ?></strong>
						<span class="rating-stars">
							<?= str_repeat('★', (int) $commentRow['rating']) ?><?= str_repeat('☆', 5 - (int) $commentRow['rating']) ?>
						</span>
					</div>
					<p class="comment-body">
						<?= htmlspecialchars($commentRow['comment'] !== '' ? $commentRow['comment'] : 'No comment provided.') ?>
					</p>
					<small class="comment-date">
						<?= htmlspecialchars(substr($commentRow['createdon'], 0, 16)) ?>
					</small>
				</article>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</section>

<section class="comments-section">
	<div class="section-heading">
		<div>
			<p class="movie-detail-kicker">Leave Feedback</p>
			<h2><?= $currentUser ? 'Add or update your rating' : 'Log in to comment' ?></h2>
		</div>
	</div>

	<?php if ($currentUser): ?>
		<form id="comment-form" class="comment-form" method="POST" action="/DB-Programming-2/ajax/submit_comment.php" novalidate>
			<input type="hidden" name="movie_id" value="<?= htmlspecialchars($movieId) ?>">

			<div class="rating-picker">
				<label>Rating</label>
				<div class="rating-options">
					<?php for ($rating = 5; $rating >= 1; $rating--): ?>
						<label class="rating-option">
							<input
								type="radio"
								name="rating"
								value="<?= $rating ?>"
								<?= (int) ($myReview['rating'] ?? 5) === $rating ? 'checked' : '' ?>
							>
							<span><?= $rating ?> star<?= $rating > 1 ? 's' : '' ?></span>
						</label>
					<?php endfor; ?>
				</div>
			</div>

			<div class="form-group">
				<label for="comment">Comment</label>
				<textarea
					id="comment"
					name="comment"
					rows="5"
					placeholder="Share what you thought about this movie..."
				><?= htmlspecialchars($myReview['comment'] ?? '') ?></textarea>
				<small class="char-count"><span id="comment-count">0</span> / 2000 characters</small>
			</div>

			<div class="comment-form-actions">
				<button type="submit"><?= $myReview ? 'Update Review' : 'Submit Review' ?></button>
				<span id="comment-status" class="comment-status" aria-live="polite"></span>
				<?php if ($myReview): ?>
					<button type="button" id="delete-review-btn" class="delete-review-btn">Delete my review</button>
				<?php endif; ?>
			</div>
		</form>
	<?php else: ?>
		<p class="comment-empty">
			You need to <a href="/DB-Programming-2/auth/login.php">log in</a> to add a rating or comment.
		</p>
	<?php endif; ?>
</section>

<script>
(function () {
	const form = document.getElementById('comment-form');
	const textarea = document.getElementById('comment');
	const counter = document.getElementById('comment-count');
	const status = document.getElementById('comment-status');

	if (!form || !textarea || !counter || !status) {
		return;
	}

	function updateCount() {
		counter.textContent = textarea.value.length;
		counter.style.color = textarea.value.length > 2000 ? '#dc3545' : '';
	}

	textarea.addEventListener('input', updateCount);
	updateCount();

	form.addEventListener('submit', async function (event) {
		event.preventDefault();

		const ratingField = form.querySelector('input[name="rating"]:checked');
		const rating = ratingField ? ratingField.value : '';

		if (!rating) {
			status.textContent = 'Please choose a rating.';
			status.className = 'comment-status comment-status-error';
			return;
		}

		status.textContent = 'Saving your review...';
		status.className = 'comment-status comment-status-info';

		try {
			const response = await fetch(form.action, {
				method: 'POST',
				headers: {
					'Accept': 'application/json'
				},
				body: new URLSearchParams(new FormData(form))
			});

			const responseText = await response.text();
			let payload;
			try {
				payload = JSON.parse(responseText);
			} catch (parseError) {
				const preview = responseText.replace(/\s+/g, ' ').slice(0, 180);
				throw new Error(`Server returned an invalid response: ${preview}`);
			}

			if (!response.ok || !payload.success) {
				const errorDetail = payload && payload.error ? ` (${payload.error})` : '';
				throw new Error((payload.message || 'Unable to save review.') + errorDetail);
			}

			status.textContent = payload.message || 'Review saved.';
			status.className = 'comment-status comment-status-success';
			window.location.reload();
		} catch (error) {
			status.textContent = error.message;
			status.className = 'comment-status comment-status-error';
		}
	});

	// Delete review handler
	const deleteBtn = document.getElementById('delete-review-btn');
	if (deleteBtn) {
		deleteBtn.addEventListener('click', async function () {
			if (!confirm('Delete your review? This action will hide your review from the site.')) return;

			status.textContent = 'Deleting your review...';
			status.className = 'comment-status comment-status-info';

			try {
				const resp = await fetch('/DB-Programming-2/ajax/delete_review.php', {
					method: 'POST',
					headers: { 'Accept': 'application/json' },
					body: new URLSearchParams({ review_id: '<?= htmlspecialchars($myReview['id'] ?? '') ?>' })
				});

				const data = await resp.json();
				if (!resp.ok || !data.success) {
					throw new Error(data.message || 'Unable to delete review.');
				}

				window.location.reload();
			} catch (err) {
				status.textContent = err.message;
				status.className = 'comment-status comment-status-error';
			}
		});
	}
}());
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
