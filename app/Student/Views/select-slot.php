<?php
/** QuickBite Student — Chọn slot: HTML + echo (logic trong slot_service + slot_controller). */
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>QuickBite • Select Pickup Time Slot</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(site_url('assets/css/student.css'), ENT_QUOTES, 'UTF-8'); ?>" />
  </head>
  <body<?php echo $qb_modal ? ' class="qb-flow-embed"' : ''; ?>>
    <main class="qb-slot-page">
      <div class="qb-slot-card">
        <div class="qb-slot-head">
          <div class="qb-slot-head-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <path d="M12 8v5l3 2" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M21 12a9 9 0 1 1-9-9 9 9 0 0 1 9 9Z" stroke="white" stroke-width="2"/>
            </svg>
          </div>
          <div>
            <div class="qb-slot-head-title">Select Pickup Time Slot</div>
            <div class="qb-slot-head-sub">Choose when you'd like to pick up your order</div>
          </div>
        </div>

        <div class="qb-slot-list">
          <?php if (empty($slot_rows)): ?>
            <div class="qb-slot-empty-big">No time slots available.</div>
          <?php else: ?>
            <?php foreach ($slot_rows as $r): ?>
              <form method="post" action="<?php echo htmlspecialchars(student_url('slot'), ENT_QUOTES, 'UTF-8'); ?>" class="qb-slot-row <?php echo $r['active'] ? 'active' : ''; ?>">
                <?php echo flow_modal_hidden($qb_modal); ?>
                <input type="hidden" name="time_slot_id" value="<?php echo (int)$r['id']; ?>" />
                <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($return_to, ENT_QUOTES, 'UTF-8'); ?>" />
                <button type="submit" class="qb-slot-row-btn" <?php echo $r['slot_btn_disabled'] ? 'disabled' : ''; ?>>
                  <div class="qb-slot-row-top">
                    <div class="qb-slot-dot <?php echo htmlspecialchars($r['cls'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></div>
                    <div class="qb-slot-row-time"><?php echo htmlspecialchars($r['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php if ($r['active']): ?>
                      <div class="qb-slot-check" aria-hidden="true">✓</div>
                    <?php endif; ?>
                  </div>
                  <div class="qb-slot-row-status"><?php echo htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8'); ?></div>

                  <div class="qb-slot-row-mid">
                    <div class="qb-slot-row-label">Orders w/ prepared</div>
                    <div class="qb-slot-row-count"><?php echo (int)$r['used']; ?> / <?php echo (int)$r['cap']; ?></div>
                  </div>

                  <div class="qb-slot-bar">
                    <div class="qb-slot-bar-fill <?php echo htmlspecialchars($r['cls'], ENT_QUOTES, 'UTF-8'); ?>" style="width: <?php echo (int)$r['pct']; ?>%"></div>
                  </div>
                </button>
              </form>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="qb-slot-footer">
          <a href="<?php echo htmlspecialchars(flow_modal_url($return_to, $qb_modal)); ?>" class="qb-slot-back">Back</a>
        </div>
      </div>
    </main>
    <?php if ($qb_modal): ?>
    <?php endif; ?>
  </body>
</html>
