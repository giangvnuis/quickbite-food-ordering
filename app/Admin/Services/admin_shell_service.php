<?php
/**
 * QuickBite Admin — Shell HTML: qb_admin_shell_start / qb_admin_shell_end bọc nội dung trang admin.
 *
 * Layout: Shared/Layouts/admin_layout.php (sidebar, topbar, main).
 */
declare(strict_types=1);

require_once __DIR__ . '/../../Shared/Layouts/admin_layout.php';

function qb_admin_shell_start(string $active, string $title, string $subtitle): void {
  global $admin_user;
  render_admin_layout_start($active, $title, $title, $subtitle, $admin_user);
}

function qb_admin_shell_end(): void {
  render_admin_layout_end();
}
