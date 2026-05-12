<?php
/**
 * Bảng route student — được nạp bởi public/student.php (Front Controller).
 */
declare(strict_types=1);

$page = (string)($_GET['page'] ?? 'home');
$map = [
  'home' => 'home_controller.php',
  'cart' => 'cart_controller.php',
  'slot' => 'slot_controller.php',
  'checkout' => 'checkout_controller.php',
  'payment' => 'payment_controller.php',
  'orders' => 'order_controller.php',
  'order-history' => 'order_history_controller.php',
  'order-detail' => 'order_detail_controller.php',
  'profile' => 'profile_controller.php',
];
$target = $map[$page] ?? 'home_controller.php';
require_once __DIR__ . '/../app/Student/Controllers/' . $target;
