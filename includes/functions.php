<?php
// PURPOSE: Shared utility functions for the website.

function generate_uuid(): string {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function redirect(string $url): void {
    header("Location: $url");
    exit();
}

function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function app_base_url(): string {
    static $base_url = null;

    if ($base_url !== null) {
        return $base_url;
    }

    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $project_path = '/DB-Programming-2';
    $project_position = strpos($script_name, $project_path);

    if ($project_position === false) {
        $base_url = $project_path;
        return $base_url;
    }

    $base_url = substr($script_name, 0, $project_position + strlen($project_path));
    return $base_url;
}

function project_path(): string {
    $base_url = app_base_url();
    $project_path = '/DB-Programming-2';
    $project_position = strpos($base_url, $project_path);

    if ($project_position === false) {
        return $project_path;
    }

    return substr($base_url, $project_position);
}

function normalize_app_url(?string $path): string {
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $base_url = rtrim(app_base_url(), '/');
    if ($path === $base_url || strpos($path, $base_url . '/') === 0) {
        return $path;
    }

    $project_path = project_path();
    $project_position = strpos($base_url, $project_path);
    $host_prefix = $project_position === false ? '' : substr($base_url, 0, $project_position);

    if ($path === $project_path || strpos($path, $project_path . '/') === 0) {
        return $host_prefix . $path;
    }

    if ($path[0] === '/') {
        return $base_url . $path;
    }

    return $base_url . '/' . $path;
}

function movie_poster_url(?string $image_path, string $default_path = '/assets/images/placeholder.png'): string {
    if ($image_path === null || trim($image_path) === '') {
        return normalize_app_url($default_path);
    }

    return normalize_app_url($image_path);
}
