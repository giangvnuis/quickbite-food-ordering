<?php
/**
 * Controller Quản lý Đơn hàng
 * Vai trò: Controller (Bộ điều khiển) - Tiếp nhận yêu cầu từ sinh viên và điều phối dữ liệu.
 */
declare(strict_types=1);

// BƯỚC 1: Nạp ngữ cảnh (Context) và dịch vụ xử lý (Service)
// student_context.php: Kiểm tra quyền đăng nhập của sinh viên
require_once __DIR__ . '/../student_context.php';
// order_service.php: Chứa các hàm truy vấn SQL đã được tối ưu
require_once __DIR__ . '/../Services/order_service.php';

// BƯỚC 2: Khởi tạo dữ liệu người dùng hiện tại
$ctx = student_ctx();
$conn = $ctx['conn']; // Kết nối Database
$user = $ctx['user']; // Thông tin sinh viên đang đăng nhập

// BƯỚC 3: Xử lý Logic Nghiệp vụ (Business Logic)
// student_orders_page_data: Hàm này sẽ thực hiện 3 việc:
// 1. Tự động cập nhật trạng thái đơn (Auto-process).
// 2. Lấy danh sách đơn hàng lọc theo yêu cầu từ biến $_GET (URL).
// 3. Batch Load toàn bộ món ăn đi kèm các đơn đó.
// extract(): Hàm này giúp giải nén mảng dữ liệu thành các biến riêng biệt (như $orders, $cart_count...) để View dễ dàng sử dụng.
extract(student_orders_page_data($conn, (int) $user['id'], $_GET), EXTR_SKIP);

// BƯỚC 4: Đẩy dữ liệu ra giao diện (View)
// Sau khi đã có đầy đủ "nguyên liệu" từ Service, Controller gọi View để hiển thị cho sinh viên.
require_once __DIR__ . '/../Views/orders.php';
