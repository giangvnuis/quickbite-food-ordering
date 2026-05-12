<?php
/**
 * DRY — kiểm tra / đồng bộ tồn kho dùng chung checkout, counter, admin (TV2).
 *
 * Tồn kho món instant: validate / trừ / hoàn; checkout student trừ ngay (marker trong notes) để tránh oversell.
 */
declare(strict_types=1);

/** Có trong orders.notes khi đã trừ stock lúc checkout (student) — picked_up không trừ lần 2. */
const QB_INSTANT_STOCK_CHECKOUT_MARKER = '[QB_STOCK_AT_CHECKOUT]';

// --- Có cột stock_qty trên products không (cache) ---

function qb_products_has_stock_qty(mysqli $conn): bool {
  static $cached = null;
  if ($cached !== null) {
    return $cached;
  }
  $res = $conn->query("SHOW COLUMNS FROM `products` LIKE 'stock_qty'");
  $cached = $res instanceof mysqli_result && $res->num_rows > 0;
  return $cached;
}

function qb_order_notes_has_checkout_stock_deduct(mysqli $conn, int $order_id): bool {
  $stmt = $conn->prepare('SELECT COALESCE(notes, \'\') AS n FROM orders WHERE id = ? LIMIT 1');
  if (!$stmt) {
    return false;
  }
  $stmt->bind_param('i', $order_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  if (!$row) {
    return false;
  }
  return str_contains((string)$row['n'], QB_INSTANT_STOCK_CHECKOUT_MARKER);
}

// --- Trước khi tạo đơn: đủ tồn cho từng dòng instant ---

/**
 * @param list<array{product_id:int,quantity:int}> $lines
 */
function qb_instant_stock_validate_available(mysqli $conn, array $lines): ?string {
  // Pre-check nhẹ trước transaction checkout/counter order để báo lỗi sớm cho người dùng.
  if (!qb_products_has_stock_qty($conn)) {
    return null;
  }
  foreach ($lines as $it) {
    $pid = (int)($it['product_id'] ?? 0);
    $need = (int)($it['quantity'] ?? 0);
    if ($pid <= 0 || $need <= 0) {
      continue;
    }
    $st = $conn->prepare("SELECT stock_qty, name FROM products WHERE id = ? AND product_type = 'instant' LIMIT 1");
    if (!$st) {
      return 'Could not check stock.';
    }
    $st->bind_param('i', $pid);
    $st->execute();
    $res = $st->get_result();
    $r = $res ? $res->fetch_assoc() : null;
    $st->close();
    if (!$r) {
      return 'A selected product is not available.';
    }
    $have = (int)($r['stock_qty'] ?? 0);
    if ($have < $need) {
      $name = (string)($r['name'] ?? 'Item');
      return 'Not enough stock for ' . $name . ' (need ' . $need . ', have ' . $have . ').';
    }
  }
  return null;
}

/**
 * Trong transaction: khóa từng dòng instant, trừ stock, gắn marker vào orders.notes (student checkout).
 */
function qb_instant_stock_apply_student_checkout(mysqli $conn, int $order_id): bool {
  // Khi checkout student: lock từng product instant và trừ stock ngay để chống oversell.
  if (!qb_products_has_stock_qty($conn)) {
    return true;
  }
  $stmt = $conn->prepare(
    "SELECT product_id, SUM(quantity) AS q
     FROM order_items
     WHERE order_id = ? AND LOWER(TRIM(COALESCE(product_type, ''))) = 'instant'
     GROUP BY product_id"
  );
  if (!$stmt) {
    return false;
  }
  $stmt->bind_param('i', $order_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = [];
  if ($res) {
    while ($r = $res->fetch_assoc()) {
      $rows[] = ['product_id' => (int)$r['product_id'], 'q' => (int)$r['q']];
    }
  }
  $stmt->close();
  if ($rows === []) {
    return true;
  }

  usort($rows, static fn ($a, $b) => $a['product_id'] <=> $b['product_id']);

  foreach ($rows as $r) {
    $pid = $r['product_id'];
    $need = $r['q'];
    if ($pid <= 0 || $need <= 0) {
      continue;
    }
    $lk = $conn->prepare("SELECT id, stock_qty FROM products WHERE id = ? AND product_type = 'instant' LIMIT 1 FOR UPDATE");
    if (!$lk) {
      return false;
    }
    $lk->bind_param('i', $pid);
    $lk->execute();
    $r2 = $lk->get_result()->fetch_assoc();
    $lk->close();
    if (!$r2) {
      return false;
    }
    $have = (int)($r2['stock_qty'] ?? 0);
    if ($have < $need) {
      return false;
    }
    $up = $conn->prepare('UPDATE products SET stock_qty = stock_qty - ? WHERE id = ? AND stock_qty >= ?');
    if (!$up) {
      return false;
    }
    $up->bind_param('iii', $need, $pid, $need);
    if (!$up->execute() || $up->affected_rows !== 1) {
      $up->close();
      return false;
    }
    $up->close();
  }

  $mark = $conn->prepare(
    "UPDATE orders SET notes = TRIM(CONCAT(COALESCE(notes, ''), ' ', ?)) WHERE id = ? LIMIT 1"
  );
  if (!$mark) {
    return false;
  }
  $m = QB_INSTANT_STOCK_CHECKOUT_MARKER;
  $mark->bind_param('si', $m, $order_id);
  $ok = $mark->execute();
  $mark->close();
  return $ok;
}

// --- Sau picked_up: trừ tồn theo order_items instant (đơn cũ / counter không trừ lúc checkout) ---

function qb_instant_stock_subtract_for_order(mysqli $conn, int $order_id): bool {
  // Dùng cho đơn không trừ ở checkout (vd counter/legacy), tránh trừ lặp nhờ marker notes.
  if (!qb_products_has_stock_qty($conn)) {
    return true;
  }
  if (qb_order_notes_has_checkout_stock_deduct($conn, $order_id)) {
    return true;
  }
  $sql = 'UPDATE products p
    INNER JOIN (
      SELECT product_id, SUM(quantity) AS q
      FROM order_items
      WHERE order_id = ? AND LOWER(TRIM(COALESCE(product_type, \'\'))) = \'instant\'
      GROUP BY product_id
    ) t ON t.product_id = p.id
    SET p.stock_qty = GREATEST(0, COALESCE(p.stock_qty, 0) - t.q)';
  $st = $conn->prepare($sql);
  if (!$st) {
    return false;
  }
  $st->bind_param('i', $order_id);
  $ok = $st->execute();
  $st->close();
  return $ok;
}

// --- Hoàn tồn khi hủy / revert trạng thái khỏi picked_up ---

function qb_instant_stock_restore_for_order(mysqli $conn, int $order_id): bool {
  if (!qb_products_has_stock_qty($conn)) {
    return true;
  }
  $sql = 'UPDATE products p
    INNER JOIN (
      SELECT product_id, SUM(quantity) AS q
      FROM order_items
      WHERE order_id = ? AND LOWER(TRIM(COALESCE(product_type, \'\'))) = \'instant\'
      GROUP BY product_id
    ) t ON t.product_id = p.id
    SET p.stock_qty = COALESCE(p.stock_qty, 0) + t.q';
  $st = $conn->prepare($sql);
  if (!$st) {
    return false;
  }
  $st->bind_param('i', $order_id);
  $ok = $st->execute();
  $st->close();
  return $ok;
}

// --- Admin đổi status: trừ tại picked_up chỉ khi chưa trừ lúc checkout; hoàn khi hủy / bỏ picked_up (trừ khi tồn đã khóa lúc checkout) ---

function qb_instant_stock_sync_picked_up_change(mysqli $conn, int $order_id, string $oldStatus, string $newStatus): bool {
  // Tại sao cần hàm riêng khi admin đổi status?
  // — Sinh viên checkout online đã trừ tồn ngay (marker trong notes) → không được trừ thêm khi admin bấm picked_up.
  // — Đơn quầy / đơn cũ trừ tồn lúc chuyển sang picked_up; nếu hủy hoặc bỏ picked_up phải hoàn lại đúng lượng instant.

  $old = strtolower($oldStatus);
  $new = strtolower($newStatus);
  $wasPu = ($old === 'picked_up');
  $nowPu = ($new === 'picked_up');
  $hadCheckout = qb_order_notes_has_checkout_stock_deduct($conn, $order_id);
  // Tại sao đọc hadCheckout từ notes?
  // — Phân biệt hai luồng: đã trừ khi thanh toán (SV) vs chưa trừ (counter) để không double-deduct / hoàn sai.

  if ($new === 'cancelled') {
    // Tại sao hủy luôn thử hoàn nếu đã checkout hoặc đã từng picked_up?
    // — Checkout: tiền đã trừ tồn → hủy phải cộng lại.
    // — Picked_up chưa qua checkout marker: tồn đã trừ lúc pickup → hủy cũng phải hoàn.
    if ($hadCheckout) {
      return qb_instant_stock_restore_for_order($conn, $order_id);
    }
    if ($wasPu) {
      return qb_instant_stock_restore_for_order($conn, $order_id);
    }
    return true;
  }

  if ($wasPu === $nowPu) {
    // Tại sao thoát sớm?
    // — Không qua/về picked_up thì không đổi “đã giao hàng / đã trừ tồn theo pickup”.
    return true;
  }
  if ($nowPu) {
    // Tại sao gọi subtract khi vừa vào picked_up?
    // — subtract_for_order tự bỏ qua nếu đã có marker checkout (đã trừ lúc thanh toán).
    return qb_instant_stock_subtract_for_order($conn, $order_id);
  }
  // Tại sao hoàn khi rời picked_up mà chỉ khi !hadCheckout?
  // — Nếu đã trừ lúc checkout, tồn đã lock sớm; hoàn khi kéo về ready chỉ đúng khi tồn vừa trừ lúc “pickup” (counter), không đụng đơn đã trừ ở checkout.
  if ($wasPu && !$hadCheckout) {
    return qb_instant_stock_restore_for_order($conn, $order_id);
  }
  return true;
}
