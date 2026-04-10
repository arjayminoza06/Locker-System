/**
 * responsive.js — Asian College Locker Management System
 * Handles: sidebar open/close, overlay, resize cleanup,
 *          tablet tooltip labels, swipe-to-close
 *
 * Include ONCE at the bottom of header.php (before </body>):
 *   <script src="responsive.js"></script>
 */

(function () {
  'use strict';

  /* ── Grab elements ── */
  const sidebar   = document.querySelector('.sidebar');
  const mainEl    = document.querySelector('.main');
  const hamburger = document.getElementById('hamburgerBtn');
  const overlay   = document.getElementById('sidebarOverlay');

  if (!sidebar) return; // safety: don't run on login page

  /* ── Tablet nav labels (data-label used by CSS tooltip) ── */
  const navLabels = {
    'dashboard.php':   'Dashboard',
    'students.php':    'Students',
    'lockers.php':     'Lockers',
    'assignments.php': 'Assignments',
    'buildings.php':   'Buildings',
    'maintenance.php': 'Maintenance',
    'reports.php':     'Reports',
  };
  sidebar.querySelectorAll('nav a').forEach(link => {
    const href = link.getAttribute('href');
    if (navLabels[href]) link.setAttribute('data-label', navLabels[href]);
  });

  /* ── Open sidebar ── */
  function openSidebar() {
    sidebar.classList.add('open');
    document.body.classList.add('sidebar-open');
    if (overlay) overlay.classList.add('visible');
  }

  /* ── Close sidebar ── */
  function closeSidebar() {
    sidebar.classList.remove('open');
    document.body.classList.remove('sidebar-open');
    if (overlay) overlay.classList.remove('visible');
  }

  /* ── Toggle ── */
  function toggleSidebar() {
    sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
  }

  /* ── Hamburger click ── */
  if (hamburger) hamburger.addEventListener('click', toggleSidebar);

  /* ── Overlay click → close ── */
  if (overlay) overlay.addEventListener('click', closeSidebar);

  /* ── Escape key → close ── */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSidebar();
  });

  /* ── Auto-close when a nav link is tapped on mobile ── */
  sidebar.querySelectorAll('nav a').forEach(link => {
    link.addEventListener('click', function () {
      if (window.innerWidth < 768) closeSidebar();
    });
  });

  /* ── Close on resize to desktop ── */
  let resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      if (window.innerWidth >= 768) closeSidebar();
    }, 100);
  });

  /* ── Swipe-to-close (touch) ── */
  let touchStartX = 0;
  let touchStartY = 0;

  document.addEventListener('touchstart', function (e) {
    touchStartX = e.touches[0].clientX;
    touchStartY = e.touches[0].clientY;
  }, { passive: true });

  document.addEventListener('touchend', function (e) {
    if (!sidebar.classList.contains('open')) return;
    const dx = e.changedTouches[0].clientX - touchStartX;
    const dy = Math.abs(e.changedTouches[0].clientY - touchStartY);
    // Swipe left ≥ 60px, and more horizontal than vertical
    if (dx < -60 && dy < 80) closeSidebar();
  }, { passive: true });

  /* ── Swipe-from-left-edge to open (< 768px) ── */
  document.addEventListener('touchstart', function (e) {
    touchStartX = e.touches[0].clientX;
    touchStartY = e.touches[0].clientY;
  }, { passive: true });

  document.addEventListener('touchend', function (e) {
    if (window.innerWidth >= 768) return;
    if (sidebar.classList.contains('open')) return;
    const dx = e.changedTouches[0].clientX - touchStartX;
    const dy = Math.abs(e.changedTouches[0].clientY - touchStartY);
    // Swipe right ≥ 60px from left edge (within 40px), horizontal dominant
    if (touchStartX < 40 && dx > 60 && dy < 80) openSidebar();
  }, { passive: true });

})();