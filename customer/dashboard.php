
<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('customer');

$pageTitle = 'My Dashboard';
$user = current_user();

$customer = null;
$appointments = [];

$stats = [
    'total' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'cancelled' => 0,
];

$flashMessages = flash_get();

$successMessage = $flashMessages['success'] ?? '';
$errorMessage = $flashMessages['error'] ?? '';

try {

    // Get customer profile.
    $customerStmt = db()->prepare(
        'SELECT id, full_name, email, phone
         FROM customers
         WHERE user_id = :user_id
         LIMIT 1'
    );

    $customerStmt->execute([
        ':user_id' => $user['id']
    ]);

    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

    if ($customer) {

        // Get appointment statistics.
        $statsStmt = db()->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'pending') AS pending,
                SUM(status = 'confirmed') AS confirmed,
                SUM(status = 'completed') AS completed,
                SUM(status = 'cancelled') AS cancelled
             FROM appointments
             WHERE customer_id = :customer_id"
        );

        $statsStmt->execute([
            ':customer_id' => $customer['id']
        ]);

        $result = $statsStmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $stats = [
                'total' => (int) ($result['total'] ?? 0),
                'pending' => (int) ($result['pending'] ?? 0),
                'confirmed' => (int) ($result['confirmed'] ?? 0),
                'completed' => (int) ($result['completed'] ?? 0),
                'cancelled' => (int) ($result['cancelled'] ?? 0),
            ];
        }

        // Get recent appointments.
        $appointmentStmt = db()->prepare(
            "SELECT
                a.id,
                a.appointment_date,
                a.start_time,
                a.end_time,
                a.status,
                s.name AS service_name,
                s.price,
                st.full_name AS stylist_name
             FROM appointments a
             INNER JOIN services s ON s.id = a.service_id
             INNER JOIN stylists st ON st.id = a.stylist_id
             WHERE a.customer_id = :customer_id
             ORDER BY a.appointment_date DESC, a.start_time DESC
             LIMIT 10"
        );

        $appointmentStmt->execute([
            ':customer_id' => $customer['id']
        ]);

        $appointments = $appointmentStmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {

    error_log('Customer dashboard error: ' . $e->getMessage());

    $errorMessage = 'Unable to load your dashboard. Please try again later.';

}

function appointment_status_class(string $status): string
{
    return match ($status) {
        'pending' => 'status-pending',
        'confirmed' => 'status-confirmed',
        'completed' => 'status-completed',
        'cancelled' => 'status-cancelled',
        'no-show' => 'status-noshow',
        default => 'status-pending',
    };
}

?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<style>

.customer-dashboard {
    max-width: 1250px;
    margin: 0 auto;
}

.customer-welcome {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding: 28px;
    margin-bottom: 25px;
    background: #fff;
    border: 1px solid #e9e0d5;
    border-radius: 12px;
}

.customer-welcome h2 {
    margin-bottom: 8px;
    color: #302820;
    font-family: Georgia, serif;
    font-size: 27px;
}

.customer-welcome p {
    color: #81766b;
    font-size: 13px;
}

.customer-welcome-date {
    padding: 15px 20px;
    background: #f7f3ed;
    border-radius: 9px;
    color: #493b30;
    font-size: 13px;
}

.customer-stats {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 25px;
}

.customer-stat-card {
    padding: 22px;
    background: #fff;
    border: 1px solid #e9e0d5;
    border-radius: 10px;
}

.customer-stat-card span {
    color: #81766b;
    font-size: 12px;
}

.customer-stat-card h3 {
    margin-top: 10px;
    color: #302820;
    font-size: 29px;
}

.customer-section {
    padding: 25px;
    background: #fff;
    border: 1px solid #e9e0d5;
    border-radius: 12px;
}

.customer-section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 22px;
}

.customer-section-heading h3 {
    color: #302820;
    font-family: Georgia, serif;
    font-size: 21px;
}

.customer-section-heading p {
    margin-top: 6px;
    color: #81766b;
    font-size: 12px;
}

.customer-book-btn {
    display: inline-block;
    padding: 12px 18px;
    background: #493b30;
    border-radius: 7px;
    color: #fff;
    font-size: 12px;
    text-decoration: none;
}

.customer-book-btn:hover {
    background: #a88a5e;
}

.customer-table-wrapper {
    overflow-x: auto;
}

.customer-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

.customer-table th {
    padding: 15px 12px;
    background: #f7f3ed;
    color: #81766b;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .5px;
}

.customer-table td {
    padding: 16px 12px;
    border-bottom: 1px solid #eee7df;
    color: #493b30;
    font-size: 12px;
}

.customer-table tr:last-child td {
    border-bottom: none;
}

.appointment-status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    text-transform: capitalize;
}

.status-pending {
    background: #fff3dc;
    color: #9a6a19;
}

.status-confirmed {
    background: #e4f3e8;
    color: #347348;
}

.status-completed {
    background: #e8edf7;
    color: #4d638e;
}

.status-cancelled {
    background: #fbe8e6;
    color: #a34d43;
}

.status-noshow {
    background: #eee8f5;
    color: #73528f;
}

.customer-empty {
    padding: 45px 20px;
    text-align: center;
    color: #81766b;
}

.customer-empty h4 {
    margin-bottom: 8px;
    color: #493b30;
    font-family: Georgia, serif;
    font-size: 20px;
}

.customer-alert {
    padding: 14px 18px;
    margin-bottom: 20px;
    border-radius: 7px;
    font-size: 13px;
}

.customer-alert.success {
    background: #e7f4e9;
    color: #347348;
}

.customer-alert.error {
    background: #fff0ee;
    color: #a33c32;
}

@media (max-width: 1100px) {
    .customer-stats {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 900px) {
    .customer-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {

    .customer-welcome {
        align-items: flex-start;
        flex-direction: column;
    }

    .customer-stats {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .customer-stat-card {
        padding: 15px;
    }

    .customer-section {
        padding: 16px;
    }

    .customer-section-heading {
        align-items: flex-start;
        flex-direction: column;
    }

}

</style>

<div class="customer-dashboard">

    <?php if ($successMessage): ?>
        <div class="customer-alert success">
            <?= e($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="customer-alert error">
            <?= e($errorMessage) ?>
        </div>
    <?php endif; ?>

    <?php if (!$customer): ?>

        <div class="customer-alert error">
            Your customer profile could not be found.
            Please contact the administrator.
        </div>

    <?php else: ?>

        <!-- WELCOME SECTION -->

        <section class="customer-welcome">

            <div>
                <h2>
                    Welcome back, <?= e($customer['full_name']) ?>!
                </h2>

                <p>
                    Manage your salon appointments and enjoy your self-care journey.
                </p>
            </div>

            <div class="customer-welcome-date">
                <span>Today</span>
                <br>
                <strong><?= date('F d, Y') ?></strong>
            </div>

        </section>

        <!-- APPOINTMENT STATISTICS -->

        <section class="customer-stats">

            <div class="customer-stat-card">
                <span>Total Appointments</span>
                <h3><?= $stats['total'] ?></h3>
            </div>

            <div class="customer-stat-card">
                <span>Pending</span>
                <h3><?= $stats['pending'] ?></h3>
            </div>

            <div class="customer-stat-card">
                <span>Confirmed</span>
                <h3><?= $stats['confirmed'] ?></h3>
            </div>

            <div class="customer-stat-card">
                <span>Completed</span>
                <h3><?= $stats['completed'] ?></h3>
            </div>

            <div class="customer-stat-card">
                <span>Cancelled</span>
                <h3><?= $stats['cancelled'] ?></h3>
            </div>

        </section>

        <!-- RECENT APPOINTMENTS -->

        <section class="customer-section">

            <div class="customer-section-heading">

                <div>
                    <h3>My Appointments</h3>

                    <p>
                        View your recent salon appointment requests.
                    </p>
                </div>

                <a
                    href="<?= e(url('customer/booking.php')) ?>"
                    class="customer-book-btn"
                >
                    + Book an Appointment
                </a>

            </div>

            <?php if (empty($appointments)): ?>

                <div class="customer-empty">

                    <h4>No appointments yet</h4>

                    <p>
                        Your bookings will appear here once you make an appointment.
                    </p>

                </div>

            <?php else: ?>

                <div class="customer-table-wrapper">

                    <table class="customer-table">

                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Stylist</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Price</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($appointments as $appointment): ?>

                                <tr>

                                    <td>
                                        <strong>
                                            <?= e($appointment['service_name']) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= e($appointment['stylist_name']) ?>
                                    </td>

                                    <td>
                                        <?= e(date(
                                            'M d, Y',
                                            strtotime($appointment['appointment_date'])
                                        )) ?>
                                    </td>

                                    <td>
                                        <?= e(date(
                                            'g:i A',
                                            strtotime($appointment['start_time'])
                                        )) ?>
                                        –
                                        <?= e(date(
                                            'g:i A',
                                            strtotime($appointment['end_time'])
                                        )) ?>
                                    </td>

                                    <td>
                                        ₱<?= number_format(
                                            (float) $appointment['price'],
                                            2
                                        ) ?>
                                    </td>

                                    <td>
                                        <span class="appointment-status <?= e(appointment_status_class($appointment['status'])) ?>">
                                            <?= e($appointment['status']) ?>
                                        </span>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
