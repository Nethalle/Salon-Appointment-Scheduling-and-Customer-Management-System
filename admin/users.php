
<?php
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pageTitle = 'Users';
$pageSubtitle = 'Manage salon system accounts.';
$activePage = 'users';

$currentUser = current_user();
$currentUserId = (int) ($currentUser['id'] ?? 0);


// ==========================================
// HANDLE ACCOUNT STATUS UPDATE
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post();
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $userId = filter_var(
        $_POST['user_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $newStatus = $_POST['new_status'] ?? '';

    if (
        $action !== 'toggle'
        || !$userId
        || !in_array($newStatus, ['0', '1'], true)
    ) {
        flash_set('error', 'Invalid account update request.');
        redirect('admin/users.php');
    }

    // Prevent the logged-in admin from deactivating their own account.
    if ($userId === $currentUserId && $newStatus === '0') {
        flash_set('error', 'You cannot deactivate your own account.');
        redirect('admin/users.php');
    }

    try {

        $stmt = db()->prepare(
            'UPDATE users
             SET is_active = :status
             WHERE id = :id'
        );

        $stmt->execute([
            ':status' => (int) $newStatus,
            ':id' => $userId
        ]);

        if ($stmt->rowCount() === 0) {

            $check = db()->prepare(
                'SELECT id FROM users WHERE id = :id'
            );

            $check->execute([':id' => $userId]);

            if (!$check->fetch()) {
                flash_set('error', 'User account not found.');
                redirect('admin/users.php');
            }
        }

        flash_set(
            'success',
            $newStatus === '1'
                ? 'Account activated successfully.'
                : 'Account deactivated successfully.'
        );

    } catch (PDOException $e) {

        error_log('User status update error: ' . $e->getMessage());

        flash_set('error', 'Unable to update the account status.');
    }

    redirect('admin/users.php');
}


// ==========================================
// SEARCH AND FILTER
// ==========================================

$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$allowedRoles = ['admin', 'staff', 'customer'];
$allowedStatuses = ['active', 'inactive'];

if (!in_array($roleFilter, $allowedRoles, true)) {
    $roleFilter = '';
}

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}


// ==========================================
// LOAD USER STATISTICS
// ==========================================

try {

    $statsStmt = db()->query(
        "SELECT
            COUNT(*) AS total_users,
            SUM(is_active = 1) AS active_users,
            SUM(is_active = 0) AS inactive_users,
            SUM(role = 'customer') AS customers,
            SUM(role = 'staff') AS staff,
            SUM(role = 'admin') AS admins
         FROM users"
    );

    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log('User statistics error: ' . $e->getMessage());

    $stats = [
        'total_users' => 0,
        'active_users' => 0,
        'inactive_users' => 0,
        'customers' => 0,
        'staff' => 0,
        'admins' => 0
    ];
}


// ==========================================
// LOAD USER ACCOUNTS
// ==========================================

try {

    $sql = "
        SELECT
            u.id,
            u.name,
            u.email,
            u.role,
            u.is_active,
            u.created_at,
            c.phone
        FROM users u
        LEFT JOIN customers c
            ON c.user_id = u.id
        WHERE 1 = 1
    ";

    $params = [];

    if ($search !== '') {

        $sql .= "
            AND (
                u.name LIKE :search_name
                OR u.email LIKE :search_email
                OR c.phone LIKE :search_phone
            )
        ";

        $searchValue = '%' . $search . '%';

        $params[':search_name'] = $searchValue;
        $params[':search_email'] = $searchValue;
        $params[':search_phone'] = $searchValue;
    }

    if ($roleFilter !== '') {

        $sql .= ' AND u.role = :role';

        $params[':role'] = $roleFilter;
    }

    if ($statusFilter === 'active') {
        $sql .= ' AND u.is_active = 1';
    } elseif ($statusFilter === 'inactive') {
        $sql .= ' AND u.is_active = 0';
    }

    $sql .= ' ORDER BY u.created_at DESC, u.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log('Load users error: ' . $e->getMessage());

    $users = [];

    flash_set('error', 'Unable to load user accounts.');
}


// ==========================================
// PAGE LAYOUT
// ==========================================

require_once __DIR__ . '/../includes/header.php';

$messages = flash_get();
?>

<style>
.users-page {
    max-width: 1450px;
    margin: 0 auto;
}

.users-heading {
    margin-bottom: 24px;
}

.users-heading .eyebrow {
    color: #bd9251;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.users-heading h2 {
    color: #302820;
    font-family: Georgia, serif;
    font-size: 28px;
    margin: 0 0 7px;
}

.users-heading p {
    color: #927f70;
    font-size: 12px;
    margin: 0;
}

.users-alert {
    padding: 13px 16px;
    border-radius: 7px;
    margin-bottom: 18px;
    font-size: 12px;
}

.users-alert.success {
    background: #e7f4e9;
    border: 1px solid #c9e7cf;
    color: #287443;
}

.users-alert.error {
    background: #fff0ef;
    border: 1px solid #f1d1ce;
    color: #a33b35;
}

.users-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.users-stat {
    background: #fffdfa;
    border: 1px solid #e9ded1;
    border-radius: 10px;
    padding: 22px;
}

.users-stat span {
    display: block;
    color: #927f70;
    font-size: 12px;
    margin-bottom: 10px;
}

.users-stat strong {
    color: #302820;
    font-family: Georgia, serif;
    font-size: 29px;
}

.users-card {
    background: #fffdfa;
    border: 1px solid #e9ded1;
    border-radius: 11px;
    padding: 24px;
    margin-bottom: 24px;
}

.users-card-heading {
    margin-bottom: 20px;
}

.users-card-heading h3 {
    color: #302820;
    font-family: Georgia, serif;
    font-size: 21px;
    margin: 0 0 6px;
}

.users-card-heading p {
    color: #927f70;
    font-size: 12px;
    margin: 0;
}

.users-filter-form {
    display: grid;
    grid-template-columns: minmax(200px, 2fr) 1fr 1fr auto auto;
    gap: 10px;
    margin-bottom: 20px;
}

.users-filter-form input,
.users-filter-form select {
    width: 100%;
    min-height: 40px;
    padding: 9px 11px;
    border: 1px solid #dfd1c1;
    border-radius: 7px;
    background: #fff;
    color: #49392e;
    font-family: inherit;
    font-size: 12px;
}

.users-button {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    min-height: 38px;
    padding: 9px 15px;
    border: none;
    border-radius: 7px;
    background: #49392e;
    color: #fff;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
}

.users-button:hover {
    background: #72543b;
}

.users-reset {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 9px 14px;
    border: 1px solid #dfd1c1;
    border-radius: 7px;
    color: #795c45;
    font-size: 12px;
    text-decoration: none;
}

.users-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.users-table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
}

.users-table th {
    padding: 14px 12px;
    background: #f6f1eb;
    color: #927f70;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .6px;
    text-align: left;
    text-transform: uppercase;
}

.users-table td {
    padding: 15px 12px;
    border-bottom: 1px solid #eee3d8;
    color: #49392e;
    font-size: 12px;
    vertical-align: middle;
}

.users-table tr:last-child td {
    border-bottom: none;
}

.user-name {
    display: block;
    color: #302820;
    font-weight: 700;
    margin-bottom: 4px;
}

.user-email {
    color: #927f70;
    font-size: 11px;
}

.user-role,
.user-status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    text-transform: capitalize;
}

.user-role.admin {
    background: #eee7f5;
    color: #69458a;
}

.user-role.staff {
    background: #e7eef7;
    color: #41668e;
}

.user-role.customer {
    background: #f5eee1;
    color: #856638;
}

.user-status.active {
    background: #e5f3e8;
    color: #287443;
}

.user-status.inactive {
    background: #fce9e7;
    color: #a33b35;
}

.user-action-form {
    display: inline;
}

.user-action-button {
    padding: 8px 11px;
    border: 1px solid #dfd1c1;
    border-radius: 6px;
    background: #fff;
    color: #795c45;
    font-family: inherit;
    font-size: 11px;
    cursor: pointer;
}

.user-action-button:hover {
    background: #f6f1eb;
}

.user-action-button.activate {
    border-color: #c9e7cf;
    color: #287443;
}

.users-empty {
    padding: 35px 15px !important;
    color: #927f70 !important;
    text-align: center;
}

.users-count {
    color: #927f70;
    font-size: 11px;
    margin-top: 16px;
}

@media (max-width: 850px) {
    .users-filter-form {
        grid-template-columns: 1fr 1fr;
    }

    .users-stats {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 500px) {
    .users-filter-form {
        grid-template-columns: 1fr;
    }

    .users-card {
        padding: 16px;
    }
}
</style>


<div class="users-page">

    <!-- PAGE HEADING -->

    <div class="users-heading">
        <div class="eyebrow">Salon Management</div>
        <h2>User Management</h2>
        <p>View and manage all accounts registered in your salon system.</p>
    </div>


    <!-- FLASH MESSAGES -->

    <?php foreach ($messages as $message): ?>

        <div class="users-alert <?= e($message['type']) ?>">
            <?= e($message['message']) ?>
        </div>

    <?php endforeach; ?>


    <!-- STATISTICS -->

    <div class="users-stats">

        <div class="users-stat">
            <span>Total Accounts</span>
            <strong><?= (int) $stats['total_users'] ?></strong>
        </div>

        <div class="users-stat">
            <span>Active Accounts</span>
            <strong><?= (int) $stats['active_users'] ?></strong>
        </div>

        <div class="users-stat">
            <span>Inactive Accounts</span>
            <strong><?= (int) $stats['inactive_users'] ?></strong>
        </div>

    </div>


    <!-- USER TABLE -->

    <section class="users-card">

        <div class="users-card-heading">
            <h3>All System Users</h3>
            <p>Search accounts, filter by role, and manage account availability.</p>
        </div>


        <!-- FILTERS -->

        <form method="GET" class="users-filter-form">

            <input
                type="search"
                name="search"
                placeholder="Search name, email, or phone..."
                value="<?= e($search) ?>"
            >

            <select name="role">
                <option value="">All Roles</option>

                <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>
                    Admin
                </option>

                <option value="staff" <?= $roleFilter === 'staff' ? 'selected' : '' ?>>
                    Staff
                </option>

                <option value="customer" <?= $roleFilter === 'customer' ? 'selected' : '' ?>>
                    Customer
                </option>
            </select>

            <select name="status">
                <option value="">All Statuses</option>

                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>
                    Active
                </option>

                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>
                    Inactive
                </option>
            </select>

            <button type="submit" class="users-button">
                Search
            </button>

            <a
                href="<?= e(url('admin/users.php')) ?>"
                class="users-reset"
            >
                Reset
            </a>

        </form>


        <!-- USERS TABLE -->

        <div class="users-table-wrapper">

            <table class="users-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User Information</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Date Registered</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($users)): ?>

                        <tr>
                            <td colspan="7" class="users-empty">
                                No user accounts found.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($users as $user): ?>

                            <?php
                            $userId = (int) $user['id'];
                            $isActive = (int) $user['is_active'] === 1;
                            $isCurrentUser = $userId === $currentUserId;
                            ?>

                            <tr>

                                <td>#<?= $userId ?></td>

                                <td>
                                    <span class="user-name">
                                        <?= e($user['name']) ?>
                                    </span>

                                    <span class="user-email">
                                        <?= e($user['email']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e($user['phone'] ?: '—') ?>
                                </td>

                                <td>
                                    <span class="user-role <?= e($user['role']) ?>">
                                        <?= e($user['role']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= $user['created_at']
                                        ? e(date('M d, Y', strtotime($user['created_at'])))
                                        : '—' ?>
                                </td>

                                <td>

                                    <span class="user-status <?= $isActive ? 'active' : 'inactive' ?>">
                                        <?= $isActive ? 'Active' : 'Inactive' ?>
                                    </span>

                                </td>

                                <td>

                                    <?php if ($isCurrentUser): ?>

                                        <span class="user-email">Current account</span>

                                    <?php else: ?>

                                        <form
                                            method="POST"
                                            action="<?= e(url('admin/users.php')) ?>"
                                            class="user-action-form"
                                            onsubmit="return confirm('Are you sure you want to <?= $isActive ? 'deactivate' : 'activate' ?> this account?');"
                                        >

                                            <?= csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="toggle"
                                            >

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?= $userId ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="new_status"
                                                value="<?= $isActive ? '0' : '1' ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="user-action-button <?= $isActive ? '' : 'activate' ?>"
                                            >
                                                <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <div class="users-count">
            Showing <?= count($users) ?> account(s)
        </div>

    </section>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
