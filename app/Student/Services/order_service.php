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

// Nạp các thành phần xử lý Modal và các hàm bổ trợ hệ thống
require_once __DIR__ . '/../../Shared/Components/flow_modal.php';
require_once __DIR__ . '/../../Helpers/load.php';

/** * ĐỊNH NGHĨA NHÃN TRẠNG THÁI (MỤC PHỤ TRỢ)
 * Trả về mảng nhãn hiển thị giúp giao diện đồng nhất với Database.
 */
function order_service_status_filter_labels(): array {
  return [
    'all'       => 'All',        
    'pending'   => 'Pending',    
    'preparing' => 'Preparing',  
    'ready'     => 'Ready',      
    'picked_up' => 'Completed',  
    'cancelled' => 'Cancelled',  
    'no_show'   => 'No show',    
  ];
}

/**
 * [MỤC 1: TỐI ƯU HIỆU SUẤT - BATCH LOADING]
 * Tác dụng: Lấy toàn bộ món ăn của nhiều đơn hàng chỉ bằng 1 câu lệnh SQL duy nhất.
 * Giải trình: Tránh lỗi N+1 query (lỗi làm web chậm khi có nhiều đơn hàng).
 */
function order_service_items_by_order_ids(mysqli $conn, array $order_ids): array {
  $items_by_order = [];
  // Kiểm tra đầu vào: Nếu không có đơn hàng, thoát ngay để tiết kiệm tài nguyên
  if (empty($order_ids)) {
    return $items_by_order;
  }

  // KỸ THUẬT DYNAMIC IN-CLAUSE:
  // Tạo chuỗi danh sách dấu hỏi (?,?,?) tương ứng với số lượng đơn hàng để bind dữ liệu an toàn.
  $in = implode(',', array_fill(0, count($order_ids), '?'));
  $types = str_repeat('i', count($order_ids)); // 'i' đại diện cho Integer

  // Câu lệnh SQL truy vấn tập trung vào bảng order_items
  $sql = "SELECT order_id, product_name, quantity, unit_price_cents
          FROM order_items
          WHERE order_id IN ($in)
          ORDER BY order_id DESC, id ASC";

  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return $items_by_order;
  }

  // Sử dụng toán tử Spread (...) để truyền mảng IDs vào Prepared Statement
  $stmt->bind_param($types, ...$order_ids);
  $stmt->execute();
  $res = $stmt->get_result();

  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $oid = (int)$row['order_id'];
      // CẤU TRÚC DỮ LIỆU ĐA CHIỀU: Nhóm món ăn theo key là Order ID để View dễ truy xuất
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
 * [MỤC 2: LOGIC LỌC ĐA NĂNG & BẢO MẬT]
 * Tác dụng: Xây dựng câu lệnh SQL động dựa trên yêu cầu của người dùng.
 * Giải trình: Tái sử dụng code cho cả màn hình Đơn hôm nay và Lịch sử đơn hàng.
 */
function get_orders_with_filters(mysqli $conn, int $user_id, array $filters): array {
  
  // BẢO MẬT WHITE-LISTING: Chỉ chấp nhận các trạng thái hợp lệ, chặn đứng mọi cố ý sửa đổi URL bậy bạ.
  $allowed_st = ['all', 'pending', 'preparing', 'ready', 'picked_up', 'cancelled', 'no_show'];
  $st = isset($filters['st']) ? strtolower((string)$filters['st']) : 'all';
  if (!in_array($st, $allowed_st, true)) {
    $st = 'all'; 
  }

  // PHÂN TÁCH NGỮ CẢNH (CONTEXT):
  // Giúp hệ thống biết đang cần lấy đơn "Active" (đang thực hiện) hay "History" (quá khứ).
  $context = (string)($filters['context'] ?? 'active_today');
  if (!in_array($context, ['active_today', 'history'], true)) {
    $context = 'active_today';
  }

  // QUẢN LÝ ĐIỀU KIỆN TRUY VẤN (WHERE CLAUSE):
  $where_parts = ['o.user_id = ?']; // Luôn lọc theo đúng User ID để đảm bảo tính riêng tư dữ liệu
  $bind_types = 'i';
  $bind_params = [$user_id];

  // XỬ LÝ LOGIC THỜI GIAN ĐỘNG:
  if ($context === 'active_today') {
    // Tối ưu hóa cho trang chủ: Chỉ lấy đơn trong ngày hiện tại
    $where_parts[] = 'DATE(o.created_at) = CURDATE()';
  } else {
    // Xử lý các Preset thời gian cho trang Lịch sử (Tuần, Tháng, Tùy chỉnh)
    $range = isset($filters['range']) ? (string)$filters['range'] : 'all';
    if (!in_array($range, ['today', 'week', 'month', 'all', 'custom'], true)) {
      $range = 'all';
    }

    $today = date('Y-m-d');
    // VALIDATION NGÀY THÁNG: Sử dụng Regex để kiểm tra định dạng Y-m-d trước khi đưa vào SQL.
    $hist_to = isset($filters['hist_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['hist_to'])
      ? (string)$filters['hist_to']
      : $today;
    $hist_from = isset($filters['hist_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['hist_from'])
      ? (string)$filters['hist_from']
      : date('Y-m-d', strtotime('-30 days', strtotime($hist_to)));

    // LOGIC TỰ SỬA LỖI: Nếu SV chọn ngày bắt đầu > ngày kết thúc, hệ thống tự động đảo lại cho đúng.
    if ($hist_from > $hist_to) {
      $t = $hist_from; $hist_from = $hist_to; $hist_to = $t;
    }

    if ($range === 'today') {
      $where_parts[] = 'DATE(o.created_at) = CURDATE()';
    } elseif ($range === 'week') {
      $where_parts[] = 'o.created_at >= (CURDATE() - INTERVAL 7 DAY)';
    } elseif ($range === 'month') {
      $where_parts[] = 'o.created_at >= (CURDATE() - INTERVAL 30 DAY)';
    } elseif ($range === 'custom') {
      // SỬ DỤNG BETWEEN VÀ BIND PARAM: Đảm bảo tuyệt đối không bị lỗi định dạng ngày tháng trong SQL.
      $where_parts[] = 'DATE(o.created_at) BETWEEN ? AND ?';
      $bind_types .= 'ss';
      $bind_params[] = $hist_from;
      $bind_params[] = $hist_to;
    }
  }

  // Bổ sung điều kiện lọc trạng thái nếu người dùng có yêu cầu cụ thể
  if ($st !== 'all') {
    $where_parts[] = 'o.status = ?';
    $bind_types .= 's';
    $bind_params[] = $st;
  }

  $where_sql = implode(' AND ', $where_parts);

  // GIỚI HẠN DỮ LIỆU (PAGINATION/LIMIT):
  // Tránh việc tải hàng ngàn đơn hàng cũ lên trình duyệt cùng một lúc, gây treo máy.
  $limit = null;
  if (array_key_exists('limit', $filters)) {
    $limit = $filters['limit'] === null ? null : max(1, (int)$filters['limit']);
  }

  $orders = [];
  $order_ids = [];
  $total_orders = 0;

  // THỰC THI BƯỚC 1: Đếm tổng số đơn thỏa mãn (phục vụ hiển thị con số trên UI)
  $count_sql = "SELECT COUNT(*) AS c FROM orders o WHERE $where_sql";
  $stmt = $conn->prepare($count_sql);
  if ($stmt) {
    $stmt->bind_param($bind_types, ...$bind_params);
    $stmt->execute();
    $cr = $stmt->get_result();
    $crow = $cr ? $cr->fetch_assoc() : null;
    $stmt->close();
    if ($crow) { $total_orders = (int)($crow['c'] ?? 0); }
  }

  // THỰC THI BƯỚC 2: Lấy dữ liệu chi tiết kèm JOIN bảng Time_Slots để lấy giờ nhận món
  $list_sql = "SELECT o.id, o.order_code, o.status, o.total_cents, o.created_at, o.time_slot_id,
          o.payment_method, o.payment_status,
          ts.start_time, ts.end_time
     FROM orders o
     LEFT JOIN time_slots ts ON ts.id = o.time_slot_id
     WHERE $where_sql
     ORDER BY o.created_at DESC"; 

  if ($limit !== null) { $list_sql .= ' LIMIT ' . $limit; }

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
 * [MỤC 3: TỔNG HỢP VÀ TỰ ĐỘNG HÓA QUY TRÌNH]
 * Tác dụng: Chuẩn bị mọi "nguyên liệu" cho giao diện danh sách đơn hàng.
 * Giải trình: Thể hiện tính tự động hóa của hệ thống
 */
function student_orders_page_data(mysqli $conn, int $user_id, array $get): array {
  $qb_modal = flow_modal_request(); 

  $cart_count = qb_cart_badge_count($conn, $user_id);

  // QUY TRÌNH TỰ ĐỘNG (AUTO-PROCESS): 
  // Hệ thống tự động chuyển đơn hàng sang trạng thái chế biến khi đến giờ hẹn, không đợi nhân viên thao tác.
  qb_orders_auto_pending_to_preparing($conn);

  $allowed_st = ['all', 'pending', 'preparing', 'ready', 'picked_up', 'cancelled', 'no_show'];
  $st = isset($get['st']) ? strtolower((string)$get['st']) : 'all';
  if (!in_array($st, $allowed_st, true)) { $st = 'all'; }

  // HÀM HELPER NỘI BỘ: Tạo URL động thông minh, giữ nguyên trạng thái Modal khi SV chuyển tab lọc đơn.
  $orders_q = static function (array $patch) use ($st, $qb_modal): string {
    $q = array_merge(['page' => 'orders', 'st' => $st], $patch);
    if ($qb_modal) { $q['modal'] = '1'; }
    return site_url('student.php?' . http_build_query($q));
  };

  // XÁC THỰC THANH TOÁN (POST-PAYMENT VALIDATION):
  // Sau khi thanh toán online, hệ thống kiểm tra lại một lần nữa tính xác thực của giao dịch trước khi hiện thông báo thành công.
  $pay_success = false;
  $pay_success_oid = 0;
  $pay_success_code = '';
  if (isset($get['paid']) && (string)$get['paid'] === '1') {
    $try_pay_oid = isset($get['order_id']) ? (int)$get['order_id'] : 0;
    if ($try_pay_oid > 0) {
      $pm_online = 'online'; $ps_paid = 'paid';
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

  // THỰC THI LẤY DỮ LIỆU: Gọi hàm lọc với bối cảnh 'active_today' để tối ưu tốc độ xử lý trong ngày.
  $bundle = get_orders_with_filters($conn, $user_id, [
    'context' => 'active_today',
    'st' => $st,
    'limit' => null,
  ]);
  
  $orders = $bundle['orders'];
  $order_ids = $bundle['order_ids'];
  $total_orders = $bundle['total_orders'];
  
  // Lấy chi tiết món ăn (Batch Load) sau khi đã có danh sách đơn hàng lọc được.
  $items_by_order = order_service_items_by_order_ids($conn, $order_ids);

  // Đóng gói dữ liệu trả về cho Controller
  return compact(
    'qb_modal', 'cart_count', 'st', 'orders_q', 'pay_success', 'pay_success_oid', 'pay_success_code',
    'orders_continue_href', 'status_choices', 'orders', 'order_ids', 'total_orders', 'items_by_order'
  );
}
