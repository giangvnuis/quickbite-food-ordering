<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin_context.php';
require_once __DIR__ . '/../Services/admin_shell_service.php';
require_once __DIR__ . '/../Services/profile_service.php';

/** @var mysqli $conn */

$admin_id = (int) ($admin_user['id'] ?? 0);
$has_phone = qb_users_has_phone_column($conn);

$data = get_admin_profile($conn, $admin_id, $admin_user, $has_phone);
$full_name = $data['full_name'];
$email = $data['email'];
$phone = $data['phone'];
$flash_ok = '';
$flash_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $out = admin_profile_process_post($conn, $_POST, $admin_id, $has_phone);
  $flash_ok = $out['flash_ok'];
  $flash_err = $out['flash_err'];
  if ($flash_ok !== '') {
    $data = get_admin_profile($conn, $admin_id, $_SESSION['user'] ?? $admin_user, $has_phone);
    $full_name = $data['full_name'];
    $email = $data['email'];
    $phone = $data['phone'];
  } elseif ($flash_err !== '' && (string) ($_POST['which'] ?? '') === 'profile') {
    $full_name = trim((string) ($_POST['full_name'] ?? $full_name));
    $email = trim((string) ($_POST['email'] ?? $email));
    $phone = $has_phone ? trim((string) ($_POST['phone'] ?? $phone)) : $phone;
  }
}

require_once __DIR__ . '/../Views/profile.php';
