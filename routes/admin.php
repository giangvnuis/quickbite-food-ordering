<?php
/**
 * Bảng route admin — được nạp bởi public/admin.php (Front Controller).
 */
declare(strict_types=1);

$page = (string)($_GET['page'] ?? 'dashboard');
$map = [
  'dashboard' => 'dashboard_controller.php',
  'menu' => 'menu_controller.php',
  'orders' => 'order_controller.php',
  'order-detail' => 'order_detail_controller.php',
  'users' => 'user_controller.php',
  'counter-order' => 'counter_order_controller.php',
  'profile' => 'profile_controller.php',
];
$target = $map[$page] ?? 'dashboard_controller.php';
require_once __DIR__ . '/../app/Admin/Controllers/' . $target;
