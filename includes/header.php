
<?php

// ==========================================
// PAGE CONFIGURATION
// ==========================================

$pageTitle = $pageTitle ?? APP_NAME;
$pageSubtitle = $pageSubtitle ?? '';

$user = current_user() ?? [];

if (!is_array($user)) {
    $user = [];
}

$role = strtolower($user['role'] ?? 'customer');

$displayName = $user['name']
    ?? $user['full_name']
    ?? $user['email']
    ?? ucfirst($role);

$initial = strtoupper(substr($displayName, 0, 1));

$projectBase = rtrim(BASE_URL, '/');

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= e($pageTitle) ?> | <?= e(APP_NAME) ?>
    </title>

    <meta name="theme-color" content="#302820">

    <link
        rel="stylesheet"
        href="<?= e(url('assets/css/style.css')) ?>"
    >

</head>

<body>

<div class="app-shell">

    <?php require __DIR__ . '/sidebar.php'; ?>

    <div class="main-panel">

        <!-- TOP NAVIGATION -->

        <header class="topbar">

            <div class="topbar-left">

                <button
                    type="button"
                    class="menu-toggle"
                    id="menuToggle"
                    aria-label="Toggle navigation"
                    aria-controls="sidebar"
                    aria-expanded="false"
                >
                    ☰
                </button>

                <div class="page-heading">

                    <h1 class="page-title">
                        <?= e($pageTitle) ?>
                    </h1>

                    <?php if ($pageSubtitle !== ''): ?>

                        <p class="page-subtitle">
                            <?= e($pageSubtitle) ?>
                        </p>

                    <?php endif; ?>

                </div>

            </div>

            <!-- USER INFORMATION -->

            <div class="topbar-user">

                <div class="user-avatar">
                    <?= e($initial) ?>
                </div>

                <div class="user-details">

                    <strong>
                        <?= e($displayName) ?>
                    </strong>

                    <span>
                        <?= e(ucfirst($role)) ?>
                    </span>

                </div>

            </div>

        </header>

        <!-- MAIN CONTENT -->

        <main class="content-area">
