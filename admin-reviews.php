<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'reviews-lib.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'bookings-lib.php';
reviews_admin_start_session();
$auth = reviews_admin_is_authenticated();
$adminUsername = isset($_SESSION['reviews_admin_username']) ? (string)$_SESSION['reviews_admin_username'] : '';
$adminUsernameEscaped = htmlspecialchars($adminUsername, ENT_QUOTES, 'UTF-8');
$bookingDashboardPayload = $auth ? bookings_prepare_dashboard_payload(bookings_load_all()) : ['requested' => [], 'upcoming' => [], 'recent' => [], 'stats' => ['requested_count' => 0, 'upcoming_count' => 0, 'urgent_count' => 0, 'day_reminder_due_count' => 0, 'hour_reminder_due_count' => 0]];
$bookingDashboardJson = htmlspecialchars(json_encode($bookingDashboardPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Panel | Newcomer Connect</title>
  <meta name="robots" content="noindex, nofollow">
  <script>
    (function () {
      try {
        if (localStorage.getItem('theme') === 'dark') {
          document.documentElement.setAttribute('data-theme', 'dark');
        }
      } catch (e) {
        // Ignore storage access errors and fall back to light mode.
      }
    })();
  </script>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="admin-page" data-admin-email="<?php echo $adminUsernameEscaped; ?>" data-admin-auth="<?php echo $auth ? '1' : '0'; ?>" data-booking-payload="<?php echo $bookingDashboardJson; ?>">
  <?php if (!$auth): ?>
  <div class="admin-auth-shell">
    <div class="admin-auth-bg" aria-hidden="true"></div>
    <div class="admin-auth-card form-card">
      <a href="index.html" class="admin-auth-brand" aria-label="Back to website">
        <img src="assets/icons/logo-real.png" alt="Newcomer Connect">
        <div>
          <strong>Newcomer Connect</strong>
          <span>Website Admin</span>
        </div>
      </a>
      <h1>Admin Login</h1>
      <p>Sign in to manage testimonials, consultation bookings, urgency, and reminder runs from one dashboard.</p>

      <form id="adminLoginForm" class="admin-login-form">
        <div class="form-group">
          <label class="form-label" for="adminUsername">Email / Username</label>
          <input class="form-control" id="adminUsername" name="username" type="text" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="adminPassword">Password</label>
          <input class="form-control" id="adminPassword" name="password" type="password" required>
        </div>
        <button type="submit" class="btn btn-primary">Login to Admin Panel</button>
      </form>

      <div id="adminLoginError" class="review-error" style="display:none; margin-top:16px;"></div>
    </div>
  </div>
  <?php else: ?>
  <div class="admin-shell">
    <button id="adminSidebarBackdrop" class="admin-sidebar-backdrop" type="button" aria-label="Close sidebar"></button>
    <aside class="admin-sidebar">
      <a href="admin-reviews.php" class="admin-brand" aria-label="Admin dashboard home">
        <img src="assets/icons/logo-real.png" alt="Newcomer Connect">
        <div>
          <strong>Newcomer Connect</strong>
          <span>Admin Panel</span>
        </div>
      </a>

      <nav class="admin-nav" aria-label="Admin sections">
        <a class="admin-nav-link active" href="admin-reviews.php">Operations Dashboard</a>
        <a class="admin-nav-link" href="#reviewModeration">Review Moderation</a>
        <a class="admin-nav-link" href="#bookingManagement">Consultation Bookings</a>
        <a class="admin-nav-link is-disabled" href="#" aria-disabled="true">Blog Management <em>Coming Soon</em></a>
        <a class="admin-nav-link is-disabled" href="#" aria-disabled="true">Media Library <em>Coming Soon</em></a>
        <a class="admin-nav-link is-disabled" href="#" aria-disabled="true">Page Content <em>Coming Soon</em></a>
        <a class="admin-nav-link is-disabled" href="#" aria-disabled="true">Settings <em>Coming Soon</em></a>
      </nav>
    </aside>

    <main class="admin-main">
      <header class="admin-topbar">
        <div class="admin-topbar-left">
          <button id="adminSidebarToggle" class="admin-sidebar-toggle" type="button" aria-label="Toggle sidebar" aria-expanded="true">☰</button>
          <div>
            <p class="admin-kicker">Website Operations</p>
            <h1>Admin Dashboard</h1>
            <p class="admin-subtitle">Moderate reviews, manage consultation bookings, prioritize urgent cases, and run reminder emails from one place.</p>
          </div>
        </div>

        <div class="admin-profile-wrap">
          <button id="adminProfileBtn" class="admin-profile-btn" type="button" aria-haspopup="menu" aria-expanded="false">
            <span id="adminProfileInitials" class="admin-profile-avatar">AD</span>
            <span class="admin-profile-labels">
              <strong>Administrator</strong>
              <small id="adminProfileEmail"><?php echo $adminUsernameEscaped !== '' ? $adminUsernameEscaped : 'Signed in'; ?></small>
            </span>
          </button>

          <div id="adminProfileMenu" class="admin-profile-menu" role="menu" aria-label="Profile menu">
            <button id="adminLogoutBtn" type="button" class="admin-menu-action" role="menuitem">Logout</button>
          </div>
        </div>
      </header>

      <section id="adminDashboard" class="admin-dashboard">
        <section id="reviewModeration" class="admin-module-block">
          <div class="admin-module-head">
            <div>
              <p class="admin-kicker">Testimonials</p>
              <h2 class="admin-module-title">Review Moderation</h2>
              <p class="admin-subtitle">Approve or reject reviews before they appear on the public website.</p>
            </div>
          </div>

          <div class="admin-kpi-grid">
            <article class="admin-kpi-card">
              <p>Pending Reviews</p>
              <strong id="adminPendingCount">0</strong>
            </article>
            <article class="admin-kpi-card">
              <p>Approved Reviews</p>
              <strong id="adminApprovedCount">0</strong>
            </article>
            <article class="admin-kpi-card">
              <p>Module Status</p>
              <strong>Live</strong>
            </article>
          </div>

          <div id="reviewStatus" class="review-success" style="display:none;"></div>

          <div class="admin-grid">
            <section class="admin-section-card">
              <div class="admin-section-head">
                <h4 class="admin-col-title">Pending Queue</h4>
                <span class="admin-chip" id="adminPendingChip">0 items</span>
              </div>
              <div id="pendingReviewList" class="admin-review-list"></div>
            </section>

            <section class="admin-section-card">
              <div class="admin-section-head">
                <h4 class="admin-col-title">Recent Approved</h4>
                <span class="admin-chip" id="adminApprovedChip">0 items</span>
              </div>
              <div id="approvedReviewList" class="admin-review-list"></div>
            </section>
          </div>
        </section>

        <section id="bookingManagement" class="admin-module-block">
          <div class="admin-module-head">
            <div>
              <p class="admin-kicker">Consultations</p>
              <h2 class="admin-module-title">Consultation Bookings</h2>
              <p class="admin-subtitle">Review new consultation requests, sort urgent cases, update booking status, and send due reminders.</p>
            </div>
          </div>

          <div class="admin-kpi-grid">
            <article class="admin-kpi-card">
              <p>Requested Bookings</p>
              <strong id="requestedCount">0</strong>
            </article>
            <article class="admin-kpi-card">
              <p>Upcoming Schedule</p>
              <strong id="upcomingCount">0</strong>
            </article>
            <article class="admin-kpi-card">
              <p>Urgent Cases</p>
              <strong id="urgentCount">0</strong>
            </article>
            <article class="admin-kpi-card">
              <p>Due Reminders</p>
              <strong id="reminderCount">0</strong>
            </article>
          </div>

          <div id="bookingStatus" class="review-success" style="display:none;"></div>

          <section class="admin-toolbar">
            <div>
              <h4>Reminder Control</h4>
              <p>Run due reminders manually here, or point shared hosting cron at booking-reminders.php every 5-10 minutes.</p>
            </div>
            <button id="adminRunRemindersBtn" type="button" class="btn btn-primary">Run Due Reminders</button>
          </section>

          <div class="admin-grid">
            <section class="admin-section-card">
              <div class="admin-section-head">
                <h4 class="admin-col-title">Requested Queue</h4>
                <span class="admin-chip" id="requestedChip">0 items</span>
              </div>
              <div id="requestedBookingList" class="admin-booking-list"></div>
            </section>

            <section class="admin-section-card">
              <div class="admin-section-head">
                <h4 class="admin-col-title">Upcoming Schedule</h4>
                <span class="admin-chip" id="upcomingChip">0 items</span>
              </div>
              <div id="upcomingBookingList" class="admin-booking-list"></div>
            </section>

            <section class="admin-section-card span-full">
              <div class="admin-section-head">
                <h4 class="admin-col-title">Recent History</h4>
                <span class="admin-chip" id="recentChip">0 items</span>
              </div>
              <div id="recentBookingList" class="admin-booking-list"></div>
            </section>
          </div>
        </section>
      </section>
    </main>
  </div>
  <?php endif; ?>

  <script>
    (function() {
      const dashboard = document.getElementById('adminDashboard');
      const loginForm = document.getElementById('adminLoginForm');
      const loginError = document.getElementById('adminLoginError');
      const reviewStatusBox = document.getElementById('reviewStatus');
      const bookingStatusBox = document.getElementById('bookingStatus');
      const pendingList = document.getElementById('pendingReviewList');
      const approvedList = document.getElementById('approvedReviewList');
      const requestedList = document.getElementById('requestedBookingList');
      const upcomingList = document.getElementById('upcomingBookingList');
      const recentList = document.getElementById('recentBookingList');
      const logoutBtn = document.getElementById('adminLogoutBtn');
      const profileBtn = document.getElementById('adminProfileBtn');
      const profileMenu = document.getElementById('adminProfileMenu');
      const profileEmail = document.getElementById('adminProfileEmail');
      const profileInitials = document.getElementById('adminProfileInitials');
      const pendingCountEl = document.getElementById('adminPendingCount');
      const approvedCountEl = document.getElementById('adminApprovedCount');
      const pendingChipEl = document.getElementById('adminPendingChip');
      const approvedChipEl = document.getElementById('adminApprovedChip');
      const requestedCountEl = document.getElementById('requestedCount');
      const upcomingCountEl = document.getElementById('upcomingCount');
      const urgentCountEl = document.getElementById('urgentCount');
      const reminderCountEl = document.getElementById('reminderCount');
      const requestedChipEl = document.getElementById('requestedChip');
      const upcomingChipEl = document.getElementById('upcomingChip');
      const recentChipEl = document.getElementById('recentChip');
      const remindersBtn = document.getElementById('adminRunRemindersBtn');
      const sidebarToggleBtn = document.getElementById('adminSidebarToggle');
      const sidebarBackdrop = document.getElementById('adminSidebarBackdrop');
      const isAuthenticated = document.body.getAttribute('data-admin-auth') === '1';
      const preloadedBookingPayload = (() => {
        const raw = document.body.getAttribute('data-booking-payload') || '';
        if (!raw) return null;
        try {
          return JSON.parse(raw);
        } catch (error) {
          return null;
        }
      })();
      const SIDEBAR_STATE_KEY = 'admin_sidebar_collapsed';

      const stars = (count) => '★'.repeat(Math.max(1, Math.min(5, Number(count) || 0)));
      const esc = (value) => {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
      };

      const getInitials = (value) => {
        const text = (value || '').trim();
        if (!text) return 'AD';

        if (text.includes('@')) {
          const local = text.split('@')[0].replace(/[^a-zA-Z0-9]/g, '');
          return (local.slice(0, 2) || 'AD').toUpperCase();
        }

        const parts = text.split(/\s+/).filter(Boolean);
        if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
        return (parts[0][0] + parts[1][0]).toUpperCase();
      };

      const setAdminIdentity = (value) => {
        const text = (value || '').trim();
        if (profileEmail) {
          profileEmail.textContent = text || 'Not signed in';
        }
        if (profileInitials) {
          profileInitials.textContent = getInitials(text);
        }
      };

      const showStatus = (element, message, isError) => {
        if (!element) return;
        element.textContent = message || '';
        element.className = isError ? 'review-error' : 'review-success';
        element.style.display = message ? 'block' : 'none';
      };

      const setSidebarCollapsed = (collapsed, persist) => {
        document.body.classList.toggle('admin-sidebar-collapsed', collapsed);

        if (sidebarToggleBtn) {
          sidebarToggleBtn.setAttribute('aria-expanded', String(!collapsed));
          sidebarToggleBtn.textContent = collapsed ? '☰' : '✕';
        }

        if (persist) {
          try {
            localStorage.setItem(SIDEBAR_STATE_KEY, collapsed ? '1' : '0');
          } catch (e) {
            // Ignore storage access issues.
          }
        }
      };

      const setupSidebarState = () => {
        if (!isAuthenticated) return;

        const isMobile = window.matchMedia('(max-width: 768px)').matches;
        let collapsed = false;

        try {
          const saved = localStorage.getItem(SIDEBAR_STATE_KEY);
          if (saved === '1') collapsed = true;
          if (saved === '0') collapsed = false;
        } catch (e) {
          // Ignore storage access issues.
        }

        if (isMobile) {
          let hasSavedState = false;
          try {
            hasSavedState = localStorage.getItem(SIDEBAR_STATE_KEY) !== null;
          } catch (e) {
            hasSavedState = true;
          }
          if (!hasSavedState) {
            collapsed = true;
          }
        }

        setSidebarCollapsed(collapsed, false);
      };

      async function reviewApi(action, payload) {
        const body = new FormData();
        body.append('action', action);
        Object.entries(payload || {}).forEach(([key, value]) => body.append(key, value));

        const response = await fetch('review-admin-api.php', {
          method: 'POST',
          credentials: 'same-origin',
          cache: 'no-store',
          headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
          body
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
          throw new Error(data.message || 'Request failed.');
        }
        return data;
      }

      async function reviewFetchList() {
        const response = await fetch(`review-admin-api.php?action=list&_=${Date.now()}`, {
          credentials: 'same-origin',
          cache: 'no-store',
          headers: { Accept: 'application/json' }
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
          throw new Error(data.message || 'Could not load reviews.');
        }
        return data;
      }

      async function bookingApi(action, payload) {
        const body = new FormData();
        body.append('action', action);
        Object.entries(payload || {}).forEach(([key, value]) => body.append(key, value));

        const response = await fetch('booking-admin-api.php', {
          method: 'POST',
          credentials: 'same-origin',
          cache: 'no-store',
          headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
          body
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
          throw new Error(data.message || 'Request failed.');
        }

        return data;
      }

      async function bookingFetchList() {
        const response = await fetch(`booking-admin-api.php?action=list&_=${Date.now()}`, {
          credentials: 'same-origin',
          cache: 'no-store',
          headers: { Accept: 'application/json' }
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
          throw new Error(data.message || 'Could not load bookings.');
        }
        return data;
      }

      const updateReviewCounts = (pending, approved) => {
        const pendingCount = Array.isArray(pending) ? pending.length : 0;
        const approvedCount = Array.isArray(approved) ? approved.length : 0;

        if (pendingCountEl) pendingCountEl.textContent = String(pendingCount);
        if (approvedCountEl) approvedCountEl.textContent = String(approvedCount);
        if (pendingChipEl) pendingChipEl.textContent = `${pendingCount} item${pendingCount === 1 ? '' : 's'}`;
        if (approvedChipEl) approvedChipEl.textContent = `${approvedCount} item${approvedCount === 1 ? '' : 's'}`;
      };

      const updateBookingCounts = (stats, requested, upcoming, recent) => {
        const requestedCount = Number(stats?.requested_count || requested.length || 0);
        const upcomingCount = Number(stats?.upcoming_count || upcoming.length || 0);
        const urgentCount = Number(stats?.urgent_count || 0);
        const reminderCount = Number(stats?.day_reminder_due_count || 0) + Number(stats?.hour_reminder_due_count || 0);

        if (requestedCountEl) requestedCountEl.textContent = String(requestedCount);
        if (upcomingCountEl) upcomingCountEl.textContent = String(upcomingCount);
        if (urgentCountEl) urgentCountEl.textContent = String(urgentCount);
        if (reminderCountEl) reminderCountEl.textContent = String(reminderCount);
        if (requestedChipEl) requestedChipEl.textContent = `${requested.length} item${requested.length === 1 ? '' : 's'}`;
        if (upcomingChipEl) upcomingChipEl.textContent = `${upcoming.length} item${upcoming.length === 1 ? '' : 's'}`;
        if (recentChipEl) recentChipEl.textContent = `${recent.length} item${recent.length === 1 ? '' : 's'}`;
      };

      const renderPending = (items) => {
        if (!pendingList) return;
        if (!Array.isArray(items) || items.length === 0) {
          pendingList.innerHTML = '<div class="admin-empty">No pending reviews.</div>';
          return;
        }

        pendingList.innerHTML = items.map((item) => `
          <article class="admin-review-card">
            <div class="admin-review-head">
              <strong>${esc(item.name)}</strong>
              <span>${esc(item.city)}</span>
            </div>
            <div class="admin-review-meta">${stars(item.rating)} | Service: ${esc(item.service)}</div>
            <p>${esc(item.message)}</p>
            <div class="admin-review-email">Email: ${esc(item.email || '')}</div>
            <div class="admin-review-actions">
              <button class="btn btn-primary" data-action="approve" data-id="${esc(item.id)}" type="button">Approve</button>
              <button class="btn btn-outline" data-action="reject" data-id="${esc(item.id)}" type="button">Reject</button>
            </div>
          </article>
        `).join('');
      };

      const renderApproved = (items) => {
        if (!approvedList) return;
        if (!Array.isArray(items) || items.length === 0) {
          approvedList.innerHTML = '<div class="admin-empty">No approved reviews yet.</div>';
          return;
        }

        approvedList.innerHTML = items.slice(0, 10).map((item) => `
          <article class="admin-review-card approved">
            <div class="admin-review-head">
              <strong>${esc(item.name)}</strong>
              <span>${esc(item.city)}</span>
            </div>
            <div class="admin-review-meta">${stars(item.rating)} | Service: ${esc(item.service)}</div>
            <p>${esc(item.message)}</p>
          </article>
        `).join('');
      };

      const reminderSummary = (item) => {
        if (!item) return 'Reminder state unavailable.';
        if (['completed', 'cancelled'].includes(item.status)) {
          return `Reminders disabled because this booking is ${item.status_label.toLowerCase()}.`;
        }

        const dayState = item.day_reminder_sent_at ? 'Day-before sent' : 'Day-before pending';
        const hourState = item.hour_reminder_sent_at ? '1-hour sent' : '1-hour pending';
        return `${dayState} | ${hourState}`;
      };

      const renderActionButtons = (item) => {
        const id = esc(item.id || '');
        const actions = [];

        if (item.status === 'requested') {
          actions.push(`<button class="btn btn-primary" type="button" data-booking-action="status" data-id="${id}" data-status="confirmed">Confirm</button>`);
          actions.push(`<button class="btn btn-outline" type="button" data-booking-action="status" data-id="${id}" data-status="cancelled">Cancel</button>`);
        } else if (item.status === 'confirmed') {
          actions.push(`<button class="btn btn-primary" type="button" data-booking-action="status" data-id="${id}" data-status="completed">Complete</button>`);
          actions.push(`<button class="btn btn-outline" type="button" data-booking-action="status" data-id="${id}" data-status="cancelled">Cancel</button>`);
          actions.push(`<button class="btn btn-outline" type="button" data-booking-action="status" data-id="${id}" data-status="requested">Reset</button>`);
        } else {
          actions.push(`<button class="btn btn-outline" type="button" data-booking-action="status" data-id="${id}" data-status="requested">Reopen</button>`);
        }

        return actions.join('');
      };

      const renderBookingCard = (item) => {
        const email = esc(item.email || '');
        const phone = esc(item.phone || '');
        const phoneLink = esc((item.phone || '').replace(/[^0-9+]/g, ''));
        const source = esc(item.source_label || 'Website');
        const service = esc(item.service_interest || 'Not specified');
        const schedule = esc(item.scheduled_display || 'Schedule pending');
        const urgencyClass = esc(`urgency-${item.urgency || 'standard'}`);
        const statusClass = esc(`status-${item.status || 'requested'}`);
        const message = esc(item.message || 'No notes provided.');

        return `
          <article class="admin-booking-card">
            <div class="admin-booking-head">
              <div>
                <strong>${esc(item.full_name || 'Booking')}</strong>
                <span>${esc(item.source_label || 'Website')}</span>
              </div>
              <div class="admin-booking-tags">
                <span class="admin-badge ${statusClass}">${esc(item.status_label || 'Requested')}</span>
                <span class="admin-badge ${urgencyClass}">${esc(item.urgency_label || 'Standard')}</span>
              </div>
            </div>
            <div class="admin-booking-meta">${schedule} | ${service}</div>
            <p class="admin-booking-note">${message}</p>
            <div class="admin-booking-contact">
              <a href="mailto:${email}">${email}</a>
              ${phone ? `<a href="tel:${phoneLink}">${phone}</a>` : '<span>Phone not provided</span>'}
            </div>
            <div class="admin-reminder-line">${esc(reminderSummary(item))}</div>
            <div class="admin-booking-actions">${renderActionButtons(item)}</div>
          </article>
        `;
      };

      const renderBookingList = (container, items, emptyMessage) => {
        if (!container) return;
        if (!Array.isArray(items) || items.length === 0) {
          container.innerHTML = `<div class="admin-empty">${esc(emptyMessage)}</div>`;
          return;
        }

        container.innerHTML = items.map((item) => renderBookingCard(item)).join('');
      };

      async function refreshReviewDashboard() {
        try {
          const data = await reviewFetchList();
          const pending = data.pending || [];
          const approved = data.approved || [];
          renderPending(pending);
          renderApproved(approved);
          updateReviewCounts(pending, approved);
        } catch (error) {
          showStatus(reviewStatusBox, error.message, true);
        }
      }

      async function refreshBookingDashboard() {
        try {
          const data = await bookingFetchList();
          const requested = data.requested || [];
          const upcoming = data.upcoming || [];
          const recent = data.recent || [];

          renderBookingList(requestedList, requested, 'No requested bookings right now.');
          renderBookingList(upcomingList, upcoming, 'No upcoming consultations yet.');
          renderBookingList(recentList, recent, 'No completed or cancelled bookings yet.');
          updateBookingCounts(data.stats || {}, requested, upcoming, recent);
        } catch (error) {
          showStatus(bookingStatusBox, error.message, true);
        }
      }

      const hydrateBookingDashboard = (data) => {
        const requested = data?.requested || [];
        const upcoming = data?.upcoming || [];
        const recent = data?.recent || [];

        renderBookingList(requestedList, requested, 'No requested bookings right now.');
        renderBookingList(upcomingList, upcoming, 'No upcoming consultations yet.');
        renderBookingList(recentList, recent, 'No completed or cancelled bookings yet.');
        updateBookingCounts(data?.stats || {}, requested, upcoming, recent);
      };

      if (profileBtn && profileMenu) {
        profileBtn.addEventListener('click', () => {
          const isOpen = profileMenu.classList.toggle('open');
          profileBtn.setAttribute('aria-expanded', String(isOpen));
        });

        document.addEventListener('click', (event) => {
          if (!(event.target instanceof Node)) return;
          if (!profileMenu.contains(event.target) && !profileBtn.contains(event.target)) {
            profileMenu.classList.remove('open');
            profileBtn.setAttribute('aria-expanded', 'false');
          }
        });
      }

      if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', () => {
          const collapsed = document.body.classList.contains('admin-sidebar-collapsed');
          setSidebarCollapsed(!collapsed, true);
        });
      }

      if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', () => {
          setSidebarCollapsed(true, true);
        });
      }

      window.addEventListener('resize', () => {
        if (!isAuthenticated) return;

        if (!window.matchMedia('(max-width: 768px)').matches) {
          let hasPreference = true;
          try {
            hasPreference = localStorage.getItem(SIDEBAR_STATE_KEY) !== null;
          } catch (e) {
            hasPreference = true;
          }
          if (!hasPreference) {
            setSidebarCollapsed(false, false);
          }
        }
      }, { passive: true });

      setAdminIdentity(document.body.getAttribute('data-admin-email'));
      setupSidebarState();

      if (loginForm) {
        loginForm.addEventListener('submit', async (event) => {
          event.preventDefault();
          if (loginError) loginError.style.display = 'none';

          const username = loginForm.username.value;
          const password = loginForm.password.value;

          try {
            await reviewApi('login', { username, password });
            window.location.reload();
          } catch (error) {
            if (loginError) {
              loginError.textContent = error.message;
              loginError.style.display = 'block';
            }
          }
        });
      }

      if (logoutBtn) {
        logoutBtn.addEventListener('click', async () => {
          try {
            await reviewApi('logout');
          } catch (e) {
            // Intentionally ignored to ensure local logout UI reset.
          }
          window.location.reload();
        });
      }

      if (remindersBtn) {
        remindersBtn.addEventListener('click', async () => {
          remindersBtn.disabled = true;
          const originalLabel = remindersBtn.textContent;
          remindersBtn.textContent = 'Running...';

          try {
            const data = await bookingApi('send-reminders');
            const result = data.result || {};
            const message = `Reminders processed. Day-before sent: ${result.day_sent || 0}. One-hour sent: ${result.hour_sent || 0}.`;
            showStatus(bookingStatusBox, message, false);
            await refreshBookingDashboard();
          } catch (error) {
            showStatus(bookingStatusBox, error.message, true);
          } finally {
            remindersBtn.disabled = false;
            remindersBtn.textContent = originalLabel;
          }
        });
      }

      document.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;

        const reviewAction = target.getAttribute('data-action');
        const reviewId = target.getAttribute('data-id');
        if (reviewAction && reviewId) {
          target.disabled = true;
          try {
            await reviewApi(reviewAction, { id: reviewId });
            showStatus(reviewStatusBox, reviewAction === 'approve' ? 'Review approved.' : 'Review rejected.', false);
            await refreshReviewDashboard();
          } catch (error) {
            showStatus(reviewStatusBox, error.message, true);
          } finally {
            target.disabled = false;
          }
          return;
        }

        const bookingAction = target.getAttribute('data-booking-action');
        const bookingId = target.getAttribute('data-id');
        const status = target.getAttribute('data-status');
        if (!bookingAction || !bookingId || !status) return;

        target.disabled = true;
        try {
          const data = await bookingApi('status', { id: bookingId, status });
          showStatus(bookingStatusBox, data.message || 'Booking updated.', false);
          await refreshBookingDashboard();
        } catch (error) {
          showStatus(bookingStatusBox, error.message, true);
        } finally {
          target.disabled = false;
        }
      });

      if (isAuthenticated && dashboard) {
        if (preloadedBookingPayload) {
          hydrateBookingDashboard(preloadedBookingPayload);
        }
        refreshReviewDashboard();
        refreshBookingDashboard();
      }
    })();
  </script>
</body>
</html>