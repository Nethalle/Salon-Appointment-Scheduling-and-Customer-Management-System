
<?php

require_once __DIR__ . '/../includes/auth.php';

// Redirect logged-in users to their dashboard
if (is_logged_in()) {
    redirect(dashboard_path(current_user()['role']));
}

$messages = flash_get();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | <?= e(APP_NAME) ?></title>

    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>

<body class="auth-page">

    <div class="auth-container">

        <!-- LEFT SIDE: BRANDING -->

        <section class="auth-brand-panel">

            <div class="auth-brand">

                <div class="brand-icon">S</div>

                <div>
                    <h2>Salon Studio</h2>
                    <span>BEAUTY & CARE</span>
                </div>

            </div>

            <div class="auth-brand-content">

                <span class="auth-eyebrow">WELCOME TO SALON STUDIO</span>

                <h1>
                    Beauty begins<br>
                    with <em>you.</em>
                </h1>

                <p>
                    Experience personalized beauty services
                    and effortless appointment scheduling
                    in one elegant space.
                </p>

            </div>

            <div class="auth-brand-footer">
                BEAUTY. ELEGANCE. CONFIDENCE.
            </div>

        </section>


        <!-- RIGHT SIDE: LOGIN FORM -->

        <section class="auth-form-panel">

            <div class="auth-form-wrapper">

                <div class="auth-mobile-brand">
                    <div class="brand-icon">S</div>
                    <span>Salon Studio</span>
                </div>

                <div class="auth-heading">

                    <span class="auth-eyebrow">YOUR SALON EXPERIENCE</span>

                    <h1>Welcome Back</h1>

                    <p>
                        Please enter your account details to continue.
                    </p>

                </div>


                <!-- FLASH MESSAGES -->

                <?php foreach ($messages as $message): ?>

                    <?php
                    $type = in_array(
                        $message['type'],
                        ['success', 'error'],
                        true
                    ) ? $message['type'] : 'error';
                    ?>

                    <div class="alert alert-<?= e($type) ?>">
                        <?= e($message['message']) ?>
                    </div>

                <?php endforeach; ?>


                <!-- LOGIN FORM -->

                <form action="<?= e(url('actions/login.php')) ?>"
                      method="POST"
                      class="auth-form">

                    <?= csrf_field() ?>

                    <div class="form-group">

                        <label for="email">Email Address</label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your email address"
                            value="<?= old('email') ?>"
                            maxlength="150"
                            autocomplete="username"
                            required
                            autofocus
                        >

                    </div>


                    <div class="form-group">

                        <label for="password">Password</label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                    </div>


                    <button type="submit" class="auth-submit-btn">
                        Sign In
                        <span>→</span>
                    </button>

                </form>


                <div class="auth-divider">
                    <span>NEW TO SALON STUDIO?</span>
                </div>


                <p class="auth-switch">
                    Don't have an account?
                    <a href="<?= e(url('public/register.php')) ?>">
                        Create an account
                    </a>
                </p>


                <p class="auth-copyright">
                    &copy; <?= date('Y') ?> Salon Studio.
                    All rights reserved.
                </p>

            </div>

        </section>

    </div>

</body>
</html>
