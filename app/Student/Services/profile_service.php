<?php
/**
 * QuickBite Student — Hồ sơ SV: đọc hiển thị, cập nhật profile, đổi mật khẩu.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../Helpers/load.php';
require_once __DIR__ . '/../../Helpers/phone_helper.php';

/** Viết tắt tên cho avatar (khớp pattern các trang SV khác). */
function profile_user_initials(string $full_name): string {
  $initials = '';
  $parts = preg_split('/\s+/', trim($full_name));
  if (is_array($parts) && count($parts) > 0) {
    $initials = mb_strtoupper(mb_substr($parts[0], 0, 1));
    if (count($parts) > 1) {
      $initials .= mb_strtoupper(mb_substr($parts[count($parts) - 1], 0, 1));
    }
  }
  return $initials !== '' ? $initials : 'U';
}

/**
 * Đọc thông tin SV (DB), thống kê đơn theo range GET, số món giỏ — không xử lý POST.
 *
 * @param array<string,mixed> $user
 * @param array<string,mixed> $get
 * @return array<string,mixed>
 */
function get_profile_display_data(mysqli $conn, array $user, array $get): array {
  $user_id = (int)$user['id'];

  $has_student_id = table_has_column($conn, 'users', 'student_id');
  $has_phone = qb_users_has_phone_column($conn);

  $full_name = (string)($user['full_name'] ?? '');
  $email = (string)($user['email'] ?? '');
  $student_id = '';
  $phone = '';

  $sel_profile = 'SELECT full_name, email, student_id';
  if ($has_phone) {
    $sel_profile .= ', phone';
  }
  $sel_profile .= ' FROM users WHERE id = ? LIMIT 1';
  $stmt = $conn->prepare($sel_profile);
  if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row) {
      $full_name = (string)$row['full_name'];
      $email = (string)$row['email'];
      $student_id = $has_student_id ? (string)($row['student_id'] ?? '') : '';
      $phone = $has_phone ? (string)($row['phone'] ?? '') : '';
    }
  }

  $today = date('Y-m-d');
  $stats_end = isset($get['stats_end']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$get['stats_end'])
    ? (string)$get['stats_end']
    : $today;
  $stats_start = isset($get['stats_start']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$get['stats_start'])
    ? (string)$get['stats_start']
    : date('Y-m-d', strtotime('-30 days', strtotime($stats_end)));
  if ($stats_start > $stats_end) {
    $tmp = $stats_start;
    $stats_start = $stats_end;
    $stats_end = $tmp;
  }

  $stats_total = 0;
  $stats_success = 0;
  $stats_cancelled = 0;
  $stats_spent = 0;
  $stmt = $conn->prepare(
    'SELECT
       COUNT(*) AS total,
       SUM(CASE WHEN status = \'picked_up\' THEN 1 ELSE 0 END) AS success_cnt,
       SUM(CASE WHEN status = \'cancelled\' THEN 1 ELSE 0 END) AS cancelled_cnt,
       COALESCE(SUM(CASE WHEN status = \'picked_up\' THEN total_cents ELSE 0 END), 0) AS spent_total
     FROM orders
     WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?'
  );
  if ($stmt) {
    $stmt->bind_param('iss', $user_id, $stats_start, $stats_end);
    $stmt->execute();
    $sr = $stmt->get_result();
    $srow = $sr ? $sr->fetch_assoc() : null;
    $stmt->close();
    if ($srow) {
      $stats_total = (int)($srow['total'] ?? 0);
      $stats_success = (int)($srow['success_cnt'] ?? 0);
      $stats_cancelled = (int)($srow['cancelled_cnt'] ?? 0);
      $stats_spent = (int)($srow['spent_total'] ?? 0);
    }
  }

  $cart_count = 0;
  $stmt = $conn->prepare("SELECT id FROM cart WHERE user_id = ? AND status = 'active' LIMIT 1");
  if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $cart = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($cart) {
      $cart_id = (int)$cart['id'];
      $stmt = $conn->prepare('SELECT COALESCE(SUM(quantity), 0) AS qty FROM cart_items WHERE cart_id = ?');
      if ($stmt) {
        $stmt->bind_param('i', $cart_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
          $cart_count = (int)$row['qty'];
        }
      }
    }
  }

  $profile_initials = profile_user_initials($full_name);

  return compact(
    'has_student_id',
    'has_phone',
    'full_name',
    'email',
    'student_id',
    'phone',
    'stats_end',
    'stats_start',
    'stats_total',
    'stats_success',
    'stats_cancelled',
    'stats_spent',
    'cart_count',
    'profile_initials'
  );
}

/**
 * Cập nhật tên, email, MSSV, phone — validate phone qua phone_helper (normalize + qb_phone_is_valid).
 *
 * @param array<string,mixed> $user
 * @param array<string,mixed> $post
 * @return array{flash_ok:string,flash_err:string}
 */
function update_profile(mysqli $conn, array $user, array $post): array {
  $user_id = (int)$user['id'];
  $has_student_id = table_has_column($conn, 'users', 'student_id');
  $has_phone = qb_users_has_phone_column($conn);

  $full_name = trim((string)($post['full_name'] ?? ''));
  $email = trim((string)($post['email'] ?? ''));
  $student_id = $has_student_id ? trim((string)($post['student_id'] ?? '')) : '';
  $phone_raw = (string)($post['phone'] ?? '');
  $phone_n = $has_phone ? qb_normalize_phone($phone_raw) : '';

  if (mb_strlen($full_name) < 2) {
    return ['flash_ok' => '', 'flash_err' => 'Please enter your full name (at least 2 characters).'];
  }
  if ($has_student_id && $student_id === '') {
    return ['flash_ok' => '', 'flash_err' => 'Please enter your student ID.'];
  }
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return ['flash_ok' => '', 'flash_err' => 'Please enter a valid email address.'];
  }
  if ($has_phone && !qb_phone_is_valid($phone_n)) {
    return ['flash_ok' => '', 'flash_err' => 'Please enter a valid phone number (10–11 digits).'];
  }

  if (qb_user_email_taken($conn, $email, $user_id)) {
    return ['flash_ok' => '', 'flash_err' => 'That email is already used by another account.'];
  }

  if ($has_student_id) {
    $stmt = $conn->prepare('SELECT id FROM users WHERE student_id = ? AND id <> ? LIMIT 1');
    if ($stmt) {
      $stmt->bind_param('si', $student_id, $user_id);
      $stmt->execute();
      $r2 = $stmt->get_result();
      $dup_sid = $r2 ? (bool)$r2->fetch_row() : false;
      $stmt->close();
      if ($dup_sid) {
        return ['flash_ok' => '', 'flash_err' => 'That student ID is already registered to another account.'];
      }
    }
  }

  if ($has_phone && qb_user_phone_taken($conn, $phone_n, $user_id)) {
    return ['flash_ok' => '', 'flash_err' => 'That phone number is already used by another account.'];
  }

  $role = 'student';
  $stmt = null;
  if ($has_student_id && $has_phone) {
    $stmt = $conn->prepare('UPDATE users SET full_name = ?, email = ?, student_id = ?, phone = ? WHERE id = ? AND role = ?');
    if ($stmt) {
      $stmt->bind_param('ssssis', $full_name, $email, $student_id, $phone_n, $user_id, $role);
    }
  } elseif ($has_student_id) {
    $stmt = $conn->prepare('UPDATE users SET full_name = ?, email = ?, student_id = ? WHERE id = ? AND role = ?');
    if ($stmt) {
      $stmt->bind_param('sssis', $full_name, $email, $student_id, $user_id, $role);
    }
  } elseif ($has_phone) {
    $stmt = $conn->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ? AND role = ?');
    if ($stmt) {
      $stmt->bind_param('sssis', $full_name, $email, $phone_n, $user_id, $role);
    }
  } else {
    $stmt = $conn->prepare('UPDATE users SET full_name = ?, email = ? WHERE id = ? AND role = ?');
    if ($stmt) {
      $stmt->bind_param('ssis', $full_name, $email, $user_id, $role);
    }
  }

  if (!$stmt) {
    return ['flash_ok' => '', 'flash_err' => 'Database error.'];
  }

  $ok = $stmt->execute();
  $stmt->close();
  if (!$ok) {
    return ['flash_ok' => '', 'flash_err' => 'Could not save changes. Please try again.'];
  }

  $_SESSION['user']['full_name'] = $full_name;
  $_SESSION['user']['email'] = $email;

  return ['flash_ok' => 'Your profile has been updated.', 'flash_err' => ''];
}

/**
 * Đổi mật khẩu khi nhập đúng mật khẩu hiện tại và xác nhận khớp.
 *
 * @param array<string,mixed> $user
 * @param array<string,mixed> $post
 * @return array{flash_ok:string,flash_err:string}
 */
function change_password(mysqli $conn, array $user, array $post): array {
  $user_id = (int)$user['id'];
  $current = (string)($post['current_password'] ?? '');
  $new = (string)($post['new_password'] ?? '');
  $new2 = (string)($post['new_password_confirm'] ?? '');

  if ($current === '' || $new === '' || $new2 === '') {
    return ['flash_ok' => '', 'flash_err' => 'Please fill in all password fields.'];
  }
  if ($new !== $new2) {
    return ['flash_ok' => '', 'flash_err' => 'New passwords do not match.'];
  }
  if (strlen($new) < 8) {
    return ['flash_ok' => '', 'flash_err' => 'New password must be at least 8 characters.'];
  }

  $stmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
  $hash = '';
  if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $r = $stmt->get_result();
    $urow = $r ? $r->fetch_assoc() : null;
    $stmt->close();
    if ($urow) {
      $hash = (string)($urow['password_hash'] ?? '');
    }
  }

  if ($hash === '' || !password_verify($current, $hash)) {
    return ['flash_ok' => '', 'flash_err' => 'Current password is incorrect.'];
  }

  $new_hash = password_hash($new, PASSWORD_DEFAULT);
  if ($new_hash === false) {
    return ['flash_ok' => '', 'flash_err' => 'Could not update password. Try again.'];
  }

  $stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND role = ?');
  $role = 'student';
  if (!$stmt) {
    return ['flash_ok' => '', 'flash_err' => 'Database error.'];
  }
  $stmt->bind_param('sis', $new_hash, $user_id, $role);
  $ok = $stmt->execute();
  $stmt->close();
  if (!$ok) {
    return ['flash_ok' => '', 'flash_err' => 'Could not save new password.'];
  }

  return ['flash_ok' => 'Your password has been changed.', 'flash_err' => ''];
}
