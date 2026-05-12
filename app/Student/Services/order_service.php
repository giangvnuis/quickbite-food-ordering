<?php
/**
 * QuickBite Student — Danh sách đơn (active trong ngày) + helper query dùng chung cho history.
 *
 * Tại sao phân biệt orders vs order-history?
 * — `orders` (page=orders): chỉ đơn *hôm nay* — màn hình theo dõi đơn đang chờ / đang làm / lấy trong ngày.
 * — `order-history`: đơn theo *khoảng thời gian* + LIMIT — tra cứu lịch sử mà không làm danh sách “đang active” quá dài
 *   hay trộn đơn cũ với luồng xử lý hôm nay.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../Shared/Components/flow_modal.php';
require_once __DIR__ . '/../../Helpers/load.php';

/** @return array<string,string> */
function order_service_status_filter_labels(): array {
  return [
    'all' => 'All',
    'pending' => 'Pending',
    'preparing' => 'Preparing',
    'ready' => 'Ready',
    'picked_up' => 'Completed',
    'cancelled' => 'Cancelled',
    'no_show' => 'No show',
  ];
}

/**
 * Đọc order_items cho nhiều order_id (batch sau khi có danh sách đơn).
 *
 * @param list<int> $order_ids
 * @return array<int, list<array<string,mixed>>>
 */
function order_service_items_by_order_ids(mysqli $conn, array $order_ids): array {
  $items_by_order = [];
  if (empty($order_ids)) {
    return $items_by_order;
  }
  $in = implode(',', array_fill(0, count($order_ids), '?'));
  $types = str_repeat('i', count($order_ids));
  $sql = "SELECT order_id, product_name, quantity, unit_price_cents
          FROM order_items
          WHERE order_id IN ($in)
          ORDER BY order_id DESC, id ASC";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return $items_by_order;
  }
  $stmt->bind_param($types, ...$order_ids);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $oid = (int)$row['order_id'];
      if (!isset($items_by_order[$oid])) {
        $items_by_order[$oid] = [];
      }
      $items_by_order[$oid][] = $row;
    }
  }
  $stmt->close();
  return $items_by_order;
}

/**
 * Query danh sách đơn của SV: WHERE động (chỉ hôm nay vs lịch sử + range + status) + COUNT + SELECT có JOIN slot.
 *
 * @param array<string,mixed> $filters
 *   - context: 'active_today' | 'history'
 *   - st: 'all' | pending | preparing | …
 *   Khi context=history:
 *   - range: today | week | month | all | custom
 *   - hist_from, hist_to (Y-m-d) khi range=custom hoặc để snapshot form
 *   - limit: int|null (history dùng 50; active_today = null)
 * @return array{orders: list<array<string,mixed>>, order_ids: list<int>, total_orders: int}
 */
function get_orders_with_filters(mysqli $conn, int $user_id, array $filters): array {
  // Tại sao gom một hàm cho cả “đơn trong ngày” và “lịch sử”?
  // — Hai màn dùng chung điều kiện user + status + JOIN slot; chỉ khác phần lọc thời gian.
  //   Gom tránh lặp SQL và lệch logic giữa orders vs order-history.

  $allowed_st = ['all', 'pending', 'preparing', 'ready', 'picked_up', 'cancelled', 'no_show'];
  $st = isset($filters['st']) ? strtolower((string)$filters['st']) : 'all';
  if (!in_array($st, $allowed_st, true)) {
    $st = 'all';
  }
  // Tại sao whitelist status từ GET?
  // — Tham số URL có thể bị sửa tay; chỉ chấp nhận giá trị đã định nghĩa để tránh lọc lạ / lỗi bind.

  $context = (string)($filters['context'] ?? 'active_today');
  if (!in_array($context, ['active_today', 'history'], true)) {
    $context = 'active_today';
  }

  $where_parts = ['o.user_id = ?'];
  $bind_types = 'i';
  $bind_params = [$user_id];

  if ($context === 'active_today') {
    // Tại sao chỉ hôm nay (CURDATE)?
    // — Trang “Orders” là theo dõi đơn đang xử lý trong ngày, không kéo cả quá khứ làm loãng danh sách.
    $where_parts[] = 'DATE(o.created_at) = CURDATE()';
  } else {
    // Tại sao nhánh history phức hơn?
    // — Người dùng chọn preset (tuần/tháng) hoặc khoảng tùy chỉnh; cần chuẩn hóa ngày + đổi chỗ nếu from > to.
    $range = isset($filters['range']) ? (string)$filters['range'] : 'all';
    if (!in_array($range, ['today', 'week', 'month', 'all', 'custom'], true)) {
      $range = 'all';
    }
    $today = date('Y-m-d');
    $hist_to = isset($filters['hist_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['hist_to'])
      ? (string)$filters['hist_to']
      : $today;
    $hist_from = isset($filters['hist_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['hist_from'])
      ? (string)$filters['hist_from']
      : date('Y-m-d', strtotime('-30 days', strtotime($hist_to)));
    if ($hist_from > $hist_to) {
      $t = $hist_from;
      $hist_from = $hist_to;
      $hist_to = $t;
    }
    if ($range === 'today') {
      $where_parts[] = 'DATE(o.created_at) = CURDATE()';
    } elseif ($range === 'week') {
      $where_parts[] = 'o.created_at >= (CURDATE() - INTERVAL 7 DAY)';
    } elseif ($range === 'month') {
      $where_parts[] = 'o.created_at >= (CURDATE() - INTERVAL 30 DAY)';
    } elseif ($range === 'custom') {
      // Tại sao BETWEEN bind tham số thay vì nối chuỗi?
      // — Tránh nhập ngày “trộn” vào SQL thô; giữ kiểu prepared statement an toàn hơn.
      $where_parts[] = 'DATE(o.created_at) BETWEEN ? AND ?';
      $bind_types .= 'ss';
      $bind_params[] = $hist_from;
      $bind_params[] = $hist_to;
    }
  }

  if ($st !== 'all') {
    $where_parts[] = 'o.status = ?';
    $bind_types .= 's';
    $bind_params[] = $st;
  }

  $where_sql = implode(' AND ', $where_parts);

  $limit = null;
  if (array_key_exists('limit', $filters)) {
    $limit = $filters['limit'] === null ? null : max(1, (int)$filters['limit']);
  }
  // Tại sao LIMIT tách khỏi COUNT?
  // — total_orders phục vụ UI “có bao nhiêu đơn khớp lọc”; danh sách có thể giới hạn (history 50 dòng) nhưng vẫn biết tổng đầy đủ.

  $orders = [];
  $order_ids = [];
  $total_orders = 0;

  $count_sql = "SELECT COUNT(*) AS c FROM orders o WHERE $where_sql";
  $stmt = $conn->prepare($count_sql);
  if ($stmt) {
    $stmt->bind_param($bind_types, ...$bind_params);
    $stmt->execute();
    $cr = $stmt->get_result();
    $crow = $cr ? $cr->fetch_assoc() : null;
    $stmt->close();
    if ($crow) {
      $total_orders = (int)($crow['c'] ?? 0);
    }
  }

  $list_sql = "SELECT o.id, o.order_code, o.status, o.total_cents, o.created_at, o.time_slot_id,
          o.payment_method, o.payment_status,
          ts.start_time, ts.end_time
     FROM orders o
     LEFT JOIN time_slots ts ON ts.id = o.time_slot_id
     WHERE $where_sql
     ORDER BY o.created_at DESC";
  if ($limit !== null) {
    $list_sql .= ' LIMIT ' . $limit;
  }

  $stmt = $conn->prepare($list_sql);
  if ($stmt) {
    $stmt->bind_param($bind_types, ...$bind_params);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
      while ($row = $res->fetch_assoc()) {
        $orders[] = $row;
        $order_ids[] = (int)$row['id'];
      }
    }
    $stmt->close();
  }

  return [
    'orders' => $orders,
    'order_ids' => $order_ids,
    'total_orders' => $total_orders,
  ];
}

/**
 * @param array<string,mixed> $get
 * @return array<string,mixed>
 */
function student_orders_page_data(mysqli $conn, int $user_id, array $get): array {
  $qb_modal = flow_modal_request();

  $cart_count = qb_cart_badge_count($conn, $user_id);

  qb_orders_auto_pending_to_preparing($conn);

  $allowed_st = ['all', 'pending', 'preparing', 'ready', 'picked_up', 'cancelled', 'no_show'];
  $st = isset($get['st']) ? strtolower((string)$get['st']) : 'all';
  if (!in_array($st, $allowed_st, true)) {
    $st = 'all';
  }

  $orders_q = static function (array $patch) use ($st, $qb_modal): string {
    $q = array_merge(['page' => 'orders', 'st' => $st], $patch);
    if ($qb_modal) {
      $q['modal'] = '1';
    }
    return site_url('student.php?' . http_build_query($q));
  };

  $pay_success = false;
  $pay_success_oid = 0;
  $pay_success_code = '';
  if (isset($get['paid']) && (string)$get['paid'] === '1') {
    $try_pay_oid = isset($get['order_id']) ? (int)$get['order_id'] : 0;
    if ($try_pay_oid > 0) {
      $pm_online = 'online';
      $ps_paid = 'paid';
      $stmt = $conn->prepare('SELECT id, order_code FROM orders WHERE id = ? AND user_id = ? AND payment_method = ? AND payment_status = ? LIMIT 1');
      if ($stmt) {
        $stmt->bind_param('iiss', $try_pay_oid, $user_id, $pm_online, $ps_paid);
        $stmt->execute();
        $res = $stmt->get_result();
        $pay_row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($pay_row) {
          $pay_success = true;
          $pay_success_oid = $try_pay_oid;
          $pay_success_code = (string)$pay_row['order_code'];
        }
      }
    }
  }

  $orders_continue_href = student_url('orders', ['st' => $st], $qb_modal);

  $status_choices = order_service_status_filter_labels();

  $bundle = get_orders_with_filters($conn, $user_id, [
    'context' => 'active_today',
    'st' => $st,
    'limit' => null,
  ]);
  $orders = $bundle['orders'];
  $order_ids = $bundle['order_ids'];
  $total_orders = $bundle['total_orders'];
  $items_by_order = order_service_items_by_order_ids($conn, $order_ids);

  return compact(
    'qb_modal', 'cart_count', 'st', 'orders_q', 'pay_success', 'pay_success_oid', 'pay_success_code',
    'orders_continue_href', 'status_choices', 'orders', 'order_ids', 'total_orders', 'items_by_order'
  );
}
