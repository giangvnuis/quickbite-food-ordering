<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin_context.php';
require_once __DIR__ . '/../Services/admin_shell_service.php';
require_once __DIR__ . '/../../Shared/Components/stat_icons.php';
require_once __DIR__ . '/../../Helpers/load.php';
require_once __DIR__ . '/../Services/dashboard_service.php';

/** @var mysqli $conn */

qb_orders_auto_pending_to_preparing($conn);
extract(admin_dashboard_data($conn, $_GET), EXTR_SKIP);
require_once __DIR__ . '/../Views/dashboard.php';
