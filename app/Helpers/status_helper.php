<?php
/**
 * DRY — badge trạng thái đơn / thanh toán + nhãn thời gian đơn (TV2: get_order_status_badge, payment badge).
 */
declare(strict_types=1);

/**
 * @return array{0:string,1:string} [label, css_class]
 */
function qb_status_badge(string $status): array {
  $status = strtolower($status);
  return match ($status) {
    'ready' => ['Ready', 'ready'],
    'preparing' => ['Preparing', 'preparing'],
    'pending' => ['Pending', 'pending'],
    'picked_up' => ['Completed', 'completed'],
    'cancelled' => ['Cancelled', 'cancelled'],
    'no_show' => ['No show', 'no-show'],
    default => [ucfirst($status), 'pending'],
  };
}

/**
 * @return array{0:string,1:string} [label, css_class]
 */
function qb_pay_badge(string $status): array {
  $status = strtolower($status);
  return match ($status) {
    'paid' => ['Paid', 'paid'],
    'refund_requested' => ['Refund Requested', 'refund-requested'],
    'refunded' => ['Refunded', 'refunded'],
    'failed' => ['Failed', 'failed'],
    default => ['Unpaid', 'unpaid'],
  };
}

/** TV2 — cùng `qb_status_badge`. */
function get_order_status_badge(string $status): array {
  return qb_status_badge($status);
}

/** TV2 — cùng `qb_pay_badge`. */
function get_payment_badge(string $status): array {
  return qb_pay_badge($status);
}

function qb_time_ago(int $ts): string {
  $diff = time() - $ts;
  if ($diff < 60) {
    return 'Ordered just now';
  }
  $mins = (int)floor($diff / 60);
  if ($mins < 60) {
    return 'Ordered ' . $mins . ' minutes ago';
  }
  $hrs = (int)floor($mins / 60);
  if ($hrs < 24) {
    return 'Ordered ' . $hrs . ' hours ago';
  }
  $days = (int)floor($hrs / 24);
  return 'Ordered ' . $days . ' days ago';
}

function qb_datetime(string $dt): string {
  $t = strtotime($dt);
  if (!$t) {
    return $dt;
  }
  return date('d/m/Y • H:i', $t);
}
