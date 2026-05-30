<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['Creator']);

$root_url = '/DB-Programming-2';
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
    $insert = $pdo->prepare(
        'INSERT INTO dbProj_categories (id, name, createdby) VALUES (?, ?, ?)'
    );
    $insert->execute([$new_id, $name, $user_id]);
    return $new_id;
}

function build_description(string $release_year, string $description): string {
    return '[Release Year: ' . $release_year . "]\n" . $description;
}

$action = $_POST['action'] ?? 'add';
$title = trim($_POST['title'] ?? '');
$genre = trim($_POST['genre'] ?? '');
$release_year = trim($_POST['release_year'] ?? '');
$description = trim($_POST['description'] ?? '');
$status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
$current_year = (int) date('Y');

if ($title === '' || $description === '') {
    $target = $action === 'edit'
        ? $root_url . '/creator/edit_review.php?id=' . urlencode($_POST['movie_id'] ?? '')
        : $root_url . '/creator/add_review.php';
    redirect_with_error($target, 'Title and description are required.');
}

if (strlen($description) < 50) {
    $target = $action === 'edit'
        ? $root_url . '/creator/edit_review.php?id=' . urlencode($_POST['movie_id'] ?? '')
        : $root_url . '/creator/add_review.php';
    redirect_with_error($target, 'Description must be at least 50 characters.');
}

if ($release_year === '' || !is_numeric($release_year)) {
    $target = $action === 'edit'
        ? $root_url . '/creator/edit_review.php?id=' . urlencode($_POST['movie_id'] ?? '')
        : $root_url . '/creator/add_review.php';
    redirect_with_error($target, 'Release year is required.');
}

$release_year_int = (int) $release_year;
if ($release_year_int < 1888 || $release_year_int > $current_year) {
    $target = $action === 'edit'
        ? $root_url . '/creator/edit_review.php?id=' . urlencode($_POST['movie_id'] ?? '')
        : $root_url . '/creator/add_review.php';
    redirect_with_error($target, 'Release year is out of range.');
}

$category_id = get_or_create_category($pdo, $genre, $user_id);
if ($category_id === null) {
    $target = $action === 'edit'
        ? $root_url . '/creator/edit_review.php?id=' . urlencode($_POST['movie_id'] ?? '')
        : $root_url . '/creator/add_review.php';
    redirect_with_error($target, 'Genre is required.');
}

$movie_id = null;
$image_url = null;
$media_url = null;
$existing = null;

if ($action === 'edit') {
    $movie_id = trim($_POST['movie_id'] ?? '');
    if ($movie_id === '') {
        redirect($root_url . '/creator/index.php?msg=invalid');
    }

    $stmt = $pdo->prepare(
        'SELECT id, is_published, image_url, media_url
         FROM dbProj_movies
         WHERE id = ? AND creator_id = ? AND inactive = FALSE'
    );
    $stmt->execute([$movie_id, $user_id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        redirect($root_url . '/creator/index.php?msg=unauthorized');
    }

    if ((bool) $existing['is_published']) {
        redirect_with_error($root_url . '/creator/edit_review.php?id=' . urlencode($movie_id), 'Published reviews cannot be edited.');
    }

    $image_url = $existing['image_url'] ?? null;
    $media_url = $existing['media_url'] ?? null;
}

$poster_file = $_FILES['poster'] ?? null;
$trailer_file = $_FILES['trailer'] ?? null;

if ($action === 'add' && (!$poster_file || $poster_file['error'] === UPLOAD_ERR_NO_FILE)) {
    redirect_with_error($root_url . '/creator/add_review.php', 'Poster image is required.');
}

$poster_upload_dir = __DIR__ . '/../uploads/posters';
$trailer_upload_dir = __DIR__ . '/../uploads/media';

if ($poster_file && $poster_file['error'] !== UPLOAD_ERR_NO_FILE) {
    $poster_result = handle_upload(
        $poster_file,
        ['jpg', 'jpeg', 'png', 'webp'],
        ['image/jpeg', 'image/png', 'image/webp'],
        5 * 1024 * 1024,
        $poster_upload_dir
    );

    if (!$poster_result['ok']) {
        $target = $action === 'edit'
            ? $root_url . '/creator/edit_review.php?id=' . urlencode($movie_id)
            : $root_url . '/creator/add_review.php';
        redirect_with_error($target, $poster_result['message']);
    }

    $image_url = $root_url . '/uploads/posters/' . $poster_result['filename'];
}

if ($trailer_file && $trailer_file['error'] !== UPLOAD_ERR_NO_FILE) {
    $trailer_result = handle_upload(
        $trailer_file,
        ['mp4', 'webm'],
        ['video/mp4', 'video/webm'],
        50 * 1024 * 1024,
        $trailer_upload_dir
    );

    if (!$trailer_result['ok']) {
        $target = $action === 'edit'
            ? $root_url . '/creator/edit_review.php?id=' . urlencode($movie_id)
            : $root_url . '/creator/add_review.php';
        redirect_with_error($target, $trailer_result['message']);
    }

    $media_url = $root_url . '/uploads/media/' . $trailer_result['filename'];
}

$stored_description = build_description($release_year, $description);
$is_published = $status === 'published';

if ($action === 'add') {
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
        $stored_description,
        $image_url,
        $media_url,
        $category_id,
        $user_id,
        $is_published,
        $user_id,
        $user_id
    ]);

    redirect($root_url . '/creator/index.php?msg=saved');
}

$fields = [
    'title = ?',
    'description = ?',
    'category_id = ?',
    'is_published = ?',
    'modifiedon = NOW()',
    'modifiedby = ?'
];
$params = [$title, $stored_description, $category_id, $is_published, $user_id];

if ($image_url !== null) {
    $fields[] = 'image_url = ?';
    $params[] = $image_url;
}

if ($media_url !== null) {
    $fields[] = 'media_url = ?';
    $params[] = $media_url;
}

$params[] = $movie_id;
$params[] = $user_id;

$sql = 'UPDATE dbProj_movies SET ' . implode(', ', $fields) . ' WHERE id = ? AND creator_id = ?';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

redirect($root_url . '/creator/index.php?msg=saved');
