<?php
/**
 * QuickBite Admin — Dashboard: KPI theo khoảng ngày, top món, đơn gần nhất, tóm tắt slot hôm nay.
 *
 * Tại sao tính doanh thu = cents?
 * — Tiền tệ trong DB là integer (xu / cents), cộng trừ bằng int tránh sai số float (0.1 + 0.2).
 *   Chỉ format sang VND hiển thị khi render (format_vnd / number_format).
 */
declare(strict_types=1);

/** So sánh % so với kỳ trước (tránh chia cho 0). */
function admin_dashboard_percent_change(int $current, int $previous): int {
  if ($previous <= 0) {
    return $current > 0 ? 100 : 0;
  }
  return (int) round((($current - $previous) / $previous) * 100);
}

/**
 * KPI kỳ hiện tại + kỳ so sánh (đơn, doanh thu picked_up = cents, prepared, SV có giỏ active).
 *
 * @return array{
 *   stats: array{
 *     total_orders:int,
 *     prepared_orders:int,
 *     completed_orders:int,
 *     revenue_cents:int,
 *     students_ordering:int
 *   },
 *   yesterday_stats: array{total_orders:int,revenue_cents:int}
 * }
 */
function get_dashboard_stats(mysqli $conn, string $range_start, string $range_end, string $prev_start, string $prev_end): array {
  $stats = [
    'total_orders' => 0,
    'prepared_orders' => 0,
    'completed_orders' => 0,
    'revenue_cents' => 0,
    'students_ordering' => 0,
  ];
  $yesterday_stats = [
    'total_orders' => 0,
    'revenue_cents' => 0,
  ];

  $stmt = $conn->prepare(
    "SELECT
      SUM(CASE WHEN DATE(created_at) BETWEEN ? AND ? THEN 1 ELSE 0 END) AS total_range,
      SUM(CASE WHEN DATE(created_at) BETWEEN ? AND ? THEN 1 ELSE 0 END) AS total_prev,
      SUM(CASE WHEN DATE(created_at) BETWEEN ? AND ? AND status IN ('preparing','ready') THEN 1 ELSE 0 END) AS prepared_range,
      SUM(CASE WHEN DATE(created_at) BETWEEN ? AND ? AND status = 'picked_up' THEN 1 ELSE 0 END) AS completed_range,
      SUM(CASE WHEN DATE(created_at) BETWEEN ? AND ? AND status = 'picked_up' THEN total_cents ELSE 0 END) AS revenue_range,
      SUM(CASE WHEN DATE(created_at) BETWEEN ? AND ? AND status = 'picked_up' THEN total_cents ELSE 0 END) AS revenue_prev
   FROM orders"
  );
  if ($stmt) {
    $stmt->bind_param(
      'ssssssssssss',
      $range_start,
      $range_end,
      $prev_start,
      $prev_end,
      $range_start,
      $range_end,
      $range_start,
      $range_end,
      $range_start,
      $range_end,
      $prev_start,
      $prev_end
    );
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row) {
      $stats['total_orders'] = (int) ($row['total_range'] ?? 0);
      $stats['prepared_orders'] = (int) ($row['prepared_range'] ?? 0);
      $stats['completed_orders'] = (int) ($row['completed_range'] ?? 0);
      $stats['revenue_cents'] = (int) ($row['revenue_range'] ?? 0);
      $yesterday_stats['total_orders'] = (int) ($row['total_prev'] ?? 0);
      $yesterday_stats['revenue_cents'] = (int) ($row['revenue_prev'] ?? 0);
    }
  }

  $stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) AS c FROM cart WHERE status = 'active'");
  if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    $r = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($r) {
      $stats['students_ordering'] = (int) ($r['c'] ?? 0);
    }
  }

  return [
    'stats' => $stats,
    'yesterday_stats' => $yesterday_stats,
  ];
}

/**
 * Đơn hôm nay theo khung giờ pickup (LEFT JOIN để thấy slot chưa có đơn).
 *
 * @return list<array{slot_id:int,label:string,order_count:int,capacity:int}>
 */
function get_today_orders_summary(mysqli $conn): array {
  $rows = [];
  $sql = 'SELECT ts.id AS slot_id, ts.start_time, ts.end_time, COALESCE(ts.prepared_capacity, 0) AS capacity,
                 COUNT(o.id) AS order_count
          FROM time_slots ts
          LEFT JOIN orders o ON o.time_slot_id = ts.id AND DATE(o.created_at) = CURDATE()
          WHERE ts.is_active = 1
          GROUP BY ts.id, ts.start_time, ts.end_time, ts.prepared_capacity
          ORDER BY ts.start_time ASC';
  $res = $conn->query($sql);
  if ($res) {
    while ($r = $res->fetch_assoc()) {
      $st = (string) ($r['start_time'] ?? '');
      $en = (string) ($r['end_time'] ?? '');
      $label = ($st !== '' && $en !== '')
        ? substr($st, 0, 5) . ' – ' . substr($en, 0, 5)
        : 'Slot';
      $rows[] = [
        'slot_id' => (int) ($r['slot_id'] ?? 0),
        'label' => $label,
        'order_count' => (int) ($r['order_count'] ?? 0),
        'capacity' => (int) ($r['capacity'] ?? 0),
      ];
    }
  }
  return $rows;
}

/**
 * @param array<string, mixed> $get Thường là $_GET (period, start_date, end_date).
 * @return array<string, mixed>
 */
function admin_dashboard_data(mysqli $conn, array $get): array {
  $period = trim((string) ($get['period'] ?? 'today'));
  if (!in_array($period, ['today', 'week', 'month', 'custom'], true)) {
    $period = 'today';
  }

  $today = date('Y-m-d');
  $range_start = $today;
  $range_end = $today;
  $custom_start = trim((string) ($get['start_date'] ?? ''));
  $custom_end = trim((string) ($get['end_date'] ?? ''));

  if ($period === 'week') {
    $range_start = date('Y-m-d', strtotime('monday this week'));
    $range_end = $today;
  } elseif ($period === 'month') {
    $range_start = date('Y-m-01');
    $range_end = $today;
  } elseif ($period === 'custom') {
    $ok_start = preg_match('/^\d{4}-\d{2}-\d{2}$/', $custom_start);
    $ok_end = preg_match('/^\d{4}-\d{2}-\d{2}$/', $custom_end);
    if ($ok_start && $ok_end && $custom_start <= $custom_end) {
      $range_start = $custom_start;
      $range_end = $custom_end;
    } elseif ($ok_start && $ok_end && $custom_start > $custom_end) {
      $range_start = $custom_end;
      $range_end = $custom_start;
    } else {
      $range_start = $today;
      $range_end = $today;
    }
  } else {
    $period = 'today';
  }

  $range_start_dt = new DateTimeImmutable($range_start);
  $range_end_dt = new DateTimeImmutable($range_end);
  $range_days = (int) $range_start_dt->diff($range_end_dt)->days + 1;
  if ($range_days < 1) {
    $range_days = 1;
  }
  $prev_end_dt = $range_start_dt->modify('-1 day');
  $prev_start_dt = $prev_end_dt->modify('-' . ($range_days - 1) . ' days');
  $prev_start = $prev_start_dt->format('Y-m-d');
  $prev_end = $prev_end_dt->format('Y-m-d');

  $range_label = match ($period) {
    'week' => 'This Week',
    'month' => 'This Month',
    'custom' => 'Custom Range',
    default => 'Today',
  };

  $topbar_title = $range_label;
  $topbar_subtitle = $period === 'today'
    ? date('l, F j, Y')
    : (date('M j, Y', strtotime($range_start)) . ' - ' . date('M j, Y', strtotime($range_end)));

  $compare_label = $period === 'today' ? 'vs yesterday' : 'vs previous period';

  $stat_bundle = get_dashboard_stats($conn, $range_start, $range_end, $prev_start, $prev_end);
  $stats = $stat_bundle['stats'];
  $yesterday_stats = $stat_bundle['yesterday_stats'];

  $today_slot_summary = get_today_orders_summary($conn);
  $today_slot_chart_max = 0;
  foreach ($today_slot_summary as $s) {
    $today_slot_chart_max = max($today_slot_chart_max, (int) $s['order_count']);
  }

  $top_items = [];
  $stmt = $conn->prepare(
    "SELECT
      oi.product_name,
      SUM(oi.quantity) AS qty
   FROM order_items oi
   JOIN orders o ON o.id = oi.order_id
   WHERE DATE(o.created_at) BETWEEN ? AND ?
   GROUP BY oi.product_name
   ORDER BY qty DESC, oi.product_name ASC
   LIMIT 5"
  );
  if ($stmt) {
    $stmt->bind_param('ss', $range_start, $range_end);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
      while ($r = $res->fetch_assoc()) {
        $top_items[] = $r;
      }
    }
    $stmt->close();
  }

  $recent_orders = [];
  $stmt = $conn->prepare(
    "SELECT
      o.id, o.order_code, o.status, o.total_cents, o.created_at,
      u.full_name, ts.start_time, ts.end_time
   FROM orders o
   JOIN users u ON u.id = o.user_id
   LEFT JOIN time_slots ts ON ts.id = o.time_slot_id
   WHERE DATE(o.created_at) BETWEEN ? AND ?
   ORDER BY o.created_at DESC
   LIMIT 5"
  );
  if ($stmt) {
    $stmt->bind_param('ss', $range_start, $range_end);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
      while ($r = $res->fetch_assoc()) {
        $recent_orders[] = $r;
      }
    }
    $stmt->close();
  }

  $total_o = (int) $stats['total_orders'];
  $prepared_pct = $total_o > 0 ? (int) round(((int) $stats['prepared_orders'] / $total_o) * 100) : 0;
  $completed_pct = $total_o > 0 ? (int) round(((int) $stats['completed_orders'] / $total_o) * 100) : 0;

  return [
    'period' => $period,
    'range_start' => $range_start,
    'range_end' => $range_end,
    'range_label' => $range_label,
    'compare_label' => $compare_label,
    'topbar_title' => $topbar_title,
    'topbar_subtitle' => $topbar_subtitle,
    'stats' => $stats,
    'yesterday_stats' => $yesterday_stats,
    'prepared_pct' => $prepared_pct,
    'completed_pct' => $completed_pct,
    'today_slot_summary' => $today_slot_summary,
    'today_slot_chart_max' => $today_slot_chart_max,
    'top_items' => $top_items,
    'recent_orders' => $recent_orders,
  ];
}
