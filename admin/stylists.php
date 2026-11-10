
<?php
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pageTitle = 'Stylists';
$pageSubtitle = 'Manage salon stylists and their services.';
$activePage = 'stylists';


// ==========================================
// SAVE STYLIST SERVICE ASSIGNMENTS
// ==========================================

$saveAssignments = function (PDO $pdo, int $stylistId, array $serviceIds): void {

    // Get all currently active service IDs.
    $stmt = $pdo->query(
        'SELECT id FROM services WHERE is_active = 1'
    );

    $activeIds = array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );

    // Remove duplicate selections and validate them.
    $serviceIds = array_unique(array_map('intval', $serviceIds));

    foreach ($serviceIds as $serviceId) {
        if (!in_array($serviceId, $activeIds, true)) {
            throw new InvalidArgumentException(
                'One or more selected services are unavailable.'
            );
        }
    }

    // Replace the stylist's service assignments.
    $delete = $pdo->prepare(
        'DELETE FROM stylist_services WHERE stylist_id = :id'
    );

    $delete->execute([':id' => $stylistId]);

    if (!empty($serviceIds)) {

        $insert = $pdo->prepare(
            'INSERT INTO stylist_services (stylist_id, service_id)
             VALUES (:stylist_id, :service_id)'
        );

        foreach ($serviceIds as $serviceId) {

            $insert->execute([
                ':stylist_id' => $stylistId,
                ':service_id' => $serviceId
            ]);
        }
    }
};


// ==========================================
// HANDLE FORM SUBMISSIONS
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = $_POST['action'] ?? '';


    // --------------------------------------
    // ADD STYLIST
    // --------------------------------------

    if ($action === 'add') {

        $name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $serviceIds = $_POST['service_ids'] ?? [];

        if ($name === '' || strlen($name) > 100) {

            flash_set('error', 'Please enter a valid stylist name.');
            redirect('admin/stylists.php');
        }

        if (strlen($phone) > 30) {

            flash_set('error', 'Phone number must not exceed 30 characters.');
            redirect('admin/stylists.php');
        }

        if (!is_array($serviceIds)) {
            flash_set('error', 'Invalid service selection.');
            redirect('admin/stylists.php');
        }

        try {

            $pdo = db();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO stylists (full_name, phone, is_active)
                 VALUES (:name, :phone, 1)'
            );

            $stmt->execute([
                ':name' => $name,
                ':phone' => $phone !== '' ? $phone : null
            ]);

            $stylistId = (int) $pdo->lastInsertId();

            $saveAssignments($pdo, $stylistId, $serviceIds);

            $pdo->commit();

            flash_set('success', 'Stylist added successfully.');

        } catch (Throwable $e) {

            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Add stylist error: ' . $e->getMessage());

            flash_set(
                'error',
                $e instanceof InvalidArgumentException
                    ? $e->getMessage()
                    : 'Unable to add the stylist. Please try again.'
            );
        }

        redirect('admin/stylists.php');
    }


    // --------------------------------------
    // UPDATE STYLIST
    // --------------------------------------

    if ($action === 'update') {

        $stylistId = filter_var(
            $_POST['stylist_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        $name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $serviceIds = $_POST['service_ids'] ?? [];

        if (!$stylistId) {

            flash_set('error', 'Invalid stylist selected.');
            redirect('admin/stylists.php');
        }

        if ($name === '' || strlen($name) > 100) {

            flash_set('error', 'Please enter a valid stylist name.');
            redirect('admin/stylists.php');
        }

        if (strlen($phone) > 30) {

            flash_set('error', 'Phone number must not exceed 30 characters.');
            redirect('admin/stylists.php');
        }

        if (!is_array($serviceIds)) {

            flash_set('error', 'Invalid service selection.');
            redirect('admin/stylists.php');
        }

        try {

            $pdo = db();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'UPDATE stylists
                 SET full_name = :name,
                     phone = :phone
                 WHERE id = :id'
            );

            $stmt->execute([
                ':name' => $name,
                ':phone' => $phone !== '' ? $phone : null,
                ':id' => $stylistId
            ]);

            // Check whether the stylist exists.
            $check = $pdo->prepare(
                'SELECT id FROM stylists WHERE id = :id'
            );

            $check->execute([':id' => $stylistId]);

            if (!$check->fetch()) {
                throw new InvalidArgumentException('Stylist not found.');
            }

            $saveAssignments($pdo, $stylistId, $serviceIds);

            $pdo->commit();

            flash_set('success', 'Stylist information updated successfully.');

        } catch (Throwable $e) {

            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Update stylist error: ' . $e->getMessage());

            flash_set(
                'error',
                $e instanceof InvalidArgumentException
                    ? $e->getMessage()
                    : 'Unable to update the stylist.'
            );
        }

        redirect('admin/stylists.php');
    }


    // --------------------------------------
    // ACTIVATE / DEACTIVATE STYLIST
    // --------------------------------------

    if ($action === 'toggle') {

        $stylistId = filter_var(
            $_POST['stylist_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        $newStatus = $_POST['new_status'] ?? '';

        if (
            !$stylistId
            || !in_array($newStatus, ['0', '1'], true)
        ) {

            flash_set('error', 'Invalid stylist status.');
            redirect('admin/stylists.php');
        }

        try {

            $stmt = db()->prepare(
                'UPDATE stylists
                 SET is_active = :status
                 WHERE id = :id'
            );

            $stmt->execute([
                ':status' => (int) $newStatus,
                ':id' => $stylistId
            ]);

            if ($stmt->rowCount() === 0) {

                $check = db()->prepare(
                    'SELECT id FROM stylists WHERE id = :id'
                );

                $check->execute([':id' => $stylistId]);

                if (!$check->fetch()) {
                    flash_set('error', 'Stylist not found.');
                    redirect('admin/stylists.php');
                }
            }

            flash_set(
                'success',
                $newStatus === '1'
                    ? 'Stylist activated successfully.'
                    : 'Stylist deactivated successfully.'
            );

        } catch (PDOException $e) {

            error_log('Toggle stylist error: ' . $e->getMessage());

            flash_set('error', 'Unable to update stylist status.');
        }

        redirect('admin/stylists.php');
    }


    flash_set('error', 'Invalid stylist action.');
    redirect('admin/stylists.php');
}


// ==========================================
// LOAD ACTIVE SERVICES
// ==========================================

try {

    $stmt = db()->query(
        'SELECT id, name, price, duration_minutes
         FROM services
         WHERE is_active = 1
         ORDER BY name ASC'
    );

    $availableServices = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log('Load stylist services error: ' . $e->getMessage());

    $availableServices = [];
}


// ==========================================
// LOAD STYLISTS AND ASSIGNED SERVICES
// ==========================================

try {

    $stmt = db()->query(
        'SELECT
            st.id,
            st.full_name,
            st.phone,
            st.is_active,
            GROUP_CONCAT(
                DISTINCT s.name
                ORDER BY s.name
                SEPARATOR ", "
            ) AS assigned_services
         FROM stylists st
         LEFT JOIN stylist_services ss
            ON ss.stylist_id = st.id
         LEFT JOIN services s
            ON s.id = ss.service_id
         GROUP BY
            st.id,
            st.full_name,
            st.phone,
            st.is_active
         ORDER BY st.id DESC'
    );

    $stylists = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalStylists = count($stylists);
    $activeStylists = 0;
    $inactiveStylists = 0;

    foreach ($stylists as $stylist) {

        if ((int) $stylist['is_active'] === 1) {
            $activeStylists++;
        } else {
            $inactiveStylists++;
        }
    }

    // Load service IDs assigned to each stylist.
    $assignmentStmt = db()->query(
        'SELECT stylist_id, service_id FROM stylist_services'
    );

    $stylistAssignments = [];

    foreach ($assignmentStmt->fetchAll(PDO::FETCH_ASSOC) as $assignment) {

        $stylistAssignments[(int) $assignment['stylist_id']][] =
            (int) $assignment['service_id'];
    }

} catch (PDOException $e) {

    error_log('Load stylists error: ' . $e->getMessage());

    $stylists = [];
    $stylistAssignments = [];
    $totalStylists = 0;
    $activeStylists = 0;
    $inactiveStylists = 0;

    flash_set('error', 'Unable to load stylist records.');
}


// ==========================================
// PAGE LAYOUT
// ==========================================

require_once __DIR__ . '/../includes/header.php';

$messages = flash_get();
?>

<style>
.stylists-page {
    max-width: 1450px;
    margin: 0 auto;
}

.stylists-heading {
    margin-bottom: 24px;
}

.stylists-heading .eyebrow {
    color: #bd9251;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.stylists-heading h2 {
    color: #302820;
    font-family: Georgia, serif;
    font-size: 28px;
    margin: 0 0 7px;
}

.stylists-heading p {
    color: #927f70;
    font-size: 12px;
    margin: 0;
}

.stylist-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stylist-stat {
    background: #fffdfa;
    border: 1px solid #e9ded1;
    border-radius: 10px;
    padding: 22px;
}

.stylist-stat span {
    display: block;
    color: #927f70;
    font-size: 12px;
    margin-bottom: 10px;
}

.stylist-stat strong {
    color: #302820;
    font-family: Georgia, serif;
    font-size: 29px;
}

.stylist-card {
    background: #fffdfa;
    border: 1px solid #e9ded1;
    border-radius: 11px;
    padding: 24px;
    margin-bottom: 24px;
}

.stylist-card-heading {
    margin-bottom: 20px;
}

.stylist-card-heading h3 {
    color: #302820;
    font-family: Georgia, serif;
    font-size: 21px;
    margin: 0 0 6px;
}

.stylist-card-heading p {
    color: #927f70;
    font-size: 12px;
    margin: 0;
}

.stylist-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.stylist-field label {
    display: block;
    color: #594638;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 8px;
}

.stylist-field input {
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

.stylist-field input:focus {
    outline: none;
    border-color: #bd9251;
    box-shadow: 0 0 0 3px rgba(189, 146, 81, .12);
}

.stylist-services-options {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.stylist-service-option {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 11px;
    border: 1px solid #eee3d8;
    border-radius: 7px;
    background: #fff;
    color: #594638;
    font-size: 12px;
    cursor: pointer;
}

.stylist-service-option input {
    width: 15px;
    min-height: auto;
    height: 15px;
    accent-color: #49392e;
}

.stylist-form-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 20px;
}

.stylist-button {
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

.stylist-button:hover {
    background: #72543b;
}

.stylist-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.stylist-table {
    width: 100%;
    min-width: 950px;
    border-collapse: collapse;
}

.stylist-table th {
    padding: 14px 12px;
    background: #f6f1eb;
    color: #927f70;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .6px;
    text-align: left;
    text-transform: uppercase;
}

.stylist-table td {
    padding: 15px 12px;
    border-bottom: 1px solid #eee3d8;
    color: #49392e;
    font-size: 12px;
    vertical-align: middle;
}

.stylist-table tr:last-child td {
    border-bottom: none;
}

.stylist-edit-input {
    width: 100%;
    min-width: 120px;
    padding: 9px;
    border: 1px solid #dfd1c1;
    border-radius: 6px;
    font-family: inherit;
    font-size: 12px;
}

.stylist-status {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
}

.stylist-status.active {
    background: #e5f3e8;
    color: #287443;
}

.stylist-status.inactive {
    background: #fce9e7;
    color: #a33b35;
}

.stylist-service-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    max-width: 260px;
}

.stylist-service-tag {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 5px;
    background: #f5eee5;
    color: #795c45;
    font-size: 10px;
}

.stylist-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.stylist-toggle-button {
    padding: 8px 11px;
    border: 1px solid #dfd1c1;
    border-radius: 6px;
    background: #fff;
    color: #795c45;
    font-family: inherit;
    font-size: 11px;
    cursor: pointer;
}

.stylist-toggle-button:hover {
    background: #f6f1eb;
}

.stylist-alert {
    padding: 13px 16px;
    border-radius: 7px;
    margin-bottom: 18px;
    font-size: 12px;
}

.stylist-alert.success {
    background: #e7f4e9;
    border: 1px solid #c9e7cf;
    color: #287443;
}

.stylist-alert.error {
    background: #fff0ef;
    border: 1px solid #f1d1ce;
    color: #a33b35;
}

.stylist-empty {
    padding: 35px 15px;
    text-align: center;
    color: #927f70;
    font-size: 13px;
}

.stylist-edit-services {
    min-width: 220px;
}

.stylist-edit-services .stylist-service-option {
    padding: 7px;
    margin-bottom: 5px;
}

@media (max-width: 750px) {
    .stylist-stats,
    .stylist-form-grid,
    .stylist-services-options {
        grid-template-columns: 1fr;
    }

    .stylist-card {
        padding: 16px;
    }
}
</style>


<div class="stylists-page">

    <!-- PAGE HEADING -->

    <div class="stylists-heading">
        <div class="eyebrow">Salon Management</div>
        <h2>Stylist Management</h2>
        <p>Manage your salon stylists and the services they provide.</p>
    </div>


    <!-- FLASH MESSAGES -->

    <?php foreach ($messages as $message): ?>

        <div class="stylist-alert <?= e($message['type']) ?>">
            <?= e($message['message']) ?>
        </div>

    <?php endforeach; ?>


    <!-- STATISTICS -->

    <div class="stylist-stats">

        <div class="stylist-stat">
            <span>Total Stylists</span>
            <strong><?= $totalStylists ?></strong>
        </div>

        <div class="stylist-stat">
            <span>Active Stylists</span>
            <strong><?= $activeStylists ?></strong>
        </div>

        <div class="stylist-stat">
            <span>Inactive Stylists</span>
            <strong><?= $inactiveStylists ?></strong>
        </div>

    </div>


    <!-- ADD STYLIST -->

    <section class="stylist-card">

        <div class="stylist-card-heading">
            <h3>Add New Stylist</h3>
            <p>Enter stylist information and select the services they can perform.</p>
        </div>

        <form method="POST" action="<?= e(url('admin/stylists.php')) ?>">

            <?= csrf_field() ?>

            <input type="hidden" name="action" value="add">

            <div class="stylist-form-grid">

                <div class="stylist-field">
                    <label for="stylist_name">Full Name</label>

                    <input
                        type="text"
                        id="stylist_name"
                        name="full_name"
                        maxlength="100"
                        placeholder="Enter stylist's full name"
                        required
                    >
                </div>

                <div class="stylist-field">
                    <label for="stylist_phone">Phone Number</label>

                    <input
                        type="text"
                        id="stylist_phone"
                        name="phone"
                        maxlength="30"
                        placeholder="Enter phone number (optional)"
                    >
                </div>

            </div>


            <div class="stylist-field" style="margin-top: 22px;">

                <label>Assign Services</label>

                <?php if (empty($availableServices)): ?>

                    <p>No active services available to assign.</p>

                <?php else: ?>

                    <div class="stylist-services-options">

                        <?php foreach ($availableServices as $service): ?>

                            <label class="stylist-service-option">

                                <input
                                    type="checkbox"
                                    name="service_ids[]"
                                    value="<?= (int) $service['id'] ?>"
                                >

                                <span>
                                    <?= e($service['name']) ?>
                                </span>

                            </label>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>


            <div class="stylist-form-actions">

                <button type="submit" class="stylist-button">
                    + Add Stylist
                </button>

            </div>

        </form>

    </section>


    <!-- STYLIST LIST -->

    <section class="stylist-card">

        <div class="stylist-card-heading">
            <h3>All Salon Stylists</h3>
            <p>Edit stylist information, service assignments, and availability.</p>
        </div>

        <div class="stylist-table-wrapper">

            <table class="stylist-table">

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Phone</th>
                        <th>Assigned Services</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (empty($stylists)): ?>

                        <tr>
                            <td colspan="6" class="stylist-empty">
                                No stylists have been added yet.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($stylists as $stylist): ?>

                            <?php
                            $stylistId = (int) $stylist['id'];

                            $assignedIds =
                                $stylistAssignments[$stylistId] ?? [];
                            ?>

                            <tr>

                                <td>
                                    #<?= $stylistId ?>
                                </td>

                                <td>

                                    <form
                                        method="POST"
                                        action="<?= e(url('admin/stylists.php')) ?>"
                                        id="edit-stylist-<?= $stylistId ?>"
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="update"
                                        >

                                        <input
                                            type="hidden"
                                            name="stylist_id"
                                            value="<?= $stylistId ?>"
                                        >

                                        <input
                                            class="stylist-edit-input"
                                            type="text"
                                            name="full_name"
                                            value="<?= e($stylist['full_name']) ?>"
                                            maxlength="100"
                                            required
                                            aria-label="Stylist name"
                                        >

                                </td>

                                <td>

                                        <input
                                            class="stylist-edit-input"
                                            type="text"
                                            name="phone"
                                            value="<?= e($stylist['phone'] ?? '') ?>"
                                            maxlength="30"
                                            aria-label="Stylist phone"
                                        >

                                </td>

                                <td>

                                        <div class="stylist-edit-services">

                                            <?php foreach ($availableServices as $service): ?>

                                                <label class="stylist-service-option">

                                                    <input
                                                        type="checkbox"
                                                        name="service_ids[]"
                                                        value="<?= (int) $service['id'] ?>"
                                                        <?= in_array(
                                                            (int) $service['id'],
                                                            $assignedIds,
                                                            true
                                                        ) ? 'checked' : '' ?>
                                                    >

                                                    <span>
                                                        <?= e($service['name']) ?>
                                                    </span>

                                                </label>

                                            <?php endforeach; ?>

                                        </div>

                                    </form>

                                </td>

                                <td>

                                    <?php if ((int) $stylist['is_active'] === 1): ?>

                                        <span class="stylist-status active">
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span class="stylist-status inactive">
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <div class="stylist-actions">

                                        <button
                                            type="submit"
                                            form="edit-stylist-<?= $stylistId ?>"
                                            class="stylist-button"
                                        >
                                            Save
                                        </button>

                                        <form
                                            method="POST"
                                            action="<?= e(url('admin/stylists.php')) ?>"
                                            onsubmit="return confirm('Are you sure you want to <?= (int) $stylist['is_active'] === 1 ? 'deactivate' : 'activate' ?> this stylist?');"
                                        >

                                            <?= csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="toggle"
                                            >

                                            <input
                                                type="hidden"
                                                name="stylist_id"
                                                value="<?= $stylistId ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="new_status"
                                                value="<?= (int) $stylist['is_active'] === 1 ? '0' : '1' ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="stylist-toggle-button"
                                            >
                                                <?= (int) $stylist['is_active'] === 1
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
