<?php
/**
 * Luật đơn prepared, snippet SQL slot pickup, cutoff đặt slot — nghiệp vụ đặt hàng (cùng layer Helpers).
 */
declare(strict_types=1);

/**
 * Giới hạn số dòng món prepared trong một đơn (mỗi product_id = một dòng sau GROUP BY).
 *
 * Tại sao có rule này? Bếp/quầy chỉ xử lý được một số món prepared *khác nhau* trên cùng một đơn trong
 * một khung giờ: quá nhiều dòng prepared làm vượt kế hoạch chế biến, chờ lâu và tắc luồng pickup.
 * Con số 4 là ngưỡng vận hành (capacity UX), không phải constraint DB — có thể chỉnh qua hằng số này.
 */
const QB_ORDER_PREPARED_MAX_LINES = 4;

/** Số lượng tối đa cho một món prepared trong đơn. */
const QB_ORDER_PREPARED_MAX_QTY_PER_LINE = 3;
/** Mỗi sinh viên tối đa số pickup slot có prepared trong 1 ngày. */
const QB_ORDER_PREPARED_MAX_SLOTS_PER_DAY = 2;

function qb_normalize_product_line_type(string $raw): string {
  return strtolower(trim($raw)) === 'instant' ? 'instant' : 'prepared';
}

/**
 * @param list<array{type?:string,product_type?:string,quantity:int}> $lines
 */
function qb_prepared_order_rules_error(array $lines): ?string {
  $prepared_qtys = [];
  foreach ($lines as $row) {
    $raw = (string)($row['type'] ?? $row['product_type'] ?? 'prepared');
    if (qb_normalize_product_line_type($raw) === 'instant') {
      continue;
    }
    $qty = (int)($row['quantity'] ?? 0);
    if ($qty < 1) {
      continue;
    }
    $prepared_qtys[] = $qty;
  }
  if (count($prepared_qtys) > QB_ORDER_PREPARED_MAX_LINES) {
    return 'Maximum ' . QB_ORDER_PREPARED_MAX_LINES . ' prepared dishes per order (each dish is one line).';
  }
  foreach ($prepared_qtys as $qty) {
    if ($qty > QB_ORDER_PREPARED_MAX_QTY_PER_LINE) {
      return 'Maximum ' . QB_ORDER_PREPARED_MAX_QTY_PER_LINE . ' per prepared dish.';
    }
  }
  return null;
}

/**
 * @param list<array{type?:string,product_type?:string,quantity:int}> $lines
 */
function qb_prepared_quantity_sum(array $lines): int {
  $sum = 0;
  foreach ($lines as $row) {
    $raw = (string)($row['type'] ?? $row['product_type'] ?? 'prepared');
    if (qb_normalize_product_line_type($raw) === 'instant') {
      continue;
    }
    $sum += (int)($row['quantity'] ?? 0);
  }
  return $sum;
}

function qb_sql_prepared_orders_in_slot_count(): string {
  return "(SELECT COUNT(DISTINCT o.id) FROM orders o INNER JOIN order_items oi ON oi.order_id = o.id AND (oi.product_type = 'prepared' OR oi.product_type IS NULL) WHERE o.time_slot_id = ts.id AND o.status IN ('pending','preparing','ready'))";
}

function qb_sql_time_slots_with_prepared_slot_usage(): string {
  $c = qb_sql_prepared_orders_in_slot_count();
  return "SELECT ts.id, ts.start_time, ts.end_time, COALESCE(ts.prepared_capacity, 0) AS prepared_capacity, COALESCE($c, 0) AS prepared_used FROM time_slots ts WHERE ts.is_active = 1 ORDER BY ts.start_time ASC";
}

function qb_sql_single_time_slot_prepared_usage(): string {
  $c = qb_sql_prepared_orders_in_slot_count();
  return "SELECT ts.id, ts.start_time, ts.end_time, COALESCE(ts.prepared_capacity, 0) AS prepared_capacity, COALESCE($c, 0) AS prepared_used FROM time_slots ts WHERE ts.id = ? AND ts.is_active = 1 LIMIT 1";
}

const QB_SLOT_ORDER_CUTOFF_MINUTES_BEFORE_START = 10;

function qb_slot_order_deadline_ts(string $dateYmd, string $start_time): ?int {
  $start_time = trim($start_time);
  if ($start_time === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
    return null;
  }
  $slotStart = strtotime($dateYmd . ' ' . $start_time);
  if ($slotStart === false) {
    return null;
  }
  return $slotStart - (QB_SLOT_ORDER_CUTOFF_MINUTES_BEFORE_START * 60);
}

function qb_slot_orders_still_open_for_start_today(string $start_time): bool {
  $deadline = qb_slot_order_deadline_ts(date('Y-m-d'), $start_time);
  if ($deadline === null) {
    return false;
  }
  return time() < $deadline;
}
