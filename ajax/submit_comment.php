<?php
// PURPOSE: AJAX endpoint for submitting movie comments and ratings.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_helpers.php';

$acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
$wantsJson = stripos($acceptHeader, 'application/json') !== false;

function comment_json_response(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function comment_redirect_response(string $movieId, string $type, string $message): void {
    set_flash($type, $message);
    redirect(app_base_url() . '/review.php?id=' . urlencode($movieId));
}

function comment_length(string $text): int {
    if (function_exists('mb_strlen')) {
        return mb_strlen($text, 'UTF-8');
    }
    return strlen($text);
}

if ($wantsJson) {
    ini_set('display_errors', '0');
    header('Content-Type: application/json; charset=UTF-8');

    register_shutdown_function(static function () use ($wantsJson): void {
        if (!$wantsJson) {
            return;
        }

        $error = error_get_last();
        if (!$error) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($error['type'], $fatalTypes, true)) {
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Server error while saving review.',
            'error' => $error['message'],
        ]);
    });
}
try {
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

    $sessionUser = get_user_by_id($pdo, (string) $user['id']);
    if (!$sessionUser || (int) ($sessionUser['inactive'] ?? 0) === 1) {
        logout_user();
        if ($wantsJson) {
            comment_json_response([
                'success' => false,
                'message' => 'Your session is no longer valid. Please log in again.'
            ], 401);
        }
        set_flash('error', 'Your session is no longer valid. Please log in again.');
        redirect(app_base_url() . '/auth/login.php');
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

    if (comment_length($comment) > 2000) {
        if ($wantsJson) {
            comment_json_response(['success' => false, 'message' => 'Comment must be 2000 characters or fewer.'], 422);
        }
        comment_redirect_response($movieId, 'error', 'Comment must be 2000 characters or fewer.');
    }

    if (!save_movie_review($pdo, $movieId, $sessionUser['id'], $rating, $comment, $sessionUser['id'])) {
        if ($wantsJson) {
            comment_json_response(['success' => false, 'message' => 'Unable to save your review.'], 500);
        }
        comment_redirect_response($movieId, 'error', 'Unable to save your review.');
    }

    $savedReview = get_user_movie_review($pdo, $movieId, $sessionUser['id']);

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
            'username' => $sessionUser['username'],
        ],
    ]);
} catch (Throwable $e) {
    if ($wantsJson) {
        comment_json_response([
            'success' => false,
            'message' => 'Server error while saving review.',
            'error' => $e->getMessage(),
        ], 500);
    }

    comment_redirect_response('', 'error', 'Server error while saving review.');
}
