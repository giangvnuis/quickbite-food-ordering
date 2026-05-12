<?php
/**
 * Khung HTML Admin: CSS + sidebar + topbar + `<main>` mở (đóng bằng `render_admin_layout_end`).
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/Components/sidebar.php';
require_once dirname(__DIR__) . '/Components/navbar.php';

/** Cache-bust theo filemtime asset trong `public/assets/css/`. */
function render_layout_css_version(string $filename): string {
  $path = dirname(__DIR__, 3) . '/public/assets/css/' . $filename;
  return (string)@filemtime($path);
}

/**
 * @param array<string, mixed> $adminUser Session admin (`full_name`, …).
 */
function render_admin_layout_start(
  string $activeNav,
  string $documentTitle,
  string $topbarTitle,
  string $topbarSubtitle,
  array $adminUser
): void {
  $style_ver = render_layout_css_version('student.css');
  $admin_style_ver = render_layout_css_version('admin.css');
  ?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>QuickBite Admin • <?php echo htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(site_url('assets/css/student.css'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo urlencode($style_ver); ?>" />
    <link rel="stylesheet" href="<?php echo htmlspecialchars(site_url('assets/css/admin.css'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo urlencode($admin_style_ver); ?>" />
  </head>
  <body>
    <div class="qb-admin">
      <?php render_admin_sidebar($activeNav, $adminUser); ?>
      <div class="qb-admin-main">
        <?php render_admin_topbar($topbarTitle, $topbarSubtitle); ?>
        <main class="qb-admin-content">
  <?php
}

/** Đóng `<main>` và shell admin. */
function render_admin_layout_end(): void {
  ?>
        </main>
      </div>
    </div>
  </body>
</html>
  <?php
}
