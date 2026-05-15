<?php
/** * View hiển thị danh sách đơn hàng. 
 * tập trung vào 3 yếu tố cốt lõi:
 * 1. UX (Trải nghiệm người dùng): Tăng tốc độ nhận diện thông tin đơn hàng.
 * 2. Business Logic (Nghiệp vụ): Ràng buộc thời gian thực để bảo vệ doanh thu.
 * 3. Reusability (Tái sử dụng): Cấu trúc code linh hoạt cho nhiều ngữ cảnh hiển thị.
 */
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>QuickBite • My Orders</title>
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
        
        <div></div> <div class="qb-actions">
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
                // [1. UX] Cá nhân hóa: Tự động tách tên để tạo Avatar chữ cái (Ví dụ: Vũ Huyền -> VH) giúp giao diện chuyên nghiệp hơn
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
      <header class="qb-orders-hero">
        <div class="qb-orders-hero-row">
          <div class="qb-orders-hero-text">
            <h1>My orders</h1>
            <p>Today’s orders only</p>
          </div>
          <nav class="qb-orders-tabs" aria-label="Order views">
            <a class="active" href="<?php echo htmlspecialchars($orders_q(['st' => $st])); ?>" aria-current="page">Active</a>
            <a href="<?php echo htmlspecialchars(flow_modal_url('order-history.php', $qb_modal)); ?>">History</a>
          </nav>
        </div>
      </header>

      <div class="qb-history-filters qb-orders-active-filters">
        <div class="qb-history-filters-row-status">
          <span class="qb-orders-toolbar-label" id="active-status-label">Status</span>
          <div class="qb-orders-filter qb-orders-filter--wrap" role="group" aria-labelledby="active-status-label">
            <?php foreach ($status_choices as $code => $label): ?>
              <a
                class="<?php echo $st === $code ? 'active' : ''; ?>"
                href="<?php echo htmlspecialchars($orders_q(['st' => $code])); ?>"
              ><?php echo htmlspecialchars($label); ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <p class="qb-history-total" role="status">
          <strong><?php echo (int)$total_orders; ?></strong> <?php echo $total_orders === 1 ? 'order matches' : 'orders match'; ?> this filter today.
        </p>
      </div>

      <div class="qb-orders-list">
        <?php if (empty($orders)): ?>
          <div class="qb-orders-empty">
            <div class="qb-orders-empty-icon" aria-hidden="true">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
                <path d="M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v0a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2v0Z"/>
                <path d="M9 12h6M9 16h6" stroke-linecap="round"/>
              </svg>
            </div>
            <div class="qb-orders-empty-title">No orders here</div>
            <a class="qb-orders-cta<?php echo $qb_modal ? ' qb-flow-close-parent' : ''; ?>" href="<?php echo htmlspecialchars(student_url('home'), ENT_QUOTES, 'UTF-8'); ?>">Browse menu</a>
          </div>
        <?php else: ?>
          
          <?php foreach ($orders as $o): ?>
            <?php
              $oid = (int)$o['id'];
              $code = (string)$o['order_code'];
              $status = (string)$o['status'];
              // [3. REUSE] Global Helper: Hàm qb_status_badge() trả về nhãn và màu sắc thống nhất cho toàn hệ thống
              [$status_label, $status_cls] = qb_status_badge($status);
              $total = (int)$o['total_cents'];
              $pm = (string)($o['payment_method'] ?? 'counter');
              $created_at = strtotime((string)$o['created_at']);
              $pay_status = strtolower((string)($o['payment_status'] ?? 'unpaid'));

              /**
               * [2. LOGIC] NGHIỆP VỤ HỦY ĐƠN (CANCEL WINDOW)
               * Quy định: Chỉ được hủy đơn trong 5 phút đầu để nhà bếp chưa kịp làm.
               * Tác dụng: Giảm thiểu lãng phí thực phẩm và xung đột doanh thu cho căng tin.
               */
              $within_cancel_window = ($created_at > 0 && (time() - $created_at) <= 300);
              $can_cancel = ($status === 'pending' && $within_cancel_window && !in_array($pay_status, ['refund_requested', 'refunded'], true));
              $cancel_window_ended = ($status === 'pending' && !$within_cancel_window && !in_array($pay_status, ['refund_requested', 'refunded'], true));
              $refund_requested_row = ($status === 'pending' && $pay_status === 'refund_requested');

              $pickup = '';
              if (!empty($o['start_time']) && !empty($o['end_time'])) {
                $pickup = substr((string)$o['start_time'], 0, 5) . ' – ' . substr((string)$o['end_time'], 0, 5);
              }

              $items = $items_by_order[$oid] ?? [];
              $items_count = 0;
              foreach ($items as $it) $items_count += (int)$it['quantity'];
            ?>

            <article class="qb-order-card <?php echo $status === 'ready' ? 'highlight' : ''; ?>">
              <?php if ($status === 'ready'): ?>
                <div class="qb-order-banner">
                  🎉 Your order is ready for pickup!
                </div>
              <?php endif; ?>

              <div class="qb-order-top">
                <div class="qb-order-left">
                  <div class="qb-order-code-row">
                    <span class="qb-order-code">#<?php echo htmlspecialchars($code); ?></span>
                    <span class="qb-order-status <?php echo htmlspecialchars($status_cls); ?>"><?php echo htmlspecialchars($status_label); ?></span>
                  </div>
                  <div class="qb-order-meta"><?php echo htmlspecialchars(qb_time_ago($created_at)); ?></div>
                </div>
                <div class="qb-order-total">
                  <div class="qb-order-total-label">Total</div>
                  <div class="qb-order-total-val"><?php echo htmlspecialchars(money_vnd($total)); ?></div>
                </div>
              </div>

              <?php if ($pickup !== ''): ?>
                <div class="qb-order-pickup">
                  <div class="qb-order-pickup-ic" aria-hidden="true">🕒</div>
                  <div>
                    <div class="qb-order-pickup-label">Pickup Time</div>
                    <div class="qb-order-pickup-time"><?php echo htmlspecialchars($pickup); ?></div>
                  </div>
                </div>
              <?php endif; ?>

              <div class="qb-order-items">
                <div class="qb-order-items-head">Order Items (<?php echo (int)$items_count; ?>)</div>
                <div class="qb-order-lines">
                  <?php
                    $lineShown = 0;
                    foreach ($items as $it) {
                      // [1. UX] Truncation: Chỉ hiện tối đa 6 món đầu để giữ thẻ đơn hàng gọn gàng, cân đối
                      if ($lineShown >= 6) break;
                      $lineShown++;
                      $nm = (string)$it['product_name'];
                      $q = (int)$it['quantity'];
                      $unit = (int)$it['unit_price_cents'];
                      $lineTotal = $q * $unit;
                      ?>
                      <div class="qb-order-line">
                        <div class="qb-order-line-name"><?php echo htmlspecialchars($nm); ?> <span class="qb-x">×<?php echo (int)$q; ?></span></div>
                        <div class="qb-order-line-price"><?php echo htmlspecialchars(money_vnd($lineTotal)); ?></div>
                      </div>
                      <?php
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
                
                <?php if ($can_cancel): ?>
                  <a class="qb-order-btn ghost qb-order-btn--cancel" href="<?php echo htmlspecialchars(flow_modal_url('order-details.php?id=' . (int)$oid, $qb_modal) . '#cancel-form'); ?>">Cancel Order</a>
                <?php elseif ($cancel_window_ended): ?>
                  <button type="button" class="qb-order-btn qb-order-btn--cancel-disabled" disabled aria-disabled="true">Cancel Order</button>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </main>

    <?php if ($pay_success): ?>
      <div class="qb-modal" role="dialog" aria-modal="true" aria-labelledby="pay-success-title">
        <div class="qb-modal-card">
          <div class="qb-modal-top">
            <div class="qb-modal-check" aria-hidden="true">✓</div>
            <div class="qb-modal-title" id="pay-success-title">Payment successful</div>
          </div>
          <div class="qb-modal-body">
            <div class="qb-modal-label">Order number</div>
            <div class="qb-modal-code">#<?php echo htmlspecialchars($pay_success_code); ?></div>
          </div>
          <div class="qb-modal-actions">
            <a class="qb-modal-primary" href="<?php echo htmlspecialchars(flow_modal_url('order-details.php?id=' . $pay_success_oid, $qb_modal)); ?>">View details</a>
            <a class="qb-modal-secondary" href="<?php echo htmlspecialchars($orders_continue_href); ?>">Continue</a>
          </div>
        </div>
      </div>
    <?php endif; ?>

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
