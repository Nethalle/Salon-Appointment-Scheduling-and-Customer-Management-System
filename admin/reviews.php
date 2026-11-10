
<?php
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pageTitle = 'Reviews';
$pageSubtitle = 'Manage customer feedback and ratings.';
$activePage = 'reviews';


// ==========================================
// HANDLE REVIEW APPROVAL
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post();
    verify_csrf();

    $reviewId = filter_var(
        $_POST['review_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $newStatus = $_POST['new_status'] ?? '';

    if (
        !$reviewId
        || !in_array($newStatus, ['0', '1'], true)
    ) {
        flash_set('error', 'Invalid review update request.');
        redirect('admin/reviews.php');
    }

    try {

        $stmt = db()->prepare(
            'UPDATE reviews
             SET is_approved = :status
             WHERE id = :id'
        );

        $stmt->execute([
            ':status' => (int) $newStatus,
            ':id' => $reviewId
        ]);

        if ($stmt->rowCount() === 0) {

            $check = db()->prepare(
                'SELECT id, is_approved
                 FROM reviews
                 WHERE id = :id'
            );

            $check->execute([':id' => $reviewId]);

            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                flash_set('error', 'Review not found.');
                redirect('admin/reviews.php');
            }
        }

        flash_set(
            'success',
            $newStatus === '1'
                ? 'Review approved successfully.'
                : 'Review hidden successfully.'
        );

    } catch (PDOException $e) {

        error_log('Review update error: ' . $e->getMessage());

        flash_set('error', 'Unable to update the review.');
    }

    redirect('admin/reviews.php');
}


// ==========================================
// FILTERS
// ==========================================

$statusFilter = $_GET['status'] ?? '';
$ratingFilter = $_GET['rating'] ?? '';

if (!in_array($statusFilter, ['approved', 'pending'], true)) {
    $statusFilter = '';
}

if (
    $ratingFilter !== ''
    && (
        !ctype_digit((string) $ratingFilter)
        || (int) $ratingFilter < 1
        || (int) $ratingFilter > 5
    )
) {
    $ratingFilter = '';
}


// ==========================================
// REVIEW STATISTICS
// ==========================================

try {

    $statsStmt = db()->query(
        "SELECT
            COUNT(*) AS total_reviews,
            COALESCE(SUM(is_approved = 1), 0) AS approved_reviews,
            COALESCE(SUM(is_approved = 0), 0) AS pending_reviews,
            COALESCE(AVG(rating), 0) AS average_rating
         FROM reviews"
    );

    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log('Review statistics error: ' . $e->getMessage());

    $stats = [
        'total_reviews' => 0,
        'approved_reviews' => 0,
        'pending_reviews' => 0,
        'average_rating' => 0
    ];
}


// ==========================================
// LOAD REVIEWS
// ==========================================

try {

    $sql = "
        SELECT
            r.id,
            r.appointment_id,
            r.customer_id,
            r.rating,
            r.comment,
            r.is_approved,
            r.created_at,
            c.full_name AS customer_name,
            s.name AS service_name,
            st.full_name AS stylist_name,
            a.appointment_date
        FROM reviews r
        LEFT JOIN customers c
            ON c.id = r.customer_id
        LEFT JOIN appointments a
            ON a.id = r.appointment_id
        LEFT JOIN services s
            ON s.id = a.service_id
        LEFT JOIN stylists st
            ON st.id = a.stylist_id
        WHERE 1 = 1
    ";

    $params = [];

    if ($statusFilter === 'approved') {
        $sql .= ' AND r.is_approved = 1';
    } elseif ($statusFilter === 'pending') {
        $sql .= ' AND r.is_approved = 0';
    }

    if ($ratingFilter !== '') {
        $sql .= ' AND r.rating = :rating';
        $params[':rating'] = (int) $ratingFilter;
    }

    $sql .= ' ORDER BY r.created_at DESC, r.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log('Load reviews error: ' . $e->getMessage());

    $reviews = [];

    flash_set('error', 'Unable to load customer reviews.');
}


// ==========================================
// PAGE LAYOUT
// ==========================================

require_once __DIR__ . '/../includes/header.php';

$messages = flash_get();

?>

<style>
.reviews-page {
    max-width: 1450px;
    margin: 0 auto;
}

.reviews-heading {
    margin-bottom: 24px;
}

.reviews-eyebrow {
    color: #bd9251;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.reviews-heading h2 {
    color: #302820;
    font-family: Georgia, serif;
    font-size: 28px;
    margin: 0 0 7px;
}

.reviews-heading p {
    color: #927f70;
    font-size: 12px;
    margin: 0;
}

.reviews-alert {
    padding: 13px 16px;
    border-radius: 7px;
    margin-bottom: 18px;
    font-size: 12px;
}

.reviews-alert.success {
    background: #e7f4e9;
    border: 1px solid #c9e7cf;
    color: #287443;
}

.reviews-alert.error {
    background: #fff0ef;
    border: 1px solid #f1d1ce;
    color: #a33b35;
}

.reviews-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.reviews-stat {
    background: #fffdfa;
    border: 1px solid #e9ded1;
    border-radius: 10px;
    padding: 22px;
}

.reviews-stat span {
    display: block;
    color: #927f70;
    font-size: 12px;
    margin-bottom: 10px;
}

.reviews-stat strong {
    color: #302820;
    font-family: Georgia, serif;
    font-size: 28px;
}

.reviews-card {
    background: #fffdfa;
    border: 1px solid #e9ded1;
    border-radius: 11px;
    padding: 24px;
    margin-bottom: 24px;
}

.reviews-card-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}

.reviews-card-heading h3 {
    color: #302820;
    font-family: Georgia, serif;
    font-size: 21px;
    margin: 0 0 6px;
}

.reviews-card-heading p {
    color: #927f70;
    font-size: 12px;
    margin: 0;
}

.reviews-filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.reviews-filters select {
    min-height: 39px;
    padding: 8px 12px;
    border: 1px solid #dfd1c1;
    border-radius: 7px;
    background: #fff;
    color: #49392e;
    font-family: inherit;
    font-size: 12px;
}

.reviews-button {
    padding: 10px 15px;
    border: none;
    border-radius: 7px;
    background: #49392e;
    color: #fff;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}

.reviews-reset {
    display: inline-flex;
    align-items: center;
    padding: 9px 14px;
    border: 1px solid #dfd1c1;
    border-radius: 7px;
    color: #795c45;
    font-size: 12px;
    text-decoration: none;
}

.reviews-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.reviews-table {
    width: 100%;
    min-width: 1050px;
    border-collapse: collapse;
}

.reviews-table th {
    padding: 14px 12px;
    background: #f6f1eb;
    color: #927f70;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .6px;
    text-align: left;
    text-transform: uppercase;
}

.reviews-table td {
    padding: 15px 12px;
    border-bottom: 1px solid #eee3d8;
    color: #49392e;
    font-size: 12px;
    vertical-align: top;
}

.reviews-table tr:last-child td {
    border-bottom: none;
}

.review-customer {
    display: block;
    color: #302820;
    font-weight: 700;
    margin-bottom: 4px;
}

.review-date {
    color: #927f70;
    font-size: 10px;
}

.review-stars {
    color: #c49343;
    font-size: 15px;
    letter-spacing: 1px;
    white-space: nowrap;
}

.review-comment {
    display: block;
    max-width: 260px;
    color: #795c45;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.review-status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
}

.review-status.approved {
    background: #e5f3e8;
    color: #287443;
}

.review-status.pending {
    background: #fff2d9;
    color: #95620d;
}

.review-action-form {
    display: inline;
}

.review-action-button {
    padding: 8px 11px;
    border: 1px solid #dfd1c1;
    border-radius: 6px;
    background: #fff;
    color: #795c45;
    font-family: inherit;
    font-size: 11px;
    cursor: pointer;
}

.review-action-button.approve {
    border-color: #c9e7cf;
    color: #287443;
}

.review-action-button:hover {
    background: #f6f1eb;
}

.reviews-empty {
    padding: 35px 15px !important;
    color: #927f70 !important;
    text-align: center;
}

@media (max-width: 900px) {
    .reviews-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 500px) {
    .reviews-stats {
        grid-template-columns: 1fr;
    }

    .reviews-card {
        padding: 16px;
    }
}
</style>


<div class="reviews-page">

    <!-- HEADING -->

    <div class="reviews-heading">
        <div class="reviews-eyebrow">Salon Management</div>
        <h2>Customer Reviews</h2>
        <p>Monitor customer satisfaction and manage submitted feedback.</p>
    </div>


    <!-- MESSAGES -->

    <?php foreach ($messages as $message): ?>

        <div class="reviews-alert <?= e($message['type']) ?>">
            <?= e($message['message']) ?>
        </div>

    <?php endforeach; ?>


    <!-- STATISTICS -->

    <div class="reviews-stats">

        <div class="reviews-stat">
            <span>Total Reviews</span>
            <strong><?= (int) $stats['total_reviews'] ?></strong>
        </div>

        <div class="reviews-stat">
            <span>Approved Reviews</span>
            <strong><?= (int) $stats['approved_reviews'] ?></strong>
        </div>

        <div class="reviews-stat">
            <span>Pending Reviews</span>
            <strong><?= (int) $stats['pending_reviews'] ?></strong>
        </div>

        <div class="reviews-stat">
            <span>Average Rating</span>
            <strong>
                <?= number_format((float) $stats['average_rating'], 1) ?>
                <span style="display:inline;color:#c49343;font-size:18px;">★</span>
            </strong>
        </div>

    </div>


    <!-- REVIEW TABLE -->

    <section class="reviews-card">

        <div class="reviews-card-heading">

            <div>
                <h3>All Customer Reviews</h3>
                <p>Review customer feedback and control which reviews are approved.</p>
            </div>

        </div>


        <!-- FILTERS -->

        <form method="GET" class="reviews-filters">

            <select name="status">

                <option value="">All Statuses</option>

                <option value="approved"
                    <?= $statusFilter === 'approved' ? 'selected' : '' ?>>
                    Approved
                </option>

                <option value="pending"
                    <?= $statusFilter === 'pending' ? 'selected' : '' ?>>
                    Pending
                </option>

            </select>


            <select name="rating">

                <option value="">All Ratings</option>

                <?php for ($i = 5; $i >= 1; $i--): ?>

                    <option value="<?= $i ?>"
                        <?= (string) $ratingFilter === (string) $i ? 'selected' : '' ?>>
                        <?= $i ?> Star<?= $i > 1 ? 's' : '' ?>
                    </option>

                <?php endfor; ?>

            </select>


            <button type="submit" class="reviews-button">
                Apply Filters
            </button>


            <a
                href="<?= e(url('admin/reviews.php')) ?>"
                class="reviews-reset"
            >
                Reset
            </a>

        </form>


        <!-- TABLE -->

        <div class="reviews-table-wrapper">

            <table class="reviews-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Stylist</th>
                        <th>Appointment Date</th>
                        <th>Rating</th>
                        <th>Comment</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($reviews)): ?>

                        <tr>
                            <td colspan="9" class="reviews-empty">
                                No customer reviews found.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($reviews as $review): ?>

                            <?php
                            $approved = (int) $review['is_approved'] === 1;
                            $rating = (int) $review['rating'];
                            ?>

                            <tr>

                                <td>
                                    #<?= (int) $review['id'] ?>
                                </td>


                                <td>

                                    <span class="review-customer">
                                        <?= e($review['customer_name'] ?? 'Unknown Customer') ?>
                                    </span>

                                    <span class="review-date">
                                        <?= e(date('M d, Y', strtotime($review['created_at']))) ?>
                                    </span>

                                </td>


                                <td>
                                    <?= e($review['service_name'] ?? '—') ?>
                                </td>


                                <td>
                                    <?= e($review['stylist_name'] ?? '—') ?>
                                </td>


                                <td>
                                    <?= !empty($review['appointment_date'])
                                        ? e(date('M d, Y', strtotime($review['appointment_date'])))
                                        : '—' ?>
                                </td>


                                <td>

                                    <span class="review-stars"
                                        title="<?= $rating ?> out of 5 stars">

                                        <?= str_repeat('★', $rating) ?><?= str_repeat('☆', 5 - $rating) ?>

                                    </span>

                                </td>


                                <td>

                                    <span class="review-comment">
                                        <?= $review['comment'] !== null && $review['comment'] !== ''
                                            ? e($review['comment'])
                                            : 'No written comment.' ?>
                                    </span>

                                </td>


                                <td>

                                    <span class="review-status <?= $approved ? 'approved' : 'pending' ?>">
                                        <?= $approved ? 'Approved' : 'Pending' ?>
                                    </span>

                                </td>


                                <td>

                                    <form
                                        method="POST"
                                        action="<?= e(url('admin/reviews.php')) ?>"
                                        class="review-action-form"
                                        onsubmit="return confirm('Are you sure you want to <?= $approved ? 'hide' : 'approve' ?> this review?');"
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="review_id"
                                            value="<?= (int) $review['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="new_status"
                                            value="<?= $approved ? '0' : '1' ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="review-action-button <?= $approved ? '' : 'approve' ?>"
                                        >
                                            <?= $approved ? 'Hide Review' : 'Approve' ?>
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
