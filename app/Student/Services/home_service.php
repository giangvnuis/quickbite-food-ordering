<?php
/**
 * QuickBite Student — Dữ liệu trang Home (menu, slot, giỏ).
 *
 * Tại sao query slot và menu riêng?
 * — Menu đọc bảng `products` (+ category/search/sort); slot đọc `time_slots` + subquery đếm đơn prepared.
 *   Ghép JOIN một query sẽ nhân bản dòng món theo slot / làm plan khó tối ưu; tách hai truy vấn đơn giản,
 *   cache-friendly và tránh JOIN đa-bảng phức tạp không cần cho danh sách món.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../Helpers/load.php';
require_once __DIR__ . '/../../Helpers/order_slot_rules.php';

/** @return array{has_category_id:bool,has_product_type:bool,has_stock_qty:bool,has_description:bool} */
function home_detect_product_schema(mysqli $conn): array {
  return [
    'has_category_id' => table_has_column($conn, 'products', 'category_id'),
    'has_product_type' => table_has_column($conn, 'products', 'product_type'),
    'has_stock_qty' => table_has_column($conn, 'products', 'stock_qty'),
    'has_description' => table_has_column($conn, 'products', 'description'),
  ];
}

/** @return array<int, array{id:int,name:string,active:bool,href:string}> */
function home_build_category_tabs(bool $has_category_id, mysqli $conn, string $sort): array {
  $tabs = [['id' => 0, 'name' => 'All', 'active' => true, 'href' => student_url('home', ['sort' => $sort])]];
  if (!$has_category_id) {
    return $tabs;
  }
  $stmt = $conn->prepare('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC');
  if (!$stmt) {
    return $tabs;
  }
  $stmt->execute();
  $res = $stmt->get_result();
  $cid = 0;
  $items = [];
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $items[] = $row;
    }
  }
  $stmt->close();
  foreach ($items as $row) {
    $cid = (int)$row['id'];
    $tabs[] = [
      'id' => $cid,
      'name' => (string)$row['name'],
      'active' => false,
      'href' => student_url('home', ['cat_id' => (string)$cid, 'sort' => $sort]),
    ];
  }
  return $tabs;
}

function home_sort_order_sql(string $sort): string {
  $allowed_sorts = [
    'newest' => 'id DESC',
    'price_asc' => 'price_cents ASC, id DESC',
    'price_desc' => 'price_cents DESC, id DESC',
    'name_asc' => 'name ASC, id DESC',
  ];
  return $allowed_sorts[$sort] ?? $allowed_sorts['newest'];
}

/**
 * Danh sách món cho grid Home (chỉ đọc `products`, không JOIN slot).
 *
 * @param array<string,bool> $schema từ home_detect_product_schema()
 * @param array{q:string,sort:string,active_cat_id:int} $filters
 * @return list<array<string,mixed>>
 */
function get_menu_items_for_display(mysqli $conn, array $schema, array $filters): array {
  $q = $filters['q'];
  $sort = $filters['sort'];
  $active_cat_id = $filters['active_cat_id'];
  $has_category_id = $schema['has_category_id'];

  $order_by = home_sort_order_sql($sort);
  $sql = 'SELECT id, name, image_path, price_cents';
  if ($schema['has_product_type']) {
    $sql .= ', product_type';
  }
  if ($schema['has_stock_qty']) {
    $sql .= ', stock_qty';
  }
  if ($schema['has_description']) {
    $sql .= ', description';
  }
  $sql .= ' FROM products WHERE is_active = 1';
  $types = '';
  $params = [];
  if ($has_category_id && $active_cat_id > 0) {
    $sql .= ' AND category_id = ?';
    $types .= 'i';
    $params[] = $active_cat_id;
  }
  if ($q !== '') {
    $sql .= ' AND name LIKE ?';
    $types .= 's';
    $params[] = '%' . $q . '%';
  }
  $sql .= " ORDER BY $order_by";

  $products = [];
  $stmt = $conn->prepare($sql);
  if ($stmt) {
    if ($types !== '') {
      $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
      while ($row = $res->fetch_assoc()) {
        $products[] = $row;
      }
    }
    $stmt->close();
  }
  return $products;
}

/**
 * Slot khả dụng (truy vấn `time_slots` + usage prepared — không gắn menu).
 *
 * @return list<array<string,mixed>>
 */
function get_available_slots(mysqli $conn): array {
  $time_slots = [];
  $stmt = $conn->prepare(qb_sql_time_slots_with_prepared_slot_usage());
  if (!$stmt) {
    $stmt = $conn->prepare('SELECT id, start_time, end_time, 0 AS prepared_capacity, 0 AS prepared_used FROM time_slots WHERE is_active = 1 ORDER BY start_time ASC');
  }
  if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
      while ($row = $res->fetch_assoc()) {
        $time_slots[] = $row;
      }
    }
    $stmt->close();
  }
  return $time_slots;
}

/** Nhãn độ đầy slot trên trang chủ (khác checkout: chữ "Nearly full"). */
function student_home_slot_status(int $used, int $cap): array {
  if ($cap <= 0) {
    return ['Available', 'ok'];
  }
  if ($used >= $cap) {
    return ['Full', 'bad'];
  }
  if ($used >= (int)ceil($cap * 0.75)) {
    return ['Nearly full', 'warn'];
  }
  return ['Available', 'ok'];
}

/**
 * Gắn cờ active cho tab category theo `active_cat_id`.
 *
 * @param array<int, array{id:int,name:string,active:bool,href:string}> $tabs
 */
function home_mark_category_tabs_active(array $tabs, int $active_cat_id): array {
  foreach ($tabs as $i => $t) {
    $tabs[$i]['active'] = $active_cat_id === (int)$t['id'];
  }
  return $tabs;
}

/**
 * Chuẩn hóa session slot + nhãn hiển thị (logic tách khỏi view).
 *
 * @return array{
 *   selected_slot_id:int,
 *   selected_slot_label:string,
 *   selected_slot_sub:string,
 *   selected_slot_sub_class:string,
 *   selected_slot_used:int,
 *   selected_slot_cap:int
 * }
 */
function home_resolve_selected_slot_display(bool $is_admin_view, array $time_slots): array {
  $selected_slot_id = isset($_SESSION['time_slot_id']) ? (int)$_SESSION['time_slot_id'] : 0;
  $selected_slot_label = 'Select time';
  $selected_slot_sub = 'Available';
  $selected_slot_sub_class = 'ok';
  $selected_slot_used = 0;
  $selected_slot_cap = 0;

  if (!$is_admin_view && $selected_slot_id > 0) {
    $session_slot = null;
    foreach ($time_slots as $ts) {
      if ((int)$ts['id'] === $selected_slot_id) {
        $session_slot = $ts;
        break;
      }
    }
    if ($session_slot === null || !qb_slot_orders_still_open_for_start_today((string)$session_slot['start_time'])) {
      unset($_SESSION['time_slot_id']);
      $selected_slot_id = 0;
    }
  }

  foreach ($time_slots as $ts) {
    if ($selected_slot_id > 0 && (int)$ts['id'] === $selected_slot_id) {
      $selected_slot_label = substr((string)$ts['start_time'], 0, 5) . ' – ' . substr((string)$ts['end_time'], 0, 5);
      $selected_slot_used = (int)($ts['prepared_used'] ?? 0);
      $selected_slot_cap = (int)($ts['prepared_capacity'] ?? 0);
      [$selected_slot_sub, $selected_slot_sub_class] = student_home_slot_status($selected_slot_used, $selected_slot_cap);
      break;
    }
  }

  if ($selected_slot_id <= 0 && $time_slots !== []) {
    if ($is_admin_view) {
      $first = $time_slots[0];
      $selected_slot_id = (int)$first['id'];
      $_SESSION['time_slot_id'] = $selected_slot_id;
      $selected_slot_label = substr((string)$first['start_time'], 0, 5) . ' – ' . substr((string)$first['end_time'], 0, 5);
      $selected_slot_used = (int)($first['prepared_used'] ?? 0);
      $selected_slot_cap = (int)($first['prepared_capacity'] ?? 0);
      [$selected_slot_sub, $selected_slot_sub_class] = student_home_slot_status($selected_slot_used, $selected_slot_cap);
    } else {
      foreach ($time_slots as $first) {
        if (!qb_slot_orders_still_open_for_start_today((string)$first['start_time'])) {
          continue;
        }
        $selected_slot_id = (int)$first['id'];
        $_SESSION['time_slot_id'] = $selected_slot_id;
        $selected_slot_label = substr((string)$first['start_time'], 0, 5) . ' – ' . substr((string)$first['end_time'], 0, 5);
        $selected_slot_used = (int)($first['prepared_used'] ?? 0);
        $selected_slot_cap = (int)($first['prepared_capacity'] ?? 0);
        [$selected_slot_sub, $selected_slot_sub_class] = student_home_slot_status($selected_slot_used, $selected_slot_cap);
        break;
      }
    }
  }

  return [
    'selected_slot_id' => $selected_slot_id,
    'selected_slot_label' => $selected_slot_label,
    'selected_slot_sub' => $selected_slot_sub,
    'selected_slot_sub_class' => $selected_slot_sub_class,
    'selected_slot_used' => $selected_slot_used,
    'selected_slot_cap' => $selected_slot_cap,
  ];
}

/** Chuẩn bị dòng hiển thị cho modal chọn slot (toàn bộ tính toán trạng thái ở đây). */
function home_build_slot_modal_rows(bool $is_admin_view, array $time_slots, int $selected_slot_id): array {
  $rows = [];
  foreach ($time_slots as $s) {
    $sid = (int)$s['id'];
    $slot_label = substr((string)$s['start_time'], 0, 5) . ' – ' . substr((string)$s['end_time'], 0, 5);
    $cap_m = (int)($s['prepared_capacity'] ?? 0);
    $used_m = (int)($s['prepared_used'] ?? 0);
    [$status_m, $cls_m] = student_home_slot_status($used_m, $cap_m);
    $pct_m = ($cap_m > 0) ? max(0, min(100, (int)round(($used_m / $cap_m) * 100))) : 0;
    $active_m = ($sid === $selected_slot_id);
    $slot_orders_open_m = true;
    $slot_btn_disabled_m = false;
    if (!$is_admin_view) {
      $slot_orders_open_m = qb_slot_orders_still_open_for_start_today((string)$s['start_time']);
      if (!$slot_orders_open_m) {
        $status_m = 'Ordering closed';
        $cls_m = 'bad';
      }
      $slot_btn_disabled_m = ($cap_m > 0 && $used_m >= $cap_m) || !$slot_orders_open_m;
    }
    $rows[] = [
      'sid' => $sid,
      'slot_label' => $slot_label,
      'cap_m' => $cap_m,
      'used_m' => $used_m,
      'status_m' => $status_m,
      'cls_m' => $cls_m,
      'pct_m' => $pct_m,
      'active_m' => $active_m,
      'slot_orders_open_m' => $slot_orders_open_m,
      'slot_btn_disabled_m' => $slot_btn_disabled_m,
    ];
  }
  return $rows;
}

/**
 * Chuẩn bị dòng thẻ món (instant vs prepared / stock / ảnh) — view chỉ echo.
 *
 * @param list<array<string,mixed>> $products
 * @return list<array<string,mixed>>
 */
function home_build_product_card_rows(array $products, bool $has_product_type, bool $has_stock_qty, bool $has_description): array {
  $rows = [];
  foreach ($products as $p) {
    $name = (string)($p['name'] ?? '');
    $price_cents = (int)($p['price_cents'] ?? 0);
    $is_instant = $has_product_type && (strtolower(trim((string)($p['product_type'] ?? ''))) === 'instant');
    $stock_val = $has_stock_qty ? (int)($p['stock_qty'] ?? 0) : null;
    $show_instant_stock = $is_instant && $has_stock_qty;
    $out_of_instant = $show_instant_stock && $stock_val !== null && $stock_val <= 0;
    $image_path = (string)($p['image_path'] ?? '');
    $img_src = $image_path;
    if ($img_src !== '' && !preg_match('/^https?:\/\//i', $img_src)) {
      $img_src = ltrim($img_src, '/');
    }
    $desc = $has_description ? trim((string)($p['description'] ?? '')) : '';
    $rows[] = [
      'id' => (int)($p['id'] ?? 0),
      'name' => $name,
      'price_cents' => $price_cents,
      'is_instant' => $is_instant,
      'type_class' => $is_instant ? 'instant' : 'prepared',
      'type_label' => $is_instant ? 'Instant' : 'Prepared',
      'show_type_badge' => $has_product_type,
      'stock_val' => $stock_val,
      'show_instant_stock' => $show_instant_stock,
      'out_of_instant' => $out_of_instant,
      'img_src' => $img_src,
      'has_image' => $img_src !== '',
      'description' => $desc,
      'has_description' => $desc !== '',
    ];
  }
  return $rows;
}

/** Viết tắt tên user cho avatar (logic tách khỏi view). */
function student_home_user_initials(string $full_name): string {
  $initials = '';
  $parts = preg_split('/\s+/', trim($full_name));
  if (is_array($parts) && count($parts) > 0) {
    $initials = mb_strtoupper(mb_substr($parts[0], 0, 1));
    if (count($parts) > 1) {
      $initials .= mb_strtoupper(mb_substr($parts[count($parts) - 1], 0, 1));
    }
  }
  return $initials !== '' ? $initials : 'U';
}

/**
 * Dữ liệu cho modal chi tiết món (từng món một dialog — chỉ HTML trong partial food-detail).
 *
 * @param list<array<string,mixed>> $card_rows from home_build_product_card_rows
 */
function home_build_food_detail_modals(array $card_rows): array {
  return $card_rows;
}

/**
 * Sort options cho `<select>` — flags selected trong service.
 *
 * @return list<array{value:string,label:string,selected:bool}>
 */
function home_build_sort_options(string $current_sort): array {
  $opts = [
    ['value' => 'newest', 'label' => 'Newest'],
    ['value' => 'price_asc', 'label' => 'Price: Low → High'],
    ['value' => 'price_desc', 'label' => 'Price: High → Low'],
    ['value' => 'name_asc', 'label' => 'Name: A → Z'],
  ];
  foreach ($opts as $i => $o) {
    $opts[$i]['selected'] = ($current_sort === $o['value']);
  }
  return $opts;
}

/**
 * @param array<string,mixed> $user
 * @param array<string,mixed> $get
 * @return array<string,mixed>
 */
function student_home_page_data(mysqli $conn, array $user, array $get): array {
  $is_admin_view = ((string)($user['role'] ?? 'student') === 'admin');

  $schema = home_detect_product_schema($conn);
  $q = isset($get['q']) ? trim((string)$get['q']) : '';
  $sort = isset($get['sort']) ? (string)$get['sort'] : 'newest';
  $active_cat_id = isset($get['cat_id']) ? (int)$get['cat_id'] : 0;

  $category_tabs = home_mark_category_tabs_active(
    home_build_category_tabs($schema['has_category_id'], $conn, $sort),
    $active_cat_id
  );

  $products = get_menu_items_for_display($conn, $schema, [
    'q' => $q,
    'sort' => $sort,
    'active_cat_id' => $active_cat_id,
  ]);

  $cart_count = qb_cart_badge_count($conn, (int)$user['id']);

  $flash_msg = isset($get['msg']) ? trim((string)$get['msg']) : '';
  $flash_type = isset($get['type']) ? trim((string)$get['type']) : '';
  if ($flash_type !== 'success') {
    $flash_type = 'error';
  }

  $qb_open_flow = '';
  if (!$is_admin_view && isset($get['open_flow'])) {
    $of = strtolower(trim((string)$get['open_flow']));
    if ($of === 'cart') {
      $qb_open_flow = 'cart';
    }
  }

  $time_slots = get_available_slots($conn);
  $slot_pick = home_resolve_selected_slot_display($is_admin_view, $time_slots);

  $product_card_rows = home_build_product_card_rows(
    $products,
    $schema['has_product_type'],
    $schema['has_stock_qty'],
    $schema['has_description']
  );
  $slot_modal_rows = home_build_slot_modal_rows($is_admin_view, $time_slots, $slot_pick['selected_slot_id']);
  $sort_options = home_build_sort_options($sort);
  $food_detail_modals = home_build_food_detail_modals($product_card_rows);
  $user_initials = student_home_user_initials((string)($user['full_name'] ?? ''));

  return [
    'is_admin_view' => $is_admin_view,
    'products' => $products,
    'product_card_rows' => $product_card_rows,
    'has_category_id' => $schema['has_category_id'],
    'has_product_type' => $schema['has_product_type'],
    'has_stock_qty' => $schema['has_stock_qty'],
    'has_description' => $schema['has_description'],
    'q' => $q,
    'sort' => $sort,
    'sort_options' => $sort_options,
    'category_tabs' => $category_tabs,
    'active_cat_id' => $active_cat_id,
    'cart_count' => $cart_count,
    'flash_msg' => $flash_msg,
    'flash_type' => $flash_type,
    'qb_open_flow' => $qb_open_flow,
    'time_slots' => $time_slots,
    'slot_modal_rows' => $slot_modal_rows,
    'food_detail_modals' => $food_detail_modals,
    'user_initials' => $user_initials,
    'selected_slot_id' => $slot_pick['selected_slot_id'],
    'selected_slot_label' => $slot_pick['selected_slot_label'],
    'selected_slot_sub' => $slot_pick['selected_slot_sub'],
    'selected_slot_sub_class' => $slot_pick['selected_slot_sub_class'],
    'selected_slot_used' => $slot_pick['selected_slot_used'],
    'selected_slot_cap' => $slot_pick['selected_slot_cap'],
  ];
}
