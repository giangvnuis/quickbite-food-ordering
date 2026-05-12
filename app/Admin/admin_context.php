<?php
/**
 * Bootstrap khu vực admin: kết nối DB, xác thực guard, biến `$admin_user`.
 * Tiện ích (tiền tệ, trạng thái, layout shell, …) nạp từ Helpers/Services ở controller tương ứng.
 */
declare(strict_types=1);

require_once __DIR__ . '/../Guards/auth_guard.php';
require_once __DIR__ . '/../Config/database.php';
/** @var mysqli $conn */
qb_runtime_verify_account_active($conn);

$admin_user = require_admin();
