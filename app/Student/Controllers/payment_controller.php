<?php
/**
 * QuickBite Student — Controller xử lý thanh toán.
 * Tác dụng: Tiếp nhận yêu cầu xác nhận thanh toán (POST) và điều hướng hiển thị trang Pay (GET).
 */
declare(strict_types=1);

// 1. THIẾT LẬP MÔI TRƯỜNG & DỮ LIỆU
require_once __DIR__ . '/../student_context.php';      // Lấy bối cảnh đăng nhập của sinh viên
require_once __DIR__ . '/../Services/payment_service.php'; // Nạp các hàm nghiệp vụ thanh toán (đã viết ở file service)

$ctx = student_ctx();  // Khởi tạo context (kết nối DB, session...)
$conn = $ctx['conn'];  // Đối tượng kết nối MySQL
$user = $ctx['user'];  // Thông tin sinh viên đang đăng nhập

// Lấy thông tin trạng thái Modal (nếu trang đang mở trong cửa sổ popup)
$qb_modal_early = flow_modal_request();
$pay_error = ''; // Biến lưu thông báo lỗi nếu quá trình thanh toán thất bại

// 2. XỬ LÝ KHI SINH VIÊN NHẤN NÚT "PAY NOW" (REQUEST_METHOD IS POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Lấy ID đơn hàng từ thanh địa chỉ (URL)
  $order_id_post = (int) ($_GET['order_id'] ?? 0);
  $uid = (int) $user['id']; // ID của sinh viên hiện tại
  
  /**
   * THỰC THI BƯỚC 2: Cập nhật trạng thái thanh toán vào Database.
   * Gọi hàm update_payment_status từ Service Layer.
   */
  if ($order_id_post > 0 && update_payment_status($conn, $uid, $order_id_post)) {
    // Nếu cập nhật thành công -> Chuyển hướng (Redirect) về danh sách đơn hàng kèm thông báo "Đã trả tiền" (paid=1)
    header('Location: ' . flow_modal_url('orders.php?paid=1&order_id=' . $order_id_post, $qb_modal_early));
    exit; // Dừng kịch bản ngay sau khi redirect
  }
  
  // Nếu không vào được IF trên (do đơn đã paid rồi, hoặc sai ID), gán lỗi để hiển thị ra giao diện
  $pay_error = 'Could not confirm payment. The order may already be paid or was updated.';
}

// 3. XỬ LÝ HIỂN THỊ GIAO DIỆN (REQUEST_METHOD IS GET HOẶC KHI POST THẤT BẠI)

/**
 * THỰC THI BƯỚC 1: Gọi Service để lấy toàn bộ dữ liệu cần thiết (Thông tin đơn, mã QR SVG...).
 * extract(...): Biến các khóa trong mảng thành các biến độc lập (ví dụ $order, $pay_qr_svg) để View sử dụng dễ dàng.
 */
extract(get_pay_display_data($conn, $user, $_GET, $pay_error), EXTR_SKIP);

// Nạp file giao diện (View) để hiển thị lên màn hình sinh viên
require_once __DIR__ . '/../Views/pay.php';
