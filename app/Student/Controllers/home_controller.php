<?php
/**
 * QuickBite Student — Home: gọi HomeService, truyền dữ liệu vào View (extract để template chỉ echo/HTML).
 */
declare(strict_types=1);

require_once __DIR__ . '/../student_context.php';
require_once __DIR__ . '/../Services/home_service.php';

$ctx = student_ctx(false);
$conn = $ctx['conn'];
$user = $ctx['user'];
$home = student_home_page_data($conn, $user, $_GET);
extract($home, EXTR_SKIP);

require_once __DIR__ . '/../Views/home.php';
