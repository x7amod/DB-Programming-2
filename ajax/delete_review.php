<?php
// PURPOSE: AJAX endpoint to let a user soft-delete their own review (sets inactive = TRUE).
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/db_helpers.php';
require_once __DIR__ . '/../includes/auth.php';

$current = current_user();
if (!$current) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$review_id = trim($_POST['review_id'] ?? '');
if ($review_id === '') {
    echo json_encode(['success' => false, 'message' => 'Missing review id.']);
    exit;
}

$review = get_review_by_id($pdo, $review_id);
if (!$review) {
    echo json_encode(['success' => false, 'message' => 'Review not found.']);
    exit;
}

if ($review['user_id'] !== $current['id']) {
    echo json_encode(['success' => false, 'message' => 'Not allowed.']);
    exit;
}

$ok = set_review_inactive($pdo, $review_id, $current['id']);
if ($ok) {
    echo json_encode(['success' => true, 'message' => 'Review deleted.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not delete review.']);
}
