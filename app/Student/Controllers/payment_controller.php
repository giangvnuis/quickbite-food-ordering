<?php
declare(strict_types=1);

require_once __DIR__ . '/../student_context.php';
require_once __DIR__ . '/../Services/payment_service.php';
$ctx = student_ctx();
$conn = $ctx['conn'];
$user = $ctx['user'];

$qb_modal_early = flow_modal_request();
$pay_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $order_id_post = (int) ($_GET['order_id'] ?? 0);
  $uid = (int) $user['id'];
  if ($order_id_post > 0 && update_payment_status($conn, $uid, $order_id_post)) {
    header('Location: ' . flow_modal_url('orders.php?paid=1&order_id=' . $order_id_post, $qb_modal_early));
    exit;
  }
  $pay_error = 'Could not confirm payment. The order may already be paid or was updated.';
}

extract(get_pay_display_data($conn, $user, $_GET, $pay_error), EXTR_SKIP);
require_once __DIR__ . '/../Views/pay.php';
