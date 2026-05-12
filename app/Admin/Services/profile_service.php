<?php
/**
 * QuickBite Admin — Hồ sơ admin: đọc DB, đổi mật khẩu, cập nhật thông tin.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../Helpers/phone_helper.php';
require_once __DIR__ . '/../../Helpers/load.php';

/**
 * Đọc hồ sơ admin từ DB (fallback session).
 *
 * @param array<string, mixed> $admin_user Session user (fallback).
 * @return array{full_name: string, email: string, phone: string}
 */
function get_admin_profile(mysqli $conn, int $admin_id, array $admin_user, bool $has_phone): array {
  $full_name = (string) ($admin_user['full_name'] ?? '');
  $email = (string) ($admin_user['email'] ?? '');
  $phone = '';

  $sql = 'SELECT full_name, email';
  if ($has_phone) {
    $sql .= ', phone';
  }
  $sql .= ' FROM users WHERE id = ? AND role = ? LIMIT 1';
  $stmt = $conn->prepare($sql);
  if ($stmt) {
    $role = 'admin';
    $stmt->bind_param('is', $admin_id, $role);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row) {
      $full_name = (string) ($row['full_name'] ?? '');
      $email = (string) ($row['email'] ?? '');
      $phone = $has_phone ? (string) ($row['phone'] ?? '') : '';
    }
  }

  return [
    'full_name' => $full_name,
    'email' => $email,
    'phone' => $phone,
  ];
}

/**
 * Đổi mật khẩu admin (xác thực mật khẩu hiện tại).
 *
 * @param array<string, mixed> $post
 * @return array{flash_ok: string, flash_err: string}
 */
function update_admin_password(mysqli $conn, array $post, int $admin_id): array {
  $flash_ok = '';
  $flash_err = '';

  $current = (string) ($post['current_password'] ?? '');
  $new = (string) ($post['new_password'] ?? '');
  $new2 = (string) ($post['new_password_confirm'] ?? '');

  if ($current === '' || $new === '' || $new2 === '') {
    $flash_err = 'Please fill in all password fields.';
  } elseif ($new !== $new2) {
    $flash_err = 'New passwords do not match.';
  } elseif (strlen($new) < 8) {
    $flash_err = 'New password must be at least 8 characters.';
  } else {
    $stmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ? AND role = ? LIMIT 1');
    $hash = '';
    if ($stmt) {
      $role = 'admin';
      $stmt->bind_param('is', $admin_id, $role);
      $stmt->execute();
      $res = $stmt->get_result();
      $row = $res ? $res->fetch_assoc() : null;
      $stmt->close();
      if ($row) {
        $hash = (string) ($row['password_hash'] ?? '');
      }
    }

    if ($hash === '' || !password_verify($current, $hash)) {
      $flash_err = 'Current password is incorrect.';
    } else {
      $new_hash = password_hash($new, PASSWORD_DEFAULT);
      if ($new_hash === false) {
        $flash_err = 'Could not update password. Try again.';
      } else {
        $stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND role = ? LIMIT 1');
        if ($stmt) {
          $role = 'admin';
          $stmt->bind_param('sis', $new_hash, $admin_id, $role);
          $ok = $stmt->execute();
          $stmt->close();
          if ($ok) {
            $flash_ok = 'Your password has been changed.';
          } else {
            $flash_err = 'Could not save new password.';
          }
        } else {
          $flash_err = 'Database error.';
        }
      }
    }
  }

  return ['flash_ok' => $flash_ok, 'flash_err' => $flash_err];
}

/**
 * Cập nhật tên, email, phone admin; đồng bộ session khi thành công.
 *
 * @param array<string, mixed> $post
 * @return array{flash_ok: string, flash_err: string}
 */
function update_admin_profile_info(mysqli $conn, array $post, int $admin_id, bool $has_phone): array {
  $flash_ok = '';
  $flash_err = '';

  $full_name = trim((string) ($post['full_name'] ?? ''));
  $email = trim((string) ($post['email'] ?? ''));
  $phone_n = $has_phone ? qb_normalize_phone((string) ($post['phone'] ?? '')) : '';

  if (mb_strlen($full_name) < 2) {
    $flash_err = 'Please enter your full name (at least 2 characters).';
  } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $flash_err = 'Please enter a valid email address.';
  } elseif ($has_phone && !qb_phone_is_valid($phone_n)) {
    $flash_err = 'Please enter a valid phone number (10-11 digits).';
  } elseif (qb_user_email_taken($conn, $email, $admin_id)) {
    $flash_err = 'That email is already used by another account.';
  } elseif ($has_phone && qb_user_phone_taken($conn, $phone_n, $admin_id)) {
    $flash_err = 'That phone number is already used by another account.';
  } else {
    if ($has_phone) {
      $stmt = $conn->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ? AND role = ? LIMIT 1');
      if ($stmt) {
        $role = 'admin';
        $stmt->bind_param('sssis', $full_name, $email, $phone_n, $admin_id, $role);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
          $_SESSION['user']['full_name'] = $full_name;
          $_SESSION['user']['email'] = $email;
          $flash_ok = 'Your profile has been updated.';
        } else {
          $flash_err = 'Could not save changes. Please try again.';
        }
      } else {
        $flash_err = 'Database error.';
      }
    } else {
      $stmt = $conn->prepare('UPDATE users SET full_name = ?, email = ? WHERE id = ? AND role = ? LIMIT 1');
      if ($stmt) {
        $role = 'admin';
        $stmt->bind_param('ssis', $full_name, $email, $admin_id, $role);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
          $_SESSION['user']['full_name'] = $full_name;
          $_SESSION['user']['email'] = $email;
          $flash_ok = 'Your profile has been updated.';
        } else {
          $flash_err = 'Could not save changes. Please try again.';
        }
      } else {
        $flash_err = 'Database error.';
      }
    }
  }

  return ['flash_ok' => $flash_ok, 'flash_err' => $flash_err];
}

/**
 * @deprecated Dùng get_admin_profile()
 * @return array{full_name: string, email: string, phone: string, flash_ok: string, flash_err: string}
 */
function admin_profile_page_data(mysqli $conn, int $admin_id, array $admin_user, bool $has_phone): array {
  $p = get_admin_profile($conn, $admin_id, $admin_user, $has_phone);
  return array_merge($p, ['flash_ok' => '', 'flash_err' => '']);
}

/**
 * Xử lý POST profile hoặc password.
 *
 * @param array<string, mixed> $post
 * @return array{flash_ok: string, flash_err: string}
 */
function admin_profile_process_post(mysqli $conn, array $post, int $admin_id, bool $has_phone): array {
  if ((string) ($post['which'] ?? '') === 'password') {
    return update_admin_password($conn, $post, $admin_id);
  }
  return update_admin_profile_info($conn, $post, $admin_id, $has_phone);
}
