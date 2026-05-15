<?php
/**
 * QuickBite Student — Thanh toán: demo online (mock QR), counter không qua trang pay; hoàn tiền (refund request).
 *
 * Hai luồng:
 * - Online: redirect tới trang pay → POST xác nhận → update_payment_status → paid.
 * - Counter: không có “chờ thanh toán online”; đơn tạo với payment_method=counter, SV thanh toán tại quầy (admin/counter flow).
 *
 * Tại sao cần xác nhận 2 bước trước khi đổi status?
 * — Bước 1: đọc đơn đảm bảo đúng user + đúng luồng (online/counter) + trạng thái cho phép.
 *   Bước 2: UPDATE … WHERE … AND payment_status = ? AND payment_method = ?
 *   để một lần ghi không làm “nhảy cóc” trạng thái (double-submit, tab hai, hoặc đơn đã paid/refund).
 */
declare(strict_types=1);

// Nạp các thành phần giao diện Modal (cửa sổ nổi)
require_once __DIR__ . '/../../Shared/Components/flow_modal.php';
// Nạp các hàm tiện ích hệ thống (DB, Redirect, Auth...)
require_once __DIR__ . '/../../Helpers/load.php';

/** * TẠO MÃ QR ĐỒ HỌA (MOCK QR)
 * Sử dụng định dạng SVG để hiển thị mã QR giả lập mà không cần file ảnh.
 */
function student_pay_mock_qr_svg(): string {
  $u = 4;      // Kích thước mỗi ô vuông nhỏ (pixel)
  $n = 29;     // Số lượng ô vuông trên mỗi cạnh (29x29)
  $w = $n * $u; // Tổng chiều rộng ảnh
  $rects = []; // Mảng chứa các khối hình chữ nhật của mã QR

  // Hàm ẩn danh để thêm nhanh một ô vuông vào mảng
  $add = static function (int $x, int $y, int $bw, int $bh, string $fill) use (&$rects, $u): void {
    $rects[] = sprintf('<rect x="%d" y="%d" width="%d" height="%d" fill="%s"/>', $x * $u, $y * $u, $bw * $u, $bh * $u, $fill);
  };

  // Vẽ 3 khối vuông định vị (Finder Patterns) ở 3 góc của mã QR
  foreach ([[0, 0], [22, 0], [0, 22]] as $o) {
    $ox = $o[0];
    $oy = $o[1];
    $add($ox, $oy, 7, 7, '#0f172a');      // Lớp ngoài đen
    $add($ox + 1, $oy + 1, 5, 5, '#ffffff'); // Lớp giữa trắng
    $add($ox + 2, $oy + 2, 3, 3, '#0f172a'); // Lớp trong đen
  }

  // Danh sách tọa độ các điểm đen giả lập dữ liệu bên trong mã QR
  $bits = [
    [8, 0, 1, 1], [10, 0, 1, 1], [12, 0, 1, 1], [14, 0, 2, 1], [17, 0, 1, 1], [19, 0, 1, 1],
    [8, 2, 1, 1], [11, 2, 2, 1], [15, 2, 1, 1], [18, 2, 2, 1],
    [9, 4, 3, 1], [14, 4, 1, 1], [16, 4, 4, 1],
    [8, 6, 1, 1], [10, 6, 5, 1], [17, 6, 1, 1], [20, 6, 2, 1],
    [8, 8, 2, 2], [11, 8, 1, 2], [13, 8, 3, 2], [17, 8, 2, 2], [20, 8, 2, 2],
    [8, 11, 1, 1], [10, 11, 2, 1], [14, 11, 2, 2], [18, 11, 1, 1], [21, 11, 1, 1],
    [8, 14, 4, 1], [13, 14, 1, 1], [16, 14, 2, 1], [20, 14, 2, 1],
    [8, 16, 1, 2], [10, 16, 3, 1], [15, 16, 2, 2], [19, 16, 3, 1],
    [8, 19, 2, 1], [12, 19, 1, 1], [14, 19, 4, 1], [20, 19, 2, 1],
    [8, 21, 1, 1], [11, 21, 2, 1], [15, 21, 1, 1], [18, 21, 3, 1],
  ];

  // Duyệt qua danh sách và vẽ từng điểm đen
  foreach ($bits as $b) {
    $add($b[0], $b[1], $b[2], $b[3], '#0f172a');
  }

  // Xuất ra chuỗi mã HTML SVG hoàn chỉnh
  return sprintf(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="200" height="200" shape-rendering="crispEdges" aria-hidden="true" class="qb-pay-qr-svg"><rect width="%d" height="%d" fill="#fff"/>%s</svg>',
    $w, $w, $w, $w, implode('', $rects)
  );
}

/**
 * Cập nhật payment_status — luồng SV demo: online + unpaid → paid.
 *
 * Tại sao WHERE gắn chặt payment_method + payment_status?
 * — Đó là “bước 2” sau khi đã kiểm tra đơn trên server: chỉ đổi khi trạng thái hiện tại vẫn là unpaid,
 *   tránh hai request đồng thời hoặc đơn đã được xử lý/refund vẫn bị ghi paid.
 *
 * @return bool true nếu có ít nhất một dòng được cập nhật
 */
function update_payment_status(mysqli $conn, int $user_id, int $order_id): bool {
  if ($order_id <= 0) {
    return false;
  }
  $stmt = $conn->prepare(
    "UPDATE orders SET payment_status = 'paid'
     WHERE id = ? AND user_id = ? AND payment_method = 'online' AND payment_status = 'unpaid'"
  );
  if (!$stmt) {
    return false;
  }
  $stmt->bind_param('ii', $order_id, $user_id);
  $stmt->execute();
  $ok = $stmt->affected_rows > 0;
  $stmt->close();
  return $ok;
}

/**
 * SV yêu cầu hoàn tiền: đơn pending + đã paid — ghi notes + payment_status = refund_requested.
 * Gọi trong transaction (caller đã begin_transaction).
 *
 * @throws Exception
 */
function request_refund(
  mysqli $conn,
  int $user_id,
  int $order_id,
  string $bank,
  string $account_number,
  string $account_name
): void {
  $refund_note = sprintf(
    '[REFUND_REQUEST]|bank=%s|account_number=%s|account_name=%s|requested_at=%s',
    rawurlencode($bank),
    rawurlencode($account_number),
    rawurlencode($account_name),
    date('Y-m-d H:i:s')
  );
  $stmt = $conn->prepare(
    "UPDATE orders
     SET payment_status = 'refund_requested',
         notes = CASE
           WHEN notes IS NULL OR notes = '' THEN ?
           ELSE CONCAT(notes, '\n', ?)
         END
     WHERE id = ? AND user_id = ? AND status = 'pending' AND payment_status = 'paid'
     LIMIT 1"
  );
  if (!$stmt) {
    throw new Exception('DB error while requesting refund.');
  }
  $stmt->bind_param('ssii', $refund_note, $refund_note, $order_id, $user_id);
  if (!$stmt->execute()) {
    $stmt->close();
    throw new Exception('Could not submit refund request.');
  }
  if ($stmt->affected_rows <= 0) {
    $stmt->close();
    throw new Exception('Refund request could not be submitted. The order may have been updated already.');
  }
  $stmt->close();
}

/**
 * Dữ liệu trang chờ thanh toán online (pay.php). Đơn counter → redirect (không có màn pay online).
 *
 * @param array<string,mixed> $user
 * @param array<string,mixed> $get
 * @return array<string,mixed>
 */
function get_pay_display_data(mysqli $conn, array $user, array $get, string $pay_error = ''): array {
  $user_id = (int)$user['id'];
  $order_id = isset($get['order_id']) ? (int)$get['order_id'] : 0;
  $qb_modal = flow_modal_request();

  qb_orders_auto_pending_to_preparing($conn);

  if ($order_id <= 0) {
    redirect_to(student_url('orders', [], $qb_modal));
  }

  $order = null;
  $stmt = $conn->prepare('SELECT id, order_code, total_cents, payment_method, payment_status FROM orders WHERE id = ? AND user_id = ? LIMIT 1');
  if ($stmt) {
    $stmt->bind_param('ii', $order_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $order = $res ? $res->fetch_assoc() : null;
    $stmt->close();
  }
  if (!$order) {
    redirect_to(student_url('orders', [], $qb_modal));
  }

  if (($order['payment_method'] ?? '') !== 'online') {
    redirect_to(student_url('orders', [], $qb_modal));
  }

  return [
    'order' => $order,
    'order_id' => $order_id,
    'qb_modal' => $qb_modal,
    'pay_qr_svg' => student_pay_mock_qr_svg(),
    'pay_error' => $pay_error,
  ];
}
