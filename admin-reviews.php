<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'reviews-lib.php';
reviews_admin_start_session();
$auth = reviews_admin_is_authenticated();
$adminUsername = isset($_SESSION['reviews_admin_username']) ? (string)$_SESSION['reviews_admin_username'] : '';
$adminUsernameEscaped = htmlspecialchars($adminUsername, ENT_QUOTES, 'UTF-8');
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
<body class="admin-page" data-admin-email="<?php echo $adminUsernameEscaped; ?>" data-admin-auth="<?php echo $auth ? '1' : '0'; ?>">
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
      <p>Sign in to manage testimonials now and add future modules like blogs, pages, and media.</p>

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
        <a class="admin-nav-link active" href="admin-reviews.php">Review Moderation</a>
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
            <p class="admin-subtitle">Manage testimonials now, and easily extend this panel for blogs, pages, and media later.</p>
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
            <p>Panel Status</p>
            <strong>Live</strong>
          </article>
        </div>

        <div id="adminStatus" class="review-success" style="display:none;"></div>

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
    </main>
  </div>
  <?php endif; ?>

  <script>
    (function() {
      const dashboard = document.getElementById('adminDashboard');
      const loginForm = document.getElementById('adminLoginForm');
      const loginError = document.getElementById('adminLoginError');
      const statusBox = document.getElementById('adminStatus');
      const pendingList = document.getElementById('pendingReviewList');
      const approvedList = document.getElementById('approvedReviewList');
      const logoutBtn = document.getElementById('adminLogoutBtn');
      const profileBtn = document.getElementById('adminProfileBtn');
      const profileMenu = document.getElementById('adminProfileMenu');
      const profileEmail = document.getElementById('adminProfileEmail');
      const profileInitials = document.getElementById('adminProfileInitials');
      const pendingCountEl = document.getElementById('adminPendingCount');
      const approvedCountEl = document.getElementById('adminApprovedCount');
      const pendingChipEl = document.getElementById('adminPendingChip');
      const approvedChipEl = document.getElementById('adminApprovedChip');
      const sidebarToggleBtn = document.getElementById('adminSidebarToggle');
      const sidebarBackdrop = document.getElementById('adminSidebarBackdrop');
      const isAuthenticated = document.body.getAttribute('data-admin-auth') === '1';
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

      const updateCounts = (pending, approved) => {
        const pendingCount = Array.isArray(pending) ? pending.length : 0;
        const approvedCount = Array.isArray(approved) ? approved.length : 0;

        if (pendingCountEl) pendingCountEl.textContent = String(pendingCount);
        if (approvedCountEl) approvedCountEl.textContent = String(approvedCount);
        if (pendingChipEl) pendingChipEl.textContent = `${pendingCount} item${pendingCount === 1 ? '' : 's'}`;
        if (approvedChipEl) approvedChipEl.textContent = `${approvedCount} item${approvedCount === 1 ? '' : 's'}`;
      };

      const showStatus = (message, isError) => {
        if (!statusBox) return;
        statusBox.textContent = message || '';
        statusBox.className = isError ? 'review-error' : 'review-success';
        statusBox.style.display = message ? 'block' : 'none';
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

      async function api(action, payload) {
        const body = new FormData();
        body.append('action', action);
        Object.entries(payload || {}).forEach(([k, v]) => body.append(k, v));

        const response = await fetch('review-admin-api.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
          body
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
          throw new Error(data.message || 'Request failed.');
        }
        return data;
      }

      async function fetchList() {
        const response = await fetch('review-admin-api.php?action=list', {
          headers: { Accept: 'application/json' }
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
          throw new Error(data.message || 'Could not load reviews.');
        }
        return data;
      }

      function renderPending(items) {
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
      }

      function renderApproved(items) {
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
      }

      async function refreshDashboard() {
        try {
          const data = await fetchList();
          const pending = data.pending || [];
          const approved = data.approved || [];
          renderPending(pending);
          renderApproved(approved);
          updateCounts(pending, approved);
        } catch (error) {
          showStatus(error.message, true);
        }
      }

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
          // On desktop, default to expanded when no explicit preference.
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
            await api('login', { username, password });
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
            await api('logout');
          } catch (e) {
            // Intentionally ignored to ensure local logout UI reset.
          }
          window.location.reload();
        });
      }

      document.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;

        const action = target.getAttribute('data-action');
        const reviewId = target.getAttribute('data-id');
        if (!action || !reviewId) return;

        target.disabled = true;
        try {
          await api(action, { id: reviewId });
          showStatus(action === 'approve' ? 'Review approved.' : 'Review rejected.', false);
          await refreshDashboard();
        } catch (error) {
          showStatus(error.message, true);
        } finally {
          target.disabled = false;
        }
      });

      if (isAuthenticated && dashboard) {
        refreshDashboard();
      }
    })();
  </script>
</body>
</html>
