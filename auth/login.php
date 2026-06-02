<?php
// PURPOSE: Visitor login page for the website.
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db_helpers.php';

if (is_logged_in()) {
    redirect(app_base_url() . '/index.php');
}

$errors    = [];
$old_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $old_email = $email;

    // Server-side validation
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'A valid email address is required.';
    }
    if ($password === '') {
        $errors['password'] = 'Password is required.';
    }

    if (empty($errors)) {
        $user = find_user_by_email($pdo, $email);

        // Always run password_verify to prevent timing-based user enumeration
        $hash_to_check = $user['password_hash'] ?? '$2y$10$invalidhashinvalidhashinvalidha.';
        $password_ok   = password_verify($password, $hash_to_check);

        if (!$user || !$password_ok) {
            $errors['general'] = 'Invalid email or password.';
        } elseif ((bool) $user['inactive']) {
            $errors['general'] = 'This account has been deactivated. Please contact support.';
        } else {
            login_user($user);

            // Role-based redirect
            if (in_array($user['role'], ['Admin', 'Support'], true)) {
                redirect(app_base_url() . '/admin/index.php');
            } elseif ($user['role'] === 'Creator') {
                redirect(app_base_url() . '/creator/index.php');
            } else {
                redirect(app_base_url() . '/index.php');
            }
        }
    }
}
?>

<div class="form-container">
    <h1>Log In</h1>
    <p>Don't have an account? <a href="<?= app_base_url() ?>/auth/signup.php">Sign up here</a>.</p>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-error"><?= sanitize($errors['general']) ?></div>
    <?php endif; ?>

    <form method="POST" id="login-form" novalidate>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                value="<?= sanitize($old_email) ?>"
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
                placeholder="Your password"
                autocomplete="current-password"
            >
            <?php if (isset($errors['password'])): ?>
                <span class="field-error"><?= sanitize($errors['password']) ?></span>
            <?php endif; ?>
        </div>

        <button type="submit">Log In</button>
    </form>
</div>

<script>
(function () {
    const form = document.getElementById('login-form');

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
        const errors  = [];
        const email    = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (email === '') {
            errors.push({ field: 'email', message: 'Email is required.' });
        } else if (!emailRegex.test(email)) {
            errors.push({ field: 'email', message: 'Please enter a valid email address.' });
        }

        if (password === '') {
            errors.push({ field: 'password', message: 'Password is required.' });
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
