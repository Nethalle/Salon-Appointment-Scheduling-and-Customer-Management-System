
<?php

require_once __DIR__ . '/../includes/auth.php';

// Only accept POST requests
require_post();

// Verify CSRF token
verify_csrf();

// Get submitted values
$name = trim($_POST['name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$phone = trim($_POST['phone'] ?? '');

$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Preserve non-sensitive form values
old_set([
    'name' => $name,
    'email' => $email,
    'phone' => $phone
]);

// Validate full name
if (
    $name === '' ||
    mb_strlen($name) > 100
) {
    flash_set('error', 'Please enter a valid full name.');
    redirect('public/register.php');
}

// Validate email
if (
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($email) > 150
) {
    flash_set('error', 'Please enter a valid email address.');
    redirect('public/register.php');
}

// Validate phone number
if (strlen($phone) > 30) {
    flash_set('error', 'Phone number must not exceed 30 characters.');
    redirect('public/register.php');
}

// Validate password length
if (
    strlen($password) < 8 ||
    strlen($password) > 72
) {
    flash_set('error', 'Password must be between 8 and 72 bytes.');
    redirect('public/register.php');
}

// Check password confirmation
if ($password !== $confirmPassword) {
    flash_set('error', 'Passwords do not match.');
    redirect('public/register.php');
}

try {

    $pdo = db();

    // Check if email already exists
    $stmt = $pdo->prepare(
        'SELECT id FROM users WHERE email = :email LIMIT 1'
    );

    $stmt->execute([
        ':email' => $email
    ]);

    if ($stmt->fetch()) {
        flash_set('error', 'This email address is already registered.');
        redirect('public/register.php');
    }

    // Hash password securely
    $passwordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    // Start database transaction
    $pdo->beginTransaction();

    // Create customer login account
    $stmt = $pdo->prepare(
        'INSERT INTO users
            (name, email, password_hash, role)
         VALUES
            (:name, :email, :password_hash, :role)'
    );

    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':password_hash' => $passwordHash,
        ':role' => 'customer'
    ]);

    // Get newly created user ID
    $userId = (int) $pdo->lastInsertId();

    // Create linked customer profile
    $stmt = $pdo->prepare(
        'INSERT INTO customers
            (user_id, full_name, phone, email)
         VALUES
            (:user_id, :full_name, :phone, :email)'
    );

    $stmt->execute([
        ':user_id' => $userId,
        ':full_name' => $name,
        ':phone' => $phone !== '' ? $phone : null,
        ':email' => $email
    ]);

    // Save both records
    $pdo->commit();

    // Clear old form values
    old_clear();

    // Redirect to login
    flash_set(
        'success',
        'Your account has been created successfully. You can now log in.'
    );

    redirect('public/login.php');

} catch (PDOException $e) {

    // Undo incomplete database operations
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Handle duplicate email safely
    if ($e->getCode() === '23000') {
        flash_set('error', 'This email address is already registered.');
    } else {
        error_log('Registration error: ' . $e->getMessage());
        flash_set('error', 'Registration failed. Please try again.');
    }

    redirect('public/register.php');
}
