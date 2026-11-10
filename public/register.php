
<?php

require_once __DIR__ . '/../includes/auth.php';

// Redirect logged-in users
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

    <title>Create Account | <?= e(APP_NAME) ?></title>

    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>

<body class="auth-page">

    <div class="auth-container">

        <!-- LEFT BRAND PANEL -->

        <section class="auth-brand-panel">

            <div class="auth-brand">

                <div class="brand-icon">S</div>

                <div>
                    <h2>Salon Studio</h2>
                    <span>BEAUTY & CARE</span>
                </div>

            </div>

            <div class="auth-brand-content">

                <span class="auth-eyebrow">JOIN OUR COMMUNITY</span>

                <h1>
                    Your beauty<br>
                    journey starts<br>
                    <em>here.</em>
                </h1>

                <p>
                    Create your account and enjoy convenient
                    appointment scheduling and personalized
                    salon experiences.
                </p>

            </div>

            <div class="auth-brand-footer">
                BEAUTY. ELEGANCE. CONFIDENCE.
            </div>

        </section>


        <!-- RIGHT REGISTRATION PANEL -->

        <section class="auth-form-panel">

            <div class="auth-form-wrapper">

                <div class="auth-mobile-brand">
                    <div class="brand-icon">S</div>
                    <span>Salon Studio</span>
                </div>

                <div class="auth-heading">

                    <span class="auth-eyebrow">BECOME A MEMBER</span>

                    <h1>Create Account</h1>

                    <p>
                        Fill in your details to get started.
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


                <!-- REGISTRATION FORM -->

                <form action="<?= e(url('actions/register.php')) ?>"
                      method="POST"
                      class="auth-form">

                    <?= csrf_field() ?>


                    <div class="form-group">

                        <label for="name">Full Name</label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            placeholder="Enter your full name"
                            value="<?= old('name') ?>"
                            maxlength="100"
                            autocomplete="name"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="email">Email Address</label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your email address"
                            value="<?= old('email') ?>"
                            maxlength="150"
                            autocomplete="email"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="phone">Phone Number</label>

                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            placeholder="e.g. 09123456789"
                            value="<?= old('phone') ?>"
                            maxlength="30"
                            autocomplete="tel"
                        >

                    </div>


                    <div class="form-group">

                        <label for="password">Password</label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Create a password (8-72 characters)"
                            minlength="8"
                            maxlength="72"
                            autocomplete="new-password"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="confirm_password">Confirm Password</label>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Re-enter your password"
                            minlength="8"
                            maxlength="72"
                            autocomplete="new-password"
                            required
                        >

                    </div>


                    <button type="submit" class="auth-submit-btn">

                        Create Account
                        <span>→</span>

                    </button>

                </form>


                <div class="auth-divider">
                    <span>ALREADY A MEMBER?</span>
                </div>


                <p class="auth-switch">

                    Already have an account?

                    <a href="<?= e(url('public/login.php')) ?>">
                        Sign in
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
