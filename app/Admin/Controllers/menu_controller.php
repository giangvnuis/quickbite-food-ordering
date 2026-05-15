<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin_context.php';
require_once __DIR__ . '/../Services/menu_service.php';
require_once __DIR__ . '/../Services/admin_shell_service.php';
require_once __DIR__ . '/../../Shared/Components/stat_icons.php';

/** @var mysqli $conn */

$has_stock_qty = table_has_column($conn, 'products', 'stock_qty');

$after_post = admin_menu_process_post($conn, $_POST, $_GET, $has_stock_qty);
if ($after_post !== null) {
  header('Location: ' . $after_post);
  exit;
}

extract(admin_menu_page_data($conn, $_GET, $has_stock_qty), EXTR_SKIP);
require_once __DIR__ . '/../Views/menu.php';
