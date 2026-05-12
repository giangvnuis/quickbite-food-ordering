<?php
/**
 * QuickBite Student — Trang chờ thanh toán online (demo): HTML + echo (logic trong payment_service + payment_controller).
 */
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>QuickBite • Pay</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(site_url('assets/css/student.css'), ENT_QUOTES, 'UTF-8'); ?>" />
  </head>
  <body<?php echo $qb_modal ? ' class="qb-flow-embed"' : ''; ?>>
    <main class="qb-pay">
      <div class="qb-pay-card">
        <div class="qb-pay-title">Online Payment</div>
        <div class="qb-pay-sub">This is a simulation (no real gateway).</div>

        <?php if ($pay_error !== ''): ?>
          <div class="qb-flash error" role="alert"><?php echo htmlspecialchars($pay_error); ?></div>
        <?php endif; ?>

        <div class="qb-pay-row">
          <div class="qb-pay-label">Order</div>
          <div class="qb-pay-value">#<?php echo htmlspecialchars((string)$order['order_code']); ?></div>
        </div>
        <div class="qb-pay-row">
          <div class="qb-pay-label">Total</div>
          <div class="qb-pay-value"><?php echo htmlspecialchars(money_vnd((int)$order['total_cents'])); ?></div>
        </div>

        <div class="qb-pay-qr-wrap">
          <p class="qb-pay-qr-label">Scan to pay <span class="qb-pay-qr-badge">demo</span></p>
          <div class="qb-pay-qr-frame">
            <?php echo $pay_qr_svg; ?>
          </div>
          <p class="qb-pay-qr-hint">This QR is for display only — tap Pay Now below to complete the simulation.</p>
        </div>

        <form method="post" action="<?php echo htmlspecialchars(flow_modal_url('pay.php?order_id=' . (int)$order_id, $qb_modal)); ?>">
          <?php echo flow_modal_hidden($qb_modal); ?>
          <button class="qb-pay-now" type="submit">Pay Now</button>
        </form>

        <a class="qb-pay-back" href="<?php echo htmlspecialchars(flow_modal_url('orders.php', $qb_modal)); ?>">Back to my orders</a>
      </div>
    </main>
    <?php if ($qb_modal): ?>
    <?php endif; ?>
  </body>
</html>
