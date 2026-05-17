<?php
/** QuickBite Student — Hồ sơ: HTML + echo (dữ liệu từ get_profile_display_data; POST ở profile_controller). */
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>QuickBite • My profile</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(site_url('assets/css/student.css'), ENT_QUOTES, 'UTF-8'); ?>" />
  </head>
  <body>
    <header class="qb-topbar">
      <div class="qb-topbar-inner">
        <a class="qb-brand" href="<?php echo htmlspecialchars(student_url('home'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="QuickBite Home">
          <div class="qb-brand-mark" aria-hidden="true"><span>Q</span></div>
          <div class="qb-brand-text">
            <div class="qb-brand-name">QuickBite</div>
            <div class="qb-brand-sub">IS-VNU</div>
          </div>
        </a>
        <div></div>
        <div class="qb-actions">
          <a class="qb-cart-btn" href="<?php echo htmlspecialchars(cart_entry_href(false)); ?>" aria-label="Open cart">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M6 6h15l-1.5 9h-12L6 6Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
              <path d="M6 6 5 3H2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M9 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2ZM18 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" stroke="currentColor" stroke-width="2"/>
            </svg>
            <?php if ($cart_count > 0): ?>
              <span class="qb-cart-count"><?php echo (int)$cart_count; ?></span>
            <?php endif; ?>
          </a>
          <div class="qb-user-wrap">
            <button class="qb-user" type="button" id="qbUserBtn" aria-haspopup="menu" aria-expanded="false" title="<?php echo htmlspecialchars($full_name); ?>">
              <?php echo htmlspecialchars($profile_initials); ?>
            </button>
            <div class="qb-user-menu" id="qbUserMenu" role="menu" aria-label="User menu">
              <a role="menuitem" href="<?php echo htmlspecialchars(student_url('profile'), ENT_QUOTES, 'UTF-8'); ?>">My profile</a>
              <a role="menuitem" href="<?php echo htmlspecialchars(student_url('orders'), ENT_QUOTES, 'UTF-8'); ?>">My orders</a>
              <div class="qb-user-sep" aria-hidden="true"></div>
              <a role="menuitem" class="danger" href="<?php echo htmlspecialchars(site_url('logout.php'), ENT_QUOTES, 'UTF-8'); ?>">Log out</a>
            </div>
          </div>
        </div>
      </div>
    </header>

    <main class="qb-profile-shell">
      <header class="qb-profile-hero">
        <h1>My profile</h1>
      </header>

      <?php if ($flash_ok !== ''): ?>
        <div class="qb-flash success" role="status"><?php echo htmlspecialchars($flash_ok); ?></div>
      <?php endif; ?>
      <?php if ($flash_err !== ''): ?>
        <div class="qb-flash error" role="alert"><?php echo htmlspecialchars($flash_err); ?></div>
      <?php endif; ?>

      <section class="qb-profile-stats-card" aria-labelledby="profile-stats-heading">
        <h2 id="profile-stats-heading" class="qb-profile-section-title">Total orders</h2>
        <form class="qb-profile-stats-range" method="get" action="<?php echo htmlspecialchars(student_url('profile'), ENT_QUOTES, 'UTF-8'); ?>">
          <div class="qb-profile-field qb-profile-field--inline">
            <label for="stats_start">From</label>
            <input id="stats_start" type="date" name="stats_start" value="<?php echo htmlspecialchars($stats_start); ?>" onchange="this.form.submit()" />
          </div>
          <div class="qb-profile-field qb-profile-field--inline">
            <label for="stats_end">To</label>
            <input id="stats_end" type="date" name="stats_end" value="<?php echo htmlspecialchars($stats_end); ?>" onchange="this.form.submit()" />
          </div>
        </form>
        <div class="qb-profile-stats-grid">
          <div class="qb-profile-stat">
            <span class="qb-profile-stat-label">Total orders</span>
            <span class="qb-profile-stat-value"><?php echo (int)$stats_total; ?></span>
          </div>
          <div class="qb-profile-stat qb-profile-stat--ok">
            <span class="qb-profile-stat-label">Completed</span>
            <span class="qb-profile-stat-value"><?php echo (int)$stats_success; ?></span>
          </div>
          <div class="qb-profile-stat qb-profile-stat--bad">
            <span class="qb-profile-stat-label">Cancelled</span>
            <span class="qb-profile-stat-value"><?php echo (int)$stats_cancelled; ?></span>
          </div>
          <div class="qb-profile-stat qb-profile-stat--spent" role="group" aria-label="Total spent in date range">
            <span class="qb-profile-stat-label">Total spent</span>
            <span class="qb-profile-stat-value"><?php echo htmlspecialchars(money_vnd($stats_spent)); ?></span>
            <span class="qb-profile-stat-hint">Completed orders in this range</span>
          </div>
        </div>
      </section>

      <div class="qb-profile-grid">
        <div class="qb-profile-card">
          <h2 class="qb-profile-section-title">Personal information</h2>
          <form method="post" action="<?php echo htmlspecialchars(student_url('profile'), ENT_QUOTES, 'UTF-8'); ?>" class="qb-profile-form">
            <input type="hidden" name="which" value="profile" />
            <div class="qb-profile-field">
              <label for="pf-name">Full name</label>
              <input id="pf-name" type="text" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required autocomplete="name" />
            </div>
            <?php if ($has_student_id): ?>
              <div class="qb-profile-field">
                <label for="pf-sid">Student ID (MSSV)</label>
                <input id="pf-sid" type="text" name="student_id" value="<?php echo htmlspecialchars($student_id); ?>" required autocomplete="username" />
              </div>
            <?php endif; ?>
            <div class="qb-profile-field">
              <label for="pf-email">Email</label>
              <input id="pf-email" type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required autocomplete="email" />
            </div>
            <?php if ($has_phone): ?>
              <div class="qb-profile-field">
                <label for="pf-phone">Phone number</label>
                <input id="pf-phone" type="tel" name="phone" inputmode="numeric" value="<?php echo htmlspecialchars($phone); ?>" required autocomplete="tel" placeholder="10–11 digits" />
              </div>
            <?php endif; ?>
            <button type="submit" class="qb-profile-save">Save changes</button>
          </form>
        </div>

        <div class="qb-profile-card">
          <h2 class="qb-profile-section-title">Password</h2>
          <form method="post" action="<?php echo htmlspecialchars(student_url('profile'), ENT_QUOTES, 'UTF-8'); ?>" class="qb-profile-form">
            <input type="hidden" name="which" value="password" />
            <div class="qb-profile-field">
              <label for="pf-cur">Current password</label>
              <input id="pf-cur" type="password" name="current_password" required autocomplete="current-password" />
            </div>
            <div class="qb-profile-field">
              <label for="pf-new">New password</label>
              <input id="pf-new" type="password" name="new_password" required minlength="8" autocomplete="new-password" />
            </div>
            <div class="qb-profile-field">
              <label for="pf-new2">Confirm new password</label>
              <input id="pf-new2" type="password" name="new_password_confirm" required minlength="8" autocomplete="new-password" />
            </div>
            <button type="submit" class="qb-profile-save qb-profile-save--secondary">Update password</button>
          </form>
        </div>
      </div>

    </main>
    <script>
      (function () {
        const btn = document.getElementById('qbUserBtn');
        const menu = document.getElementById('qbUserMenu');
        if (!btn || !menu) return;
        function closeMenu() {
          menu.classList.remove('open');
          btn.setAttribute('aria-expanded', 'false');
        }
        btn.addEventListener('click', function (e) {
          e.stopPropagation();
          const isOpen = menu.classList.toggle('open');
          btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
        document.addEventListener('click', closeMenu);
        menu.addEventListener('click', function (e) { e.stopPropagation(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMenu(); });
      })();
    </script>
  </body>
</html>
