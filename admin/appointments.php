
<?php
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pageTitle = 'Appointments';
$pageSubtitle = 'Manage salon appointment bookings.';
$activePage = 'appointments';

$errors = [];

// ==========================================
// ALLOWED APPOINTMENT STATUSES
// ==========================================

$allowedStatuses = [
    'pending',
    'confirmed',
    'completed',
    'cancelled',
    'no-show'
];


// ==========================================
// UPDATE APPOINTMENT STATUS
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $appointmentId = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
    $newStatus = $_POST['status'] ?? '';

   
    if (!$appointmentId || !in_array($newStatus, $allowedStatuses, true)) {
        flash_set('error', 'Invalid appointment information.');
        redirect('admin/appointments.php');
    }

    try {
        $stmt = db()->prepare(
            'UPDATE appointments
             SET status = :status
             WHERE id = :id'
        );

        $stmt->execute([
            ':status' => $newStatus,
            ':id' => $appointmentId
        ]);

        flash_set('success', 'Appointment status updated successfully.');

    } catch (PDOException $e) {
        error_log('Appointment status update error: ' . $e->getMessage());
        flash_set('error', 'Unable to update the appointment status.');
    }

    redirect('admin/appointments.php');
}


// ==========================================
// FILTER APPOINTMENTS
// ==========================================

$filterStatus = $_GET['status'] ?? 'all';

$allowedFilters = [
    'all',
    'pending',
    'confirmed',
    'completed',
    'cancelled',
    'no-show'
];

if (!in_array($filterStatus, $allowedFilters, true)) {
    $filterStatus = 'all';
}


// ==========================================
// GET APPOINTMENTS
// ==========================================

$sql = "
    SELECT
        a.id,
        a.appointment_date,
        a.start_time,
        a.end_time,
        a.status,
        a.source,
        c.full_name AS customer_name,
        c.phone AS customer_phone,
        s.full_name AS stylist_name,
        sv.name AS service_name,
        sv.price AS service_price
    FROM appointments a
    INNER JOIN customers c ON a.customer_id = c.id
    INNER JOIN stylists s ON a.stylist_id = s.id
    INNER JOIN services sv ON a.service_id = sv.id
";

$params = [];

if ($filterStatus !== 'all') {
    $sql .= " WHERE a.status = :status";
    $params[':status'] = $filterStatus;
}

$sql .= " ORDER BY a.appointment_date DESC, a.start_time DESC";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ==========================================
// GET APPOINTMENT COUNTS
// ==========================================

$countStmt = db()->query(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'pending') AS pending,
        SUM(status = 'confirmed') AS confirmed,
        SUM(status = 'completed') AS completed,
        SUM(status = 'cancelled') AS cancelled,
        SUM(status = 'no-show') AS no_show
     FROM appointments"
);

$counts = $countStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$flashMessages = flash_get();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-content">

    <!-- PAGE INTRODUCTION -->

    <div class="section-heading">
        <div>
            <span class="section-eyebrow">SALON MANAGEMENT</span>
            <h2>Appointment Management</h2>
            <p>View and manage all salon appointment bookings.</p>
        </div>
    </div>


    <!-- FLASH MESSAGES -->

    <?php foreach ($flashMessages as $message): ?>

        <div class="alert alert-<?= e($message['type']) ?>">
            <?= e($message['message']) ?>
        </div>

    <?php endforeach; ?>


    <!-- APPOINTMENT STATISTICS -->

    <div class="stats-grid">

        <div class="stat-card">
            <span>Total Appointments</span>
            <strong><?= (int) ($counts['total'] ?? 0) ?></strong>
        </div>

        <div class="stat-card">
            <span>Pending</span>
            <strong><?= (int) ($counts['pending'] ?? 0) ?></strong>
        </div>

        <div class="stat-card">
            <span>Confirmed</span>
            <strong><?= (int) ($counts['confirmed'] ?? 0) ?></strong>
        </div>

        <div class="stat-card">
            <span>Completed</span>
            <strong><?= (int) ($counts['completed'] ?? 0) ?></strong>
        </div>

        <div class="stat-card">
            <span>Cancelled</span>
            <strong><?= (int) ($counts['cancelled'] ?? 0) ?></strong>
        </div>

    </div>


    <!-- APPOINTMENT TABLE -->

    <section class="content-card">

        <div class="card-header">

            <div>
                <h3>All Appointments</h3>
                <p>Review and update customer bookings.</p>
            </div>

            <form method="GET" class="filter-form">

                <select name="status" onchange="this.form.submit()">

                    <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>
                        All Statuses
                    </option>

                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>
                        Pending
                    </option>

                    <option value="confirmed" <?= $filterStatus === 'confirmed' ? 'selected' : '' ?>>
                        Confirmed
                    </option>

                    <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>
                        Completed
                    </option>

                    <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>
                        Cancelled
                    </option>

                    <option value="no-show" <?= $filterStatus === 'no-show' ? 'selected' : '' ?>>
                        No-show
                    </option>

                </select>

            </form>

        </div>


        <div class="table-responsive">

            <table class="data-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Stylist</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Update Status</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($appointments)): ?>

                        <tr>
                            <td colspan="9" class="empty-state">
                                No appointments found.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($appointments as $appointment): ?>

                            <tr>

                                <td>
                                    #<?= (int) $appointment['id'] ?>
                                </td>

                                <td>
                                    <strong><?= e($appointment['customer_name']) ?></strong>

                                    <?php if (!empty($appointment['customer_phone'])): ?>
                                        <small class="table-subtext">
                                            <?= e($appointment['customer_phone']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= e($appointment['service_name']) ?>
                                </td>

                                <td>
                                    <?= e($appointment['stylist_name']) ?>
                                </td>

                                <td>
                                    <?= e(date('M d, Y', strtotime($appointment['appointment_date']))) ?>
                                </td>

                                <td>
                                    <?= e(date('g:i A', strtotime($appointment['start_time']))) ?>
                                    –
                                    <?= e(date('g:i A', strtotime($appointment['end_time']))) ?>
                                </td>

                                <td>
                                    ₱<?= number_format((float) $appointment['service_price'], 2) ?>
                                </td>

                                <td>
                                    <span class="status-badge status-<?= e($appointment['status']) ?>">
                                        <?= e(ucwords(str_replace('-', ' ', $appointment['status']))) ?>
                                    </span>
                                </td>

                                <td>

                                    <form method="POST" class="status-update-form">

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="appointment_id"
                                            value="<?= (int) $appointment['id'] ?>"
                                        >

                                        <select name="status" aria-label="Update appointment status">

                                            <?php foreach ($allowedStatuses as $status): ?>

                                                <option
                                                    value="<?= e($status) ?>"
                                                    <?= $appointment['status'] === $status ? 'selected' : '' ?>
                                                >
                                                    <?= e(ucwords(str_replace('-', ' ', $status))) ?>
                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                        <button type="submit" class="btn-small">
                                            Save
                                        </button>

                                    </form>

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
