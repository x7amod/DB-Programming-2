<?php
// PURPOSE: AJAX endpoint for submitting movie comments and ratings.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../src/db_helpers.php';

$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

if ($wantsJson) {
    header('Content-Type: application/json; charset=UTF-8');
}

function comment_json_response(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function comment_redirect_response(string $movieId, string $type, string $message): void {
    set_flash($type, $message);
    redirect('/DB-Programming-2/public/review.php?id=' . urlencode($movieId));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($wantsJson) {
        comment_json_response(['success' => false, 'message' => 'Invalid request method.'], 405);
    }
    comment_redirect_response('', 'error', 'Invalid request method.');
}

$user = current_user();
if (!$user) {
    if ($wantsJson) {
        comment_json_response(['success' => false, 'message' => 'You must be logged in to comment.'], 401);
    }
    comment_redirect_response('', 'error', 'You must be logged in to comment.');
}

$movieId = trim($_POST['movie_id'] ?? '');
$rating = (int) ($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if ($movieId === '') {
    if ($wantsJson) {
        comment_json_response(['success' => false, 'message' => 'No movie selected.'], 422);
    }
    comment_redirect_response('', 'error', 'No movie selected.');
}

$movie = get_movie_by_id($pdo, $movieId);
if (!$movie || (int) $movie['inactive'] === 1 || (int) $movie['is_published'] !== 1) {
    if ($wantsJson) {
        comment_json_response(['success' => false, 'message' => 'Movie not found.'], 404);
    }
    comment_redirect_response($movieId, 'error', 'Movie not found.');
}

if ($rating < 1 || $rating > 5) {
    if ($wantsJson) {
        comment_json_response(['success' => false, 'message' => 'Please choose a rating between 1 and 5.'], 422);
    }
    comment_redirect_response($movieId, 'error', 'Please choose a rating between 1 and 5.');
}

if (mb_strlen($comment) > 2000) {
    if ($wantsJson) {
        comment_json_response(['success' => false, 'message' => 'Comment must be 2000 characters or fewer.'], 422);
    }
    comment_redirect_response($movieId, 'error', 'Comment must be 2000 characters or fewer.');
}

if (!save_movie_review($pdo, $movieId, $user['id'], $rating, $comment, $user['id'])) {
    if ($wantsJson) {
        comment_json_response(['success' => false, 'message' => 'Unable to save your review.'], 500);
    }
    comment_redirect_response($movieId, 'error', 'Unable to save your review.');
}

$savedReview = get_user_movie_review($pdo, $movieId, $user['id']);

if (!$wantsJson) {
    comment_redirect_response($movieId, 'success', 'Your review has been saved.');
}

comment_json_response([
    'success' => true,
    'message' => 'Your review has been saved.',
    'review' => [
        'id' => $savedReview['id'] ?? null,
        'rating' => (int) ($savedReview['rating'] ?? $rating),
        'comment' => $savedReview['comment'] ?? $comment,
        'createdon' => $savedReview['createdon'] ?? date('Y-m-d H:i:s'),
        'username' => $user['username'],
    ],
]);
