<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$movie_id = trim($_POST['movie_id'] ?? '');
$score = filter_input(INPUT_POST, 'score', FILTER_VALIDATE_INT);

if ($movie_id === '' || !$score || $score < 1 || $score > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid rating data']);
    exit();
}

$movie_stmt = $pdo->prepare(
    'SELECT id FROM dbProj_movies WHERE id = ? AND is_published = TRUE AND inactive = FALSE'
);
$movie_stmt->execute([$movie_id]);
if (!$movie_stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Review not found']);
    exit();
}

$user_id = current_user()['id'];

$existing_stmt = $pdo->prepare(
    'SELECT id FROM dbProj_reviews WHERE movie_id = ? AND user_id = ? AND inactive = FALSE LIMIT 1'
);
$existing_stmt->execute([$movie_id, $user_id]);
$existing = $existing_stmt->fetch();

if ($existing) {
    $update_stmt = $pdo->prepare(
        'UPDATE dbProj_reviews SET rating = ?, modifiedon = NOW(), modifiedby = ? WHERE id = ?'
    );
    $update_stmt->execute([$score, $user_id, $existing['id']]);
} else {
    $insert_stmt = $pdo->prepare(
        'INSERT INTO dbProj_reviews (id, movie_id, user_id, rating, comment, createdby, modifiedby)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $insert_stmt->execute([
        generate_uuid(),
        $movie_id,
        $user_id,
        $score,
        '',
        $user_id,
        $user_id
    ]);
}

$avg_stmt = $pdo->prepare(
    'SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_ratings
     FROM dbProj_reviews WHERE movie_id = ? AND inactive = FALSE'
);
$avg_stmt->execute([$movie_id]);
$stats = $avg_stmt->fetch();

$new_avg = $stats ? (float) $stats['avg_rating'] : 0.0;
$total_ratings = $stats ? (int) $stats['total_ratings'] : 0;

echo json_encode([
    'success' => true,
    'new_avg' => round($new_avg, 1),
    'total_ratings' => $total_ratings
]);
