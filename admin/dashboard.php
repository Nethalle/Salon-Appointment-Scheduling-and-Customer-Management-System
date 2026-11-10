
<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pageTitle = 'Admin Dashboard';
$pageSubtitle = 'Overview of your salon operations';

$pdo = db();

// Get a count from a database table.
function get_table_count(PDO $pdo, string $table): int
{
    $allowedTables = [
        'appointments',
        'customers',
        'stylists',
        'services'
    ];

    if (!in_array($table, $allowedTables, true)) {
        return 0;
    }

    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('Dashboard count error: ' . $e->getMessage());
        return 0;
    }
}

$totalAppointments = get_table_count($pdo, 'appointments');
$totalCustomers = get_table_count($pdo, 'customers');
$totalStylists = get_table_count($pdo, 'stylists');
$totalServices = get_table_count($pdo, 'services');

// Get the five latest appointments.
$recentAppointments = [];

try {
    $stmt = $pdo->query(
        'SELECT * FROM appointments ORDER BY id DESC LIMIT 5'
    );

    $recentAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Recent appointments error: ' . $e->getMessage());
}

// Find the first available field from a list of possible names.
function appointment_field(array $appointment, array $possibleFields, string $fallback = '—'): string
{
    foreach ($possibleFields as $field) {
        if (isset($appointment[$field]) && $appointment[$field] !== '') {
            return (string) $appointment[$field];
        }
    }

    return $fallback;
}

require_once __DIR__ . '/../includes/header.php';

?>

<section class="dashboard-welcome">
    <div>
        <span class="dashboard-eyebrow">SALON OVERVIEW</span>

        <h2>
            Welcome back, <?= e(current_user()['name'] ?? 'Admin') ?>!
        </h2>

        <p>
            Here's what's happening in your salon today.
        </p>
    </div>

    <div class="dashboard-date">
        <span>Today</span>
        <strong><?= date('F d, Y') ?></strong>
    </div>
</section>

<section class="dashboard-stats">

    <article class="stat-card">
        <div class="stat-icon icon-appointments">▣</div>
        <div class="stat-information">
            <span>Total Appointments</span>
            <strong><?= number_format($totalAppointments) ?></strong>
            <small>All recorded appointments</small>
        </div>
    </article>

    <article class="stat-card">
        <div class="stat-icon icon-customers">♙</div>
        <div class="stat-information">
            <span>Total Customers</span>
            <strong><?= number_format($totalCustomers) ?></strong>
            <small>Registered customer records</small>
        </div>
    </article>

    <article class="stat-card">
        <div class="stat-icon icon-stylists">✂</div>
        <div class="stat-information">
            <span>Total Stylists</span>
            <strong><?= number_format($totalStylists) ?></strong>
            <small>Stylist records</small>
        </div>
    </article>

    <article class="stat-card">
        <div class="stat-icon icon-services">✧</div>
        <div class="stat-information">
            <span>Total Services</span>
            <strong><?= number_format($totalServices) ?></strong>
            <small>Available service records</small>
        </div>
    </article>

</section>

<section class="dashboard-section">

    <div class="section-heading">
        <div>
            <span class="dashboard-eyebrow">SALON ACTIVITY</span>
            <h2>Recent Appointments</h2>
            <p>Review the latest appointment records.</p>
        </div>

        <a
            href="<?= e(url('admin/appointments.php')) ?>"
            class="view-all-link"
        >
            View All <span>→</span>
        </a>
    </div>

    <div class="appointments-table-wrapper">

        <table class="appointments-table">

            <thead>
                <tr>
                    <th>Appointment ID</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

                <?php if (empty($recentAppointments)): ?>

                    <tr>
                        <td colspan="5" class="empty-table">
                            <div class="empty-icon">▣</div>
                            <strong>No appointments yet</strong>
                            <p>New appointment records will appear here.</p>
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($recentAppointments as $appointment): ?>

                        <?php
                        $appointmentId = appointment_field(
                            $appointment,
                            ['id'],
                            '—'
                        );

                        $customer = appointment_field(
                            $appointment,
                            ['customer_name', 'full_name', 'customer_id'],
                            '—'
                        );

                        $service = appointment_field(
                            $appointment,
                            ['service_name', 'service_id'],
                            '—'
                        );

                        $date = appointment_field(
                            $appointment,
                            ['appointment_date', 'scheduled_date', 'date'],
                            '—'
                        );

                        $status = appointment_field(
                            $appointment,
                            ['status'],
                            'Pending'
                        );

                        if ($date !== '—') {
                            $timestamp = strtotime($date);

                            if ($timestamp !== false) {
                                $date = date('M d, Y', $timestamp);
                            }
                        }

                        $statusClass = strtolower(
                            preg_replace('/[^a-zA-Z0-9]+/', '-', $status)
                        );
                        ?>

                        <tr>
                            <td>#<?= e($appointmentId) ?></td>
                            <td><?= e($customer) ?></td>
                            <td><?= e($service) ?></td>
                            <td><?= e($date) ?></td>
                            <td>
                                <span class="appointment-status status-<?= e($statusClass) ?>">
                                    <?= e(ucfirst($status)) ?>
                                </span>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
