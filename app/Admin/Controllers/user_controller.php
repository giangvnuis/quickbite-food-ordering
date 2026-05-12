<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin_context.php';
require_once __DIR__ . '/../Services/user_service.php';
require_once __DIR__ . '/../Services/admin_shell_service.php';
require_once __DIR__ . '/../../Shared/Components/stat_icons.php';

/** @var mysqli $conn */

$qb_um_has_phone = qb_users_has_phone_column($conn);

$after = admin_users_process_post($conn, $_POST, $admin_user);
if ($after !== null) {
  header('Location: ' . $after);
  exit;
}

extract(admin_users_page_data($conn, $_GET, $qb_um_has_phone), EXTR_SKIP);
require_once __DIR__ . '/../Views/users.php';
