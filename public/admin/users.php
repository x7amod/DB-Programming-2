<?php
// PURPOSE: Admin user management — view, filter, deactivate/reactivate users.
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../src/db_helpers.php';

require_role(['Admin']);

// Handle POST actions (PRG pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $user_id = $_POST['user_id'] ?? '';
    $by_id   = current_user()['id'];

    if ($action === 'toggle_active' && $user_id !== '') {
        // Prevent admin from deactivating their own account
        if ($user_id === $by_id) {
            set_flash('error', 'You cannot deactivate your own account.');
        } else {
            toggle_user_active($pdo, $user_id, $by_id);
            set_flash('success', 'User status updated.');
        }
    }
    redirect('/admin/users.php');
}

// Filters from GET
$filters = [
    'role'   => $_GET['role']   ?? '',
    'status' => $_GET['status'] ?? '',
];

$users         = get_all_users($pdo, $filters);
$allowed_roles = ['', 'Viewer', 'Creator', 'Admin', 'Support'];
?>

<div class="admin-section">
    <div class="admin-section-header">
        <h1>Manage Users</h1>
        <a href="/admin/index.php">&larr; Back to Dashboard</a>
    </div>

    <!-- Filter Form -->
    <form method="GET" class="filter-form">
        <label for="role-filter">Role:</label>
        <select id="role-filter" name="role" onchange="this.form.submit()">
            <option value="">All Roles</option>
            <?php foreach (['Viewer', 'Creator', 'Admin', 'Support'] as $r): ?>
                <option value="<?= $r ?>" <?= ($filters['role'] === $r) ? 'selected' : '' ?>><?= $r ?></option>
            <?php endforeach; ?>
        </select>

        <label for="status-filter">Status:</label>
        <select id="status-filter" name="status" onchange="this.form.submit()">
            <option value="">All</option>
            <option value="0" <?= ($filters['status'] === '0') ? 'selected' : '' ?>>Active</option>
            <option value="1" <?= ($filters['status'] === '1') ? 'selected' : '' ?>>Inactive</option>
        </select>

        <button type="submit">Filter</button>
        <a href="/admin/users.php" class="btn-link">Clear</a>
    </form>

    <p><?= count($users) ?> user(s) found.</p>

    <?php if (empty($users)): ?>
        <p>No users match the current filter.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr class="<?= $u['inactive'] ? 'row-inactive' : '' ?>">
                        <td><?= sanitize($u['username']) ?></td>
                        <td><?= sanitize($u['email']) ?></td>
                        <td>
                            <span class="role-badge role-<?= strtolower(sanitize($u['role'])) ?>">
                                <?= sanitize($u['role']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge <?= $u['inactive'] ? 'status-inactive' : 'status-active' ?>">
                                <?= $u['inactive'] ? 'Inactive' : 'Active' ?>
                            </span>
                        </td>
                        <td><?= sanitize(substr($u['createdon'], 0, 10)) ?></td>
                        <td class="actions">
                            <a href="/admin/edit_user.php?id=<?= urlencode($u['id']) ?>" class="btn-edit">Edit</a>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Are you sure you want to change this user\'s status?');">
                                <input type="hidden" name="action"  value="toggle_active">
                                <input type="hidden" name="user_id" value="<?= sanitize($u['id']) ?>">
                                <button type="submit" class="<?= $u['inactive'] ? 'btn-activate' : 'btn-deactivate' ?>">
                                    <?= $u['inactive'] ? 'Reactivate' : 'Deactivate' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
