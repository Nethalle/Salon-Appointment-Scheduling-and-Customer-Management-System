
<?php
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pageTitle = 'Services';
$pageSubtitle = 'Manage salon services and pricing.';
$activePage = 'services';

$errors = [];

// ==========================================
// HANDLE SERVICE ACTIONS
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = $_POST['action'] ?? '';

    // --------------------------------------
    // ADD SERVICE
    // --------------------------------------

    if ($action === 'add') {

        $name = trim($_POST['name'] ?? '');
        $price = $_POST['price'] ?? '';
        $duration = $_POST['duration_minutes'] ?? '';

        if ($name === '' || strlen($name) > 100) {
            flash_set('error', 'Please enter a service name (maximum 100 characters).');
            redirect('admin/services.php');
        }

        if (!is_numeric($price) || (float) $price <= 0) {
            flash_set('error', 'Please enter a valid service price.');
            redirect('admin/services.php');
        }

        if (
            filter_var($duration, FILTER_VALIDATE_INT) === false
            || (int) $duration <= 0
        ) {
            flash_set('error', 'Please enter a valid service duration.');
            redirect('admin/services.php');
        }

        try {
            $stmt = db()->prepare(
                'INSERT INTO services (name, price, duration_minutes, is_active)
                 VALUES (:name, :price, :duration, 1)'
            );

            $stmt->execute([
                ':name' => $name,
                ':price' => round((float) $price, 2),
                ':duration' => (int) $duration
            ]);

            flash_set('success', 'Service added successfully.');

        } catch (PDOException $e) {

            error_log('Add service error: ' . $e->getMessage());

            flash_set('error', 'Unable to add the service. Please try again.');
        }

        redirect('admin/services.php');
    }


    // --------------------------------------
    // UPDATE SERVICE
    // --------------------------------------

    if ($action === 'update') {

        $serviceId = filter_var(
            $_POST['service_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        $name = trim($_POST['name'] ?? '');
        $price = $_POST['price'] ?? '';
        $duration = $_POST['duration_minutes'] ?? '';

        if (!$serviceId) {
            flash_set('error', 'Invalid service selected.');
            redirect('admin/services.php');
        }

        if ($name === '' || strlen($name) > 100) {
            flash_set('error', 'Please enter a valid service name.');
            redirect('admin/services.php');
        }

        if (!is_numeric($price) || (float) $price <= 0) {
            flash_set('error', 'Please enter a valid service price.');
            redirect('admin/services.php');
        }

        if (
            filter_var($duration, FILTER_VALIDATE_INT) === false
            || (int) $duration <= 0
        ) {
            flash_set('error', 'Please enter a valid service duration.');
            redirect('admin/services.php');
        }

        try {
            $stmt = db()->prepare(
                'UPDATE services
                 SET name = :name,
                     price = :price,
                     duration_minutes = :duration
                 WHERE id = :id'
            );

            $stmt->execute([
                ':name' => $name,
                ':price' => round((float) $price, 2),
                ':duration' => (int) $duration,
                ':id' => $serviceId
            ]);

            if ($stmt->rowCount() > 0) {
                flash_set('success', 'Service updated successfully.');
            } else {
                $check = db()->prepare(
                    'SELECT id FROM services WHERE id = :id'
                );
                $check->execute([':id' => $serviceId]);

                flash_set(
                    $check->fetch()
                        ? 'success'
                        : 'error',
                    $check->fetch() !== false
                        ? 'Service details saved.'
                        : 'Service not found.'
                );
            }

        } catch (PDOException $e) {

            error_log('Update service error: ' . $e->getMessage());

            flash_set('error', 'Unable to update the service.');
        }

        redirect('admin/services.php');
    }


    // --------------------------------------
    // ACTIVATE / DEACTIVATE SERVICE
    // --------------------------------------

    if ($action === 'toggle') {

        $serviceId = filter_var(
            $_POST['service_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        $newStatus = $_POST['new_status'] ?? '';

        if (
            !$serviceId
            || !in_array($newStatus, ['0', '1'], true)
        ) {
            flash_set('error', 'Invalid service status.');
            redirect('admin/services.php');
        }

        try {
            $stmt = db()->prepare(
                'UPDATE services
                 SET is_active = :status
                 WHERE id = :id'
            );

            $stmt->execute([
                ':status' => (int) $newStatus,
                ':id' => $serviceId
            ]);

            flash_set(
                'success',
                $newStatus === '1'
                    ? 'Service activated successfully.'
                    : 'Service deactivated successfully.'
            );

        } catch (PDOException $e) {

            error_log('Toggle service error: ' . $e->getMessage());

            flash_set('error', 'Unable to change the service status.');
        }

        redirect('admin/services.php');
    }


    // --------------------------------------
    // INVALID ACTION
    // --------------------------------------

    flash_set('error', 'Invalid service action.');
    redirect('admin/services.php');
}


// ==========================================
// LOAD SERVICES
// ==========================================

try {

    $stmt = db()->query(
        'SELECT id, name, price, duration_minutes, is_active
         FROM services
         ORDER BY id DESC'
    );

    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalServices = count($services);
    $activeServices = 0;
    $inactiveServices = 0;

    foreach ($services as $service) {

        if ((int) $service['is_active'] === 1) {
            $activeServices++;
        } else {
            $inactiveServices++;
        }
    }

} catch (PDOException $e) {

    error_log('Load services error: ' . $e->getMessage());

    $services = [];
    $totalServices = 0;
    $activeServices = 0;
    $inactiveServices = 0;

    flash_set('error', 'Unable to load salon services.');
}


// ==========================================
// PAGE LAYOUT
// ==========================================

require_once __DIR__ . '/../includes/header.php';

$messages = flash_get();
?>

<style>
.services-page {
    max-width: 1450px;
    margin: 0 auto;
}

.services-heading {
    margin-bottom: 24px;
}

.services-heading .eyebrow {
    color: #bd9251;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.services-heading h2 {
    color: #302820;
    font-family: Georgia, serif;
    font-size: 28px;
    margin: 0 0 7px;
}

.services-heading p {
    color: #927f70;
    font-size: 12px;
    margin: 0;
}

.services-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.service-stat {
    background: #fffdfa;
    border: 1px solid #e9ded1;
    border-radius: 10px;
    padding: 22px;
}

.service-stat span {
    display: block;
    color: #927f70;
    font-size: 12px;
    margin-bottom: 10px;
}

.service-stat strong {
    color: #302820;
    font-family: Georgia, serif;
    font-size: 29px;
}

.services-card {
    background: #fffdfa;
    border: 1px solid #e9ded1;
    border-radius: 11px;
    padding: 24px;
    margin-bottom: 24px;
}

.services-card-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.services-card-heading h3 {
    color: #302820;
    font-family: Georgia, serif;
    font-size: 21px;
    margin: 0 0 6px;
}

.services-card-heading p {
    color: #927f70;
    font-size: 12px;
    margin: 0;
}

.service-form-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 16px;
}

.service-field label {
    display: block;
    color: #594638;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 8px;
}

.service-field input {
    width: 100%;
    min-height: 43px;
    padding: 10px 12px;
    border: 1px solid #dfd1c1;
    border-radius: 7px;
    background: #fff;
    color: #302820;
    font-family: inherit;
    font-size: 13px;
}

.service-field input:focus {
    outline: none;
    border-color: #bd9251;
    box-shadow: 0 0 0 3px rgba(189, 146, 81, .12);
}

.service-form-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 18px;
}

.service-button {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    min-height: 38px;
    padding: 9px 16px;
    border: 0;
    border-radius: 7px;
    background: #49392e;
    color: #fff;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s ease;
}

.service-button:hover {
    background: #72543b;
}

.service-button-secondary {
    background: #eee5d9;
    color: #49392e;
}

.service-button-secondary:hover {
    background: #e2d3c1;
}

.service-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.service-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

.service-table th {
    padding: 14px 12px;
    background: #f6f1eb;
    color: #927f70;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .6px;
    text-align: left;
    text-transform: uppercase;
}

.service-table td {
    padding: 15px 12px;
    border-bottom: 1px solid #eee3d8;
    color: #49392e;
    font-size: 12px;
    vertical-align: middle;
}

.service-table tr:last-child td {
    border-bottom: none;
}

.service-name {
    color: #302820;
    font-weight: 700;
}

.service-status {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
}

.service-status.active {
    background: #e5f3e8;
    color: #287443;
}

.service-status.inactive {
    background: #fce9e7;
    color: #a33b35;
}

.service-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.service-edit-input {
    width: 100%;
    min-width: 75px;
    padding: 8px;
    border: 1px solid #dfd1c1;
    border-radius: 6px;
    font-family: inherit;
    font-size: 12px;
}

.service-toggle-button {
    padding: 8px 11px;
    border: 1px solid #dfd1c1;
    border-radius: 6px;
    background: #fff;
    color: #795c45;
    font-family: inherit;
    font-size: 11px;
    cursor: pointer;
}

.service-toggle-button:hover {
    background: #f6f1eb;
}

.service-alert {
    padding: 13px 16px;
    border-radius: 7px;
    margin-bottom: 18px;
    font-size: 12px;
}

.service-alert.success {
    background: #e7f4e9;
    border: 1px solid #c9e7cf;
    color: #287443;
}

.service-alert.error {
    background: #fff0ef;
    border: 1px solid #f1d1ce;
    color: #a33b35;
}

.service-empty {
    padding: 35px 15px;
    text-align: center;
    color: #927f70;
    font-size: 13px;
}

@media (max-width: 750px) {
    .services-stats {
        grid-template-columns: 1fr;
    }

    .service-form-grid {
        grid-template-columns: 1fr;
    }

    .services-card {
        padding: 16px;
    }

    .services-card-heading {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>

<div class="services-page">

    <!-- PAGE HEADING -->

    <div class="services-heading">
        <div class="eyebrow">Salon Management</div>
        <h2>Service Management</h2>
        <p>Add, update, and manage the services offered by your salon.</p>
    </div>


    <!-- FLASH MESSAGES -->

    <?php foreach ($messages as $message): ?>

        <div class="service-alert <?= e($message['type']) ?>">
            <?= e($message['message']) ?>
        </div>

    <?php endforeach; ?>


    <!-- SERVICE STATISTICS -->

    <div class="services-stats">

        <div class="service-stat">
            <span>Total Services</span>
            <strong><?= $totalServices ?></strong>
        </div>

        <div class="service-stat">
            <span>Active Services</span>
            <strong><?= $activeServices ?></strong>
        </div>

        <div class="service-stat">
            <span>Inactive Services</span>
            <strong><?= $inactiveServices ?></strong>
        </div>

    </div>


    <!-- ADD SERVICE FORM -->

    <section class="services-card">

        <div class="services-card-heading">
            <div>
                <h3>Add New Service</h3>
                <p>Enter the details of the new salon service.</p>
            </div>
        </div>

        <form method="POST" action="<?= e(url('admin/services.php')) ?>">

            <?= csrf_field() ?>

            <input type="hidden" name="action" value="add">

            <div class="service-form-grid">

                <div class="service-field">
                    <label for="service_name">Service Name</label>
                    <input
                        type="text"
                        id="service_name"
                        name="name"
                        maxlength="100"
                        placeholder="e.g. Hair Treatment"
                        required
                    >
                </div>

                <div class="service-field">
                    <label for="service_price">Price (₱)</label>
                    <input
                        type="number"
                        id="service_price"
                        name="price"
                        min="0.01"
                        step="0.01"
                        placeholder="500.00"
                        required
                    >
                </div>

                <div class="service-field">
                    <label for="service_duration">Duration (Minutes)</label>
                    <input
                        type="number"
                        id="service_duration"
                        name="duration_minutes"
                        min="1"
                        step="1"
                        placeholder="60"
                        required
                    >
                </div>

            </div>

            <div class="service-form-actions">
                <button type="submit" class="service-button">
                    + Add Service
                </button>
            </div>

        </form>

    </section>


    <!-- SERVICE LIST -->

    <section class="services-card">

        <div class="services-card-heading">
            <div>
                <h3>All Salon Services</h3>
                <p>Manage service details and availability.</p>
            </div>
        </div>

        <div class="service-table-wrapper">

            <table class="service-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Service Name</th>
                        <th>Price</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($services)): ?>

                        <tr>
                            <td colspan="6" class="service-empty">
                                No salon services have been added yet.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($services as $service): ?>

                            <tr>

                                <td>#<?= (int) $service['id'] ?></td>

                                <td>
                                    <form
                                        method="POST"
                                        action="<?= e(url('admin/services.php')) ?>"
                                        id="edit-service-<?= (int) $service['id'] ?>"
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="update"
                                        >

                                        <input
                                            type="hidden"
                                            name="service_id"
                                            value="<?= (int) $service['id'] ?>"
                                        >

                                        <input
                                            class="service-edit-input service-name"
                                            type="text"
                                            name="name"
                                            value="<?= e($service['name']) ?>"
                                            maxlength="100"
                                            required
                                            aria-label="Service name"
                                        >

                                </td>

                                <td>
                                        <input
                                            class="service-edit-input"
                                            type="number"
                                            name="price"
                                            value="<?= e($service['price']) ?>"
                                            min="0.01"
                                            step="0.01"
                                            required
                                            aria-label="Service price"
                                        >
                                </td>

                                <td>
                                        <input
                                            class="service-edit-input"
                                            type="number"
                                            name="duration_minutes"
                                            value="<?= (int) $service['duration_minutes'] ?>"
                                            min="1"
                                            step="1"
                                            required
                                            aria-label="Service duration"
                                        >
                                    </form>
                                </td>

                                <td>

                                    <?php if ((int) $service['is_active'] === 1): ?>

                                        <span class="service-status active">
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span class="service-status inactive">
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <div class="service-actions">

                                        <button
                                            type="submit"
                                            form="edit-service-<?= (int) $service['id'] ?>"
                                            class="service-button"
                                        >
                                            Save
                                        </button>

                                        <form
                                            method="POST"
                                            action="<?= e(url('admin/services.php')) ?>"
                                            onsubmit="return confirm('Are you sure you want to <?= (int) $service['is_active'] === 1 ? 'deactivate' : 'activate' ?> this service?');"
                                        >

                                            <?= csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="toggle"
                                            >

                                            <input
                                                type="hidden"
                                                name="service_id"
                                                value="<?= (int) $service['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="new_status"
                                                value="<?= (int) $service['is_active'] === 1 ? '0' : '1' ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="service-toggle-button"
                                            >
                                                <?= (int) $service['is_active'] === 1
                                                    ? 'Deactivate'
                                                    : 'Activate' ?>
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
