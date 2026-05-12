<?php
/**
 * DRY — hàm này dùng ở nhiều trang, đặt ở Helpers tránh lặp format số (TV2: format_vnd, format_cents_to_vnd).
 *
 * Đơn vị đầu vào theo codebase: cents (xu), hiển thị VND.
 */
declare(strict_types=1);

function format_vnd(int $amount): string {
  return number_format($amount, 0, ',', '.') . 'đ';
}

function format_cents_to_vnd(int $cents): string {
  return format_vnd($cents);
}

/** Alias lịch sử — cùng behavior `format_vnd`. */
function money_vnd(int $amount): string {
  return format_vnd($amount);
}
