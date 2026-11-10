
<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('customer');

require_post();
verify_csrf();

$user = current_user();

$appointmentId = filter_input(
    INPUT_POST,
    'appointment_id',
    FILTER_VALIDATE_INT
);

if (!$appointmentId || $appointmentId < 1) {
    flash_set('error', 'Invalid appointment selected.');
    redirect('customer/my_appointments.php');
}

try {

    $pdo = db();

    // Find the customer's profile.
    $customerStmt = $pdo->prepare(
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
        flash_set('error', 'Your customer profile could not be found.');
        redirect('customer/my_appointments.php');
    }

    // Find the appointment and ensure it belongs to this customer.
    $appointmentStmt = $pdo->prepare(
        'SELECT id, appointment_date, start_time, status
         FROM appointments
         WHERE id = :appointment_id
           AND customer_id = :customer_id
         LIMIT 1'
    );

    $appointmentStmt->execute([
        ':appointment_id' => $appointmentId,
        ':customer_id' => $customer['id']
    ]);

    $appointment = $appointmentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        flash_set('error', 'Appointment not found.');
        redirect('customer/my_appointments.php');
    }

    // Only pending appointments can be cancelled.
    if ($appointment['status'] !== 'pending') {
        flash_set('error', 'Only pending appointments can be cancelled.');
        redirect('customer/my_appointments.php');
    }

    // Do not allow cancellation after the appointment has started.
    $appointmentDateTime = strtotime(
        $appointment['appointment_date'] . ' ' . $appointment['start_time']
    );

    if ($appointmentDateTime === false || $appointmentDateTime <= time()) {
        flash_set('error', 'This appointment can no longer be cancelled online.');
        redirect('customer/my_appointments.php');
    }

    // Cancel the appointment.
    $updateStmt = $pdo->prepare(
        "UPDATE appointments
         SET status = 'cancelled'
         WHERE id = :appointment_id
           AND customer_id = :customer_id
           AND status = 'pending'"
    );

    $updateStmt->execute([
        ':appointment_id' => $appointmentId,
        ':customer_id' => $customer['id']
    ]);

    if ($updateStmt->rowCount() !== 1) {
        flash_set('error', 'The appointment could not be cancelled. Please refresh and try again.');
        redirect('customer/my_appointments.php');
    }

    flash_set('success', 'Your appointment has been cancelled successfully.');

    redirect('customer/my_appointments.php');

} catch (PDOException $e) {

    error_log('Cancel appointment error: ' . $e->getMessage());

    flash_set('error', 'Something went wrong while cancelling your appointment.');

    redirect('customer/my_appointments.php');
}
