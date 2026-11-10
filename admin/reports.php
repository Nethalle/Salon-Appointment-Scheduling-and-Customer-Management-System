
<?php
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pageTitle = 'Reports';
$pageSubtitle = 'View salon performance and business reports.';
$activePage = 'reports';

// ------------------------------------------
// DATE FILTERS
// ------------------------------------------

$today = date('Y-m-d');
$firstDayOfMonth = date('Y-m-01');

$startDate = $_GET['start_date'] ?? $firstDayOfMonth;
$endDate = $_GET['end_date'] ?? $today;

$dateValid = static function ($date): bool {
    if (!is_string($date)) {
        return false;
    }

    $parsed = DateTime::createFromFormat('!Y-m-d', $date);

    return $parsed && $parsed->format('Y-m-d') === $date;
};

if (!$dateValid($startDate)) {
    $startDate = $firstDayOfMonth;
}

if (!$dateValid($endDate)) {
    $endDate = $today;
}

if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

$params = [
    ':start_date' => $startDate,
    ':end_date' => $endDate,
];

// ------------------------------------------
// SUMMARY STATISTICS
// ------------------------------------------

$summarySql = "
    SELECT
        COUNT(*) AS total_appointments,

        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
        SUM(CASE WHEN status = 'no-show' THEN 1 ELSE 0 END) AS no_show,

        COALESCE(
            SUM(
                CASE
                    WHEN status = 'completed' THEN services.price
                    ELSE 0
                END
            ), 0
        ) AS total_revenue

    FROM appointments
    INNER JOIN services
        ON services.id = appointments.service_id

    WHERE appointment_date BETWEEN :start_date AND :end_date
";

$stmt = db()->prepare($summarySql);
$stmt->execute($params);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

$totalAppointments = (int) ($summary['total_appointments'] ?? 0);
$totalRevenue = (float) ($summary['total_revenue'] ?? 0);

// ------------------------------------------
// MOST POPULAR SERVICES
// ------------------------------------------

$serviceSql = "
    SELECT
        services.name,
        COUNT(appointments.id) AS total_bookings,
        SUM(
            CASE
                WHEN appointments.status = 'completed'
                THEN services.price
                ELSE 0
            END
        ) AS revenue

    FROM appointments

    INNER JOIN services
        ON services.id = appointments.service_id

    WHERE appointments.appointment_date
        BETWEEN :start_date AND :end_date

    GROUP BY services.id, services.name
    ORDER BY total_bookings DESC
";

$stmt = db()->prepare($serviceSql);
$stmt->execute($params);
$popularServices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ------------------------------------------
// STYLIST PERFORMANCE
// ------------------------------------------

$stylistSql = "
    SELECT
        stylists.full_name,
        COUNT(appointments.id) AS total_appointments,

        SUM(
            CASE
                WHEN appointments.status = 'completed' THEN 1
                ELSE 0
            END
        ) AS completed_appointments,

        SUM(
            CASE
                WHEN appointments.status = 'completed'
                THEN services.price
                ELSE 0
            END
        ) AS revenue

    FROM appointments

    INNER JOIN stylists
        ON stylists.id = appointments.stylist_id

    INNER JOIN services
        ON services.id = appointments.service_id

    WHERE appointments.appointment_date
        BETWEEN :start_date AND :end_date

    GROUP BY stylists.id, stylists.full_name
    ORDER BY completed_appointments DESC
";

$stmt = db()->prepare($stylistSql);
$stmt->execute($params);
$stylistPerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ------------------------------------------
// MONTHLY REVENUE
// ------------------------------------------

$monthlySql = "
    SELECT
        DATE_FORMAT(appointments.appointment_date, '%Y-%m')
            AS revenue_month,

        SUM(services.price) AS revenue

    FROM appointments

    INNER JOIN services
        ON services.id = appointments.service_id

    WHERE appointments.status = 'completed'
        AND appointments.appointment_date
            BETWEEN :start_date AND :end_date

    GROUP BY DATE_FORMAT(appointments.appointment_date, '%Y-%m')
    ORDER BY revenue_month DESC
    LIMIT 12
";

$stmt = db()->prepare($monthlySql);
$stmt->execute($params);
$monthlyRevenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ------------------------------------------
// CSV EXPORT
// ------------------------------------------

if (($_GET['export'] ?? '') === 'csv') {

    $exportSql = "
        SELECT
            appointments.id,
            customers.full_name AS customer,
            services.name AS service,
            stylists.full_name AS stylist,
            appointments.appointment_date,
            appointments.start_time,
            appointments.end_time,
            services.price,
            appointments.status

        FROM appointments

        INNER JOIN customers
            ON customers.id = appointments.customer_id

        INNER JOIN services
            ON services.id = appointments.service_id

        INNER JOIN stylists
            ON stylists.id = appointments.stylist_id

        WHERE appointments.appointment_date
            BETWEEN :start_date AND :end_date

        ORDER BY appointments.appointment_date DESC,
                 appointments.start_time DESC
    ";

    $stmt = db()->prepare($exportSql);
    $stmt->execute($params);

    header('Content-Type: text/csv; charset=utf-8');
    header(
        'Content-Disposition: attachment; filename="salon-report-' .
        $startDate . '-to-' . $endDate . '.csv"'
    );

    $output = fopen('php://output', 'w');

    // UTF-8 BOM for spreadsheet compatibility.
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, [
        'Appointment ID',
        'Customer',
        'Service',
        'Stylist',
        'Date',
        'Start Time',
        'End Time',
        'Price',
        'Status'
    ]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['customer'],
            $row['service'],
            $row['stylist'],
            $row['appointment_date'],
            $row['start_time'],
            $row['end_time'],
            $row['price'],
            $row['status']
        ]);
    }

    fclose($output);
    exit;
}

// ------------------------------------------
// MAXIMUM VALUES FOR BAR VISUALIZATION
// ------------------------------------------

$maxServiceBookings = 1;

foreach ($popularServices as $service) {
    $maxServiceBookings = max(
        $maxServiceBookings,
        (int) $service['total_bookings']
    );
}

$maxMonthlyRevenue = 1;

foreach ($monthlyRevenue as $month) {
    $maxMonthlyRevenue = max(
        $maxMonthlyRevenue,
        (float) $month['revenue']
    );
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-content">

    <section class="section-heading">
        <span class="eyebrow">SALON ANALYTICS</span>
        <h2>Business Reports</h2>
        <p>Monitor appointments, revenue, services, and stylist performance.</p>
    </section>

    <!-- DATE FILTER -->

    <section class="panel report-filter-panel">

        <form method="GET" class="report-filter-form">

            <div class="form-group">
                <label for="start_date">Start Date</label>
                <input
                    type="date"
                    name="start_date"
                    id="start_date"
                    value="<?= e($startDate) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="end_date">End Date</label>
                <input
                    type="date"
                    name="end_date"
                    id="end_date"
                    value="<?= e($endDate) ?>"
                    min="<?= e($startDate) ?>"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary">
                Apply Filter
            </button>

            <a href="<?= e(url('admin/reports.php')) ?>"
               class="btn btn-secondary">
                Reset
            </a>

            <button
                type="submit"
                name="export"
                value="csv"
                class="btn btn-secondary"
            >
                Export CSV
            </button>

        </form>

    </section>

    <!-- SUMMARY CARDS -->

    <section class="stats-grid">

        <div class="stat-card">
            <span>Total Appointments</span>
            <h3><?= number_format($totalAppointments) ?></h3>
        </div>

        <div class="stat-card">
            <span>Completed</span>
            <h3><?= number_format((int) $summary['completed']) ?></h3>
        </div>

        <div class="stat-card">
            <span>Pending</span>
            <h3><?= number_format((int) $summary['pending']) ?></h3>
        </div>

        <div class="stat-card">
            <span>Confirmed</span>
            <h3><?= number_format((int) $summary['confirmed']) ?></h3>
        </div>

        <div class="stat-card">
            <span>Cancelled</span>
            <h3><?= number_format((int) $summary['cancelled']) ?></h3>
        </div>

        <div class="stat-card">
            <span>Total Revenue</span>
            <h3>₱<?= number_format($totalRevenue, 2) ?></h3>
            <small>Completed appointments only</small>
        </div>

    </section>

    <!-- APPOINTMENT BREAKDOWN -->

    <section class="panel report-panel">

        <h3>Appointment Status Breakdown</h3>
        <p>Summary of appointments within the selected date range.</p>

        <div class="report-status-grid">

            <div>
                <span>Pending</span>
                <strong><?= number_format((int) $summary['pending']) ?></strong>
            </div>

            <div>
                <span>Confirmed</span>
                <strong><?= number_format((int) $summary['confirmed']) ?></strong>
            </div>

            <div>
                <span>Completed</span>
                <strong><?= number_format((int) $summary['completed']) ?></strong>
            </div>

            <div>
                <span>Cancelled</span>
                <strong><?= number_format((int) $summary['cancelled']) ?></strong>
            </div>

            <div>
                <span>No Show</span>
                <strong><?= number_format((int) $summary['no_show']) ?></strong>
            </div>

        </div>

    </section>

    <!-- POPULAR SERVICES -->

    <section class="panel report-panel">

        <h3>Most Popular Services</h3>
        <p>Services ranked by appointment bookings.</p>

        <?php if (empty($popularServices)): ?>

            <div class="empty-state">
                No service bookings found for this period.
            </div>

        <?php else: ?>

            <div class="report-table-wrapper">

                <table class="data-table">

                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Total Bookings</th>
                            <th>Completed Revenue</th>
                            <th>Popularity</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($popularServices as $service): ?>

                            <?php
                            $percentage = (
                                (int) $service['total_bookings']
                                / $maxServiceBookings
                            ) * 100;
                            ?>

                            <tr>
                                <td>
                                    <strong><?= e($service['name']) ?></strong>
                                </td>

                                <td>
                                    <?= number_format((int) $service['total_bookings']) ?>
                                </td>

                                <td>
                                    ₱<?= number_format((float) $service['revenue'], 2) ?>
                                </td>

                                <td>
                                    <div class="report-progress">
                                        <div
                                            class="report-progress-fill"
                                            style="width: <?= $percentage ?>%"
                                        ></div>
                                    </div>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

    <!-- STYLIST PERFORMANCE -->

    <section class="panel report-panel">

        <h3>Stylist Performance</h3>
        <p>Appointment and completed-service performance per stylist.</p>

        <?php if (empty($stylistPerformance)): ?>

            <div class="empty-state">
                No stylist appointments found for this period.
            </div>

        <?php else: ?>

            <div class="report-table-wrapper">

                <table class="data-table">

                    <thead>
                        <tr>
                            <th>Stylist</th>
                            <th>Total Appointments</th>
                            <th>Completed</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($stylistPerformance as $stylist): ?>

                            <tr>
                                <td>
                                    <strong><?= e($stylist['full_name']) ?></strong>
                                </td>

                                <td>
                                    <?= number_format((int) $stylist['total_appointments']) ?>
                                </td>

                                <td>
                                    <?= number_format((int) $stylist['completed_appointments']) ?>
                                </td>

                                <td>
                                    ₱<?= number_format((float) $stylist['revenue'], 2) ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

    <!-- MONTHLY REVENUE -->

    <section class="panel report-panel">

        <h3>Monthly Revenue</h3>
        <p>Revenue from completed appointments, grouped by month.</p>

        <?php if (empty($monthlyRevenue)): ?>

            <div class="empty-state">
                No completed appointment revenue found for this period.
            </div>

        <?php else: ?>

            <div class="monthly-revenue-list">

                <?php foreach ($monthlyRevenue as $month): ?>

                    <?php
                    $percentage = (
                        (float) $month['revenue']
                        / $maxMonthlyRevenue
                    ) * 100;

                    $monthLabel = date(
                        'F Y',
                        strtotime($month['revenue_month'] . '-01')
                    );
                    ?>

                    <div class="monthly-revenue-item">

                        <div class="monthly-revenue-heading">
                            <strong><?= e($monthLabel) ?></strong>

                            <span>
                                ₱<?= number_format((float) $month['revenue'], 2) ?>
                            </span>
                        </div>

                        <div class="report-progress">
                            <div
                                class="report-progress-fill"
                                style="width: <?= $percentage ?>%"
                            ></div>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
