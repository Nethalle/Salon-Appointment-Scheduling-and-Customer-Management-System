
<?php

// ==========================================
// SIDEBAR USER INFORMATION
// ==========================================

$sidebarUser = current_user() ?? [];

if (!is_array($sidebarUser)) {
    $sidebarUser = [];
}

$role = strtolower($sidebarUser['role'] ?? 'customer');

$displayName = $sidebarUser['name']
    ?? $sidebarUser['full_name']
    ?? $sidebarUser['email']
    ?? ucfirst($role);

$initial = strtoupper(substr($displayName, 0, 1));

$projectBase = rtrim(BASE_URL, '/');

$activePage = $activePage ?? 'dashboard';


// ==========================================
// ROLE-BASED NAVIGATION
// ==========================================

$navigation = [

    // ADMIN NAVIGATION
    'admin' => [

        [
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'icon' => '⌂',
            'path' => '/admin/dashboard.php'
        ],

        [
            'key' => 'appointments',
            'label' => 'Appointments',
            'icon' => '▦',
            'path' => '/admin/appointments.php'
        ],

        [
            'key' => 'services',
            'label' => 'Services',
            'icon' => '✂',
            'path' => '/admin/services.php'
        ],

        [
            'key' => 'stylists',
            'label' => 'Stylists',
            'icon' => '♙',
            'path' => '/admin/stylists.php'
        ],

        [
            'key' => 'users',
            'label' => 'Users',
            'icon' => '♧',
            'path' => '/admin/users.php'
        ],

        [
            'key' => 'reviews',
            'label' => 'Reviews',
            'icon' => '☆',
            'path' => '/admin/reviews.php'
        ],

        [
            'key' => 'reports',
            'label' => 'Reports',
            'icon' => '▤',
            'path' => '/admin/reports.php'
        ],

        [
            'key' => 'settings',
            'label' => 'Settings',
            'icon' => '⚙',
            'path' => '/admin/settings.php'
        ],

    ],


    // STAFF NAVIGATION
    'staff' => [

        [
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'icon' => '⌂',
            'path' => '/staff/dashboard.php'
        ],

        [
            'key' => 'appointments',
            'label' => 'Appointments',
            'icon' => '▦',
            'path' => '/staff/appointments.php'
        ],

        [
            'key' => 'schedule',
            'label' => 'Schedule',
            'icon' => '◷',
            'path' => '/staff/schedule.php'
        ],

        [
            'key' => 'customers',
            'label' => 'Customers',
            'icon' => '♙',
            'path' => '/staff/customers.php'
        ],

    ],


    // CUSTOMER NAVIGATION
    'customer' => [

        [
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'icon' => '⌂',
            'path' => '/customer/dashboard.php'
        ],

        [
            'key' => 'book',
            'label' => 'Book Appointment',
            'icon' => '＋',
            'path' => '/customer/booking.php'
        ],

        [
            'key' => 'my_appointments',
            'label' => 'My Appointments',
            'icon' => '▦',
            'path' => '/customer/my_appointments.php'
        ],

        [
            'key' => 'review',
            'label' => 'My Reviews',
            'icon' => '☆',
            'path' => '/customer/my_reviews.php'
        ],

    ],

];

$menuItems = $navigation[$role] ?? $navigation['customer'];

?>

<!-- SIDEBAR -->

<aside
    class="sidebar"
    id="sidebar"
    aria-label="Main navigation"
>

    <!-- BRAND -->

    <div class="sidebar-brand">

        <div class="brand-icon">
            S
        </div>

        <div class="brand-text">

            <h2>Salon Studio</h2>

            <span>BEAUTY & CARE</span>

        </div>

    </div>


    <!-- SECTION LABEL -->

    <div class="sidebar-section-label">
        MAIN MENU
    </div>


    <!-- NAVIGATION -->

    <nav class="sidebar-nav">

        <?php foreach ($menuItems as $item): ?>

            <a
                href="<?= e($projectBase . $item['path']) ?>"
                class="nav-link <?= $activePage === $item['key'] ? 'active' : '' ?>"
                <?= $activePage === $item['key'] ? 'aria-current="page"' : '' ?>
            >

                <span class="nav-icon" aria-hidden="true">
                    <?= e($item['icon']) ?>
                </span>

                <span class="nav-label">
                    <?= e($item['label']) ?>
                </span>

                <?php if ($activePage === $item['key']): ?>

                    <span class="active-indicator"></span>

                <?php endif; ?>

            </a>

        <?php endforeach; ?>

    </nav>


    <!-- SIDEBAR BOTTOM -->

    <div class="sidebar-bottom">

        <!-- USER INFORMATION -->

        <div class="sidebar-user">

            <div class="user-avatar sidebar-avatar">
                <?= e($initial) ?>
            </div>

            <div class="sidebar-user-info">

                <strong>
                    <?= e($displayName) ?>
                </strong>

                <span>
                    <?= e(ucfirst($role)) ?> Account
                </span>

            </div>

        </div>


        <!-- LOGOUT BUTTON -->

        <div class="sidebar-logout">

            <form
                action="<?= e(url('actions/logout.php')) ?>"
                method="POST"
            >

                <?= csrf_field() ?>

                <button type="submit" class="logout-button">

                    <span class="logout-icon">↪</span>

                    <span>Logout</span>

                </button>

            </form>

        </div>


        <!-- FOOTER -->

        <div class="sidebar-footer">

            <span>Salon Studio</span>

            <span>v1.0.0</span>

        </div>

    </div>

</aside>
