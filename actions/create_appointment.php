
<?php
require_once __DIR__ . '/../includes/auth.php';

require_login();
require_post();
verify_csrf();

// Only customers can create online appointments.
if (current_user()['role'] !== 'customer') {
    http_response_code(403);
    exit('Only customer accounts can book appointments.');
}

$userId = (int) current_user()['id'];

$serviceId = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
$stylistId = filter_input(INPUT_POST, 'stylist_id', FILTER_VALIDATE_INT);
$appointmentDate = trim($_POST['appointment_date'] ?? '');
$startTime = trim($_POST['start_time'] ?? '');
$notes = trim($_POST['notes'] ?? '');

function booking_redirect(string $message): void
{
    flash_set('error', $message);
    redirect('public/booking.php');
}

if (!$serviceId || !$stylistId || $appointmentDate === '' || $startTime === '') {
    booking_redirect('Please complete all required appointment fields.');
}

// Validate date.
$date = DateTime::createFromFormat('!Y-m-d', $appointmentDate);

if (!$date || $date->format('Y-m-d') !== $appointmentDate) {
    booking_redirect('Please select a valid appointment date.');
}

$today = new DateTime('today');

if ($date < $today) {
    booking_redirect('You cannot book an appointment in the past.');
}

// Validate time format.
$time = DateTime::createFromFormat('!H:i', $startTime);

if (!$time || $time->format('H:i') !== $startTime) {
    booking_redirect('Please select a valid appointment time.');
}

try {
    $pdo = db();

    // Confirm the selected service is active.
    $serviceStmt = $pdo->prepare(
        'SELECT id, duration_minutes
         FROM services
         WHERE id = :service_id
           AND is_active = 1
         LIMIT 1'
    );
    $serviceStmt->execute([':service_id' => $serviceId]);
    $service = $serviceStmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        booking_redirect('The selected service is unavailable.');
    }

    // Confirm the stylist is active and assigned to this service.
    $stylistStmt = $pdo->prepare(
        'SELECT st.id
         FROM stylists st
         INNER JOIN stylist_services ss
            ON ss.stylist_id = st.id
         WHERE st.id = :stylist_id
           AND ss.service_id = :service_id
           AND st.is_active = 1
         LIMIT 1'
    );
    $stylistStmt->execute([
        ':stylist_id' => $stylistId,
        ':service_id' => $serviceId,
    ]);

    if (!$stylistStmt->fetch(PDO::FETCH_ASSOC)) {
        booking_redirect('The selected stylist cannot perform this service.');
    }

    // Calculate appointment end time using the service duration.
    $duration = (int) $service['duration_minutes'];

    if ($duration <= 0) {
        booking_redirect('The service duration is invalid.');
    }

    $startDateTime = new DateTime(
        $appointmentDate . ' ' . $startTime
    );

    $endDateTime = clone $startDateTime;
    $endDateTime->modify('+' . $duration . ' minutes');

    $endTime = $endDateTime->format('H:i:s');
    $startTimeForDb = $startDateTime->format('H:i:s');

    // Salon hours: 10:00 AM to 8:00 PM.
    $openingTime = new DateTime($appointmentDate . ' 10:00:00');
    $closingTime = new DateTime($appointmentDate . ' 20:00:00');

    if (
        $startDateTime < $openingTime ||
        $endDateTime > $closingTime
    ) {
        booking_redirect('Appointments must be within salon hours (10:00 AM–8:00 PM).');
    }

    // Prevent overlapping appointments for the same stylist.
    $conflictStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM appointments
         WHERE stylist_id = :stylist_id
           AND appointment_date = :appointment_date
           AND status IN ('pending', 'confirmed')
           AND start_time < :end_time
           AND end_time > :start_time"
    );

    $conflictStmt->execute([
        ':stylist_id' => $stylistId,
        ':appointment_date' => $appointmentDate,
        ':start_time' => $startTimeForDb,
        ':end_time' => $endTime,
    ]);

    if ((int) $conflictStmt->fetchColumn() > 0) {
        booking_redirect('This time slot is no longer available. Please choose another time.');
    }

    // Confirm the customer profile exists.
    $customerStmt = $pdo->prepare(
        'SELECT id
         FROM customers
         WHERE user_id = :user_id
         LIMIT 1'
    );
    $customerStmt->execute([':user_id' => $userId]);
    $customerId = $customerStmt->fetchColumn();

    if (!$customerId) {
        booking_redirect('Your customer profile was not found. Please contact the salon.');
    }

    // Save appointment.
    $insertStmt = $pdo->prepare(
        "INSERT INTO appointments (
            customer_id,
            stylist_id,
            service_id,
            appointment_date,
            start_time,
            end_time,
            status,
            source,
            notes,
            created_by
        ) VALUES (
            :customer_id,
            :stylist_id,
            :service_id,
            :appointment_date,
            :start_time,
            :end_time,
            'pending',
            'online',
            :notes,
            :created_by
        )"
    );

    $insertStmt->execute([
        ':customer_id' => $customerId,
        ':stylist_id' => $stylistId,
        ':service_id' => $serviceId,
        ':appointment_date' => $appointmentDate,
        ':start_time' => $startTimeForDb,
        ':end_time' => $endTime,
        ':notes' => $notes !== '' ? $notes : null,
        ':created_by' => $userId,
    ]);

    flash_set('success', 'Your appointment has been submitted successfully! It is awaiting confirmation.');
    redirect('customer/dashboard.php');

} catch (PDOException $e) {
    error_log('Appointment creation error: ' . $e->getMessage());

    flash_set('error', 'Something went wrong while creating your appointment. Please try again.');
    redirect('customer/booking.php');
}
