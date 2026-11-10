
<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('customer');

$pageTitle = 'My Appointments';

$user = current_user();
$appointments = [];

$flashMessages = flash_get();

$successMessage = $flashMessages['success'] ?? '';
$errorMessage = $flashMessages['error'] ?? '';

try {

    // Get the customer's profile.
    $customerStmt = db()->prepare(
        'SELECT id
         FROM customers
         WHERE user_id = :user_id
         LIMIT 1'
    );

    $customerStmt->execute([
        ':user_id' => $user['id']
    ]);

    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        throw new RuntimeException('Customer profile not found.');
    }

    // Retrieve all appointments belonging to this customer.
    $appointmentStmt = db()->prepare(
        "SELECT
            a.id,
            a.appointment_date,
            a.start_time,
            a.end_time,
            a.status,
            a.source,
            a.notes,
            s.name AS service_name,
            s.price,
            s.duration_minutes,
            st.full_name AS stylist_name
         FROM appointments a
         INNER JOIN services s
            ON s.id = a.service_id
         INNER JOIN stylists st
            ON st.id = a.stylist_id
         WHERE a.customer_id = :customer_id
         ORDER BY
            (a.appointment_date < CURDATE()) ASC,
            a.appointment_date ASC,
            a.start_time ASC"
    );

    $appointmentStmt->execute([
        ':customer_id' => $customer['id']
    ]);

    $appointments = $appointmentStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log('My appointments error: ' . $e->getMessage());

    $errorMessage = 'Unable to load your appointments. Please try again later.';

} catch (RuntimeException $e) {

    error_log('My appointments profile error: ' . $e->getMessage());

    $errorMessage = $e->getMessage();

}

// Appointment status styling.
function my_appointment_status_class(string $status): string
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

// Check if a pending appointment can still be cancelled.
function can_cancel_appointment(array $appointment): bool
{
    $appointmentDateTime = strtotime(
        $appointment['appointment_date'] . ' ' . $appointment['start_time']
    );

    return
        $appointment['status'] === 'pending'
        && $appointmentDateTime !== false
        && $appointmentDateTime > time();
}

?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<style>

.my-appointments-page {
    max-width: 1250px;
    margin: 0 auto;
}

.appointments-intro {
    margin-bottom: 25px;
}

.appointments-intro h2 {
    margin-bottom: 7px;
    color: #302820;
    font-family: Georgia, serif;
    font-size: 27px;
}

.appointments-intro p {
    color: #81766b;
    font-size: 13px;
}

.appointments-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.appointment-summary-card {
    padding: 22px;
    background: #fff;
    border: 1px solid #e9e0d5;
    border-radius: 10px;
}

.appointment-summary-card span {
    color: #81766b;
    font-size: 12px;
}

.appointment-summary-card h3 {
    margin-top: 10px;
    color: #302820;
    font-size: 27px;
}

.appointments-panel {
    padding: 25px;
    background: #fff;
    border: 1px solid #e9e0d5;
    border-radius: 12px;
}

.appointments-panel-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 22px;
}

.appointments-panel-heading h3 {
    color: #302820;
    font-family: Georgia, serif;
    font-size: 21px;
}

.appointments-panel-heading p {
    margin-top: 6px;
    color: #81766b;
    font-size: 12px;
}

.book-more-btn {
    display: inline-block;
    padding: 12px 18px;
    background: #493b30;
    border-radius: 7px;
    color: #fff;
    font-size: 12px;
    text-decoration: none;
    white-space: nowrap;
}

.book-more-btn:hover {
    background: #a88a5e;
}

.appointments-table-wrapper {
    overflow-x: auto;
}

.appointments-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

.appointments-table th {
    padding: 15px 12px;
    background: #f7f3ed;
    color: #81766b;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .5px;
    white-space: nowrap;
}

.appointments-table td {
    padding: 17px 12px;
    border-bottom: 1px solid #eee7df;
    color: #493b30;
    font-size: 12px;
    vertical-align: middle;
}

.appointments-table tr:last-child td {
    border-bottom: none;
}

.appointment-service {
    color: #302820;
    font-weight: 600;
}

.appointment-status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    text-transform: capitalize;
    white-space: nowrap;
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

.cancel-appointment-form {
    margin: 0;
}

.cancel-appointment-btn {
    padding: 8px 12px;
    border: 1px solid #d9a39d;
    border-radius: 6px;
    background: #fff;
    color: #a34d43;
    font-family: inherit;
    font-size: 11px;
    cursor: pointer;
    transition: .2s ease;
}

.cancel-appointment-btn:hover {
    background: #fbe8e6;
}

.not-available {
    color: #a49a90;
    font-size: 11px;
}

.appointments-empty {
    padding: 55px 20px;
    text-align: center;
}

.appointments-empty h4 {
    margin-bottom: 8px;
    color: #493b30;
    font-family: Georgia, serif;
    font-size: 21px;
}

.appointments-empty p {
    margin-bottom: 20px;
    color: #81766b;
    font-size: 13px;
}

.appointment-alert {
    padding: 14px 18px;
    margin-bottom: 20px;
    border-radius: 7px;
    font-size: 13px;
}

.appointment-alert.success {
    background: #e7f4e9;
    color: #347348;
}

.appointment-alert.error {
    background: #fff0ee;
    color: #a33c32;
}

@media (max-width: 800px) {

    .appointments-summary {
        grid-template-columns: repeat(2, 1fr);
    }

    .appointments-panel-heading {
        align-items: flex-start;
        flex-direction: column;
    }

}

@media (max-width: 500px) {

    .appointments-summary {
        grid-template-columns: 1fr;
    }

    .appointments-panel {
        padding: 16px;
    }

}

</style>


<div class="my-appointments-page">

    <div class="appointments-intro">
        <h2>My Appointments</h2>
        <p>
            Keep track of your salon visits and booking requests.
        </p>
    </div>


    <?php if ($successMessage): ?>
        <div class="appointment-alert success">
            <?= e($successMessage) ?>
        </div>
    <?php endif; ?>


    <?php if ($errorMessage): ?>
        <div class="appointment-alert error">
            <?= e($errorMessage) ?>
        </div>
    <?php endif; ?>


    <?php
        $totalAppointments = count($appointments);
        $pendingCount = 0;
        $confirmedCount = 0;
        $completedCount = 0;

        foreach ($appointments as $appointment) {
            if ($appointment['status'] === 'pending') {
                $pendingCount++;
            } elseif ($appointment['status'] === 'confirmed') {
                $confirmedCount++;
            } elseif ($appointment['status'] === 'completed') {
                $completedCount++;
            }
        }
    ?>


    <!-- APPOINTMENT SUMMARY -->

    <section class="appointments-summary">

        <div class="appointment-summary-card">
            <span>Total Appointments</span>
            <h3><?= $totalAppointments ?></h3>
        </div>

        <div class="appointment-summary-card">
            <span>Pending</span>
            <h3><?= $pendingCount ?></h3>
        </div>

        <div class="appointment-summary-card">
            <span>Confirmed</span>
            <h3><?= $confirmedCount ?></h3>
        </div>

    </section>


    <!-- APPOINTMENT LIST -->

    <section class="appointments-panel">

        <div class="appointments-panel-heading">

            <div>
                <h3>Appointment History</h3>
                <p>
                    View your upcoming and previous salon appointments.
                </p>
            </div>

            <a
                href="<?= e(url('customer/booking.php')) ?>"
                class="book-more-btn"
            >
                + Book an Appointment
            </a>

        </div>


        <?php if (empty($appointments)): ?>

            <div class="appointments-empty">

                <h4>No appointments yet</h4>

                <p>
                    Your salon bookings will appear here once you make an appointment.
                </p>

                <a
                    href="<?= e(url('customer/booking.php')) ?>"
                    class="book-more-btn"
                >
                    Book Your First Appointment
                </a>

            </div>

        <?php else: ?>

            <div class="appointments-table-wrapper">

                <table class="appointments-table">

                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Stylist</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($appointments as $appointment): ?>

                            <tr>

                                <td>
                                    <span class="appointment-service">
                                        <?= e($appointment['service_name']) ?>
                                    </span>
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
                                    <span class="appointment-status <?= e(my_appointment_status_class($appointment['status'])) ?>">
                                        <?= e($appointment['status']) ?>
                                    </span>
                                </td>

                                <td>

                                    <?php if (can_cancel_appointment($appointment)): ?>

                                        <form
                                            action="<?= e(url('actions/cancel_appointment.php')) ?>"
                                            method="POST"
                                            class="cancel-appointment-form"
                                            onsubmit="return confirm('Are you sure you want to cancel this appointment?');"
                                        >

                                            <?= csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="appointment_id"
                                                value="<?= (int) $appointment['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="cancel-appointment-btn"
                                            >
                                                Cancel
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <span class="not-available">
                                            —
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
