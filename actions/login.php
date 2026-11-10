
<?php

require_once __DIR__ . '/../includes/auth.php';

// Only accept POST requests
require_post();

// Validate CSRF token
verify_csrf();

// Get submitted credentials
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Preserve email if validation fails
old_set([
    'email' => $email
]);

// Validate required fields
if ($email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {

    flash_set('error', 'Please enter a valid email address and password.');

    redirect('public/login.php');
}

// Find account by email
$stmt = db()->prepare(
    'SELECT id, name, email, password_hash, role, is_active
     FROM users
     WHERE email = :email
     LIMIT 1'
);

$stmt->execute([
    ':email' => $email
]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check credentials and account status
if (
    !$user ||
    !password_verify($password, $user['password_hash']) ||
    (int) $user['is_active'] !== 1
) {

    flash_set('error', 'Invalid email or password, or your account is inactive.');

    redirect('public/login.php');
}

// Log the user in
login_user($user);

// Remove saved form input
old_clear();

// Redirect according to role
redirect(dashboard_path($user['role']));
