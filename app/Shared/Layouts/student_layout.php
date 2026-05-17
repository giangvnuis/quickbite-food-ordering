<?php
/**
 * Khung HTML Student: chỉ `<head>` + mở `<body>` — các view full-page (Home, Cart…) tự dựng nội dung bên trong.
 *
 * Dùng khi muốn trang mới chỉ gọi `render_student_layout_head` / `render_student_layout_close` thay vì lặp boilerplate.
 */
declare(strict_types=1);

function render_student_layout_head(string $documentTitle, ?string $bodyClass = null): void {
  $v = (string)@filemtime(dirname(__DIR__, 3) . '/public/assets/css/student.css');
  ?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(site_url('assets/css/student.css'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo urlencode($v); ?>" />
  </head>
  <body<?php
    if ($bodyClass !== null && $bodyClass !== '') {
      echo ' class="' . htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') . '"';
    }
  ?>>
  <?php
}

function render_student_layout_close(): void {
  ?>
</body>
</html>
  <?php
}
