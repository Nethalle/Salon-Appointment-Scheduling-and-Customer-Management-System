
<?php
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pageTitle = 'Settings';
$pageSubtitle = 'Manage your salon information and booking preferences.';
$activePage = 'settings';

// ------------------------------------------
// DEFAULT SETTINGS
// ------------------------------------------

$defaults = [
    'salon_name' => 'Salon Studio',
    'salon_phone' => '',
    'salon_email' => '',
    'salon_address' => '',
    'salon_description' => 'Beauty. Elegance. Confidence.',
    'opening_time' => '09:00',
    'closing_time' => '18:00',
    'booking_advance_days' => '30',
    'cancellation_hours' => '24',
];

// ------------------------------------------
// HANDLE SETTINGS UPDATE
// ------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $settings = [
        'salon_name' => trim($_POST['salon_name'] ?? ''),
        'salon_phone' => trim($_POST['salon_phone'] ?? ''),
        'salon_email' => trim($_POST['salon_email'] ?? ''),
        'salon_address' => trim($_POST['salon_address'] ?? ''),
        'salon_description' => trim($_POST['salon_description'] ?? ''),
        'opening_time' => trim($_POST['opening_time'] ?? ''),
        'closing_time' => trim($_POST['closing_time'] ?? ''),
        'booking_advance_days' => trim($_POST['booking_advance_days'] ?? ''),
        'cancellation_hours' => trim($_POST['cancellation_hours'] ?? ''),
    ];

    $errors = [];

    if ($settings['salon_name'] === '') {
        $errors[] = 'Salon name is required.';
    }

    if (
        $settings['salon_email'] !== ''
        && !filter_var($settings['salon_email'], FILTER_VALIDATE_EMAIL)
    ) {
        $errors[] = 'Please enter a valid salon email address.';
    }

    $validTime = static function ($time): bool {
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return false;
        }

        return true;
    };

    if (
        !$validTime($settings['opening_time'])
        || !$validTime($settings['closing_time'])
    ) {
        $errors[] = 'Please enter valid opening and closing times.';
    } elseif ($settings['opening_time'] >= $settings['closing_time']) {
        $errors[] = 'Closing time must be later than opening time.';
    }

    if (
        !ctype_digit($settings['booking_advance_days'])
        || (int) $settings['booking_advance_days'] < 1
        || (int) $settings['booking_advance_days'] > 365
    ) {
        $errors[] = 'Booking advance days must be between 1 and 365.';
    }

    if (
        !ctype_digit($settings['cancellation_hours'])
        || (int) $settings['cancellation_hours'] > 720
    ) {
        $errors[] = 'Cancellation hours must be between 0 and 720.';
    }

    foreach ($settings as $key => $value) {
        if (strlen($value) > 255) {
            $errors[] = ucfirst(str_replace('_', ' ', $key))
                . ' must not exceed 255 characters.';
        }
    }

    if (!empty($errors)) {

        foreach ($errors as $error) {
            flash_set('error', $error);
        }

        $_SESSION['settings_form'] = $settings;

        redirect('admin/settings.php');

    }

    try {

        $pdo = db();
        $pdo->beginTransaction();

        foreach ($settings as $key => $value) {
            set_setting($key, $value);
        }

        $pdo->commit();

        unset($_SESSION['settings_form']);

        flash_set('success', 'Salon settings updated successfully.');

        redirect('admin/settings.php');

    } catch (Throwable $e) {

        if (db()->inTransaction()) {
            db()->rollBack();
        }

        error_log('Settings update error: ' . $e->getMessage());

        flash_set('error', 'Unable to save settings. Please try again.');

        redirect('admin/settings.php');
    }
}

// ------------------------------------------
// LOAD SETTINGS
// ------------------------------------------

$savedForm = $_SESSION['settings_form'] ?? [];
unset($_SESSION['settings_form']);

$settings = [];

foreach ($defaults as $key => $default) {
    $settings[$key] = $savedForm[$key] ?? get_setting($key, $default);
}

$flashMessages = flash_get();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-content">

    <section class="section-heading">
        <span class="eyebrow">SALON CONFIGURATION</span>
        <h2>Salon Settings</h2>
        <p>Manage your salon information, operating hours, and appointment policies.</p>
    </section>

    <?php foreach ($flashMessages as $message): ?>

        <div class="alert alert-<?= e($message['type']) ?>">
            <?= e($message['message']) ?>
        </div>

    <?php endforeach; ?>

    <form method="POST" class="settings-form">

        <?= csrf_field() ?>

        <!-- SALON INFORMATION -->

        <section class="panel settings-panel">

            <div class="settings-panel-heading">
                <div>
                    <span class="eyebrow">GENERAL INFORMATION</span>
                    <h3>Salon Information</h3>
                    <p>Update your salon's basic contact details.</p>
                </div>
            </div>

            <div class="settings-grid">

                <div class="form-group">
                    <label for="salon_name">Salon Name *</label>
                    <input
                        type="text"
                        id="salon_name"
                        name="salon_name"
                        maxlength="255"
                        value="<?= e($settings['salon_name']) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="salon_phone">Contact Number</label>
                    <input
                        type="text"
                        id="salon_phone"
                        name="salon_phone"
                        maxlength="255"
                        value="<?= e($settings['salon_phone']) ?>"
                        placeholder="Enter contact number"
                    >
                </div>

                <div class="form-group">
                    <label for="salon_email">Email Address</label>
                    <input
                        type="email"
                        id="salon_email"
                        name="salon_email"
                        maxlength="255"
                        value="<?= e($settings['salon_email']) ?>"
                        placeholder="Enter salon email"
                    >
                </div>

                <div class="form-group">
                    <label for="salon_address">Salon Address</label>
                    <input
                        type="text"
                        id="salon_address"
                        name="salon_address"
                        maxlength="255"
                        value="<?= e($settings['salon_address']) ?>"
                        placeholder="Enter salon address"
                    >
                </div>

                <div class="form-group settings-full-width">
                    <label for="salon_description">Salon Description / Tagline</label>
                    <textarea
                        id="salon_description"
                        name="salon_description"
                        rows="3"
                        maxlength="255"
                        placeholder="Enter salon tagline"
                    ><?= e($settings['salon_description']) ?></textarea>
                </div>

            </div>

        </section>

        <!-- BUSINESS HOURS -->

        <section class="panel settings-panel">

            <div class="settings-panel-heading">
                <div>
                    <span class="eyebrow">OPERATING SCHEDULE</span>
                    <h3>Business Hours</h3>
                    <p>Set your salon's daily opening and closing times.</p>
                </div>
            </div>

            <div class="settings-grid">

                <div class="form-group">
                    <label for="opening_time">Opening Time</label>
                    <input
                        type="time"
                        id="opening_time"
                        name="opening_time"
                        value="<?= e($settings['opening_time']) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="closing_time">Closing Time</label>
                    <input
                        type="time"
                        id="closing_time"
                        name="closing_time"
                        value="<?= e($settings['closing_time']) ?>"
                        required
                    >
                </div>

            </div>

        </section>

        <!-- BOOKING POLICIES -->

        <section class="panel settings-panel">

            <div class="settings-panel-heading">
                <div>
                    <span class="eyebrow">APPOINTMENT CONFIGURATION</span>
                    <h3>Booking Policies</h3>
                    <p>Configure appointment scheduling and cancellation preferences.</p>
                </div>
            </div>

            <div class="settings-grid">

                <div class="form-group">
                    <label for="booking_advance_days">
                        Advance Booking Limit (Days)
                    </label>

                    <input
                        type="number"
                        id="booking_advance_days"
                        name="booking_advance_days"
                        min="1"
                        max="365"
                        value="<?= e($settings['booking_advance_days']) ?>"
                        required
                    >

                    <small>Maximum number of days customers can book ahead.</small>
                </div>

                <div class="form-group">
                    <label for="cancellation_hours">
                        Cancellation Notice (Hours)
                    </label>

                    <input
                        type="number"
                        id="cancellation_hours"
                        name="cancellation_hours"
                        min="0"
                        max="720"
                        value="<?= e($settings['cancellation_hours']) ?>"
                        required
                    >

                    <small>Preferred notice period for appointment cancellations.</small>
                </div>

            </div>

        </section>

        <!-- SAVE BUTTON -->

        <div class="settings-actions">

            <button type="submit" class="btn btn-primary">
                Save Settings
            </button>

            <a href="<?= e(url('admin/dashboard.php')) ?>"
               class="btn btn-secondary">
                Cancel
            </a>

        </div>

    </form>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
