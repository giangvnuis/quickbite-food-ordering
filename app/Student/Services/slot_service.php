<?php
/**
 * QuickBite — Chọn khung giờ pickup (student): đọc slot, validate, ghi session, redirect an toàn.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../Helpers/load.php';
require_once __DIR__ . '/../../Helpers/order_slot_rules.php';
require_once __DIR__ . '/../../Shared/Components/flow_modal.php';

function student_slot_row_status(int $used, int $cap): array {
  if ($cap <= 0) {
    return ['Available', 'ok'];
  }
  if ($used >= $cap) {
    return ['Full', 'bad'];
  }
  if ($used >= (int)ceil($cap * 0.75)) {
    return ['Nearly Full', 'warn'];
  }
  return ['Available', 'ok'];
}

/**
 * Một dòng slot cho View (không tính label/bar trong template).
 *
 * @param array<string,mixed> $s Row từ DB (time_slots + usage)
 * @return array<string,mixed>
 */
function slot_build_display_row(array $s, int $selected_slot_id): array {
  $id = (int)($s['id'] ?? 0);
  $label = substr((string)$s['start_time'], 0, 5) . ' – ' . substr((string)$s['end_time'], 0, 5);
  $cap = (int)($s['prepared_capacity'] ?? 0);
  $used = (int)($s['prepared_used'] ?? 0);
  [$status, $cls] = student_slot_row_status($used, $cap);
  $pct = ($cap > 0) ? max(0, min(100, (int)round(($used / $cap) * 100))) : 0;
  $active = ($id === $selected_slot_id);
  $slot_orders_open = qb_slot_orders_still_open_for_start_today((string)($s['start_time'] ?? ''));
  if (!$slot_orders_open) {
    $status = 'Closed';
    $cls = 'bad';
  }
  $slot_btn_disabled = !$slot_orders_open;
  return compact('id', 'label', 'cap', 'used', 'status', 'cls', 'pct', 'active', 'slot_btn_disabled');
}

/**
 * @param array<string,mixed> $get
 * @return array<string,mixed>
 */
function get_select_slot_display_data(mysqli $conn, array $get): array {
  $qb_modal = flow_modal_request();

  $selected_slot_id = isset($_SESSION['time_slot_id']) ? (int)$_SESSION['time_slot_id'] : 0;

  $return_key = isset($get['return']) ? trim((string)$get['return']) : 'home';
  $return_map = [
    'home' => 'home',
    'cart' => 'cart',
    'checkout' => 'checkout',
  ];
  $return_to = $return_map[$return_key] ?? 'home';

  $slots = [];
  $stmt = $conn->prepare(qb_sql_time_slots_with_prepared_slot_usage());
  if (!$stmt) {
    $stmt = $conn->prepare('SELECT id, start_time, end_time, 0 AS prepared_capacity, 0 AS prepared_used FROM time_slots WHERE is_active = 1 ORDER BY start_time ASC');
  }
  if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
      while ($row = $res->fetch_assoc()) {
        $slots[] = $row;
      }
    }
    $stmt->close();
  }

  if ($selected_slot_id > 0) {
    foreach ($slots as $s) {
      if ((int)($s['id'] ?? 0) !== $selected_slot_id) {
        continue;
      }
      if (!qb_slot_orders_still_open_for_start_today((string)($s['start_time'] ?? ''))) {
        unset($_SESSION['time_slot_id']);
        $selected_slot_id = 0;
      }
      break;
    }
  }

  $slot_rows = [];
  foreach ($slots as $s) {
    $slot_rows[] = slot_build_display_row($s, $selected_slot_id);
  }

  return compact('qb_modal', 'selected_slot_id', 'return_to', 'slots', 'slot_rows');
}

/**
 * Lưu slot đã chọn vào session và trả về URL redirect (Student route, giữ modal nếu cần).
 *
 * @param string $returnKey Một trong: home | cart | checkout (hoặc legacy *.php)
 */
function apply_slot_selection(mysqli $conn, int $slot_id, string $returnKey, bool $modal): string {
  $legacy = [
    'home.php' => 'home',
    'cart.php' => 'cart',
    'checkout.php' => 'checkout',
  ];
  if (isset($legacy[$returnKey])) {
    $returnKey = $legacy[$returnKey];
  }

  $allowed = ['home', 'cart', 'checkout'];
  if (!in_array($returnKey, $allowed, true)) {
    $returnKey = 'home';
  }

  $ok = false;
  if ($slot_id > 0) {
    $stmt = $conn->prepare('SELECT id, start_time FROM time_slots WHERE id = ? AND is_active = 1 LIMIT 1');
    if ($stmt) {
      $stmt->bind_param('i', $slot_id);
      $stmt->execute();
      $res = $stmt->get_result();
      $row = $res ? $res->fetch_assoc() : null;
      $stmt->close();
      if ($row && qb_slot_orders_still_open_for_start_today((string)($row['start_time'] ?? ''))) {
        $ok = true;
      }
    }
  }

  if ($ok) {
    $_SESSION['time_slot_id'] = $slot_id;
  }

  return student_url($returnKey, [], $modal);
}
