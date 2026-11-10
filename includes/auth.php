<?php
// Session handling and role-based access control.
// Every page and action should start with:  require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user']);
}

/** Logged-in user as array (id, name, email, role) or null. */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/** Where each role lands after login. */
function dashboard_path(string $role): string
{
    switch ($role) {
        case 'admin':
            return 'admin/dashboard.php';
        case 'staff':
            return 'staff/dashboard.php';
        default:
            return 'customer/dashboard.php';
    }
}

/** Save the user in the session after a successful login. */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'    => (int) $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash_set('error', 'Please log in first.');
        redirect('public/login.php');
    }
}

/**
 * Allow only the given role(s). Example: require_role('admin')
 * or require_role(['admin', 'staff']).
 */
function require_role($roles): void
{
    require_login();
    $roles = (array) $roles;

    if (!in_array(current_user()['role'], $roles, true)) {
        http_response_code(403);
        flash_set('error', 'You do not have access to that page.');
        redirect(dashboard_path(current_user()['role']));
    }
}
