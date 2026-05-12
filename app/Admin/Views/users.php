<?php
/**
 * QuickBite Admin — User Management: bảng user, khóa/mở (toggle), lịch sử đơn (get_user_order_history).
 *
 * @var array<string, mixed> $users_link_extra
 */
declare(strict_types=1);

$ux = $users_link_extra ?? [];

qb_admin_shell_start('users', 'Today', $page_date);
?>
<?php if ($flash !== ''): ?>
  <div class="qb-flash <?php echo $flash_type === 'error' ? 'error' : 'success'; ?>"><?php echo htmlspecialchars($flash); ?></div>
<?php endif; ?>

<div class="qb-om-page">
<nav class="qb-um-stats" aria-label="Quick filters">
  <!-- Nhóm card tổng quan + filter nhanh theo role/status -->
  <a class="qb-um-stat <?php echo $role_filter === 'all' && $status_filter === 'all' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(admin_users_link($q, 'all', 'all', $sort, $ux), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="qb-um-stat-head"><?php echo qb_admin_stat_icon('user', 'blue'); ?><div class="qb-um-stat-label">Total Users</div></div>
    <div class="qb-um-stat-value"><?php echo (int)$stats['total']; ?></div>
  </a>
  <a class="qb-um-stat <?php echo $role_filter === 'student' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(admin_users_link($q, 'student', 'all', $sort, $ux), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="qb-um-stat-head"><?php echo qb_admin_stat_icon('user', 'blue'); ?><div class="qb-um-stat-label">Students</div></div>
    <div class="qb-um-stat-value"><?php echo (int)$stats['students']; ?></div>
  </a>
  <a class="qb-um-stat <?php echo $role_filter === 'admin' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(admin_users_link($q, 'admin', 'all', $sort, $ux), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="qb-um-stat-head"><?php echo qb_admin_stat_icon('shield', 'purple'); ?><div class="qb-um-stat-label">Admins</div></div>
    <div class="qb-um-stat-value"><?php echo (int)$stats['admins']; ?></div>
  </a>
  <a class="qb-um-stat <?php echo $status_filter === 'active' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(admin_users_link($q, 'all', 'active', $sort, $ux), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="qb-um-stat-head"><?php echo qb_admin_stat_icon('user_check', 'green'); ?><div class="qb-um-stat-label">Active</div></div>
    <div class="qb-um-stat-value"><?php echo (int)$stats['active']; ?></div>
  </a>
  <a class="qb-um-stat <?php echo $status_filter === 'locked' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(admin_users_link($q, 'all', 'locked', $sort, $ux), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="qb-um-stat-head"><?php echo qb_admin_stat_icon('user_x', 'red'); ?><div class="qb-um-stat-label">Locked</div></div>
    <div class="qb-um-stat-value"><?php echo (int)$stats['locked']; ?></div>
  </a>
</nav>

<section class="qb-um-toolbar">
  <!-- Toolbar: search + Refresh (lọc role/status chỉ qua thẻ stats phía trên). -->
  <form method="get" class="qb-um-search" action="<?php echo htmlspecialchars(admin_url('users'), ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="page" value="users" />
    <?php if ($history_user_id > 0): ?>
      <input type="hidden" name="history" value="<?php echo (int)$history_user_id; ?>" />
    <?php endif; ?>
    <?php if ($role_filter !== 'all'): ?>
      <input type="hidden" name="role" value="<?php echo htmlspecialchars($role_filter); ?>" />
    <?php endif; ?>
    <?php if ($status_filter !== 'all'): ?>
      <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>" />
    <?php endif; ?>
    <?php if ($sort !== 'name'): ?>
      <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>" />
    <?php endif; ?>
    <input type="search" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search by name, student ID, email<?php echo $qb_um_has_phone ? ', or phone' : ''; ?>..." />
  </form>
  <a class="qb-om-refresh" href="<?php echo htmlspecialchars(admin_users_link($q, $role_filter, $status_filter, $sort, $ux), ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); location.reload();">Refresh</a>
  <?php if ($role_filter !== 'admin'): ?>
    <div class="qb-um-toolbar-sort">
      <span class="qb-um-sort-label">Sort by:</span>
      <a class="qb-um-sort-chip <?php echo $sort === 'name' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_users_link($q, $role_filter, $status_filter, 'name', $ux), ENT_QUOTES, 'UTF-8'); ?>">Name</a>
      <a class="qb-um-sort-chip <?php echo $sort === 'most_orders' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_users_link($q, $role_filter, $status_filter, 'most_orders', $ux), ENT_QUOTES, 'UTF-8'); ?>">Most Orders</a>
      <a class="qb-um-sort-chip <?php echo $sort === 'most_cancelled' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_users_link($q, $role_filter, $status_filter, 'most_cancelled', $ux), ENT_QUOTES, 'UTF-8'); ?>">Most Cancelled</a>
      <a class="qb-um-sort-chip <?php echo $sort === 'highest_cancel_rate' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_users_link($q, $role_filter, $status_filter, 'highest_cancel_rate', $ux), ENT_QUOTES, 'UTF-8'); ?>">Highest Cancel Rate</a>
    </div>
  <?php endif; ?>
</section>

<?php if ($history_user_id > 0): ?>
  <!-- Lịch sử đơn (get_user_order_history); đóng = bỏ ?history= -->
  <section class="qb-admin-card qb-um-history-card" style="margin-bottom: 1rem;">
    <div style="display:flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
      <h3 style="margin:0;">Order history — <?php echo htmlspecialchars($history_user_label !== '' ? $history_user_label : ('User #' . (int)$history_user_id)); ?></h3>
      <a class="qb-om-refresh" href="<?php echo htmlspecialchars(admin_users_link($q, $role_filter, $status_filter, $sort), ENT_QUOTES, 'UTF-8'); ?>">Close</a>
    </div>
    <?php if (empty($order_history_rows)): ?>
      <p class="qb-um-muted" style="margin: 0.75rem 0 0;">No orders for this user.</p>
    <?php else: ?>
      <div class="qb-admin-table-wrap" style="margin-top: 0.75rem;">
        <table class="qb-admin-table">
          <thead>
            <tr>
              <th>Order</th>
              <th>Date</th>
              <th>Status</th>
              <th>Payment</th>
              <th>Total</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($order_history_rows as $hr): ?>
              <tr>
                <td><?php echo htmlspecialchars((string)$hr['order_code']); ?></td>
                <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string)$hr['created_at']))); ?></td>
                <td><?php echo htmlspecialchars((string)$hr['status']); ?></td>
                <td><?php echo htmlspecialchars((string)$hr['payment_status']); ?></td>
                <td><?php echo htmlspecialchars(number_format((int)$hr['total_cents'], 0, ',', '.') . '₫'); ?></td>
                <td><a href="<?php echo htmlspecialchars(admin_url('order-detail', ['id' => (int)$hr['id']]), ENT_QUOTES, 'UTF-8'); ?>">View</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<section class="qb-admin-card qb-um-table-card">
  <!-- Bảng users + nút khóa/mở; Locked = is_active 0 (không DELETE — giữ orders.user_id). -->
  <div class="qb-admin-table-wrap">
    <table class="qb-admin-table qb-um-table<?php echo $qb_um_has_phone ? ' qb-um-table--with-phone' : ''; ?>">
      <thead>
        <tr>
          <th>Full name</th>
          <th>Student ID</th>
          <th>Email</th>
          <?php if ($qb_um_has_phone): ?>
            <th>Phone</th>
          <?php endif; ?>
          <th>Role</th>
          <!-- Tại sao is_active = 0 không xóa? Giữ lịch sử đơn hàng. -->
          <th>Status</th>
          <th>Orders</th>
          <th>Cancelled</th>
          <th>Cancel %</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
          $colspan = $qb_um_has_phone ? 10 : 9;
        ?>
        <?php if (empty($users)): ?>
          <tr><td colspan="<?php echo $colspan; ?>">No users found.</td></tr>
        <?php else: ?>
          <?php foreach ($users as $u): ?>
            <?php
              $full_name = (string)$u['full_name'];
              $parts = preg_split('/\s+/', trim($full_name)) ?: [];
              $initials = '';
              foreach (array_slice($parts, 0, 2) as $pname) {
                $initials .= strtoupper(substr($pname, 0, 1));
              }
              if ($initials === '') $initials = 'U';
              $total_orders = (int)$u['total_orders'];
              $cancelled_orders = (int)$u['cancelled_orders'];
              $cancel_rate = $total_orders > 0 ? round(($cancelled_orders / $total_orders) * 100, 1) : 0.0;
            ?>
            <tr>
              <td>
                <div class="qb-um-user-cell">
                  <span class="qb-um-avatar"><?php echo htmlspecialchars($initials); ?></span>
                  <div>
                    <div class="qb-um-user-name"><?php echo htmlspecialchars($full_name); ?></div>
                    <div class="qb-um-user-sub">Joined <?php echo htmlspecialchars(date('M Y', strtotime((string)$u['created_at']))); ?></div>
                  </div>
                </div>
              </td>
              <td><?php echo htmlspecialchars((string)($u['student_id'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string)$u['email']); ?></td>
              <?php if ($qb_um_has_phone): ?>
                <td class="qb-um-td-phone"><?php echo htmlspecialchars((string)($u['phone'] ?? '') !== '' ? (string)$u['phone'] : '—'); ?></td>
              <?php endif; ?>
              <td><span class="qb-admin-pill <?php echo (string)$u['role'] === 'admin' ? 'warn' : 'success'; ?>"><?php echo htmlspecialchars((string)$u['role']); ?></span></td>
              <td>
                <?php
                  $um_uid = (int)$u['id'];
                  $um_active = (int)$u['is_active'] === 1;
                  $um_is_root = $um_uid === ADMIN_ROOT_USER_ID;
                  $um_is_self = $um_uid === (int)($admin_user['id'] ?? 0);
                  $um_can_lock = $um_active && !$um_is_root && !$um_is_self;
                ?>
                <?php // Chỉ cho click toggle khi đang locked, hoặc active mà không phải root/self admin. ?>
                <?php if (!$um_active || $um_can_lock): ?>
                  <form method="post" class="qb-um-status-form">
                    <input type="hidden" name="user_id" value="<?php echo $um_uid; ?>" />
                    <input type="hidden" name="return_url" value="<?php echo htmlspecialchars(admin_users_link($q, $role_filter, $status_filter, $sort, $ux), ENT_QUOTES, 'UTF-8'); ?>" />
                    <button class="qb-um-status-btn <?php echo $um_active ? 'active' : 'locked'; ?>" type="submit">
                      <?php echo $um_active ? 'Active' : 'Locked'; ?>
                    </button>
                  </form>
                <?php else: ?>
                  <span class="qb-um-status-static <?php echo $um_active ? 'is-active' : 'is-locked'; ?>" title="<?php echo $um_is_root ? 'Root admin cannot be locked' : 'You cannot lock your own account'; ?>"><?php echo $um_active ? 'Active' : 'Locked'; ?></span>
                <?php endif; ?>
              </td>
              <td><?php echo $total_orders; ?></td>
              <td><?php echo $cancelled_orders; ?></td>
              <td>
                <span class="qb-um-rate <?php echo $cancel_rate >= 30 ? 'high' : ''; ?>"><?php echo number_format($cancel_rate, 1); ?>%</span>
              </td>
              <td>
                <a class="qb-um-history-link" href="<?php echo htmlspecialchars(admin_users_link($q, $role_filter, $status_filter, $sort, array_merge($ux, ['history' => (string)$um_uid])), ENT_QUOTES, 'UTF-8'); ?>">Orders</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="qb-um-foot">Showing <?php echo count($users); ?> of <?php echo (int)$stats['total']; ?> users</div>
</section>
</div>
<?php qb_admin_shell_end(); ?>
