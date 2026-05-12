<?php
/**
 * Chuẩn bị DB + guard cho controller Student (tránh lặp require).
 */
declare(strict_types=1);

/**
 * Trả về mảng hai khóa `conn` và `user`. Controller có thể gán:
 * `$ctx = student_ctx(); $conn = $ctx['conn']; $user = $ctx['user'];`
 * (tránh destructuring `['conn' => $conn, ...] = …` nếu chưa quen PHP 7.1+).
 *
 * @return array{conn: mysqli, user: array<string, mixed>}
 */
function student_ctx(bool $mustBeStudent = true): array {
  require_once dirname(__DIR__) . '/Guards/auth_guard.php';
  require_once dirname(__DIR__) . '/Config/database.php';
  /** @var mysqli $conn */
  qb_runtime_verify_account_active($conn);
  $user = $mustBeStudent ? require_student() : require_login();

  return ['conn' => $conn, 'user' => $user];
}
