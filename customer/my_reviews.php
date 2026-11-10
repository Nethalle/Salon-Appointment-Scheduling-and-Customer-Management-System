
<?php

require_once __DIR__ . '/../includes/auth.php';

require_role('customer');

$pageTitle = 'My Reviews';
$user = current_user();

$customer = null;
$appointments = [];
$flashMessages = flash_get();

try {
    // Get the logged-in customer's profile.
    $customerStmt = db()->prepare(
        'SELECT id, full_name
         FROM customers
         WHERE user_id = :user_id
         LIMIT 1'
    );

    $customerStmt->execute([
        ':user_id' => $user['id']
    ]);

    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

    if ($customer) {
        // Get completed appointments and their existing reviews.
        $stmt = db()->prepare(
            'SELECT
                a.id AS appointment_id,
                a.appointment_date,
                a.start_time,
                a.end_time,
                s.name AS service_name,
                st.full_name AS stylist_name,
                r.id AS review_id,
                r.rating,
                r.comment,
                r.is_approved,
                r.created_at AS review_date
             FROM appointments a
             INNER JOIN services s ON s.id = a.service_id
             INNER JOIN stylists st ON st.id = a.stylist_id
             LEFT JOIN reviews r
                ON r.appointment_id = a.id
                AND r.customer_id = a.customer_id
             WHERE a.customer_id = :customer_id
             AND a.status = :status
             ORDER BY a.appointment_date DESC, a.start_time DESC'
        );

        $stmt->execute([
            ':customer_id' => $customer['id'],
            ':status' => 'completed'
        ]);

        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log('Customer reviews error: ' . $e->getMessage());
    $errorMessage = 'Unable to load your reviews. Please try again later.';
}

require_once __DIR__ . '/../includes/header.php';

?>

<style>
.reviews-page {
    max-width: 1100px;
    margin: 0 auto;
}

.reviews-heading {
    margin-bottom: 25px;
}

.reviews-heading h2 {
    color: #302820;
    font-family: Georgia, serif;
    font-size: 27px;
    margin-bottom: 8px;
}

.reviews-heading p {
    color: #81766b;
    font-size: 13px;
}

.review-alert {
    padding: 14px 18px;
    margin-bottom: 18px;
    border-radius: 7px;
    font-size: 13px;
}

.review-alert.success {
    background: #e7f4e9;
    color: #347348;
}

.review-alert.error {
    background: #fff0ee;
    color: #a33c32;
}

.review-card {
    background: #fff;
    border: 1px solid #e9e0d5;
    border-radius: 11px;
    padding: 25px;
    margin-bottom: 20px;
}

.review-card h3 {
    color: #302820;
    font-family: Georgia, serif;
    font-size: 20px;
    margin-bottom: 10px;
}

.review-details {
    color: #81766b;
    font-size: 12px;
    line-height: 1.9;
    margin-bottom: 18px;
}

.review-form label {
    display: block;
    color: #493b30;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 8px;
}

.review-form select,
.review-form textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #e0d4c6;
    border-radius: 7px;
    font-family: inherit;
    font-size: 13px;
    margin-bottom: 17px;
    background: #fff;
}

.review-form textarea {
    min-height: 110px;
    resize: vertical;
}

.review-submit {
    border: none;
    border-radius: 7px;
    background: #493b30;
    color: #fff;
    padding: 13px 20px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}

.review-submit:hover {
    background: #a88a5e;
}

.review-display {
    padding: 17px;
    background: #f7f3ed;
    border-radius: 8px;
}

.review-stars {
    color: #bd995e;
    font-size: 21px;
    letter-spacing: 3px;
    margin-bottom: 8px;
}

.review-comment {
    color: #66594d;
    font-size: 13px;
    line-height: 1.7;
    white-space: pre-wrap;
}

.review-empty {
    padding: 45px 20px;
    background: #fff;
    border: 1px solid #e9e0d5;
    border-radius: 11px;
    text-align: center;
}

.review-empty h3 {
    font-family: Georgia, serif;
    color: #302820;
    margin-bottom: 10px;
}

.review-empty p {
    color: #81766b;
    font-size: 13px;
}
</style>

<div class="reviews-page">

    <div class="reviews-heading">
        <h2>My Reviews</h2>
        <p>
            Share your experience with our salon services.
            You can review completed appointments.
        </p>
    </div>

    <?php foreach ($flashMessages as $message): ?>
        <?php if (is_array($message)): ?>
            <div class="review-alert <?= e($message['type'] ?? 'error') ?>">
                <?= e($message['message'] ?? '') ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if (isset($errorMessage)): ?>
        <div class="review-alert error">
            <?= e($errorMessage) ?>
        </div>
    <?php endif; ?>

    <?php if (!$customer): ?>

        <div class="review-alert error">
            Your customer profile could not be found.
            Please contact the administrator.
        </div>

    <?php elseif (empty($appointments)): ?>

        <div class="review-empty">
            <h3>No Completed Appointments Yet</h3>
            <p>
                Once your appointments are completed,
                you can share your experience here.
            </p>
        </div>

    <?php else: ?>

        <?php foreach ($appointments as $appointment): ?>

            <div class="review-card">

                <h3>
                    <?= e($appointment['service_name']) ?>
                </h3>

                <div class="review-details">
                    <strong>Stylist:</strong>
                    <?= e($appointment['stylist_name']) ?>
                    <br>

                    <strong>Date:</strong>
                    <?= e(date(
                        'F d, Y',
                        strtotime($appointment['appointment_date'])
                    )) ?>
                    <br>

                    <strong>Time:</strong>
                    <?= e(date(
                        'g:i A',
                        strtotime($appointment['start_time'])
                    )) ?>
                    –
                    <?= e(date(
                        'g:i A',
                        strtotime($appointment['end_time'])
                    )) ?>
                </div>

                <?php if ($appointment['review_id']): ?>

                    <div class="review-display">

                        <div class="review-stars">
                            <?= str_repeat('★', (int) $appointment['rating']) ?>
                            <?= str_repeat('☆', 5 - (int) $appointment['rating']) ?>
                        </div>

                        <p class="review-comment">
                            <?= e($appointment['comment'] ?: 'No written comment provided.') ?>
                        </p>

                        <p style="margin-top: 12px; font-size: 11px; color: #81766b;">
                            Submitted on
                            <?= e(date(
                                'F d, Y',
                                strtotime($appointment['review_date'])
                            )) ?>
                            ·
                            <?= (int) $appointment['is_approved'] === 1
                                ? 'Approved'
                                : 'Pending approval' ?>
                        </p>

                    </div>

                <?php else: ?>

                    <form
                        class="review-form"
                        action="<?= e(url('actions/submit_review.php')) ?>"
                        method="POST"
                    >

                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="appointment_id"
                            value="<?= (int) $appointment['appointment_id'] ?>"
                        >

                        <label for="rating-<?= (int) $appointment['appointment_id'] ?>">
                            Your Rating
                        </label>

                        <select
                            name="rating"
                            id="rating-<?= (int) $appointment['appointment_id'] ?>"
                            required
                        >
                            <option value="">Select your rating</option>
                            <option value="5">★★★★★ - Excellent</option>
                            <option value="4">★★★★☆ - Very Good</option>
                            <option value="3">★★★☆☆ - Good</option>
                            <option value="2">★★☆☆☆ - Fair</option>
                            <option value="1">★☆☆☆☆ - Poor</option>
                        </select>

                        <label for="comment-<?= (int) $appointment['appointment_id'] ?>">
                            Your Comment (Optional)
                        </label>

                        <textarea
                            name="comment"
                            id="comment-<?= (int) $appointment['appointment_id'] ?>"
                            maxlength="500"
                            placeholder="Tell us about your salon experience..."
                        ></textarea>

                        <button type="submit" class="review-submit">
                            Submit Review
                        </button>

                    </form>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
