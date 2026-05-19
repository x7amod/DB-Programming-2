<?php
// PURPOSE: Core database helper functions.

function find_user_by_email(PDO $pdo, string $email): ?array {
    $stmt = $pdo->prepare('SELECT * FROM dbProj_users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function find_user_by_username(PDO $pdo, string $username): ?array {
    $stmt = $pdo->prepare('SELECT * FROM dbProj_users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function create_user(PDO $pdo, array $data): bool {
    $stmt = $pdo->prepare(
        'INSERT INTO dbProj_users (id, username, email, password_hash, role, createdby)
         VALUES (:id, :username, :email, :password_hash, :role, :createdby)'
    );
    $stmt->execute([
        ':id'            => $data['id'],
        ':username'      => $data['username'],
        ':email'         => $data['email'],
        ':password_hash' => $data['password_hash'],
        ':role'          => $data['role'],
        ':createdby'     => $data['id'],  // same value, different placeholder
    ]);
    return $stmt->rowCount() === 1;
}

function get_all_users(PDO $pdo, array $filters = []): array {
    $sql    = 'SELECT id, username, email, role, inactive, createdon FROM dbProj_users WHERE 1=1';
    $params = [];

    if (!empty($filters['role'])) {
        $sql      .= ' AND role = :role';
        $params[':role'] = $filters['role'];
    }
    if ($filters['status'] !== '' && isset($filters['status'])) {
        $sql      .= ' AND inactive = :inactive';
        $params[':inactive'] = (int) $filters['status'];
    }

    $sql .= ' ORDER BY createdon DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_user_by_id(PDO $pdo, string $id): ?array {
    $stmt = $pdo->prepare('SELECT * FROM dbProj_users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function update_user(PDO $pdo, string $id, array $data, string $by_id): bool {
    $stmt = $pdo->prepare(
        'UPDATE dbProj_users
         SET username = :username, email = :email, role = :role,
             modifiedon = NOW(), modifiedby = :by_id
         WHERE id = :id'
    );
    $stmt->execute([
        ':username' => $data['username'],
        ':email'    => $data['email'],
        ':role'     => $data['role'],
        ':by_id'    => $by_id,
        ':id'       => $id,
    ]);
    return $stmt->rowCount() >= 0;
}

function toggle_user_active(PDO $pdo, string $id, string $by_id): bool {
    $stmt = $pdo->prepare(
        'UPDATE dbProj_users
         SET inactive = NOT inactive, modifiedon = NOW(), modifiedby = :by_id
         WHERE id = :id'
    );
    $stmt->execute([':by_id' => $by_id, ':id' => $id]);
    return $stmt->rowCount() === 1;
}

function get_all_reviews_with_details(PDO $pdo): array {
    $stmt = $pdo->prepare(
        'SELECT r.id, r.rating, r.comment, r.inactive, r.createdon,
                m.title AS movie_title,
                u.username AS reviewer_username
         FROM dbProj_reviews r
         JOIN dbProj_movies m ON r.movie_id = m.id
         JOIN dbProj_users u  ON r.user_id  = u.id
         ORDER BY r.createdon DESC'
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_review_by_id(PDO $pdo, string $id): ?array {
    $stmt = $pdo->prepare(
        'SELECT r.*, m.title AS movie_title, u.username AS reviewer_username
         FROM dbProj_reviews r
         JOIN dbProj_movies m ON r.movie_id = m.id
         JOIN dbProj_users u  ON r.user_id  = u.id
         WHERE r.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $review = $stmt->fetch();
    return $review ?: null;
}

function update_review_comment(PDO $pdo, string $id, string $comment, string $by_id): bool {
    $stmt = $pdo->prepare(
        'UPDATE dbProj_reviews
         SET comment = :comment, modifiedon = NOW(), modifiedby = :by_id
         WHERE id = :id'
    );
    $stmt->execute([':comment' => $comment, ':by_id' => $by_id, ':id' => $id]);
    return $stmt->rowCount() >= 0;
}

function toggle_review_active(PDO $pdo, string $id, string $by_id): bool {
    $stmt = $pdo->prepare(
        'UPDATE dbProj_reviews
         SET inactive = NOT inactive, modifiedon = NOW(), modifiedby = :by_id
         WHERE id = :id'
    );
    $stmt->execute([':by_id' => $by_id, ':id' => $id]);
    return $stmt->rowCount() === 1;
}

function get_stats(PDO $pdo): array {
    $users = $pdo->query('SELECT COUNT(*) FROM dbProj_users WHERE inactive = 0')->fetchColumn();
    $movies = $pdo->query('SELECT COUNT(*) FROM dbProj_movies WHERE inactive = 0')->fetchColumn();
    $reviews = $pdo->query('SELECT COUNT(*) FROM dbProj_reviews WHERE inactive = 0')->fetchColumn();
    $recent = $pdo->query(
        "SELECT COUNT(*) FROM dbProj_status_log
         WHERE createdon >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    )->fetchColumn();

    return [
        'total_users'          => (int) $users,
        'total_movies'         => (int) $movies,
        'total_reviews'        => (int) $reviews,
        'recent_deactivations' => (int) $recent,
    ];
}
