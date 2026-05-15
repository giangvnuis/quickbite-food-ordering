<?php
/**
 * QuickBite Admin — Dashboard: HTML + echo (admin_dashboard_data trong dashboard_service).
 */
declare(strict_types=1);

require_once __DIR__ . '/../admin_context.php';
require_once __DIR__ . '/../Services/admin_shell_service.php';

qb_admin_shell_start('dashboard', $topbar_title, $topbar_subtitle);
?>
<div class="qb-om-page">
<section class="qb-dash-range">
  <!-- Thanh chọn mốc thời gian + form custom date range -->
  <div class="qb-dash-range-bar">
    <div class="qb-dash-tabs">
      <span class="qb-dash-tabs-label">Time Period:</span>
      <a class="<?php echo $period === 'today' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('dashboard', ['period' => 'today']), ENT_QUOTES, 'UTF-8'); ?>">Today</a>
      <a class="<?php echo $period === 'week' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('dashboard', ['period' => 'week']), ENT_QUOTES, 'UTF-8'); ?>">This Week</a>
      <a class="<?php echo $period === 'month' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('dashboard', ['period' => 'month']), ENT_QUOTES, 'UTF-8'); ?>">This Month</a>
      <a class="<?php echo $period === 'custom' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_url('dashboard', ['period' => 'custom', 'start_date' => $range_start, 'end_date' => $range_end]), ENT_QUOTES, 'UTF-8'); ?>">Custom Range</a>
    </div>
    <?php if ($period === 'custom'): ?>
      <form method="get" class="qb-dash-range-inline" action="<?php echo htmlspecialchars(site_url('admin.php'), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="page" value="dashboard" />
        <input type="hidden" name="period" value="custom" />
        <label class="qb-dash-date-label"><span class="visually-hidden">Start date</span>
          <input type="date" name="start_date" value="<?php echo htmlspecialchars($range_start); ?>" onchange="this.form.submit()" aria-label="Start date" />
        </label>
        <span class="qb-dash-date-sep" aria-hidden="true">–</span>
        <label class="qb-dash-date-label"><span class="visually-hidden">End date</span>
          <input type="date" name="end_date" value="<?php echo htmlspecialchars($range_end); ?>" onchange="this.form.submit()" aria-label="End date" />
        </label>
      </form>
    <?php endif; ?>
  </div>
</section>

<section class="qb-dash-stats">
  <?php
    $pct_orders = admin_dashboard_percent_change($stats['total_orders'], $yesterday_stats['total_orders']);
    $pct_revenue = admin_dashboard_percent_change($stats['revenue_cents'], $yesterday_stats['revenue_cents']);
  ?>
  <!-- 4 KPI tổng quan: đơn, doanh thu, prepared, completed -->
  <article class="qb-dash-stat">
    <div class="qb-dash-stat-top">
      <div class="qb-dash-stat-title-row">
        <?php echo qb_admin_stat_icon('bag', 'blue'); ?>
        <span class="qb-dash-stat-title">Total Orders</span>
      </div>
      <span class="qb-dash-stat-chip positive"><?php echo ($pct_orders >= 0 ? '+' : '') . $pct_orders; ?>%</span>
    </div>
    <div class="qb-dash-stat-value"><?php echo (int) $stats['total_orders']; ?></div>
    <div class="qb-dash-stat-note"><?php echo htmlspecialchars($compare_label); ?></div>
  </article>
  <article class="qb-dash-stat">
    <div class="qb-dash-stat-top">
      <div class="qb-dash-stat-title-row">
        <?php echo qb_admin_stat_icon('dollar', 'purple'); ?>
        <span class="qb-dash-stat-title">Revenue</span>
      </div>
      <span class="qb-dash-stat-chip positive"><?php echo ($pct_revenue >= 0 ? '+' : '') . $pct_revenue; ?>%</span>
    </div>
    <div class="qb-dash-stat-value"><?php echo htmlspecialchars(format_vnd((int) $stats['revenue_cents'])); ?></div>
    <div class="qb-dash-stat-note">from completed orders · <?php echo htmlspecialchars($compare_label); ?></div>
  </article>
  <article class="qb-dash-stat">
    <div class="qb-dash-stat-top">
      <div class="qb-dash-stat-title-row">
        <?php echo qb_admin_stat_icon('clock', 'orange'); ?>
        <span class="qb-dash-stat-title">Prepared Orders</span>
      </div>
      <span class="qb-dash-stat-chip warn"><?php echo (int) $prepared_pct; ?>%</span>
    </div>
    <div class="qb-dash-stat-value"><?php echo (int) $stats['prepared_orders']; ?></div>
    <div class="qb-dash-stat-note">require slot booking</div>
  </article>
  <article class="qb-dash-stat">
    <div class="qb-dash-stat-top">
      <div class="qb-dash-stat-title-row">
        <?php echo qb_admin_stat_icon('user', 'blue'); ?>
        <span class="qb-dash-stat-title">Students ordering</span>
      </div>
      <span class="qb-dash-stat-chip info">live</span>
    </div>
    <div class="qb-dash-stat-value"><?php echo (int) $stats['students_ordering']; ?></div>
    <div class="qb-dash-stat-note">active carts now</div>
  </article>
  <article class="qb-dash-stat">
    <div class="qb-dash-stat-top">
      <div class="qb-dash-stat-title-row">
        <?php echo qb_admin_stat_icon('check', 'green'); ?>
        <span class="qb-dash-stat-title">Completed Orders</span>
      </div>
      <span class="qb-dash-stat-chip info"><?php echo (int) $completed_pct; ?>%</span>
    </div>
    <div class="qb-dash-stat-value"><?php echo (int) $stats['completed_orders']; ?></div>
    <div class="qb-dash-stat-note">of period total</div>
  </article>
</section>

<section class="qb-dash-chart" aria-labelledby="dash-slot-chart-title">
  <div class="qb-dash-chart-head">
    <?php echo qb_admin_stat_icon('clock', 'orange'); ?>
    <h3 id="dash-slot-chart-title">Today’s orders by pickup slot</h3>
  </div>
  <p class="qb-dash-chart-sub">Count of orders created today, grouped by time slot (prepared flow).</p>
  <?php if (empty($today_slot_summary)): ?>
    <div class="qb-dash-chart-empty">No active time slots configured.</div>
  <?php else: ?>
    <?php
      $chart_max = max(1, (int) $today_slot_chart_max);
    ?>
    <div class="qb-dash-chart-rows">
      <?php foreach ($today_slot_summary as $slot_row): ?>
        <?php
          $cnt = (int) $slot_row['order_count'];
          $bar_pct = (int) round(($cnt / $chart_max) * 100);
        ?>
        <div class="qb-dash-chart-row">
          <div class="qb-dash-chart-label"><?php echo htmlspecialchars($slot_row['label']); ?></div>
          <div class="qb-dash-chart-bar-wrap" role="img" aria-label="<?php echo (int) $cnt; ?> orders">
            <div class="qb-dash-chart-bar" style="width: <?php echo (int) $bar_pct; ?>%;"></div>
          </div>
          <div class="qb-dash-chart-n"><?php echo (int) $cnt; ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="qb-dash-grid">
  <!-- Cột trái: top selling items -->
  <section class="qb-dash-card">
    <div class="qb-dash-card-head">
      <div class="qb-dash-card-title-row">
        <?php echo qb_admin_stat_icon('trending', 'orange'); ?>
        <h3>Top Selling Items</h3>
      </div>
    </div>
    <div class="qb-dash-top-list">
      <?php if (empty($top_items)): ?>
        <div class="qb-dash-empty">No completed sales in selected period.</div>
      <?php else: ?>
        <?php foreach ($top_items as $idx => $it): ?>
          <div class="qb-dash-top-row">
            <div class="qb-dash-rank"><?php echo $idx + 1; ?></div>
            <div class="qb-dash-item-meta">
              <div class="qb-dash-item-name"><?php echo htmlspecialchars((string) $it['product_name']); ?></div>
              <div class="qb-dash-item-sub"><?php echo (int) $it['qty']; ?> orders</div>
            </div>
            <div class="qb-dash-item-qty"><?php echo (int) $it['qty']; ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
  <!-- Cột phải: đơn gần nhất + link sang Order Management -->
  <section class="qb-dash-card">
    <div class="qb-dash-card-head">
      <div class="qb-dash-card-title-row">
        <?php echo qb_admin_stat_icon('list', 'blue'); ?>
        <h3>Recent Orders</h3>
      </div>
      <a class="qb-dash-link" href="<?php echo htmlspecialchars(admin_url('orders'), ENT_QUOTES, 'UTF-8'); ?>">View All</a>
    </div>
    <div class="qb-dash-recent-list">
      <?php if (empty($recent_orders)): ?>
        <div class="qb-dash-empty">No orders yet.</div>
      <?php else: ?>
        <?php foreach ($recent_orders as $o): ?>
          <?php
            $st = (string) $o['status'];
            $slot = (string) ($o['start_time'] ?? '');
            $slot2 = (string) ($o['end_time'] ?? '');
            $slot_label = ($slot !== '' && $slot2 !== '')
              ? date('H:i', strtotime($slot)) . ' - ' . date('H:i', strtotime($slot2))
              : date('H:i', strtotime((string) $o['created_at']));
            [$dash_status_label, $dash_status_cls] = qb_status_badge($st);
          ?>
          <div class="qb-dash-recent-row">
            <div>
              <div class="qb-dash-recent-code">#<?php echo htmlspecialchars((string) $o['order_code']); ?></div>
              <div class="qb-dash-recent-sub"><?php echo htmlspecialchars((string) $o['full_name']); ?> · <?php echo htmlspecialchars($slot_label); ?></div>
            </div>
            <div class="qb-dash-recent-right">
              <span class="qb-dash-status <?php echo htmlspecialchars($dash_status_cls); ?>"><?php echo htmlspecialchars($dash_status_label); ?></span>
              <strong><?php echo htmlspecialchars(format_vnd((int) $o['total_cents'])); ?></strong>
              <small><?php echo htmlspecialchars(date('h:i A', strtotime((string) $o['created_at']))); ?></small>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</section>

</div>
<?php qb_admin_shell_end(); ?>
