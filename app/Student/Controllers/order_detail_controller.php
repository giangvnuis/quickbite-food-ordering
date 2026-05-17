<?php
declare(strict_types=1);

require_once __DIR__ . '/../student_context.php';
require_once __DIR__ . '/../Services/order_detail_service.php';
$ctx = student_ctx();
$conn = $ctx['conn'];
$user = $ctx['user'];
extract(student_order_detail_display_data($conn, $user, $_GET, $_POST), EXTR_SKIP);
require_once __DIR__ . '/../Views/order-details.php';
