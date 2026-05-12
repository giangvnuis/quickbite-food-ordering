<?php
declare(strict_types=1);

require_once __DIR__ . '/../student_context.php';
require_once __DIR__ . '/../Services/order_service.php';
$ctx = student_ctx();
$conn = $ctx['conn'];
$user = $ctx['user'];
extract(student_orders_page_data($conn, (int) $user['id'], $_GET), EXTR_SKIP);
require_once __DIR__ . '/../Views/orders.php';
