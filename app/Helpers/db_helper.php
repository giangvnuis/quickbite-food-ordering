<?php
/**
 * DRY — hàm này dùng ở nhiều trang, đặt ở Helpers tránh lặp (TV2: table_has_column, qb_user_email_taken…).
 */
declare(strict_types=1);

function table_has_column(mysqli $conn, string $table, string $column): bool {
  $stmt = $conn->prepare(
    'SELECT 1 FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = ?
       AND COLUMN_NAME = ?
     LIMIT 1'
  );
  if (!$stmt) {
    return false;
  }
  $stmt->bind_param('ss', $table, $column);
  $stmt->execute();
  $res = $stmt->get_result();
  $ok = $res ? (bool)$res->fetch_row() : false;
  $stmt->close();
  return $ok;
}

/**
 * True if a user row already has this email (optionally ignore one user id, e.g. current profile).
 */
function qb_user_email_taken(mysqli $conn, string $email, ?int $except_user_id = null): bool {
  if ($email === '') {
    return false;
  }
  if ($except_user_id !== null) {
    $stmt = $conn->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ? LIMIT 1');
    if (!$stmt) {
      return false;
    }
    $stmt->bind_param('si', $email, $except_user_id);
  } else {
    $stmt = $conn->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
    if (!$stmt) {
      return false;
    }
    $stmt->bind_param('s', $email);
  }
  $stmt->execute();
  $res = $stmt->get_result();
  $taken = $res ? (bool)$res->fetch_row() : false;
  $stmt->close();
  return $taken;
}

/**
 * True if another user already has this phone (compare normalized digits only).
 */
function qb_user_phone_taken(mysqli $conn, string $normalized_digits, ?int $except_user_id = null): bool {
  if ($normalized_digits === '') {
    return false;
  }
  if ($except_user_id !== null) {
    $stmt = $conn->prepare('SELECT 1 FROM users WHERE phone = ? AND id <> ? LIMIT 1');
    if (!$stmt) {
      return false;
    }
    $stmt->bind_param('si', $normalized_digits, $except_user_id);
  } else {
    $stmt = $conn->prepare('SELECT 1 FROM users WHERE phone = ? LIMIT 1');
    if (!$stmt) {
      return false;
    }
    $stmt->bind_param('s', $normalized_digits);
  }
  $stmt->execute();
  $res = $stmt->get_result();
  $taken = $res ? (bool)$res->fetch_row() : false;
  $stmt->close();
  return $taken;
}

/**
 * Số đơn có ít nhất một món prepared đang chiếm slot (pending/preparing/ready).
 * Dùng trong transaction sau khi khóa hàng time_slots (FOR UPDATE).
 */
function qb_count_prepared_orders_in_slot(mysqli $conn, int $slotId): int {
  $sql = "SELECT COUNT(DISTINCT o.id) AS c
          FROM orders o
          INNER JOIN order_items oi ON oi.order_id = o.id
            AND (oi.product_type = 'prepared' OR oi.product_type IS NULL)
          WHERE o.time_slot_id = ? AND o.status IN ('pending','preparing','ready')";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return 0;
  }
  $stmt->bind_param('i', $slotId);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  return (int)($row['c'] ?? 0);
}

/**
 * Số đơn prepared của 1 user trong 1 slot, theo ngày.
 * Dùng để enforce: 1 user chỉ có 1 đơn prepared cho mỗi pickup slot.
 */
function qb_count_user_prepared_orders_in_slot_on_day(mysqli $conn, int $userId, int $slotId, string $dateYmd): int {
  $sql = "SELECT COUNT(DISTINCT o.id) AS c
          FROM orders o
          INNER JOIN order_items oi ON oi.order_id = o.id
            AND (oi.product_type = 'prepared' OR oi.product_type IS NULL)
          WHERE o.user_id = ?
            AND o.time_slot_id = ?
            AND DATE(o.created_at) = ?
            AND o.status IN ('pending','preparing','ready','picked_up','no_show')";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return 0;
  }
  $stmt->bind_param('iis', $userId, $slotId, $dateYmd);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  return (int)($row['c'] ?? 0);
}

/**
 * Số pickup slot distinct có đơn prepared của 1 user trong ngày.
 * Dùng để enforce: tối đa 2 slot prepared mỗi ngày.
 */
function qb_count_user_prepared_slots_on_day(mysqli $conn, int $userId, string $dateYmd): int {
  $sql = "SELECT COUNT(DISTINCT o.time_slot_id) AS c
          FROM orders o
          INNER JOIN order_items oi ON oi.order_id = o.id
            AND (oi.product_type = 'prepared' OR oi.product_type IS NULL)
          WHERE o.user_id = ?
            AND o.time_slot_id IS NOT NULL
            AND DATE(o.created_at) = ?
            AND o.status IN ('pending','preparing','ready','picked_up','no_show')";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return 0;
  }
  $stmt->bind_param('is', $userId, $dateYmd);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  return (int)($row['c'] ?? 0);
}

/** Tổng số lượng món trong giỏ đang active (badge header). */
function qb_cart_badge_count(mysqli $conn, int $user_id): int {
  $count = 0;
  $stmt = $conn->prepare("SELECT id FROM cart WHERE user_id = ? AND status = 'active' LIMIT 1");
  if (!$stmt) {
    return 0;
  }
  $stmt->bind_param('i', $user_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $cart = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  if (!$cart) {
    return 0;
  }
  $cart_id = (int)$cart['id'];
  $stmt = $conn->prepare('SELECT COALESCE(SUM(quantity), 0) AS qty FROM cart_items WHERE cart_id = ?');
  if (!$stmt) {
    return 0;
  }
  $stmt->bind_param('i', $cart_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  return $row ? (int)$row['qty'] : 0;
}

/**
 * Đơn pending từ ≥ 5 phút → preparing (bếp). Không đổi khi payment_status = refund_requested;
 * đơn online vẫn unpaid thì giữ pending (chờ thanh toán rồi mới vào bếp).
 *
 * Gọi từ các trang đọc đơn (student + admin), không chỉ admin_orders — tránh đơn mãi pending nếu không mở Order Management.
 */
function qb_orders_auto_pending_to_preparing(mysqli $conn): void {
  $conn->query(
    "UPDATE orders SET status = 'preparing'
     WHERE status = 'pending'
       AND payment_status <> 'refund_requested'
       AND created_at <= (NOW() - INTERVAL 5 MINUTE)
       AND (payment_method <> 'online' OR payment_status = 'paid')"
  );
}
