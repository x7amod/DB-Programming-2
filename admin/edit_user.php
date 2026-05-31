<?php
// PURPOSE: Admin form for editing a user's details (username, email, role).
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db_helpers.php';

require_role(['Admin']);

$root_url = '/DB-Programming-2'; // add app base URL

$user_id = $_GET['id'] ?? ($_POST['user_id'] ?? '');

if ($user_id === '') {
    set_flash('error', 'No user specified.');
    redirect($root_url . '/admin/manage_users.php');
}

$target_user = get_user_by_id($pdo, $user_id);

if (!$target_user) {
    set_flash('error', 'User not found.');
    redirect($root_url . '/admin/manage_users.php');
}

$allowed_roles = ['Viewer', 'Creator', 'Admin'];
// Add 'Support' to this array after running sql/03_add_support_role.sql

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $role     = $_POST['role']          ?? '';
    $by_id    = current_user()['id'];

    // Server-side validation
    if ($username === '') {
        $errors['username'] = 'Username is required.';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $errors['username'] = 'Username must be between 3 and 50 characters.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'A valid email address is required.';
    }

    if (!in_array($role, $allowed_roles, true)) {
        $errors['role'] = 'Invalid role selected.';
    }

    // Uniqueness checks only when value changed
    if (empty($errors)) {
        if ($username !== $target_user['username'] && find_user_by_username($pdo, $username)) {
            $errors['username'] = 'This username is already taken.';
        }
        if ($email !== $target_user['email'] && find_user_by_email($pdo, $email)) {
            $errors['email'] = 'This email is already in use.';
        }
    }

    if (empty($errors)) {
        update_user($pdo, $user_id, compact('username', 'email', 'role'), $by_id);
        set_flash('success', 'User updated successfully.');
        redirect($root_url . '/admin/manage_users.php');
    }

    // Re-populate from POST if there were errors
    $target_user['username'] = $username;
    $target_user['email']    = $email;
    $target_user['role']     = $role;
}
?>

<div class="form-container form-container-wide">
    <div class="admin-section-header">
        <h1>Edit User</h1>
        <a href="<?= $root_url ?>/admin/manage_users.php">&larr; Back to Users</a>
    </div>

    <form method="POST" id="edit-user-form" novalidate>
        <input type="hidden" name="user_id" value="<?= sanitize($user_id) ?>">

        <div class="form-group">
            <label for="username">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                value="<?= sanitize($target_user['username']) ?>"
                class="<?= isset($errors['username']) ? 'is-invalid' : '' ?>"
            >
            <?php if (isset($errors['username'])): ?>
                <span class="field-error"><?= sanitize($errors['username']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                value="<?= sanitize($target_user['email']) ?>"
                class="<?= isset($errors['email']) ? 'is-invalid' : '' ?>"
            >
            <?php if (isset($errors['email'])): ?>
                <span class="field-error"><?= sanitize($errors['email']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="role">Role</label>
            <select id="role" name="role" class="<?= isset($errors['role']) ? 'is-invalid' : '' ?>">
                <?php foreach ($allowed_roles as $r): ?>
                    <option value="<?= $r ?>" <?= ($target_user['role'] === $r) ? 'selected' : '' ?>>
                        <?= $r ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['role'])): ?>
                <span class="field-error"><?= sanitize($errors['role']) ?></span>
            <?php endif; ?>
        </div>

        <button type="submit">Save Changes</button>
        <a href="<?= $root_url ?>/admin/manage_users.php" class="btn-link">Cancel</a>
    </form>
</div>

<script>
(function () {
    const form = document.getElementById('edit-user-form');

    form.addEventListener('submit', function (e) {
        clearErrors();
        const errors = validate();
        if (errors.length > 0) {
            e.preventDefault();
            errors.forEach(function (err) {
                showError(err.field, err.message);
            });
        }
    });

    function validate() {
        const errors   = [];
        const username = document.getElementById('username').value.trim();
        const email    = document.getElementById('email').value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (username === '') {
            errors.push({ field: 'username', message: 'Username is required.' });
        } else if (username.length < 3 || username.length > 50) {
            errors.push({ field: 'username', message: 'Username must be 3–50 characters.' });
        }

        if (email === '') {
            errors.push({ field: 'email', message: 'Email is required.' });
        } else if (!emailRegex.test(email)) {
            errors.push({ field: 'email', message: 'Please enter a valid email address.' });
        }

        return errors;
    }

    function showError(fieldId, message) {
        const input = document.getElementById(fieldId);
        input.classList.add('is-invalid');
        const span = document.createElement('span');
        span.className = 'field-error js-error';
        span.textContent = message;
        input.parentNode.appendChild(span);
    }

    function clearErrors() {
        document.querySelectorAll('.js-error').forEach(function (el) { el.remove(); });
        document.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
    }
}());
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
