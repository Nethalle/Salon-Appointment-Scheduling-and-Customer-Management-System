
<?php

require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Welcome to Salon Studio';

$user = current_user();

// ==========================================
// LOAD ACTIVE SALON SERVICES
// ==========================================

$services = [];

try {
    $stmt = db()->prepare(
        'SELECT id, name, price, duration_minutes
         FROM services
         WHERE is_active = 1
         ORDER BY id ASC'
    );

    $stmt->execute();

    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log('Homepage services error: ' . $e->getMessage());

    $services = [];
}


$dashboardUrl = $user
    ? url(dashboard_path($user['role']))
    : url('public/login.php');

$dashboardLabel = $user
    ? 'My Dashboard'
    : 'Sign In';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta
        name="description"
        content="Discover Salon Studio, your destination for beauty, care, and confidence. Explore our salon services and book your appointment."
    >

    <meta name="theme-color" content="#302820">

    <title><?= e($pageTitle) ?> | Salon Studio</title>

    <link
        rel="stylesheet"
        href="<?= e(url('assets/css/home.css')) ?>"
    >

</head>

<body>

<!-- NAVIGATION -->

<header class="home-header">

    <nav class="home-nav">

        <a href="<?= e(url('public/index.php')) ?>" class="home-brand">

            <span class="home-brand-icon">S</span>

            <span class="home-brand-text">
                <strong>Salon Studio</strong>
                <small>BEAUTY & CARE</small>
            </span>

        </a>

        <button
            class="mobile-menu-toggle"
            id="mobileMenuToggle"
            type="button"
            aria-label="Toggle navigation"
            aria-expanded="false"
        >
            ☰
        </button>

        <div class="home-nav-links" id="homeNavLinks">

            <a href="#home" class="home-nav-link active">Home</a>

            <a href="#about" class="home-nav-link">About</a>

            <a href="#services" class="home-nav-link">Services</a>

            <a href="#why-us" class="home-nav-link">Why Choose Us</a>

            <a href="#contact" class="home-nav-link">Contact</a>

            <div class="home-nav-actions">

                <a
                    href="<?= e($dashboardUrl) ?>"
                    class="nav-login-btn"
                >
                    <?= e($dashboardLabel) ?>
                </a>

                <?php if (!$user): ?>

                    <a
                        href="<?= e(url('public/register.php')) ?>"
                        class="nav-register-btn"
                    >
                        Create Account
                    </a>

                <?php endif; ?>

            </div>

        </div>

    </nav>

</header>


<main>

    <!-- HERO SECTION -->

    <section class="hero-section" id="home">

        <div class="hero-content">

            <span class="hero-eyebrow">
                WELCOME TO SALON STUDIO
            </span>

            <h1>
                Beauty begins with
                <span>self-care.</span>
            </h1>

            <p>
                Take a moment for yourself. Discover a place where
                beauty, relaxation, and confidence come together.
                Let us help you look and feel your best.
            </p>

            <div class="hero-buttons">

                <a
                    href="<?= e($user ? $dashboardUrl : url('public/login.php')) ?>"
                    class="primary-btn"
                >
                    Book an Appointment
                    <span>→</span>
                </a>

                <a href="#services" class="secondary-btn">
                    Explore Our Services
                </a>

            </div>

            <div class="hero-note">
                <span class="hero-note-line"></span>
                <span>BEAUTY. ELEGANCE. CONFIDENCE.</span>
            </div>

        </div>

        <div class="hero-visual">

            <div class="hero-image-frame">

                <img
                    src="https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=1100&q=85"
                    alt="Elegant and relaxing salon interior"
                >

            </div>

            <div class="hero-floating-card">

                <span class="floating-card-icon">✧</span>

                <div>
                    <strong>Your Beauty, Our Care</strong>
                    <small>A moment just for you</small>
                </div>

            </div>

            <div class="hero-decoration"></div>

        </div>

    </section>


    <!-- INTRODUCTION STRIP -->

    <section class="intro-strip">

        <div>
            <span class="intro-symbol">✧</span>
            <strong>Beauty</strong>
            <small>Celebrate your individuality</small>
        </div>

        <div>
            <span class="intro-symbol">♡</span>
            <strong>Care</strong>
            <small>Make time for yourself</small>
        </div>

        <div>
            <span class="intro-symbol">✦</span>
            <strong>Confidence</strong>
            <small>Feel good in your own skin</small>
        </div>

    </section>


    <!-- ABOUT SECTION -->

    <section class="about-section section-padding" id="about">

        <div class="about-image">

            <img
                src="https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?auto=format&fit=crop&w=1000&q=85"
                alt="Professional salon styling space"
                loading="lazy"
            >

            <div class="about-image-caption">
                <span>YOUR TIME.</span>
                <strong>YOUR BEAUTY.</strong>
            </div>

        </div>

        <div class="about-content">

            <span class="section-eyebrow">GET TO KNOW US</span>

            <h2>
                A little care goes
                <span>a long way.</span>
            </h2>

            <p>
                At Salon Studio, we believe that self-care is more
                than a routine. It is an opportunity to relax,
                express yourself, and feel confident.
            </p>

            <p>
                Our goal is to provide a welcoming salon experience
                where every client feels comfortable, valued,
                and cared for.
            </p>

            <a href="#contact" class="text-link">
                Get in touch <span>→</span>
            </a>

        </div>

    </section>


    
<!-- SERVICES SECTION -->

<section class="services-section section-padding" id="services">

    <div class="section-heading-centered">

        <span class="section-eyebrow">WHAT WE OFFER</span>

        <h2>
            Beauty and care,
            <span>made for you.</span>
        </h2>

        <p>
            Explore our salon services and find the perfect
            treatment for your beauty and self-care needs.
        </p>

    </div>


    <div class="service-cards">

        <?php if (!empty($services)): ?>

            <?php foreach ($services as $service): ?>

                <?php

                $serviceName = $service['name'];
                $serviceNameLower = strtolower($serviceName);

                // Default service image
                $serviceImage = 'https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=800&q=85';

                // Select an image based on service name
                if (
                    str_contains($serviceNameLower, 'manicure')
                    || str_contains($serviceNameLower, 'pedicure')
                    || str_contains($serviceNameLower, 'nail')
                ) {

                    $serviceImage = 'https://images.unsplash.com/photo-1604654894610-df63bc536371?auto=format&fit=crop&w=800&q=85';

                } elseif (
                    str_contains($serviceNameLower, 'hair')
                    || str_contains($serviceNameLower, 'rebond')
                    || str_contains($serviceNameLower, 'color')
                    || str_contains($serviceNameLower, 'cut')
                ) {

                    $serviceImage = 'https://images.unsplash.com/photo-1562322140-8baeececf3df?auto=format&fit=crop&w=800&q=85';

                }

                ?>

                <article class="service-card">

                    <div class="service-image">

                        <img
                            src="<?= e($serviceImage) ?>"
                            alt="<?= e($serviceName) ?>"
                            loading="lazy"
                        >

                        <span class="service-number">
                            <?= e(str_pad((string) $service['id'], 2, '0', STR_PAD_LEFT)) ?>
                        </span>

                    </div>


                    <div class="service-card-content">

                        <span class="service-category">
                            SALON SERVICE
                        </span>

                        <h3>
                            <?= e($serviceName) ?>
                        </h3>

                        <div class="service-details">

                            <span>
                                <strong>₱<?= number_format((float) $service['price'], 2) ?></strong>
                            </span>

                            <span>
                                <?= (int) $service['duration_minutes'] ?> minutes
                            </span>

                        </div>

                        <p>
                            Enjoy our <?= e($serviceName) ?> service
                            and take time to care for yourself.
                        </p>

                        <a
                            href="<?= e($user ? $dashboardUrl : url('public/login.php')) ?>"
                            class="service-book-link"
                        >
                            Book this service <span>→</span>
                        </a>

                    </div>

                </article>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="no-services-message">

                <h3>No services available yet</h3>

                <p>
                    Please check back later for our available salon services.
                </p>

            </div>

        <?php endif; ?>

    </div>


    <div class="services-note">

        <p>
            Prices and service durations are based on our current salon records.
        </p>

    </div>


    <div class="center-button">

        <a
            href="<?= e($user ? $dashboardUrl : url('public/login.php')) ?>"
            class="primary-btn"
        >
            Explore Booking
            <span>→</span>
        </a>

    </div>

</section>

   


    <!-- WHY CHOOSE US -->

    <section class="why-section section-padding" id="why-us">

        <div class="why-heading">

            <span class="section-eyebrow">THE SALON STUDIO EXPERIENCE</span>

            <h2>
                Your comfort is
                <span>our priority.</span>
            </h2>

            <p>
                We want every visit to be a pleasant experience,
                from choosing your service to completing your appointment.
            </p>

        </div>

        <div class="why-grid">

            <article class="why-card">

                <span class="why-icon">♡</span>

                <h3>Personalized Care</h3>

                <p>
                    We value your preferences and the beauty
                    that makes you unique.
                </p>

            </article>

            <article class="why-card">

                <span class="why-icon">✧</span>

                <h3>Relaxing Experience</h3>

                <p>
                    Enjoy a welcoming atmosphere where
                    you can take time for yourself.
                </p>

            </article>

            <article class="why-card">

                <span class="why-icon">◷</span>

                <h3>Easy Scheduling</h3>

                <p>
                    Use our online appointment system to
                    manage your salon bookings conveniently.
                </p>

            </article>

        </div>

    </section>


    <!-- CALL TO ACTION -->

    <section class="booking-cta">

        <div class="cta-content">

            <span class="section-eyebrow">YOUR SELF-CARE MOMENT</span>

            <h2>
                Ready for a little
                <span>time for yourself?</span>
            </h2>

            <p>
                Start your salon experience with Salon Studio.
                Create an account and explore our appointment services.
            </p>

            <a
                href="<?= e($user ? $dashboardUrl : url('public/register.php')) ?>"
                class="cta-button"
            >
                <?= $user ? 'Go to My Dashboard' : 'Get Started Today' ?>
                <span>→</span>
            </a>

        </div>

    </section>


    <!-- CONTACT SECTION -->

    <section class="contact-section section-padding" id="contact">

        <div class="contact-heading">

            <span class="section-eyebrow">WE WOULD LOVE TO HEAR FROM YOU</span>

            <h2>Let's connect.</h2>

            <p>
                Have questions about our salon or appointments?
                Get in touch with our team.
            </p>

        </div>

        <div class="contact-information">

            <div class="contact-item">
                <span class="contact-icon">⌖</span>
                <div>
                    <strong>Visit Us</strong>
                    <p>Contact us for our salon location.</p>
                </div>
            </div>

            <div class="contact-item">
                <span class="contact-icon">✉</span>
                <div>
                    <strong>Email</strong>
                    <p>Contact our salon for inquiries.</p>
                </div>
            </div>

            <div class="contact-item">
                <span class="contact-icon">◷</span>
                <div>
                    <strong>Business Hours</strong>
                    <p>Please contact us for our operating schedule.</p>
                </div>
            </div>

        </div>

    </section>

</main>


<!-- FOOTER -->

<footer class="home-footer">

    <div class="footer-main">

        <a href="<?= e(url('public/index.php')) ?>" class="home-brand">

            <span class="home-brand-icon">S</span>

            <span class="home-brand-text">
                <strong>Salon Studio</strong>
                <small>BEAUTY & CARE</small>
            </span>

        </a>

        <p>
            Beauty. Elegance. Confidence.
        </p>

        <div class="footer-links">
            <a href="#home">Home</a>
            <a href="#about">About</a>
            <a href="#services">Services</a>
            <a href="#contact">Contact</a>
        </div>

    </div>

    <div class="footer-bottom">

        <span>
            &copy; <?= date('Y') ?> Salon Studio. All rights reserved.
        </span>

        <span>
            Made with care.
        </span>

    </div>

</footer>


<script>

document.addEventListener('DOMContentLoaded', function () {

    const menuToggle = document.getElementById('mobileMenuToggle');
    const navLinks = document.getElementById('homeNavLinks');

    if (!menuToggle || !navLinks) {
        return;
    }

    menuToggle.addEventListener('click', function () {

        const isOpen = navLinks.classList.toggle('show');

        menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

    });

    navLinks.querySelectorAll('a').forEach(function (link) {

        link.addEventListener('click', function () {

            navLinks.classList.remove('show');

            menuToggle.setAttribute('aria-expanded', 'false');

        });

    });

});

</script>

</body>
</html>
