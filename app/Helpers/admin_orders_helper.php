<?php
/**
 * Quản trị đơn — trạng thái được phép đổi từ màn Orders (POST set_status).
 */
declare(strict_types=1);

/** @return list<string> */
function admin_orders_manage_statuses(): array {
  return ['preparing', 'ready', 'picked_up', 'cancelled', 'no_show'];
}
