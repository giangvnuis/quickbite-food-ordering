<?php
/**
 * QuickBite — Thao tác giỏ hàng (student).
 *
 * Tại sao dùng transaction? Tránh race condition khi 2 tab cùng add / cùng chỉnh số lượng —
 * khóa dòng giỏ & sản phẩm trong transaction giữ rule prepared/instant và tồn kho nhất quán.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../Helpers/load.php';
require_once __DIR__ . '/../../Helpers/order_slot_rules.php';
require_once __DIR__ . '/../../Shared/Components/flow_modal.php';

/** Redirect trong luồng giỏ (giữ modal query khi cần). */
function student_cart_redirect(string $msg = '', string $type = 'success', bool $modal = false): void {
  $parts = [];
  if ($msg !== '') {
    $parts['msg'] = $msg;
    $parts['type'] = $type;
  }
  header('Location: ' . student_url('cart', $parts, $modal));
  exit;
}

function cart_get_active_cart_id(mysqli $conn, int $user_id): int {
  $cart_id = 0;
  $stmt = $conn->prepare("SELECT id FROM cart WHERE user_id = ? AND status = 'active' LIMIT 1");
  if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $cart = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($cart) {
      $cart_id = (int)$cart['id'];
    }
  }
  return $cart_id;
}

function cart_normalize_item_image_for_display(string $image_path): string {
  $img = $image_path;
  if ($img !== '' && !preg_match('/^https?:\/\//i', $img)) {
    $img = ltrim($img, '/');
  }
  return $img;
}

/**
 * Thêm 1 phần sản phẩm vào giỏ active (merge dòng, validate prepared/instant).
 *
 * @throws Exception khi không hợp lệ hoặc lỗi DB
 */
function add_item_to_cart(mysqli $conn, int $user_id, int $product_id): string {
  if ($product_id <= 0) {
    throw new Exception('Invalid product.');
  }

  $has_product_type = table_has_column($conn, 'products', 'product_type');
  $has_stock_qty = table_has_column($conn, 'products', 'stock_qty');

  $conn->begin_transaction();

  try {
    $cart_id = 0;

    $stmt = $conn->prepare("SELECT id FROM cart WHERE user_id = ? AND status = 'active' LIMIT 1 FOR UPDATE");
    if (!$stmt) {
      throw new Exception('DB error (cart).');
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $cart = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if ($cart) {
      $cart_id = (int)$cart['id'];
    } else {
      $stmt = $conn->prepare("INSERT INTO cart (user_id, status) VALUES (?, 'active')");
      if (!$stmt) {
        throw new Exception('DB error (cart create).');
      }
      $stmt->bind_param('i', $user_id);
      if (!$stmt->execute()) {
        $stmt->close();
        $stmt = $conn->prepare("SELECT id FROM cart WHERE user_id = ? AND status = 'active' LIMIT 1 FOR UPDATE");
        if (!$stmt) {
          throw new Exception('DB error (cart retry).');
        }
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $cart = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$cart) {
          throw new Exception('Could not create cart.');
        }
        $cart_id = (int)$cart['id'];
      } else {
        $cart_id = (int)$stmt->insert_id;
        $stmt->close();
      }
    }

    $prod_cols = 'id, price_cents, name';
    if ($has_product_type) {
      $prod_cols .= ', product_type';
    }
    if ($has_stock_qty) {
      $prod_cols .= ', stock_qty';
    }
    $stmt = $conn->prepare("SELECT $prod_cols FROM products WHERE id = ? AND is_active = 1 LIMIT 1 FOR UPDATE");
    if (!$stmt) {
      throw new Exception('DB error (product).');
    }
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $product = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$product) {
      throw new Exception('This product is not available.');
    }

    $unit_price_cents = (int)$product['price_cents'];
    $product_name = (string)$product['name'];
    $product_type = $has_product_type ? (string)($product['product_type'] ?? 'prepared') : 'prepared';
    $stock_qty = $has_stock_qty ? (int)($product['stock_qty'] ?? 0) : null;

    $lineSql = 'SELECT p.id AS product_id, ci.quantity';
    if ($has_product_type) {
      $lineSql .= ', p.product_type';
    }
    $lineSql .= ' FROM cart_items ci INNER JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = ?';
    $stmt = $conn->prepare($lineSql);
    if (!$stmt) {
      throw new Exception('DB error (cart lines).');
    }
    $stmt->bind_param('i', $cart_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $simLines = [];
    if ($res) {
      while ($row = $res->fetch_assoc()) {
        $simLines[] = [
          'product_id' => (int)$row['product_id'],
          'quantity' => (int)$row['quantity'],
          'type' => $has_product_type
            ? qb_normalize_product_line_type((string)($row['product_type'] ?? 'prepared'))
            : 'prepared',
        ];
      }
    }
    $stmt->close();

    $foundLine = false;
    foreach ($simLines as &$r) {
      if ((int)$r['product_id'] === $product_id) {
        $r['quantity']++;
        $foundLine = true;
        break;
      }
    }
    unset($r);
    if (!$foundLine) {
      $simLines[] = [
        'product_id' => $product_id,
        'quantity' => 1,
        'type' => qb_normalize_product_line_type($product_type),
      ];
    }
    $prepRulesErr = qb_prepared_order_rules_error($simLines);
    if ($prepRulesErr !== null) {
      throw new Exception($prepRulesErr);
    }

    $stmt = $conn->prepare('SELECT quantity FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1 FOR UPDATE');
    if (!$stmt) {
      throw new Exception('DB error (cart item).');
    }
    $stmt->bind_param('ii', $cart_id, $product_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $existing = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if ($product_type === 'instant' && $stock_qty !== null) {
      if ($stock_qty <= 0) {
        throw new Exception('This item is out of stock.');
      }
      $in_cart = $existing ? (int)$existing['quantity'] : 0;
      if ($in_cart + 1 > $stock_qty) {
        throw new Exception(
          $stock_qty <= 1
            ? 'Only ' . $stock_qty . ' left in stock.'
            : 'Only ' . $stock_qty . ' in stock — your cart already has ' . $in_cart . '.'
        );
      }
    }

    if ($existing) {
      $stmt = $conn->prepare('UPDATE cart_items SET quantity = quantity + 1 WHERE cart_id = ? AND product_id = ?');
      if (!$stmt) {
        throw new Exception('DB error (cart item update).');
      }
      $stmt->bind_param('ii', $cart_id, $product_id);
      if (!$stmt->execute()) {
        throw new Exception('Could not update cart.');
      }
      $stmt->close();
    } else {
      $qty = 1;
      $stmt = $conn->prepare('INSERT INTO cart_items (cart_id, product_id, quantity, unit_price_cents) VALUES (?, ?, ?, ?)');
      if (!$stmt) {
        throw new Exception('DB error (cart item insert).');
      }
      $stmt->bind_param('iiii', $cart_id, $product_id, $qty, $unit_price_cents);
      if (!$stmt->execute()) {
        throw new Exception('Could not add to cart.');
      }
      $stmt->close();
    }

    $conn->commit();
    return $product_name;
  } catch (Throwable $e) {
    $conn->rollback();
    throw $e;
  }
}

/**
 * Xóa hẳn một dòng món khỏi giỏ (không phải giảm từng bước).
 *
 * @throws Exception
 */
function remove_item_from_cart(mysqli $conn, int $user_id, int $product_id): void {
  if ($product_id <= 0) {
    throw new Exception('Invalid item.');
  }
  $cart_id = cart_get_active_cart_id($conn, $user_id);
  if ($cart_id <= 0) {
    throw new Exception('Your cart is empty.');
  }

  $conn->begin_transaction();
  try {
    $stmt = $conn->prepare('SELECT 1 FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1 FOR UPDATE');
    if (!$stmt) {
      throw new Exception('DB error.');
    }
    $stmt->bind_param('ii', $cart_id, $product_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res && $res->fetch_assoc();
    $stmt->close();
    if (!$ok) {
      throw new Exception('Item not found.');
    }

    $stmt = $conn->prepare('DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?');
    if (!$stmt) {
      throw new Exception('DB error.');
    }
    $stmt->bind_param('ii', $cart_id, $product_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
  } catch (Throwable $e) {
    $conn->rollback();
    throw $e;
  }
}

/**
 * @throws Exception
 */
function increment_cart_item_quantity(mysqli $conn, int $user_id, int $product_id): void {
  if ($product_id <= 0) {
    throw new Exception('Invalid item.');
  }
  $has_product_type = table_has_column($conn, 'products', 'product_type');

  $cart_id = cart_get_active_cart_id($conn, $user_id);
  if ($cart_id <= 0) {
    throw new Exception('Your cart is empty.');
  }

  $conn->begin_transaction();
  try {
    $sqlAll = 'SELECT p.id AS product_id, ci.quantity';
    if ($has_product_type) {
      $sqlAll .= ', p.product_type';
    }
    $sqlAll .= ' FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = ? FOR UPDATE';
    $stmt = $conn->prepare($sqlAll);
    if (!$stmt) {
      throw new Exception('DB error.');
    }
    $stmt->bind_param('i', $cart_id);
    $stmt->execute();
    $resAll = $stmt->get_result();
    $simLines = [];
    if ($resAll) {
      while ($row = $resAll->fetch_assoc()) {
        $simLines[] = [
          'product_id' => (int)$row['product_id'],
          'quantity' => (int)$row['quantity'],
          'type' => $has_product_type
            ? qb_normalize_product_line_type((string)($row['product_type'] ?? 'prepared'))
            : 'prepared',
        ];
      }
    }
    $stmt->close();

    $foundInc = false;
    foreach ($simLines as &$r) {
      if ((int)$r['product_id'] === $product_id) {
        $r['quantity']++;
        $foundInc = true;
        break;
      }
    }
    unset($r);
    if (!$foundInc) {
      throw new Exception('Item not found.');
    }
    $prepErr = qb_prepared_order_rules_error($simLines);
    if ($prepErr !== null) {
      throw new Exception($prepErr);
    }

    $instantStockLines = [];
    foreach ($simLines as $r) {
      if (($r['type'] ?? 'prepared') === 'instant') {
        $instantStockLines[] = [
          'product_id' => (int)$r['product_id'],
          'quantity' => (int)$r['quantity'],
        ];
      }
    }
    $stockErr = qb_instant_stock_validate_available($conn, $instantStockLines);
    if ($stockErr !== null) {
      throw new Exception($stockErr);
    }

    $stmt = $conn->prepare('UPDATE cart_items SET quantity = quantity + 1 WHERE cart_id = ? AND product_id = ?');
    if (!$stmt) {
      throw new Exception('DB error.');
    }
    $stmt->bind_param('ii', $cart_id, $product_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
  } catch (Throwable $e) {
    $conn->rollback();
    throw $e;
  }
}

/**
 * Giảm 1; nếu còn 0 thì xóa dòng.
 *
 * @throws Exception
 */
function decrement_cart_item_quantity(mysqli $conn, int $user_id, int $product_id): void {
  if ($product_id <= 0) {
    throw new Exception('Invalid item.');
  }

  $cart_id = cart_get_active_cart_id($conn, $user_id);
  if ($cart_id <= 0) {
    throw new Exception('Your cart is empty.');
  }

  $conn->begin_transaction();
  try {
    $stmt = $conn->prepare('SELECT quantity FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1 FOR UPDATE');
    if (!$stmt) {
      throw new Exception('DB error.');
    }
    $stmt->bind_param('ii', $cart_id, $product_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
      throw new Exception('Item not found.');
    }
    $qty = (int)$row['quantity'];
    if ($qty <= 1) {
      $stmt = $conn->prepare('DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?');
      if (!$stmt) {
        throw new Exception('DB error.');
      }
      $stmt->bind_param('ii', $cart_id, $product_id);
      $stmt->execute();
      $stmt->close();
    } else {
      $stmt = $conn->prepare('UPDATE cart_items SET quantity = quantity - 1 WHERE cart_id = ? AND product_id = ?');
      if (!$stmt) {
        throw new Exception('DB error.');
      }
      $stmt->bind_param('ii', $cart_id, $product_id);
      $stmt->execute();
      $stmt->close();
    }

    $conn->commit();
  } catch (Throwable $e) {
    $conn->rollback();
    throw $e;
  }
}

/**
 * @throws Exception
 */
function clear_cart(mysqli $conn, int $user_id): void {
  $cart_id = cart_get_active_cart_id($conn, $user_id);
  if ($cart_id <= 0) {
    throw new Exception('Your cart is empty.');
  }

  $conn->begin_transaction();
  try {
    $stmt = $conn->prepare('DELETE FROM cart_items WHERE cart_id = ?');
    if (!$stmt) {
      throw new Exception('DB error.');
    }
    $stmt->bind_param('i', $cart_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
  } catch (Throwable $e) {
    $conn->rollback();
    throw $e;
  }
}

/**
 * Dữ liệu hiển thị trang giỏ (GET): không xử lý POST — controller gọi các hàm add/remove/update riêng.
 *
 * @param array<string,mixed> $get
 * @return array<string,mixed>
 */
function get_cart_display_data(mysqli $conn, int $user_id, array $get): array {
  $qb_modal = flow_modal_request();
  $has_product_type = table_has_column($conn, 'products', 'product_type');

  $cart_id = cart_get_active_cart_id($conn, $user_id);

  $selected_slot_id = isset($_SESSION['time_slot_id']) ? (int)$_SESSION['time_slot_id'] : 0;
  $slot_label = 'Select pickup time';
  $slot_cap = 0;
  $slot_used = 0;
  $slot_status = 'Available';
  $slot_status_class = 'ok';
  $slot_start_time = '';

  if ($selected_slot_id > 0) {
    $stmt = $conn->prepare(qb_sql_single_time_slot_prepared_usage());
    if ($stmt) {
      $stmt->bind_param('i', $selected_slot_id);
      $stmt->execute();
      $res = $stmt->get_result();
      $slot = $res ? $res->fetch_assoc() : null;
      $stmt->close();
      if ($slot) {
        $slot_start_time = (string)$slot['start_time'];
        $slot_label = substr($slot_start_time, 0, 5) . ' – ' . substr((string)$slot['end_time'], 0, 5);
        $slot_cap = (int)$slot['prepared_capacity'];
        $slot_used = (int)$slot['prepared_used'];

        if ($slot_cap > 0 && $slot_used >= $slot_cap) {
          $slot_status = 'Full';
          $slot_status_class = 'bad';
        } elseif ($slot_cap > 0 && $slot_used >= (int)ceil($slot_cap * 0.75)) {
          $slot_status = 'Nearly Full';
          $slot_status_class = 'warn';
        } else {
          $slot_status = 'Available';
          $slot_status_class = 'ok';
        }
      }
    }
  }

  $slot_order_deadline_passed = false;

  $items = [];
  $total_cents = 0;

  if ($cart_id > 0) {
    $sql = 'SELECT p.id AS product_id, p.name, p.image_path, ci.quantity, ci.unit_price_cents';
    if ($has_product_type) {
      $sql .= ', p.product_type';
    }
    $sql .= ' FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = ? ORDER BY p.name ASC';

    $stmt = $conn->prepare($sql);
    if ($stmt) {
      $stmt->bind_param('i', $cart_id);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($res) {
        while ($row = $res->fetch_assoc()) {
          $qty = (int)$row['quantity'];
          $unit = (int)$row['unit_price_cents'];
          $row['line_total_cents'] = $qty * $unit;
          $total_cents += (int)$row['line_total_cents'];
          $row['type'] = $has_product_type ? (string)($row['product_type'] ?? 'prepared') : 'prepared';
          $row['img_src'] = cart_normalize_item_image_for_display((string)($row['image_path'] ?? ''));
          $items[] = $row;
        }
      }
      $stmt->close();
    }
  }

  $prepared_items = [];
  $instant_items = [];
  foreach ($items as $it) {
    $t = (string)($it['type'] ?? 'prepared');
    if ($t === 'instant') {
      $instant_items[] = $it;
    } else {
      $prepared_items[] = $it;
    }
  }

  $cart_groups = [];
  if (!empty($prepared_items)) {
    $cart_groups[] = [
      'tag' => 'Prepared',
      'tag_class' => 'prepared',
      'count' => count($prepared_items),
      'items' => $prepared_items,
    ];
  }
  if (!empty($instant_items)) {
    $cart_groups[] = [
      'tag' => 'Instant',
      'tag_class' => 'instant',
      'count' => count($instant_items),
      'items' => $instant_items,
    ];
  }

  $cart_rules_err = !empty($items) ? qb_prepared_order_rules_error($items) : null;
  $cart_prepared_qty = !empty($items) ? qb_prepared_quantity_sum($items) : 0;
  $cart_checkout_block_slot = !empty($items)
    && $slot_cap > 0
    && $cart_prepared_qty > 0
    && ($slot_used + 1) > $slot_cap;
  $cart_checkout_block_no_slot = !empty($items) && $cart_prepared_qty > 0 && $selected_slot_id <= 0;
  $cart_checkout_block_slot_deadline = false;
  $cart_checkout_disabled = !empty($items)
    && ($cart_rules_err !== null || $cart_checkout_block_slot || $cart_checkout_block_no_slot || $cart_checkout_block_slot_deadline);

  $cart_checkout_hint = '';
  if (!empty($items)) {
    if ($cart_rules_err !== null) {
      $cart_checkout_hint = $cart_rules_err;
    } elseif ($cart_prepared_qty > 0 && $selected_slot_id <= 0) {
      $cart_checkout_hint = 'Select a pickup time before checkout.';
    } elseif ($cart_checkout_block_slot) {
      $cart_checkout_hint = 'This slot is full for prepared orders (max orders reached). Pick another time, or use only instant items.';
    }
  }

  $flash_msg = isset($get['msg']) ? trim((string)$get['msg']) : '';
  $flash_type = isset($get['type']) ? trim((string)$get['type']) : '';
  if ($flash_type !== 'success') {
    $flash_type = 'error';
  }

  return compact(
    'qb_modal',
    'has_product_type',
    'cart_id',
    'selected_slot_id',
    'slot_label',
    'slot_cap',
    'slot_used',
    'slot_status',
    'slot_status_class',
    'slot_start_time',
    'slot_order_deadline_passed',
    'items',
    'cart_groups',
    'total_cents',
    'cart_rules_err',
    'cart_prepared_qty',
    'cart_checkout_block_slot',
    'cart_checkout_block_no_slot',
    'cart_checkout_block_slot_deadline',
    'cart_checkout_disabled',
    'cart_checkout_hint',
    'flash_msg',
    'flash_type'
  );
}
