<?php
/**
 * Thanh topbar Admin + thanh điều hướng phụ Student (link + active).
 */
declare(strict_types=1);

/** Topbar tiêu đề trang admin + quick actions (profile / logout). */
function render_admin_topbar(string $title, string $subtitle): void {
  ?>
          <header class="qb-admin-topbar">
            <div class="qb-admin-topbar-inner">
              <div>
                <h1><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p><?php echo htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8'); ?></p>
              </div>
              <div class="qb-admin-top-actions">
                <a class="qb-orders-back" href="<?php echo htmlspecialchars(admin_url('profile'), ENT_QUOTES, 'UTF-8'); ?>">My Profile</a>
                <a class="qb-orders-back" href="<?php echo htmlspecialchars(site_url('logout.php'), ENT_QUOTES, 'UTF-8'); ?>">Logout</a>
              </div>
            </div>
          </header>
  <?php
}

/**
 * Thanh link ngang gọn cho student (dùng khi view không dựng full qb-topbar như Home).
 *
 * @param string $active Một trong: home | cart | orders | profile | checkout | slot …
 */
function render_student_nav_strip(string $active): void {
  $items = [
    ['key' => 'home', 'label' => 'Menu', 'href' => student_url('home')],
    ['key' => 'cart', 'label' => 'Cart', 'href' => student_url('cart')],
    ['key' => 'orders', 'label' => 'Orders', 'href' => student_url('orders')],
    ['key' => 'profile', 'label' => 'Profile', 'href' => student_url('profile')],
  ];
  ?>
    <nav class="qb-nav-strip" aria-label="Student sections">
      <div class="qb-nav-strip-inner">
        <?php foreach ($items as $it): ?>
          <a class="qb-nav-strip-link<?php echo $active === $it['key'] ? ' active' : ''; ?>"
             href="<?php echo htmlspecialchars($it['href'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($it['label'], ENT_QUOTES, 'UTF-8'); ?>
          </a>
        <?php endforeach; ?>
      </div>
    </nav>
  <?php
}
