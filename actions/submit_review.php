
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

$rating = filter_input(
    INPUT_POST,
    'rating',
    FILTER_VALIDATE_INT
);

$comment = trim($_POST['comment'] ?? '');

if (!$appointmentId || $appointmentId < 1) {
    flash_set('error', 'Invalid appointment.');
    redirect('customer/my_reviews.php');
}

if (!$rating || $rating < 1 || $rating > 5) {
    flash_set('error', 'Please select a valid rating from 1 to 5.');
    redirect('customer/my_reviews.php');
}

if (strlen($comment) > 500) {
    flash_set('error', 'Your comment must not exceed 500 characters.');
    redirect('customer/my_reviews.php');
}

try {

    // Find the customer's profile.
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
        flash_set('error', 'Your customer profile could not be found.');
        redirect('customer/my_reviews.php');
    }

    // Make sure the appointment belongs to this customer
    // and has already been completed.
    $appointmentStmt = db()->prepare(
        "SELECT id
         FROM appointments
         WHERE id = :appointment_id
         AND customer_id = :customer_id
         AND status = 'completed'
         LIMIT 1"
    );

    $appointmentStmt->execute([
        ':appointment_id' => $appointmentId,
        ':customer_id' => $customer['id']
    ]);

    $appointment = $appointmentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        flash_set(
            'error',
            'Only your completed appointments can be reviewed.'
        );

        redirect('customer/my_reviews.php');
    }

    // Check if the appointment already has a review.
    $existingStmt = db()->prepare(
        'SELECT id
         FROM reviews
         WHERE appointment_id = :appointment_id
         LIMIT 1'
    );

    $existingStmt->execute([
        ':appointment_id' => $appointmentId
    ]);

    if ($existingStmt->fetch()) {
        flash_set(
            'error',
            'You have already submitted a review for this appointment.'
        );

        redirect('customer/my_reviews.php');
    }

    // Save the review.
    $insertStmt = db()->prepare(
        'INSERT INTO reviews
            (appointment_id, customer_id, rating, comment)
         VALUES
            (:appointment_id, :customer_id, :rating, :comment)'
    );

    $insertStmt->execute([
        ':appointment_id' => $appointmentId,
        ':customer_id' => $customer['id'],
        ':rating' => $rating,
        ':comment' => $comment !== '' ? $comment : null
    ]);

    flash_set(
        'success',
        'Thank you! Your review has been submitted for approval.'
    );

    redirect('customer/my_reviews.php');

} catch (PDOException $e) {

    error_log('Review submission error: ' . $e->getMessage());

    // Also handles a duplicate submission caught by the unique constraint.
    if ($e->getCode() === '23000') {
        flash_set(
            'error',
            'A review for this appointment has already been submitted.'
        );
    } else {
        flash_set(
            'error',
            'Unable to submit your review. Please try again.'
        );
    }

    redirect('customer/my_reviews.php');
}
