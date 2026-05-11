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
