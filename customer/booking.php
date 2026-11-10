
<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('customer');

$pageTitle = 'Book an Appointment';
$user = current_user();

$errors = [];
$services = [];
$stylists = [];
$customer = null;
$service = null;
$availableTimes = [];

$selectedService = (int) ($_GET['service_id'] ?? 0);
$selectedStylist = (int) ($_GET['stylist_id'] ?? 0);
$selectedDate = trim($_GET['date'] ?? date('Y-m-d'));

// ==========================================
// LOAD CUSTOMER AND SERVICES
// ==========================================

try {

    // Get the logged-in customer's profile.
    $customerStmt = db()->prepare(
        'SELECT id, full_name, phone, email
         FROM customers
         WHERE user_id = :user_id
         LIMIT 1'
    );

    $customerStmt->execute([
        ':user_id' => $user['id']
    ]);

    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        $errors[] = 'Your customer profile could not be found. Please contact the administrator.';
    }

    // Load active services.
    $serviceStmt = db()->query(
        'SELECT id, name, price, duration_minutes
         FROM services
         WHERE is_active = 1
         ORDER BY name ASC'
    );

    $services = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);

    // Identify selected service.
    foreach ($services as $item) {

        if ((int) $item['id'] === $selectedService) {
            $service = $item;
            break;
        }

    }

    // Load stylists assigned to the selected service.
    if ($service) {

        $stylistStmt = db()->prepare(
            'SELECT st.id, st.full_name
             FROM stylists st
             INNER JOIN stylist_services ss
                ON ss.stylist_id = st.id
             WHERE st.is_active = 1
               AND ss.service_id = :service_id
             ORDER BY st.full_name ASC'
        );

        $stylistStmt->execute([
            ':service_id' => $selectedService
        ]);

        $stylists = $stylistStmt->fetchAll(PDO::FETCH_ASSOC);

    }

} catch (PDOException $e) {

    error_log('Booking page error: ' . $e->getMessage());

    $errors[] = 'Unable to load booking information. Please try again later.';

}

// ==========================================
// VALIDATE APPOINTMENT DATE
// ==========================================

$dateObject = DateTime::createFromFormat('!Y-m-d', $selectedDate);

$validDate = (
    $dateObject !== false &&
    $dateObject->format('Y-m-d') === $selectedDate
);

$isPastDate = $validDate && $selectedDate < date('Y-m-d');

if (!$validDate || $isPastDate) {
    $errors[] = 'Please select a valid appointment date.';
}

// ==========================================
// GENERATE AVAILABLE TIME SLOTS
// ==========================================

$stylistExists = false;

if (
    $customer &&
    $service &&
    $selectedStylist > 0 &&
    $validDate &&
    !$isPastDate
) {

    // Verify that the stylist offers the selected service.
    $stylistExists = false;

    foreach ($stylists as $stylist) {

        if ((int) $stylist['id'] === $selectedStylist) {
            $stylistExists = true;
            break;
        }

    }

    if ($stylistExists) {

        try {

            $duration = (int) $service['duration_minutes'];

            // Get existing appointments.
            $bookingStmt = db()->prepare(
                "SELECT start_time, end_time
                 FROM appointments
                 WHERE stylist_id = :stylist_id
                   AND appointment_date = :appointment_date
                   AND status <> 'cancelled'"
            );

            $bookingStmt->execute([
                ':stylist_id' => $selectedStylist,
                ':appointment_date' => $selectedDate
            ]);

            $existingBookings = $bookingStmt->fetchAll(PDO::FETCH_ASSOC);

            // Salon hours: 10 AM to 8 PM.
            // Appointment starts every 30 minutes.

            for ($minutes = 600; $minutes < 1200; $minutes += 30) {

                $endMinutes = $minutes + $duration;

                // Appointment must finish before or at 8 PM.
                if ($endMinutes > 1200) {
                    continue;
                }

                $start = sprintf(
                    '%02d:%02d:00',
                    intdiv($minutes, 60),
                    $minutes % 60
                );

                $end = sprintf(
                    '%02d:%02d:00',
                    intdiv($endMinutes, 60),
                    $endMinutes % 60
                );

                // Do not offer past times today.
                if (
                    $selectedDate === date('Y-m-d') &&
                    $start <= date('H:i:s')
                ) {
                    continue;
                }

                $hasConflict = false;

                // Check for overlapping appointments.
                foreach ($existingBookings as $booking) {

                    if (
                        $start < $booking['end_time'] &&
                        $end > $booking['start_time']
                    ) {

                        $hasConflict = true;
                        break;

                    }

                }

                if (!$hasConflict) {
                    $availableTimes[] = substr($start, 0, 5);
                }

            }

        } catch (PDOException $e) {

            error_log('Booking availability error: ' . $e->getMessage());

            $errors[] = 'Unable to check available appointment times.';

        }

    }

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?> | Salon Studio</title>

    <link
        rel="stylesheet"
        href="<?= e(url('assets/css/style.css')) ?>"
    >

    <style>

        .booking-page {
            max-width: 850px;
            margin: 40px auto;
            padding: 25px;
        }

        .booking-heading {
            margin-bottom: 28px;
        }

        .booking-heading h2 {
            margin-bottom: 8px;
            color: #302820;
            font-family: Georgia, serif;
            font-size: 30px;
        }

        .booking-heading p {
            color: #81766b;
            font-size: 14px;
        }

        .booking-card {
            padding: 30px;
            background: #fff;
            border: 1px solid #e9e0d5;
            border-radius: 12px;
        }

        .booking-form-group {
            margin-bottom: 22px;
        }

        .booking-form-group label {
            display: block;
            margin-bottom: 9px;
            color: #493b30;
            font-size: 13px;
            font-weight: 600;
        }

        .booking-form-group select,
        .booking-form-group input,
        .booking-form-group textarea {
            width: 100%;
            min-height: 46px;
            padding: 12px;
            border: 1px solid #ded5ca;
            border-radius: 7px;
            background: #fff;
            color: #302820;
            font: inherit;
        }

        .booking-form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .booking-help {
            display: block;
            margin-top: 7px;
            color: #81766b;
            font-size: 12px;
        }

        .booking-submit {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 7px;
            background: #493b30;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .booking-submit:hover {
            background: #a88a5e;
        }

        .booking-alert {
            margin-bottom: 20px;
            padding: 13px 16px;
            border-radius: 7px;
            background: #fff0ee;
            color: #a33c32;
            font-size: 13px;
        }

        .booking-empty {
            padding: 15px;
            border-radius: 7px;
            background: #f8f5f0;
            color: #81766b;
            font-size: 13px;
        }

        .booking-back {
            display: inline-block;
            margin-bottom: 20px;
            color: #8b704c;
            font-size: 13px;
            text-decoration: none;
        }

        @media (max-width: 600px) {

            .booking-page {
                margin: 15px auto;
                padding: 15px;
            }

            .booking-card {
                padding: 20px;
            }

        }

    </style>

</head>

<body>

<div class="booking-page">

    <a
        href="<?= e(url('public/index.php')) ?>"
        class="booking-back"
    >
        ← Back to Homepage
    </a>

    <div class="booking-heading">

        <h2>Book Your Appointment</h2>

        <p>
            Choose your preferred service, stylist, date, and time.
        </p>

    </div>

    <!-- ERROR MESSAGES -->

    <?php foreach ($errors as $error): ?>

        <div class="booking-alert">
            <?= e($error) ?>
        </div>

    <?php endforeach; ?>


    <?php if (!$customer): ?>

        <div class="booking-empty">
            Your customer profile is not available.
            Please contact the administrator.
        </div>

    <?php else: ?>

        <div class="booking-card">

            <!-- SERVICE AND STYLIST SELECTION -->

            <form
                action="<?= e(url('customer/booking.php')) ?>"
                method="GET"
                id="bookingSelectionForm"
            >

                <div class="booking-form-group">

                    <label for="service_id">
                        Select Service
                    </label>

                    <select
                        name="service_id"
                        id="service_id"
                        required
                    >

                        <option value="">
                            Choose a service
                        </option>

                        <?php foreach ($services as $item): ?>

                            <option
                                value="<?= (int) $item['id'] ?>"
                                <?= $selectedService === (int) $item['id'] ? 'selected' : '' ?>
                            >

                                <?= e($item['name']) ?>
                                — ₱<?= number_format((float) $item['price'], 2) ?>
                                (<?= (int) $item['duration_minutes'] ?> minutes)

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="booking-form-group">

                    <label for="stylist_id">
                        Select Stylist
                    </label>

                    <select
                        name="stylist_id"
                        id="stylist_id"
                        <?= $service ? 'required' : 'disabled' ?>
                    >

                        <option value="">
                            <?= $service ? 'Choose a stylist' : 'Select a service first' ?>
                        </option>

                        <?php foreach ($stylists as $stylist): ?>

                            <option
                                value="<?= (int) $stylist['id'] ?>"
                                <?= $selectedStylist === (int) $stylist['id'] ? 'selected' : '' ?>
                            >

                                <?= e($stylist['full_name']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <?php if ($service && empty($stylists)): ?>

                        <small class="booking-help">
                            No active stylists are assigned to this service yet.
                        </small>

                    <?php endif; ?>

                </div>


                <div class="booking-form-group">

                    <label for="appointment_date">
                        Appointment Date
                    </label>

                    <input
                        type="date"
                        name="date"
                        id="appointment_date"
                        value="<?= e($selectedDate) ?>"
                        min="<?= e(date('Y-m-d')) ?>"
                        required
                    >

                    <small class="booking-help">
                        Salon hours: 10:00 AM – 8:00 PM, Monday to Sunday.
                    </small>

                </div>


                <button
                    type="submit"
                    class="booking-submit"
                >
                    Check Available Times
                </button>

            </form>


            <!-- AVAILABLE APPOINTMENT TIMES -->

            <?php if (
                $service &&
                $selectedStylist > 0 &&
                $validDate &&
                !$isPastDate &&
                $stylistExists
            ): ?>

                <hr style="margin: 28px 0; border: 0; border-top: 1px solid #e9e0d5;">

                <h3 style="margin-bottom: 8px; color: #493b30;">
                    Available Appointment Times
                </h3>

                <p style="margin-bottom: 18px; color: #81766b; font-size: 13px;">

                    <?= e($service['name']) ?> ·
                    <?= (int) $service['duration_minutes'] ?> minutes

                </p>


                <?php if (empty($availableTimes)): ?>

                    <div class="booking-empty">
                        No available time slots for this selection.
                        Please choose another date or stylist.
                    </div>

                <?php else: ?>

                    <!-- APPOINTMENT SUBMISSION -->

                    <form
                        action="<?= e(url('actions/create_appointment.php')) ?>"
                        method="POST"
                    >

                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="service_id"
                            value="<?= (int) $service['id'] ?>"
                        >

                        <input
                            type="hidden"
                            name="stylist_id"
                            value="<?= (int) $selectedStylist ?>"
                        >

                        <input
                            type="hidden"
                            name="appointment_date"
                            value="<?= e($selectedDate) ?>"
                        >


                        <div class="booking-form-group">

                            <label for="start_time">
                                Choose an Available Time
                            </label>

                            <select
                                name="start_time"
                                id="start_time"
                                required
                            >

                                <option value="">
                                    Select appointment time
                                </option>

                                <?php foreach ($availableTimes as $time): ?>

                                    <option value="<?= e($time) ?>">

                                        <?= e(date('g:i A', strtotime($time))) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="booking-form-group">

                            <label for="notes">
                                Additional Notes (Optional)
                            </label>

                            <textarea
                                name="notes"
                                id="notes"
                                maxlength="1000"
                                placeholder="Add any special requests or instructions..."
                            ></textarea>

                        </div>


                        <button
                            type="submit"
                            class="booking-submit"
                        >
                            Confirm Appointment
                        </button>

                    </form>

                <?php endif; ?>

            <?php elseif ($service && !$selectedStylist): ?>

                <div class="booking-empty">
                    Please select a stylist and click "Check Available Times" to see the available schedule.
                </div>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>

<script>

// Automatically reload the page when the customer changes service.
// This loads only the stylists assigned to the selected service.

document.addEventListener('DOMContentLoaded', function () {

    const serviceSelect = document.getElementById('service_id');
    const stylistSelect = document.getElementById('stylist_id');
    const bookingForm = document.getElementById('bookingSelectionForm');

    if (serviceSelect && stylistSelect && bookingForm) {

        serviceSelect.addEventListener('change', function () {

            stylistSelect.value = '';

            bookingForm.submit();

        });

    }

});

</script>

</body>
</html>
