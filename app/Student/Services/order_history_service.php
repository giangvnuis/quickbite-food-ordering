<?php
/**
 * QuickBite Student — Lịch sử đơn (range + status); query lõi dùng get_orders_with_filters() trong order_service.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../Shared/Components/flow_modal.php';
require_once __DIR__ . '/../../Helpers/load.php';
require_once __DIR__ . '/order_service.php';

/**
 * @param array<string,mixed> $get
 * @return array<string,mixed>
 */
function student_order_history_page_data(mysqli $conn, int $user_id, array $get): array {
  $qb_modal = flow_modal_request();

  $cart_count = qb_cart_badge_count($conn, $user_id);

  qb_orders_auto_pending_to_preparing($conn);

  $range = isset($get['range']) ? (string)$get['range'] : 'all';
  if (!in_array($range, ['today', 'week', 'month', 'all', 'custom'], true)) {
    $range = 'all';
  }

  $today = date('Y-m-d');
  $hist_to = isset($get['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$get['to'])
    ? (string)$get['to']
    : $today;
  $hist_from = isset($get['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$get['from'])
    ? (string)$get['from']
    : date('Y-m-d', strtotime('-30 days', strtotime($hist_to)));
  if ($hist_from > $hist_to) {
    $tmp = $hist_from;
    $hist_from = $hist_to;
    $hist_to = $tmp;
  }

  $allowed_st = ['all', 'pending', 'preparing', 'ready', 'picked_up', 'cancelled', 'no_show'];
  $st = isset($get['st']) ? strtolower((string)$get['st']) : 'all';
  if (!in_array($st, $allowed_st, true)) {
    $st = 'all';
  }

  $cancel_flash = isset($get['cancelled']) && (string)$get['cancelled'] === '1';

  $bundle = get_orders_with_filters($conn, $user_id, [
    'context' => 'history',
    'range' => $range,
    'st' => $st,
    'hist_from' => $hist_from,
    'hist_to' => $hist_to,
    'limit' => 50,
  ]);
  $orders = $bundle['orders'];
  $order_ids = $bundle['order_ids'];
  $total_orders = $bundle['total_orders'];
  $items_by_order = order_service_items_by_order_ids($conn, $order_ids);

  $hist_q = static function (array $patch) use ($range, $hist_from, $hist_to, $st, $qb_modal): string {
    $q = array_merge(['page' => 'order-history', 'range' => $range, 'st' => $st], $patch);
    if (($q['range'] ?? '') === 'custom') {
      $q['from'] = $hist_from;
      $q['to'] = $hist_to;
    } else {
      unset($q['from'], $q['to']);
    }
    if ($qb_modal) {
      $q['modal'] = '1';
    }
    return site_url('student.php?' . http_build_query($q));
  };

  $status_choices = order_service_status_filter_labels();

  return compact(
    'qb_modal', 'cart_count', 'range', 'today', 'hist_to', 'hist_from', 'st', 'cancel_flash',
    'orders', 'order_ids', 'total_orders', 'items_by_order', 'hist_q', 'status_choices'
  );
}
