<?php
/**
 * QuickBite Student — Chi tiết một đơn (order_detail_controller): load đơn + dòng + POST hủy/refund.
 * Danh sách / lịch sử dùng order_service + order_history_service — không gộp vào đây.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../Shared/Components/flow_modal.php';
require_once __DIR__ . '/../../Helpers/phone_helper.php';
require_once __DIR__ . '/../../Helpers/load.php';
require_once __DIR__ . '/payment_service.php';

function student_order_detail_status_badge(string $status): array {
  $status = strtolower($status);
  return match ($status) {
    'ready' => ['Ready for Pickup', 'ready'],
    'preparing' => ['Preparing', 'preparing'],
    'pending' => ['Pending', 'pending'],
    'picked_up' => ['Completed', 'completed'],
    'cancelled' => ['Cancelled', 'cancelled'],
    default => [ucfirst($status), 'pending'],
  };
}

/**
 * @param array<string,mixed> $user
 * @param array<string,mixed> $get
 * @param array<string,mixed> $post
 * @return array<string,mixed>
 */
function student_order_detail_display_data(mysqli $conn, array $user, array $get, array $post): array {
  $user_id = (int)$user['id'];
  $order_id = isset($get['id']) ? (int)$get['id'] : 0;
  $qb_modal = flow_modal_request();

  qb_orders_auto_pending_to_preparing($conn);

  if ($order_id <= 0) {
    header('Location: ' . flow_modal_url('orders.php', $qb_modal));
    exit;
  }

  // =========================
  // Load order (must belong to user)
  // =========================
  $order = null;
  $stmt = $conn->prepare(
    "SELECT o.id, o.order_code, o.status, o.total_cents, o.subtotal_cents, o.created_at, o.notes,
            o.payment_method, o.payment_status, o.time_slot_id,
            ts.start_time, ts.end_time
     FROM orders o
     LEFT JOIN time_slots ts ON ts.id = o.time_slot_id
     WHERE o.id = ? AND o.user_id = ?
     LIMIT 1"
  );
  if ($stmt) {
    $stmt->bind_param('ii', $order_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $order = $res ? $res->fetch_assoc() : null;
    $stmt->close();
  }

  if (!$order) {
    header('Location: ' . flow_modal_url('orders.php', $qb_modal));
    exit;
  }

  $flash_error = '';
  $flash_success = '';

  // =========================
  // Cancel order (student, within 5 minutes, pending only)
  // =========================
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($post['action']) && $post['action'] === 'cancel_order') {
    $created_ts = strtotime((string)$order['created_at']);
    $status_now = strtolower((string)$order['status']);
    $payment_now = strtolower((string)($order['payment_status'] ?? 'unpaid'));
    $within_5m = ($created_ts > 0 && (time() - $created_ts) <= 300);

    if ($status_now !== 'pending') {
      $flash_error = 'Only pending orders can be cancelled.';
    } elseif (!$within_5m) {
      $flash_error = 'Cancellation is allowed only within 5 minutes.';
    } else {
      // Transaction hủy đơn + lưu thông tin hoàn tiền (nếu cần) trong notes.
      $conn->begin_transaction();
      try {
        $pmethod = (string)($order['payment_method'] ?? 'counter');
        $is_online = ($pmethod === 'online');
        $bank = trim((string)($post['refund_bank'] ?? ''));
        $acc_no = trim((string)($post['refund_account_number'] ?? ''));
        $acc_name = trim((string)($post['refund_account_name'] ?? ''));

        $needs_bank = ($payment_now === 'paid') || ($is_online && $payment_now === 'unpaid');
        if ($needs_bank && ($bank === '' || $acc_no === '' || $acc_name === '')) {
          throw new Exception('Please enter bank name, account number, and account holder name.');
        }

        if ($payment_now === 'paid') {
          request_refund($conn, $user_id, $order_id, $bank, $acc_no, $acc_name);
          $conn->commit();
          header('Location: ' . flow_modal_url('order-details.php?id=' . (int)$order_id . '&refund_requested=1', $qb_modal));
          exit;
        }

        if ($is_online) {
          $cancel_note = sprintf(
            '[CANCEL_BANK]|bank=%s|account_number=%s|account_name=%s|requested_at=%s',
            rawurlencode($bank),
            rawurlencode($acc_no),
            rawurlencode($acc_name),
            date('Y-m-d H:i:s')
          );
          $stmt = $conn->prepare(
            "UPDATE orders
             SET status = 'cancelled',
                 notes = CASE
                   WHEN notes IS NULL OR notes = '' THEN ?
                   ELSE CONCAT(notes, '\n', ?)
                 END
             WHERE id = ? AND user_id = ? AND status = 'pending' AND payment_status = 'unpaid'
             LIMIT 1"
          );
          if (!$stmt) throw new Exception('DB error while cancelling order.');
          $stmt->bind_param('ssii', $cancel_note, $cancel_note, $order_id, $user_id);
        } else {
          $stmt = $conn->prepare(
            "UPDATE orders
             SET status = 'cancelled'
             WHERE id = ? AND user_id = ? AND status = 'pending'
             LIMIT 1"
          );
          if (!$stmt) throw new Exception('DB error while cancelling order.');
          $stmt->bind_param('ii', $order_id, $user_id);
        }

        if (!$stmt->execute()) {
          $msg = 'Could not cancel order.';
          if ($stmt->error !== '') $msg .= ' ' . $stmt->error;
          throw new Exception($msg);
        }
        if ($stmt->affected_rows <= 0) {
          throw new Exception('Order could not be cancelled. The order may have been updated already.');
        }
        $stmt->close();
        $conn->commit();
        if ($is_online) {
          header('Location: ' . flow_modal_url('order-history.php?cancelled=1', $qb_modal));
        } else {
          header('Location: ' . flow_modal_url('order-history.php', $qb_modal));
        }
        exit;
      } catch (Throwable $e) {
        $conn->rollback();
        $flash_error = $e->getMessage();
      }
    }
  }

  // =========================
  // Load profile fields for Student Information (DB = source of truth after profile edits)
  // =========================
  $student_name = (string)($user['full_name'] ?? '');
  $student_email = (string)($user['email'] ?? '');
  $student_id = '';
  $student_phone = '';
  $has_user_phone = qb_users_has_phone_column($conn);
  $od_user_sql = 'SELECT full_name, email, student_id';
  if ($has_user_phone) {
    $od_user_sql .= ', phone';
  }
  $od_user_sql .= ' FROM users WHERE id = ? LIMIT 1';
  $stmt = $conn->prepare($od_user_sql);
  if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row) {
      if (array_key_exists('full_name', $row) && (string)$row['full_name'] !== '') {
        $student_name = (string)$row['full_name'];
        $_SESSION['user']['full_name'] = $student_name;
      }
      if (array_key_exists('email', $row) && (string)$row['email'] !== '') {
        $student_email = (string)$row['email'];
        $_SESSION['user']['email'] = $student_email;
      }
      if (isset($row['student_id'])) {
        $student_id = (string)$row['student_id'];
      }
      if ($has_user_phone && isset($row['phone'])) {
        $student_phone = (string)$row['phone'];
      }
    }
  }

  // =========================
  // Load order items
  // =========================
  $items = [];
  $stmt = $conn->prepare(
    'SELECT product_name, quantity, unit_price_cents, product_type
     FROM order_items
     WHERE order_id = ?
     ORDER BY id ASC'
  );
  if ($stmt) {
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
      while ($row = $res->fetch_assoc()) $items[] = $row;
    }
    $stmt->close();
  }

  $total = (int)$order['total_cents'];
  $pickup = '';
  if (!empty($order['start_time']) && !empty($order['end_time'])) {
    $pickup = substr((string)$order['start_time'], 0, 5) . ' – ' . substr((string)$order['end_time'], 0, 5);
  }

  [$status_label, $status_cls] = student_order_detail_status_badge((string)$order['status']);
  $created_at_fmt = date('M j, Y \\a\\t h:i A', strtotime((string)$order['created_at']));

  // Timeline (Figma-like)
  $status = strtolower((string)$order['status']);
  $created_ts = strtotime((string)$order['created_at']);
  $payment_status = strtolower((string)($order['payment_status'] ?? 'unpaid'));
  $within_5m = ($created_ts > 0 && (time() - $created_ts) <= 300);
  $can_cancel = ($status === 'pending' && $within_5m && !in_array($payment_status, ['refund_requested', 'refunded'], true));
  $use_bank_cancel_form = $can_cancel && (
    $payment_status === 'paid'
    || (((string)($order['payment_method'] ?? '')) === 'online' && $payment_status === 'unpaid')
  );
  $tl_placed = true;
  $tl_preparing = in_array($status, ['preparing','ready','picked_up'], true);
  $tl_ready = in_array($status, ['ready','picked_up'], true);
  $tl_completed = ($status === 'picked_up');
  if (isset($get['refund_requested']) && $get['refund_requested'] === '1') {
    $flash_success = 'Refund request submitted. Waiting for admin confirmation.';
  }

  return compact(
    'qb_modal', 'order_id', 'order', 'flash_error', 'flash_success', 'student_name', 'student_email',
    'student_id', 'student_phone', 'has_user_phone', 'items', 'total', 'pickup', 'status_label', 'status_cls',
    'created_at_fmt', 'status', 'created_ts', 'payment_status', 'within_5m', 'can_cancel', 'use_bank_cancel_form',
    'tl_placed', 'tl_preparing', 'tl_ready', 'tl_completed'
  );
}
