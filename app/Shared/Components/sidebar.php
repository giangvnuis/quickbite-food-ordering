<?php
/**
 * Sidebar điều hướng khu Admin (menu cố định + active state).
 *
 * Cần helpers URL (`url_helper` / `Helpers/load.php`) đã được load.
 */
declare(strict_types=1);

/**
 * @param string $activeNav Khớp key: dashboard | orders | menu | users | profile (tuỳ route).
 * @param array<string, mixed> $adminUser Session user (full_name, …).
 */
function render_admin_sidebar(string $activeNav, array $adminUser): void {
  $name = (string)($adminUser['full_name'] ?? 'Admin');
  ?>
        <aside class="qb-admin-sidebar">
          <a class="qb-admin-brand" href="<?php echo htmlspecialchars(student_url('home'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="QuickBite Home">
            <div class="qb-admin-logo">Q</div>
            <div>
              <div class="qb-admin-brand-title">QuickBite</div>
              <div class="qb-admin-brand-sub"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
          </a>
          <nav class="qb-admin-nav" aria-label="Admin primary">
            <a class="<?php echo $activeNav === 'dashboard' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('dashboard'), ENT_QUOTES, 'UTF-8'); ?>">
              <span class="qb-admin-nav-icon" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
              </span>
              <span>Dashboard</span>
            </a>
            <a class="<?php echo $activeNav === 'orders' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('orders'), ENT_QUOTES, 'UTF-8'); ?>">
              <span class="qb-admin-nav-icon" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h18v12H3z"/><path d="M3 7l3-4h12l3 4"/><path d="M8 11h8"/></svg>
              </span>
              <span>Order Management</span>
            </a>
            <a class="<?php echo $activeNav === 'menu' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('menu'), ENT_QUOTES, 'UTF-8'); ?>">
              <span class="qb-admin-nav-icon" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v18"/><path d="M8 7c-2 0-3-1.5-3-3V3"/><path d="M8 7c2 0 3-1.5 3-3V3"/><path d="M16 3v18"/><path d="M16 3c2 1.5 2 4.5 0 6h-2"/></svg>
              </span>
              <span>Menu Management</span>
            </a>
            <a class="<?php echo $activeNav === 'users' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('users'), ENT_QUOTES, 'UTF-8'); ?>">
              <span class="qb-admin-nav-icon" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.8-3.2 4.5-5 8-5s6.2 1.8 8 5"/></svg>
              </span>
              <span>User Management</span>
            </a>
          </nav>
        </aside>
  <?php
}
