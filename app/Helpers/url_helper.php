<?php
/**
 * DRY — hàm này dùng ở nhiều trang, đặt ở Helpers tránh lặp logic base path / router query.
 */
declare(strict_types=1);

/**
 * URL path prefix tới thư mục gốc project (không có slash cuối), ví dụ "/quickbite".
 */
function web_base_path(): string {
  $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
  if ($script === '') {
    return '';
  }
  $dir = str_replace('\\', '/', dirname($script));
  if ($dir === '/' || $dir === '.') {
    return '';
  }
  if (preg_match('#/(student|admin|ai)$#', $dir)) {
    $up = dirname($dir);
    if ($up === '/' || $up === '.') {
      return '';
    }
    $dir = $up;
  }
  return $dir === '/' ? '' : $dir;
}

/**
 * Đường dẫn URL tuyệt đối từ gốc site tới file trong project (luôn bắt đầu bằng /).
 * Hỗ trợ chuỗi có query, ví dụ "student/home.php?modal=1".
 */
function site_url(string $path): string {
  $path = str_replace('\\', '/', $path);
  $query = '';
  $qpos = strpos($path, '?');
  if ($qpos !== false) {
    $query = substr($path, $qpos);
    $path = substr($path, 0, $qpos);
  }
  $path = ltrim($path, '/');
  $b = web_base_path();
  if ($b !== '' && str_contains($b, '/public') && str_starts_with($path, 'public/')) {
    $path = substr($path, strlen('public/'));
  }
  if ($b === '') {
    return '/' . $path . $query;
  }
  return $b . '/' . $path . $query;
}

/**
 * URL student router: public/student.php?page=…
 *
 * @param bool $modal true → thêm modal=1 (iframe luồng đặt hàng).
 */
function student_url(string $page, array $query = [], bool $modal = false): string {
  $query = array_merge(['page' => $page], $query);
  if ($modal) {
    $query['modal'] = '1';
  }
  return site_url('student.php?' . http_build_query($query));
}

/**
 * Entry admin: `public/admin.php?page=…`.
 * Với `<form method="get">`, nên thêm `<input type="hidden" name="page" value="…">`: một số trình duyệt
 * không giữ `?page=…` có sẵn trên thuộc tính `action`, khiến router coi như thiếu `page` → mặc định dashboard.
 */
function admin_url(string $page, array $query = []): string {
  $query = array_merge(['page' => $page], $query);
  return site_url('admin.php?' . http_build_query($query));
}

/** DRY — redirect HTTP dùng chung auth và checkout; đặt cạnh URL helpers cho luồng rõ ràng. */
function redirect_to(string $to): void {
  header('Location: ' . $to);
  exit;
}
