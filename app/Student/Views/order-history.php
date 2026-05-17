<?php
/** QuickBite Student — view only (order-history). */
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>QuickBite • Order History</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(site_url('assets/css/student.css'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo urlencode((string)@filemtime(dirname(__DIR__, 3) . '/public/assets/css/student.css')); ?>" />
  </head>
  <body<?php echo $qb_modal ? ' class="qb-flow-embed"' : ''; ?>>
    <header class="qb-topbar">
      <div class="qb-topbar-inner">
        <a class="qb-brand<?php echo $qb_modal ? ' qb-flow-close-parent' : ''; ?>" href="<?php echo htmlspecialchars(student_url('home'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="QuickBite Home">
          <div class="qb-brand-mark" aria-hidden="true"><span>Q</span></div>
          <div class="qb-brand-text">
            <div class="qb-brand-name">QuickBite</div>
            <div class="qb-brand-sub">IS-VNU</div>
          </div>
        </a>
        <div></div>
        <div class="qb-actions">
          <a class="qb-cart-btn" href="<?php echo htmlspecialchars(cart_entry_href($qb_modal)); ?>" aria-label="Open cart">
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
            <button class="qb-user" type="button" id="qbUserBtn" aria-haspopup="menu" aria-expanded="false" title="<?php echo htmlspecialchars((string)$user['full_name']); ?>">
              <?php
                $initials = '';
                $parts = preg_split('/\s+/', trim((string)$user['full_name']));
                if (is_array($parts) && count($parts) > 0) {
                  $initials = mb_strtoupper(mb_substr($parts[0], 0, 1));
                  if (count($parts) > 1) $initials .= mb_strtoupper(mb_substr($parts[count($parts) - 1], 0, 1));
                }
                if ($initials === '') $initials = 'U';
                echo htmlspecialchars($initials);
              ?>
            </button>
            <div class="qb-user-menu" id="qbUserMenu" role="menu" aria-label="User menu">
              <a role="menuitem" href="<?php echo htmlspecialchars(student_url('profile'), ENT_QUOTES, 'UTF-8'); ?>">My profile</a>
              <a role="menuitem" href="<?php echo htmlspecialchars(student_url('orders'), ENT_QUOTES, 'UTF-8'); ?>">My Orders</a>
              <div class="qb-user-sep" aria-hidden="true"></div>
              <a role="menuitem" class="danger" href="<?php echo htmlspecialchars(site_url('logout.php'), ENT_QUOTES, 'UTF-8'); ?>">Log out</a>
            </div>
          </div>
        </div>
      </div>
    </header>

    <main class="qb-orders-page">
      <!-- Hero + filter khoảng thời gian lịch sử -->
      <header class="qb-orders-hero">
        <div class="qb-orders-hero-row">
          <div class="qb-orders-hero-text">
            <h1>Order history</h1>
            <p>All orders</p>
          </div>
          <nav class="qb-orders-tabs" aria-label="Order views">
            <a href="<?php echo htmlspecialchars(flow_modal_url('orders.php', $qb_modal)); ?>">Active</a>
            <a class="active" href="<?php echo htmlspecialchars($hist_q([])); ?>" aria-current="page">History</a>
          </nav>
        </div>
      </header>

      <?php if ($cancel_flash): ?>
        <div class="qb-flash success" role="status">Your order was cancelled. If you paid online, refund details were saved with your cancellation.</div>
      <?php endif; ?>

      <div class="qb-history-filters">
        <div class="qb-history-filters-grid">
          <span class="qb-orders-toolbar-label qb-history-filters-grid-label" id="history-range-label">Time range</span>
          <div class="qb-history-filters-grid-main">
            <div class="qb-orders-filter" role="group" aria-labelledby="history-range-label">
              <a class="<?php echo $range === 'all' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($hist_q(['range' => 'all'])); ?>">All</a>
              <a class="<?php echo $range === 'today' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($hist_q(['range' => 'today'])); ?>">Today</a>
              <a class="<?php echo $range === 'week' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($hist_q(['range' => 'week'])); ?>">This week</a>
              <a class="<?php echo $range === 'month' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($hist_q(['range' => 'month'])); ?>">This month</a>
              <a class="<?php echo $range === 'custom' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($hist_q(['range' => 'custom'])); ?>">Custom</a>
            </div>
            <?php if ($range === 'custom'): ?>
              <form class="qb-history-custom-inline" method="get" action="<?php echo htmlspecialchars(student_url('order-history'), ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo flow_modal_hidden($qb_modal); ?>
              <input type="hidden" name="range" value="custom" />
              <input type="hidden" name="st" value="<?php echo htmlspecialchars($st); ?>" />
                <label class="qb-history-inline-date" for="hist-from">
                  <span>From</span>
                  <input id="hist-from" type="date" name="from" value="<?php echo htmlspecialchars($hist_from); ?>" onchange="this.form.submit()" />
                </label>
                <label class="qb-history-inline-date" for="hist-to">
                  <span>To</span>
                  <input id="hist-to" type="date" name="to" value="<?php echo htmlspecialchars($hist_to); ?>" onchange="this.form.submit()" />
                </label>
              </form>
            <?php endif; ?>
          </div>
          <span class="qb-orders-toolbar-label qb-history-filters-grid-label" id="history-status-label">Status</span>
          <div class="qb-orders-filter qb-orders-filter--wrap" role="group" aria-labelledby="history-status-label">
            <?php foreach ($status_choices as $code => $label): ?>
              <a
                class="<?php echo $st === $code ? 'active' : ''; ?>"
                href="<?php echo htmlspecialchars($hist_q(['st' => $code])); ?>"
              ><?php echo htmlspecialchars($label); ?></a>
            <?php endforeach; ?>
          </div>
        </div>

        <p class="qb-history-total" role="status">
          <?php if ($total_orders === 0): ?>
            <strong>0</strong> orders match these filters.
          <?php else: ?>
            <strong><?php echo (int)$total_orders; ?></strong> order<?php echo $total_orders === 1 ? '' : 's'; ?> match these filters
            <?php if ($total_orders > 50): ?>
              (showing the latest <strong>50</strong>)
            <?php endif; ?>.
          <?php endif; ?>
        </p>
      </div>

      <div class="qb-orders-list">
        <?php if (empty($orders)): ?>
          <div class="qb-orders-empty">
            <div class="qb-orders-empty-icon" aria-hidden="true">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                <circle cx="12" cy="12" r="9"/>
                <path d="M12 7v5l3 3" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <div class="qb-orders-empty-title">No orders in this range</div>
            <div class="qb-orders-empty-sub">Try a longer range or place a new order from the menu.</div>
            <a class="qb-orders-cta qb-orders-cta--ghost" href="<?php echo htmlspecialchars(flow_modal_url('orders.php', $qb_modal)); ?>">View active orders</a>
          </div>
        <?php else: ?>
          <?php foreach ($orders as $o): ?>
            <?php
              $oid = (int)$o['id'];
              $code = (string)$o['order_code'];
              $status = (string)$o['status'];
              [$status_label, $status_cls] = qb_status_badge($status);
              $total = (int)$o['total_cents'];
              $pm = (string)($o['payment_method'] ?? 'counter');
              $created_at = strtotime((string)$o['created_at']);
              $created_at_text = qb_datetime((string)$o['created_at']);
              $pickup = '';
              if (!empty($o['start_time']) && !empty($o['end_time'])) {
                $pickup = substr((string)$o['start_time'], 0, 5) . ' - ' . substr((string)$o['end_time'], 0, 5);
              }
              $items = $items_by_order[$oid] ?? [];
              $items_count = 0;
              foreach ($items as $it) $items_count += (int)$it['quantity'];
            ?>
            <article class="qb-order-card">
              <div class="qb-order-top">
                <div class="qb-order-left">
                  <div class="qb-order-code-row">
                    <span class="qb-order-code">#<?php echo htmlspecialchars($code); ?></span>
                    <span class="qb-order-status <?php echo htmlspecialchars($status_cls); ?>"><?php echo htmlspecialchars($status_label); ?></span>
                  </div>
                  <div class="qb-order-meta">
                    <?php echo htmlspecialchars($created_at_text); ?>
                    <?php if ($pickup !== ''): ?>
                      <span class="qb-order-meta-sep">·</span>
                      <span class="qb-order-meta-pickup">Pickup <?php echo htmlspecialchars($pickup); ?></span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="qb-order-total">
                  <div class="qb-order-total-label">Total</div>
                  <div class="qb-order-total-val"><?php echo htmlspecialchars(money_vnd($total)); ?></div>
                </div>
              </div>

              <div class="qb-order-items">
                <div class="qb-order-items-head">Items (<?php echo (int)$items_count; ?>)</div>
                <div class="qb-order-chips">
                  <?php
                    $chipShown = 0;
                    foreach ($items as $it) {
                      if ($chipShown >= 6) break;
                      $chipShown++;
                      $nm = (string)$it['product_name'];
                      $q = (int)$it['quantity'];
                      echo '<span class="qb-chip qb-chip--order">' . htmlspecialchars($q . '× ' . $nm) . '</span>';
                    }
                  ?>
                </div>
              </div>

              <div class="qb-order-pay">
                <div class="qb-order-pay-row">
                  <div class="qb-order-pay-label">Payment Method</div>
                  <div class="qb-order-pay-val"><?php echo htmlspecialchars($pm === 'online' ? 'Online' : 'At Counter'); ?></div>
                </div>
              </div>

              <div class="qb-order-actions">
                <a class="qb-order-btn primary" href="<?php echo htmlspecialchars(flow_modal_url('order-details.php?id=' . (int)$oid, $qb_modal)); ?>">View Details</a>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
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
    <?php if ($qb_modal): ?>
    <?php endif; ?>
  </body>
</html>
