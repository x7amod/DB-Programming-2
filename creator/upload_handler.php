<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['Creator']);

$root_url = app_base_url();
$current_user = current_user();
$user_id = $current_user['id'];

function redirect_with_error(string $url, string $message): void {
    redirect($url . '?error=' . urlencode($message));
}

function ensure_dir(string $path): void {
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

function handle_upload(array $file, array $allowed_exts, array $allowed_mimes, int $max_size, string $dest_dir): array {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Upload failed.'];
    }

    if ($file['size'] > $max_size) {
        return ['ok' => false, 'message' => 'Uploaded file is too large.'];
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_exts, true)) {
        return ['ok' => false, 'message' => 'Invalid file type.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if ($mime === false || !in_array($mime, $allowed_mimes, true)) {
        return ['ok' => false, 'message' => 'Invalid file type.'];
    }

    ensure_dir($dest_dir);
    $filename = uniqid('media_', true) . '_' . time() . '.' . $extension;
    $target_path = rtrim($dest_dir, '/\\') . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        return ['ok' => false, 'message' => 'Could not save uploaded file.'];
    }

    return ['ok' => true, 'filename' => $filename];
}

function get_or_create_category(PDO $pdo, string $name, string $user_id): ?string {
    if ($name === '') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id FROM dbProj_categories WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $existing = $stmt->fetch();
    if ($existing) {
        return $existing['id'];
    }

    $new_id = generate_uuid();
    $insert = $pdo->prepare('INSERT INTO dbProj_categories (id, name, createdby) VALUES (?, ?, ?)');
    $insert->execute([$new_id, $name, $user_id]);
    return $new_id;
}

$poster_upload_dir = __DIR__ . '/../uploads/posters';
$trailer_upload_dir = __DIR__ . '/../uploads/media';

$action = $_POST['action'] ?? '';
if ($action === 'add') {
    $action = 'add_movie';
}
if ($action === 'edit') {
    $action = 'edit_review';
}

$status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
$is_published = $status === 'published';

if ($action === 'add_movie') {
    $title = trim($_POST['title'] ?? '');
    $description_body = trim($_POST['description'] ?? '');
    $genre = trim($_POST['genre'] ?? '');
    $poster_file = $_FILES['poster'] ?? null;
    $trailer_file = $_FILES['trailer'] ?? null;

    if ($title === '') {
        redirect_with_error($root_url . '/creator/add_movie.php', 'Title is required.');
    }

    if ($description_body === '') {
        redirect_with_error($root_url . '/creator/add_movie.php', 'Description is required.');
    }

    $category_id = get_or_create_category($pdo, $genre, $user_id);
    if ($category_id === null) {
        redirect_with_error($root_url . '/creator/add_movie.php', 'Genre is required.');
    }

    if (!$poster_file || $poster_file['error'] === UPLOAD_ERR_NO_FILE) {
        redirect_with_error($root_url . '/creator/add_movie.php', 'Poster image is required.');
    }

    $poster_result = handle_upload(
        $poster_file,
        ['jpg', 'jpeg', 'png', 'webp'],
        ['image/jpeg', 'image/png', 'image/webp'],
        5 * 1024 * 1024,
        $poster_upload_dir
    );
    if (!$poster_result['ok']) {
        redirect_with_error($root_url . '/creator/add_movie.php', $poster_result['message']);
    }

    $image_url = project_path() . '/uploads/posters/' . $poster_result['filename'];
    $media_url = null;
    if ($trailer_file && $trailer_file['error'] !== UPLOAD_ERR_NO_FILE) {
        $trailer_result = handle_upload(
            $trailer_file,
            ['mp4', 'webm'],
            ['video/mp4', 'video/webm'],
            50 * 1024 * 1024,
            $trailer_upload_dir
        );
        if (!$trailer_result['ok']) {
            redirect_with_error($root_url . '/creator/add_movie.php', $trailer_result['message']);
        }
        $media_url = project_path() . '/uploads/media/' . $trailer_result['filename'];
    }

    $new_id = generate_uuid();
    $stmt = $pdo->prepare(
        'INSERT INTO dbProj_movies
            (id, title, description, image_url, media_url, category_id, creator_id, is_published, view_count, createdby, modifiedby)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)'
    );
    $stmt->execute([
        $new_id,
        $title,
        $description_body,
        $image_url,
        $media_url,
        $category_id,
        $user_id,
        $is_published,
        $user_id,
        $user_id
    ]);

    redirect($root_url . '/creator/index.php?msg=movie_saved');
}

if ($action === 'add_review' || $action === 'edit_review') {
    $movie_id = trim($_POST['movie_id'] ?? '');
    $rating = (int) ($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($movie_id === '') {
        redirect_with_error($root_url . '/creator/add_review.php', 'Movie is required.');
    }
    if ($rating < 1 || $rating > 5) {
        $target = $action === 'edit_review'
            ? $root_url . '/creator/edit_review.php?id=' . urlencode($movie_id)
            : $root_url . '/creator/add_review.php?movie_id=' . urlencode($movie_id);
        redirect_with_error($target, 'Rating must be between 1 and 5.');
    }
    if (strlen($comment) < 10) {
        $target = $action === 'edit_review'
            ? $root_url . '/creator/edit_review.php?id=' . urlencode($movie_id)
            : $root_url . '/creator/add_review.php?movie_id=' . urlencode($movie_id);
        redirect_with_error($target, 'Review must be at least 10 characters.');
    }
    if (strlen($comment) > 2000) {
        $target = $action === 'edit_review'
            ? $root_url . '/creator/edit_review.php?id=' . urlencode($movie_id)
            : $root_url . '/creator/add_review.php?movie_id=' . urlencode($movie_id);
        redirect_with_error($target, 'Review must be 2000 characters or fewer.');
    }

    $movie_stmt = $pdo->prepare('SELECT id FROM dbProj_movies WHERE id = ? AND inactive = FALSE');
    $movie_stmt->execute([$movie_id]);
    if (!$movie_stmt->fetch()) {
        redirect_with_error($root_url . '/creator/add_review.php', 'Movie not found.');
    }

    $existing_stmt = $pdo->prepare(
        'SELECT id
         FROM dbProj_reviews
         WHERE movie_id = ? AND user_id = ? AND inactive = FALSE
         LIMIT 1'
    );
    $existing_stmt->execute([$movie_id, $user_id]);
    $existing_review = $existing_stmt->fetch();

    if ($action === 'add_review' && $existing_review) {
        redirect_with_error($root_url . '/creator/add_review.php', 'You already reviewed this movie.');
    }

    if ($action === 'edit_review' && !$existing_review) {
        redirect_with_error($root_url . '/creator/add_review.php?movie_id=' . urlencode($movie_id), 'No existing review found for this movie.');
    }

    if ($existing_review) {
        $stmt = $pdo->prepare(
            'UPDATE dbProj_reviews
             SET rating = ?, comment = ?, modifiedon = NOW(), modifiedby = ?
             WHERE id = ?'
        );
        $stmt->execute([$rating, $comment, $user_id, $existing_review['id']]);
    } else {
        $review_id = generate_uuid();
        $stmt = $pdo->prepare(
            'INSERT INTO dbProj_reviews
                (id, movie_id, user_id, rating, comment, inactive, createdby, modifiedby)
             VALUES
                (?, ?, ?, ?, ?, FALSE, ?, ?)'
        );
        $stmt->execute([$review_id, $movie_id, $user_id, $rating, $comment, $user_id, $user_id]);
    }

    redirect($root_url . '/creator/index.php?msg=review_saved');
}

redirect($root_url . '/creator/index.php?msg=invalid');
