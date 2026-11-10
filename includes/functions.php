<?php
// Shared helper functions (escaping, redirects, flash messages, CSRF, settings)

/** Escape text for safe HTML output. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Build a URL inside the project, e.g. url('public/login.php'). */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Redirect to a project path and stop the script. */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/* ---------- Flash messages (shown once after a redirect) ---------- */

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* ---------- Old form input (refill the form after a validation error) ---------- */

function old_set(array $data): void
{
    $_SESSION['old'] = $data;
}

function old(string $key, string $default = ''): string
{
    return e($_SESSION['old'][$key] ?? $default);
}

function old_clear(): void
{
    unset($_SESSION['old']);
}

/* ---------- CSRF protection ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to put inside every POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Stop the request if the CSRF token is missing or wrong. */
function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
        http_response_code(400);
        exit('Invalid or expired form. Go back, refresh the page and try again.');
    }
}

/** Actions only accept POST requests. */
function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed.');
    }
}

/* ---------- Settings table ---------- */

function get_setting(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = :k');
    $stmt->execute([':k' => $key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function set_setting(string $key, string $value): void
{
    $sql = 'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE setting_value = :v2';
    db()->prepare($sql)->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
}

// Phase 5 will add here: calculate_end_time(), has_overlap(), daily_limit_reached()
