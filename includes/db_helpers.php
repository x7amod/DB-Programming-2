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
    $current = $pdo->prepare('SELECT username, inactive FROM dbProj_users WHERE id = ? LIMIT 1');
    $current->execute([$id]);
    $user = $current->fetch();

    if (!$user) {
        return false;
    }

    $stmt = $pdo->prepare(
        'UPDATE dbProj_users
         SET inactive = NOT inactive, modifiedon = NOW(), modifiedby = :by_id
         WHERE id = :id'
    );
    $stmt->execute([':by_id' => $by_id, ':id' => $id]);

    if ((int) $user['inactive'] === 0) {
        $log = $pdo->prepare(
            'INSERT INTO dbProj_status_log
                (id, content_id, content_type, message, createdon, createdby)
             VALUES
                (UUID(), :content_id, :content_type, :message, NOW(), :createdby)'
        );
        $log->execute([
            ':content_id'   => $id,
            ':content_type' => 'USER',
            ':message'      => 'System Message: The user "' . $user['username'] . '" has been deactivated by an administrator.',
            ':createdby'    => $by_id,
        ]);
    }

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

function get_movie_by_id(PDO $pdo, string $id): ?array {
    $stmt = $pdo->prepare(
        'SELECT m.*, c.name AS category_name, u.username AS creator_name
         FROM dbProj_movies m
         LEFT JOIN dbProj_categories c ON m.category_id = c.id
         LEFT JOIN dbProj_users u ON m.creator_id = u.id
         WHERE m.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $movie = $stmt->fetch();
    return $movie ?: null;
}

function increment_movie_view_count(PDO $pdo, string $id): void {
    $stmt = $pdo->prepare(
        'UPDATE dbProj_movies
         SET view_count = view_count + 1, modifiedon = NOW()
         WHERE id = :id AND inactive = FALSE AND is_published = TRUE'
    );
    $stmt->execute([':id' => $id]);
}

function get_movie_reviews(PDO $pdo, string $movieId): array {
    $stmt = $pdo->prepare(
        'SELECT r.id, r.rating, r.comment, r.createdon, r.modifiedon, r.inactive,
                u.username AS reviewer_username
         FROM dbProj_reviews r
         JOIN dbProj_users u ON r.user_id = u.id
         WHERE r.movie_id = :movie_id
           AND r.inactive = FALSE
           AND u.inactive = FALSE
         ORDER BY r.createdon DESC'
    );
    $stmt->execute([':movie_id' => $movieId]);
    return $stmt->fetchAll();
}

function get_user_movie_review(PDO $pdo, string $movieId, string $userId): ?array {
    $stmt = $pdo->prepare(
        'SELECT id, movie_id, user_id, rating, comment, inactive, createdon, modifiedon
         FROM dbProj_reviews
         WHERE movie_id = :movie_id AND user_id = :user_id AND inactive = FALSE
         LIMIT 1'
    );
    $stmt->execute([
        ':movie_id' => $movieId,
        ':user_id'  => $userId,
    ]);
    $review = $stmt->fetch();
    return $review ?: null;
}

function save_movie_review(PDO $pdo, string $movieId, string $userId, int $rating, string $comment, string $byId): bool {
    $existing = get_user_movie_review($pdo, $movieId, $userId);

    if ($existing) {
        $stmt = $pdo->prepare(
            'UPDATE dbProj_reviews
             SET rating = :rating,
                 comment = :comment,
                 inactive = FALSE,
                 modifiedon = NOW(),
                 modifiedby = :by_id
             WHERE id = :id' 
        );
        $stmt->execute([
            ':rating'  => $rating,
            ':comment' => $comment,
            ':by_id'   => $byId,
            ':id'      => $existing['id'],
        ]);
        return $stmt->rowCount() >= 0;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO dbProj_reviews
            (id, movie_id, user_id, rating, comment, inactive, createdby, modifiedby)
         VALUES
            (:id, :movie_id, :user_id, :rating, :comment, FALSE, :createdby, :modifiedby)'
    );
    $stmt->execute([
        ':id'         => generate_uuid(),
        ':movie_id'   => $movieId,
        ':user_id'    => $userId,
        ':rating'     => $rating,
        ':comment'    => $comment,
        ':createdby'  => $byId,
        ':modifiedby' => $byId,
    ]);
    return $stmt->rowCount() === 1;
}

function get_popular_movies_report(PDO $pdo, string $startDate, string $endDate): array {
    $stmt = $pdo->prepare(
        'SELECT m.id, m.title, m.createdon, m.view_count,
                COALESCE(COUNT(r.id), 0) AS review_count,
                COALESCE(ROUND(AVG(r.rating), 2), 0) AS avg_rating,
                u.username AS creator_name
         FROM dbProj_movies m
         LEFT JOIN dbProj_reviews r ON m.id = r.movie_id AND r.inactive = FALSE
         LEFT JOIN dbProj_users u ON m.creator_id = u.id
         WHERE m.inactive = FALSE
           AND DATE(m.createdon) BETWEEN :start_date AND :end_date
         GROUP BY m.id, m.title, m.createdon, m.view_count, u.username
         ORDER BY m.view_count DESC, m.createdon DESC'
    );
    $stmt->execute([
        ':start_date' => $startDate,
        ':end_date'   => $endDate,
    ]);
    return $stmt->fetchAll();
}

function get_content_by_user_report(PDO $pdo, string $userId): array {
    $stmt = $pdo->prepare(
        'SELECT m.id, m.title, m.createdon, m.view_count, m.is_published,
                c.name AS category_name,
                u.username AS creator_name
         FROM dbProj_movies m
         LEFT JOIN dbProj_categories c ON m.category_id = c.id
         LEFT JOIN dbProj_users u ON m.creator_id = u.id
         WHERE m.creator_id = :user_id
           AND m.inactive = FALSE
         ORDER BY m.createdon DESC'
    );
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll();
}

function get_active_users_for_reports(PDO $pdo): array {
    $stmt = $pdo->query(
        "SELECT id, username, role
         FROM dbProj_users
         WHERE inactive = FALSE
         ORDER BY username ASC"
    );
    return $stmt->fetchAll();
}

function search_movies_live(PDO $pdo, string $term, int $limit = 8): array {
    $term = trim($term);
    if ($term === '') {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT m.id, m.title, m.description, m.image_url, m.createdon, m.view_count,
                c.name AS category_name,
                u.username AS creator_name
         FROM dbProj_movies m
         LEFT JOIN dbProj_categories c ON m.category_id = c.id
         LEFT JOIN dbProj_users u ON m.creator_id = u.id
         WHERE m.is_published = TRUE
           AND m.inactive = FALSE
           AND (
                MATCH(m.title, m.description) AGAINST(:search_term IN NATURAL LANGUAGE MODE)
                OR m.title LIKE :title_like
                OR m.description LIKE :description_like
           )
         ORDER BY m.createdon DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':search_term', $term, PDO::PARAM_STR);
    $stmt->bindValue(':title_like', '%' . $term . '%', PDO::PARAM_STR);
    $stmt->bindValue(':description_like', '%' . $term . '%', PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
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
