
/* =========================================
   SALON STUDIO
   Main JavaScript
========================================= */

document.addEventListener('DOMContentLoaded', function () {

    // ======================================
    // MOBILE SIDEBAR
    // ======================================

    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (!menuToggle || !sidebar || !overlay) {
        return;
    }

    // Open sidebar
    function openSidebar() {

        document.body.classList.add('sidebar-open');

        menuToggle.setAttribute('aria-expanded', 'true');

    }

    // Close sidebar
    function closeSidebar() {

        document.body.classList.remove('sidebar-open');

        menuToggle.setAttribute('aria-expanded', 'false');

    }

    // Toggle sidebar
    menuToggle.addEventListener('click', function () {

        const isOpen = document.body.classList.contains('sidebar-open');

        if (isOpen) {
            closeSidebar();
        } else {
            openSidebar();
        }

    });

    // Close when clicking overlay
    overlay.addEventListener('click', closeSidebar);

    // Close when clicking a navigation link
    sidebar.querySelectorAll('.nav-link').forEach(function (link) {

        link.addEventListener('click', function () {

            closeSidebar();

        });

    });

    // Close using Escape key
    document.addEventListener('keydown', function (event) {

        if (event.key === 'Escape') {
            closeSidebar();
        }

    });

    // Close when returning to desktop layout
    window.addEventListener('resize', function () {

        if (window.innerWidth > 900) {
            closeSidebar();
        }

    });

});
