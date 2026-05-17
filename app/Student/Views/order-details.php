<?php
/** QuickBite Student — view only (order-detail). */
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>QuickBite • Order Details</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(site_url('assets/css/student.css'), ENT_QUOTES, 'UTF-8'); ?>" />
  </head>
  <body<?php echo $qb_modal ? ' class="qb-flow-embed"' : ''; ?>>
    <main class="qb-od">
      <a class="qb-od-back" href="<?php echo htmlspecialchars(flow_modal_url('orders.php', $qb_modal)); ?>">← Back to My Orders</a>
      <?php if ($flash_error !== ''): ?>
        <div class="qb-flash error" style="margin-top:12px;"><?php echo htmlspecialchars($flash_error); ?></div>
      <?php endif; ?>
      <?php if ($flash_success !== ''): ?>
        <div class="qb-flash success" style="margin-top:12px;"><?php echo htmlspecialchars($flash_success); ?></div>
      <?php endif; ?>

      <section class="qb-od-hero">
        <div class="qb-od-hero-top">
          <div>
            <div class="qb-od-hero-label">Order Number</div>
            <div class="qb-od-hero-code">#<?php echo htmlspecialchars((string)$order['order_code']); ?></div>
            <div class="qb-od-status <?php echo htmlspecialchars($status_cls); ?>"><?php echo htmlspecialchars($status_label); ?></div>
          </div>
          <div class="qb-od-amount">
            <div class="qb-od-hero-label">Total Amount</div>
            <div class="qb-od-hero-total"><?php echo htmlspecialchars(money_vnd($total)); ?></div>
          </div>
        </div>
        <div class="qb-od-hero-note">
          <div class="qb-od-note-title">Your can contact our staff at <a href="tel:+84 867652305">+84 867652305</a>.</div>
        </div>
      </section>

      <section class="qb-od-grid">
        <div class="qb-od-left">
          <div class="qb-od-card">
            <div class="qb-od-card-title">Student Information</div>
            <div class="qb-od-info-row">
              <div class="qb-od-info-label">Full Name</div>
              <div class="qb-od-info-value"><?php echo htmlspecialchars($student_name); ?></div>
            </div>
            <div class="qb-od-info-row">
              <div class="qb-od-info-label">Student ID (MSSV)</div>
              <div class="qb-od-info-value"><?php echo htmlspecialchars($student_id !== '' ? $student_id : $student_email); ?></div>
            </div>
            <?php if ($has_user_phone): ?>
              <div class="qb-od-info-row">
                <div class="qb-od-info-label">Phone</div>
                <div class="qb-od-info-value"><?php echo htmlspecialchars($student_phone !== '' ? $student_phone : '—'); ?></div>
              </div>
            <?php endif; ?>
          </div>

          <div class="qb-od-card">
            <div class="qb-od-card-title">Pickup Information</div>
            <div class="qb-od-pickup">
              <div class="qb-od-pickup-ic" aria-hidden="true">🕒</div>
              <div>
                <div class="qb-od-pickup-label">Pickup Time Slot</div>
                <div class="qb-od-pickup-time"><?php echo htmlspecialchars($pickup !== '' ? $pickup : '—'); ?></div>
                <div class="qb-od-pickup-sub">Order Placed<br/><?php echo htmlspecialchars($created_at_fmt); ?></div>
              </div>
            </div>
          </div>

          <div class="qb-od-card">
            <div class="qb-od-card-title">Payment Details</div>
            <div class="qb-od-pay-row">
              <div class="qb-od-pay-label">Payment Method</div>
              <div class="qb-od-pay-value"><?php echo (($order['payment_method'] ?? '') === 'online') ? 'Online Payment' : 'Pay at Counter'; ?></div>
            </div>
            <div class="qb-od-pay-row">
              <div class="qb-od-pay-label">Payment Status</div>
              <?php
                $ps = strtolower((string)($order['payment_status'] ?? 'unpaid'));
                if (!in_array($ps, ['paid','unpaid','failed','refund_requested','refunded'], true)) $ps = 'unpaid';
                $psLabel = match ($ps) {
                  'paid' => 'Paid',
                  'refund_requested' => 'Refund Requested',
                  'refunded' => 'Refunded',
                  'failed' => 'Failed',
                  default => 'Unpaid',
                };
                $psCls = match ($ps) {
                  'paid' => 'paid',
                  'refund_requested' => 'refund-requested',
                  'refunded' => 'refunded',
                  'failed' => 'failed',
                  default => 'unpaid',
                };
              ?>
              <div class="qb-od-pay-pill <?php echo htmlspecialchars($psCls); ?>"><?php echo htmlspecialchars($psLabel); ?></div>
            </div>
          </div>

          <div class="qb-od-card">
            <div class="qb-od-card-title">Order Items (<?php echo count($items); ?>)</div>
            <div class="qb-od-items">
              <?php foreach ($items as $it): ?>
                <?php
                  $nm = (string)$it['product_name'];
                  $qty = (int)$it['quantity'];
                  $unit = (int)$it['unit_price_cents'];
                  $ptype = (string)($it['product_type'] ?? 'prepared');
                  $ptype = ($ptype === 'instant') ? 'instant' : 'prepared';
                  $line = $qty * $unit;
                ?>
                <div class="qb-od-item">
                  <span class="qb-tag <?php echo htmlspecialchars($ptype); ?>"><?php echo $ptype === 'instant' ? 'Instant' : 'Prepared'; ?></span>
                  <div class="qb-od-item-main">
                    <div class="qb-od-item-name"><?php echo htmlspecialchars($nm); ?></div>
                    <div class="qb-od-item-sub"><?php echo htmlspecialchars(money_vnd($unit)); ?> × <?php echo (int)$qty; ?></div>
                  </div>
                  <div class="qb-od-item-price"><?php echo htmlspecialchars(money_vnd($line)); ?></div>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="qb-od-total-row">
              <div>Total</div>
              <div><?php echo htmlspecialchars(money_vnd($total)); ?></div>
            </div>
          </div>
        </div>

        <div class="qb-od-right">
          <div class="qb-od-card">
            <div class="qb-od-card-title">Actions</div>
            <?php if ($can_cancel): ?>
              <form id="cancel-form" method="post" action="<?php echo htmlspecialchars(flow_modal_url('order-details.php?id=' . (int)$order_id, $qb_modal)); ?>">
                <?php echo flow_modal_hidden($qb_modal); ?>
                <input type="hidden" name="action" value="cancel_order" />
                <?php if ($use_bank_cancel_form): ?>
                  <button class="qb-od-danger qb-od-danger--action" type="button" id="qbShowBankCancelBtn">Cancel order</button>
                  <div id="qbBankCancelFields" class="qb-bank-cancel-fields">
                    <?php if ($payment_status === 'paid'): ?>
                      <p class="qb-bank-cancel-hint">This order was paid online. Enter your bank details so we can process a refund.</p>
                    <?php else: ?>
                      <p class="qb-bank-cancel-hint">You chose online payment. Enter your bank details to complete cancellation.</p>
                    <?php endif; ?>
                    <input type="text" name="refund_bank" placeholder="Bank name" required class="qb-bank-cancel-input" autocomplete="off" />
                    <input type="text" name="refund_account_number" placeholder="Account number" required class="qb-bank-cancel-input" autocomplete="off" />
                    <input type="text" name="refund_account_name" placeholder="Account holder name" required class="qb-bank-cancel-input" autocomplete="name" />
                    <button class="qb-od-danger qb-od-danger--action" type="submit"><?php echo $payment_status === 'paid' ? 'Submit refund request' : 'Confirm cancellation'; ?></button>
                  </div>
                <?php else: ?>
                  <button class="qb-od-danger qb-od-danger--action" type="submit">Cancel order</button>
                <?php endif; ?>
              </form>
            <?php elseif ($status === 'pending' && $payment_status === 'refund_requested'): ?>
              <button class="qb-od-danger qb-od-danger--warning qb-od-danger--action" type="button" disabled aria-disabled="true">Refund requested</button>
              <p class="qb-od-action-hint">Your request is pending admin confirmation.</p>
            <?php elseif ($status === 'pending' && !$within_5m && !in_array($payment_status, ['refund_requested', 'refunded'], true)): ?>
              <button class="qb-od-danger qb-od-danger--muted qb-od-danger--action" type="button" disabled aria-disabled="true">Cancel Order</button>
              <p class="qb-od-action-hint">Cancellation is only available within 5 minutes after placing the order.</p>
            <?php else: ?>
              <button class="qb-od-danger qb-od-danger--muted qb-od-danger--action" type="button" disabled aria-disabled="true">Cancel Order</button>
            <?php endif; ?>
            <a class="qb-od-ghost" href="<?php echo htmlspecialchars(flow_modal_url('orders.php', $qb_modal)); ?>">Back to Orders</a>
          </div>

          <div class="qb-od-card">
            <div class="qb-od-card-title">Order Timeline</div>
            <div class="qb-od-tl">
              <div class="qb-od-tl-item done">
                <div class="qb-od-tl-dot"></div>
                <div>
                  <div class="qb-od-tl-title">Order Placed</div>
                  <div class="qb-od-tl-sub"><?php echo htmlspecialchars($created_at_fmt); ?></div>
                </div>
              </div>
              <div class="qb-od-tl-item <?php echo $tl_preparing ? 'done' : 'wait'; ?>">
                <div class="qb-od-tl-dot"></div>
                <div>
                  <div class="qb-od-tl-title">Being Prepared</div>
                  <div class="qb-od-tl-sub"><?php echo $tl_preparing ? 'In progress' : 'Waiting'; ?></div>
                </div>
              </div>
              <div class="qb-od-tl-item <?php echo $tl_ready ? 'done' : 'wait'; ?>">
                <div class="qb-od-tl-dot"></div>
                <div>
                  <div class="qb-od-tl-title">Ready for Pickup</div>
                  <div class="qb-od-tl-sub"><?php echo $tl_ready ? 'Ready' : 'Waiting'; ?></div>
                </div>
              </div>
              <div class="qb-od-tl-item <?php echo $tl_completed ? 'done' : 'wait'; ?>">
                <div class="qb-od-tl-dot"></div>
                <div>
                  <div class="qb-od-tl-title">Completed</div>
                  <div class="qb-od-tl-sub"><?php echo $tl_completed ? 'Completed' : 'Pending'; ?></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>
    <script>
      (function () {
        const showBtn = document.getElementById('qbShowBankCancelBtn');
        const fields = document.getElementById('qbBankCancelFields');
        if (!showBtn || !fields) return;
        showBtn.addEventListener('click', function () {
          showBtn.style.display = 'none';
          fields.style.display = 'grid';
          const firstInput = fields.querySelector('input[name="refund_bank"]');
          if (firstInput) firstInput.focus();
        });
      })();
    </script>
    <?php if ($qb_modal): ?>
    <?php endif; ?>
  </body>
</html>

