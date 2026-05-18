<?php
declare(strict_types=1);

require_once __DIR__ . '/../student_context.php';
require_once __DIR__ . '/../Services/cart_service.php';

$ctx = student_ctx();
$conn = $ctx['conn'];
$user = $ctx['user'];
$uid = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string) ($_POST['action'] ?? '');
  $pid = (int) ($_POST['product_id'] ?? 0);
  $post_modal = isset($_POST['modal']) && (string) $_POST['modal'] === '1';

  // Thêm từ menu / modal chi tiết: chỉ gửi product_id, không có action
  if ($pid > 0 && $action === '') {
    try {
      $name = add_item_to_cart($conn, $uid, $pid);
      header('Location: ' . student_url('home', ['msg' => $name . ' added to cart.', 'type' => 'success'], false));
      exit;
    } catch (Throwable $e) {
      $msg = $e->getMessage() !== '' ? $e->getMessage() : 'Something went wrong.';
      header('Location: ' . student_url('home', ['msg' => $msg, 'type' => 'error'], false));
      exit;
    }
  }

  if ($action !== '') {
    try {
      switch ($action) {
        case 'inc':
          increment_cart_item_quantity($conn, $uid, $pid);
          break;
        case 'dec':
          decrement_cart_item_quantity($conn, $uid, $pid);
          break;
        case 'remove':
          remove_item_from_cart($conn, $uid, $pid);
          break;
        case 'clear':
          clear_cart($conn, $uid);
          break;
        default:
          throw new Exception('Invalid action.');
      }
      student_cart_redirect('Cart updated.', 'success', $post_modal);
    } catch (Throwable $e) {
      $em = $e->getMessage();
      student_cart_redirect($em !== '' ? $em : 'Could not update cart.', 'error', $post_modal);
    }
  }
}

extract(get_cart_display_data($conn, $uid, $_GET), EXTR_SKIP);
require_once __DIR__ . '/../Views/cart.php';
