<?php
// PURPOSE: Visitor signup page for the website.
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../src/db_helpers.php';

if (is_logged_in()) {
    redirect('/index.php');
}

$errors = [];
$old    = ['username' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username         = trim($_POST['username'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $old = ['username' => $username, 'email' => $email];

    // Server-side validation
    if ($username === '') {
        $errors['username'] = 'Username is required.';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $errors['username'] = 'Username must be between 3 and 50 characters.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'A valid email address is required.';
    }

    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }

    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // DB uniqueness checks (only when format is valid)
    if (empty($errors)) {
        if (find_user_by_email($pdo, $email)) {
            $errors['email'] = 'This email is already registered.';
        }
        if (find_user_by_username($pdo, $username)) {
            $errors['username'] = 'This username is already taken.';
        }
    }

    if (empty($errors)) {
        $new_user = [
            'id'            => generate_uuid(),
            'username'      => $username,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role'          => 'Viewer',
        ];

        if (create_user($pdo, $new_user)) {
            set_flash('success', 'Account created successfully! Please log in.');
            redirect('/auth/login.php');
        } else {
            $errors['general'] = 'Registration failed. Please try again.';
        }
    }
}
?>

<div class="form-container">
    <h1>Create an Account</h1>
    <p>Already have an account? <a href="/auth/login.php">Log in here</a>.</p>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-error"><?= sanitize($errors['general']) ?></div>
    <?php endif; ?>

    <form method="POST" id="signup-form" novalidate>

        <div class="form-group">
            <label for="username">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                value="<?= sanitize($old['username']) ?>"
                class="<?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                placeholder="Enter your username"
                autocomplete="username"
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
                value="<?= sanitize($old['email']) ?>"
                class="<?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                placeholder="you@example.com"
                autocomplete="email"
            >
            <?php if (isset($errors['email'])): ?>
                <span class="field-error"><?= sanitize($errors['email']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                class="<?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                placeholder="Minimum 8 characters"
                autocomplete="new-password"
            >
            <?php if (isset($errors['password'])): ?>
                <span class="field-error"><?= sanitize($errors['password']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                class="<?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                placeholder="Re-enter your password"
                autocomplete="new-password"
            >
            <?php if (isset($errors['confirm_password'])): ?>
                <span class="field-error"><?= sanitize($errors['confirm_password']) ?></span>
            <?php endif; ?>
        </div>

        <button type="submit">Sign Up</button>
    </form>
</div>

<script>
(function () {
    const form = document.getElementById('signup-form');

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
        const errors = [];
        const username = document.getElementById('username').value.trim();
        const email    = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const confirm  = document.getElementById('confirm_password').value;

        if (username === '') {
            errors.push({ field: 'username', message: 'Username is required.' });
        } else if (username.length < 3 || username.length > 50) {
            errors.push({ field: 'username', message: 'Username must be between 3 and 50 characters.' });
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (email === '') {
            errors.push({ field: 'email', message: 'Email is required.' });
        } else if (!emailRegex.test(email)) {
            errors.push({ field: 'email', message: 'Please enter a valid email address.' });
        }

        if (password.length < 8) {
            errors.push({ field: 'password', message: 'Password must be at least 8 characters.' });
        }

        if (password !== confirm) {
            errors.push({ field: 'confirm_password', message: 'Passwords do not match.' });
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
