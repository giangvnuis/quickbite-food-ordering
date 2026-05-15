<?php
/**
 * QuickBite — Luồng đặt hàng trong iframe modal từ home (?modal=1): giữ query khi redirect form/link.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../Helpers/url_helper.php';

// --- Nhận biết request đang trong modal (GET/POST modal=1) ---

function flow_modal_request(): bool {
  if (isset($_GET['modal']) && (string)$_GET['modal'] === '1') {
    return true;
  }
  if (isset($_POST['modal']) && (string)$_POST['modal'] === '1') {
    return true;
  }
  return false;
}

// --- Gắn ?modal=1 vào URL; hidden input cho form POST ---

/**
 * Chuyển URL kiểu cũ (cart.php, order-details.php?id=…) sang entry public/student.php?page=…
 */
function flow_modal_url(string $url, bool $modal): string {
  $url = str_replace('\\', '/', $url);
  $qpos = strpos($url, '?');
  $path = $qpos !== false ? substr($url, 0, $qpos) : $url;
  $qs = $qpos !== false ? substr($url, $qpos + 1) : '';
  parse_str($qs, $params);

  $base = strtolower(basename($path));
  $map = [
    'home.php' => 'home',
    'cart.php' => 'cart',
    'checkout.php' => 'checkout',
    'pay.php' => 'payment',
    'orders.php' => 'orders',
    'order-history.php' => 'order-history',
    'order-details.php' => 'order-detail',
    'select-slot.php' => 'slot',
    'profile.php' => 'profile',
  ];

  if (!isset($map[$base])) {
    return student_url('home', [], $modal);
  }

  $page = $map[$base];
  unset($params['modal']);

  return student_url($page, $params, $modal);
}

function flow_modal_hidden(bool $modal): string {
  // Hidden input cho POST để request tiếp theo vẫn giữ modal=1.
  return $modal ? '<input type="hidden" name="modal" value="1" />' . "\n" : '';
}

/** Link icon giỏ: trong iframe → cart + modal; full page → home mở flow cart */
function cart_entry_href(bool $modal): string {
  if ($modal) {
    return student_url('cart', [], true);
  }
  return student_url('home', ['open_flow' => 'cart'], false);
}
