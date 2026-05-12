<?php
/**
 * DRY — hàm này dùng ở nhiều trang (auth, profile), đặt ở Helpers tránh lặp (TV2).
 *
 * Chuẩn hóa / validate số điện thoại; kiểm tra cột users.phone (migration từng bước).
 */
declare(strict_types=1);

/**
 * Chỉ giữ chữ số (bỏ khoảng trắng, +, dấu gạch…).
 * Không cắt độ dài — nếu > 11 chữ số thì qb_phone_is_valid() sẽ từ chối (tránh “nhập 15 số vẫn lưu 11 số”).
 */
function qb_normalize_phone(string $raw): string {
  $raw = trim($raw);
  if ($raw === '') {
    return '';
  }
  return preg_replace('/\D+/', '', $raw);
}

// --- Đúng 10 hoặc 11 chữ số (sau normalize), không hơn không kém ---

function qb_phone_is_valid(string $normalized_digits): bool {
  if ($normalized_digits === '') {
    return false;
  }
  if (!ctype_digit($normalized_digits)) {
    return false;
  }
  $len = strlen($normalized_digits);
  return $len >= 10 && $len <= 11;
}

// --- Migration từng bước: có cột users.phone hay chưa (cache static) ---

function qb_users_has_phone_column(mysqli $conn): bool {
  static $cache = null;
  if ($cache !== null) {
    return $cache;
  }
  $res = $conn->query(
    "SELECT 1 FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'phone' LIMIT 1"
  );
  $cache = $res instanceof mysqli_result && $res->num_rows > 0;
  return $cache;
}
