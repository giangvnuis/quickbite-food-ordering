<?php
/**
 * QuickBite Admin — Menu: CRUD sản phẩm, lọc, thống kê (controller gọi + guard ở admin).
 */
declare(strict_types=1);

require_once __DIR__ . '/../../Helpers/load.php';

function admin_menu_slug_from_name(string $name): string {
  $s = strtolower(trim($name));
  if ($s === '') {
    return '';
  }
  $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
  if (is_string($converted) && $converted !== '') {
    $s = strtolower($converted);
  }
  $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
  $s = trim($s, '-');
  return $s !== '' ? $s : 'item-' . time();
}

/**
 * URL admin menu giữ filter hiện tại; $extra merge (vd type/category null để xóa lọc).
 *
 * @param array<string, mixed> $extra
 */
function admin_menu_link(string $q, string $type_filter, string $category_filter, array $extra = []): string {
  $params = [
    'q' => $q !== '' ? $q : null,
    'type' => $type_filter !== 'all' ? $type_filter : null,
    'category' => $category_filter !== 'all' ? $category_filter : null,
  ];
  foreach ($extra as $k => $v) {
    $params[$k] = $v;
  }
  $filtered = array_filter($params, static fn ($v) => $v !== null && $v !== '');
  return admin_url('menu', $filtered);
}

/**
 * Danh sách món (JOIN category) theo tìm kiếm + lọc type/category.
 *
 * @return list<array<string,mixed>>
 */
function get_all_menu_items(
  mysqli $conn,
  string $q,
  string $type_filter,
  string $category_filter,
  bool $has_stock_qty
): array {
  $q_like = $q !== '' ? '%' . $q . '%' : null;
  $products = [];
  $sql = 'SELECT p.id, p.name, p.slug, p.price_cents, p.product_type, p.is_active, c.name AS category_name';
  if ($has_stock_qty) {
    $sql .= ', p.stock_qty';
  }
  $sql .= '
        FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE 1=1';
  $types = '';
  $params = [];
  if ($q_like !== null) {
    $sql .= ' AND (p.name LIKE ? OR p.slug LIKE ?)';
    $types .= 'ss';
    $params[] = $q_like;
    $params[] = $q_like;
  }
  if ($type_filter !== 'all') {
    $sql .= ' AND p.product_type = ?';
    $types .= 's';
    $params[] = $type_filter;
  }
  if ($category_filter !== 'all') {
    $sql .= ' AND p.category_id = ?';
    $types .= 'i';
    $params[] = (int) $category_filter;
  }
  $sql .= ' ORDER BY p.id DESC';
  if ($types === '') {
    $res = $conn->query($sql);
    if ($res) {
      while ($r = $res->fetch_assoc()) {
        $products[] = $r;
      }
    }
  } else {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
      $stmt->bind_param($types, ...$params);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($res) {
        while ($r = $res->fetch_assoc()) {
          $products[] = $r;
        }
      }
      $stmt->close();
    }
  }
  return $products;
}

/**
 * @param array<string,mixed> $post
 * @return array{name:string,slug:string,price:int,type:string,desc:string,img:string,cat_id:int,stock_qty:int}|null
 */
function menu_parse_item_payload(array $post, bool $has_stock_qty): ?array {
  $name = trim((string) ($post['name'] ?? ''));
  $slug = admin_menu_slug_from_name($name);
  $price = (int) ($post['price_cents'] ?? 0);
  $type = trim((string) ($post['product_type'] ?? 'prepared'));
  $desc = trim((string) ($post['description'] ?? ''));
  $img = trim((string) ($post['image_path'] ?? ''));
  $cat_id = (int) ($post['category_id'] ?? 0);
  if ($has_stock_qty) {
    $stock_qty = $type === 'prepared' ? 0 : max(0, (int) ($post['stock_qty'] ?? 0));
  } else {
    $stock_qty = 0;
  }

  if ($name === '' || $slug === '' || $img === '' || $price <= 0 || !in_array($type, ['prepared', 'instant'], true) || $cat_id <= 0) {
    return null;
  }

  return [
    'name' => $name,
    'slug' => $slug,
    'price' => $price,
    'type' => $type,
    'desc' => $desc,
    'img' => $img,
    'cat_id' => $cat_id,
    'stock_qty' => $stock_qty,
  ];
}

function menu_item_name_exists(mysqli $conn, string $name, int $exclude_id): bool {
  $dup_sql = 'SELECT id FROM products WHERE LOWER(name) = LOWER(?)';
  if ($exclude_id > 0) {
    $dup_sql .= ' AND id <> ?';
  }
  $dup_sql .= ' LIMIT 1';
  $dup_stmt = $conn->prepare($dup_sql);
  if (!$dup_stmt) {
    return false;
  }
  if ($exclude_id > 0) {
    $dup_stmt->bind_param('si', $name, $exclude_id);
  } else {
    $dup_stmt->bind_param('s', $name);
  }
  $dup_stmt->execute();
  $dup_res = $dup_stmt->get_result();
  $dup = $dup_res instanceof mysqli_result && $dup_res->num_rows > 0;
  $dup_stmt->close();
  return $dup;
}

function menu_item_slug_exists(mysqli $conn, string $slug, int $exclude_id): bool {
  $dup_sql = 'SELECT id FROM products WHERE LOWER(slug) = LOWER(?)';
  if ($exclude_id > 0) {
    $dup_sql .= ' AND id <> ?';
  }
  $dup_sql .= ' LIMIT 1';
  $dup_stmt = $conn->prepare($dup_sql);
  if (!$dup_stmt) {
    return false;
  }
  if ($exclude_id > 0) {
    $dup_stmt->bind_param('si', $slug, $exclude_id);
  } else {
    $dup_stmt->bind_param('s', $slug);
  }
  $dup_stmt->execute();
  $dup_res = $dup_stmt->get_result();
  $dup = $dup_res instanceof mysqli_result && $dup_res->num_rows > 0;
  $dup_stmt->close();
  return $dup;
}

/**
 * Thêm món — validate trùng tên + INSERT.
 *
 * @param array<string,mixed> $post
 */
function create_menu_item(mysqli $conn, array $post, bool $has_stock_qty): bool {
  $payload = menu_parse_item_payload($post, $has_stock_qty);
  if ($payload === null) {
    $_SESSION['qb_admin_menu_flash'] = 'Please fill all required fields.';
    $_SESSION['qb_admin_menu_flash_type'] = 'error';
    return false;
  }

  if (menu_item_name_exists($conn, $payload['name'], 0)) {
    $_SESSION['qb_admin_menu_flash'] = 'Item name already exists. Please choose a different name.';
    $_SESSION['qb_admin_menu_flash_type'] = 'error';
    return false;
  }
  if (menu_item_slug_exists($conn, $payload['slug'], 0)) {
    $_SESSION['qb_admin_menu_flash'] = 'Item slug already exists. Please choose a different name.';
    $_SESSION['qb_admin_menu_flash_type'] = 'error';
    return false;
  }

  $name = $payload['name'];
  $slug = $payload['slug'];
  $price = $payload['price'];
  $type = $payload['type'];
  $desc = $payload['desc'];
  $img = $payload['img'];
  $cat_id = $payload['cat_id'];
  $stock_qty = $payload['stock_qty'];

  $ok = false;
  if ($has_stock_qty) {
    $stmt = $conn->prepare(
      'INSERT INTO products (category_id, name, slug, description, image_path, price_cents, stock_qty, product_type, is_active)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
    );
    if ($stmt) {
      $stmt->bind_param('issssiis', $cat_id, $name, $slug, $desc, $img, $price, $stock_qty, $type);
      $ok = $stmt->execute();
      $stmt->close();
    }
  } else {
    $stmt = $conn->prepare(
      'INSERT INTO products (category_id, name, slug, description, image_path, price_cents, product_type, is_active)
       VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
    );
    if ($stmt) {
      $stmt->bind_param('issssis', $cat_id, $name, $slug, $desc, $img, $price, $type);
      $ok = $stmt->execute();
      $stmt->close();
    }
  }

  $_SESSION['qb_admin_menu_flash'] = $ok ? 'Item created.' : 'Could not create item.';
  $_SESSION['qb_admin_menu_flash_type'] = $ok ? 'success' : 'error';
  return $ok;
}

/**
 * Sửa món — validate + trùng tên (trừ id hiện tại) + UPDATE.
 *
 * @param array<string,mixed> $post
 */
function update_menu_item(mysqli $conn, array $post, bool $has_stock_qty): bool {
  $pid = (int) ($post['product_id'] ?? 0);
  if ($pid <= 0) {
    $_SESSION['qb_admin_menu_flash'] = 'Invalid item.';
    $_SESSION['qb_admin_menu_flash_type'] = 'error';
    return false;
  }

  $payload = menu_parse_item_payload($post, $has_stock_qty);
  if ($payload === null) {
    $_SESSION['qb_admin_menu_flash'] = 'Please fill all required fields.';
    $_SESSION['qb_admin_menu_flash_type'] = 'error';
    return false;
  }

  if (menu_item_name_exists($conn, $payload['name'], $pid)) {
    $_SESSION['qb_admin_menu_flash'] = 'Item name already exists. Please choose a different name.';
    $_SESSION['qb_admin_menu_flash_type'] = 'error';
    return false;
  }
  if (menu_item_slug_exists($conn, $payload['slug'], $pid)) {
    $_SESSION['qb_admin_menu_flash'] = 'Item slug already exists. Please choose a different name.';
    $_SESSION['qb_admin_menu_flash_type'] = 'error';
    return false;
  }

  $name = $payload['name'];
  $slug = $payload['slug'];
  $price = $payload['price'];
  $type = $payload['type'];
  $desc = $payload['desc'];
  $img = $payload['img'];
  $cat_id = $payload['cat_id'];
  $stock_qty = $payload['stock_qty'];

  $ok = false;
  if ($has_stock_qty) {
    $stmt = $conn->prepare(
      'UPDATE products
       SET category_id = ?, name = ?, slug = ?, description = ?, image_path = ?, price_cents = ?, stock_qty = ?, product_type = ?
       WHERE id = ? LIMIT 1'
    );
    if ($stmt) {
      $stmt->bind_param('issssiisi', $cat_id, $name, $slug, $desc, $img, $price, $stock_qty, $type, $pid);
      $ok = $stmt->execute();
      $stmt->close();
    }
  } else {
    $stmt = $conn->prepare(
      'UPDATE products
       SET category_id = ?, name = ?, slug = ?, description = ?, image_path = ?, price_cents = ?, product_type = ?
       WHERE id = ? LIMIT 1'
    );
    if ($stmt) {
      $stmt->bind_param('issssisi', $cat_id, $name, $slug, $desc, $img, $price, $type, $pid);
      $ok = $stmt->execute();
      $stmt->close();
    }
  }

  $_SESSION['qb_admin_menu_flash'] = $ok ? 'Item updated.' : 'Could not update item.';
  $_SESSION['qb_admin_menu_flash_type'] = $ok ? 'success' : 'error';
  return $ok;
}

/** Bật/tắt hiển thị (`is_active`). */
function toggle_item_availability(mysqli $conn, int $product_id): bool {
  $stmt = $conn->prepare('UPDATE products SET is_active = (1 - is_active) WHERE id = ? LIMIT 1');
  if (!$stmt) {
    return false;
  }
  $stmt->bind_param('i', $product_id);
  $ok = $stmt->execute();
  $stmt->close();
  $_SESSION['qb_admin_menu_flash'] = $ok ? 'Item status updated.' : 'Could not update item.';
  $_SESSION['qb_admin_menu_flash_type'] = $ok ? 'success' : 'error';
  return $ok;
}

/**
 * Xử lý POST CRUD. Trả về URL redirect hoặc null.
 *
 * @param array<string, mixed> $post
 * @param array<string, mixed> $get
 */
function admin_menu_process_post(mysqli $conn, array $post, array $get, bool $has_stock_qty): ?string {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($post['action'])) {
    return null;
  }

  $q = trim((string) ($get['q'] ?? ''));
  $type_filter = trim((string) ($get['type'] ?? 'all'));
  if (!in_array($type_filter, ['all', 'prepared', 'instant'], true)) {
    $type_filter = 'all';
  }
  $category_filter = trim((string) ($get['category'] ?? 'all'));
  if ($category_filter !== 'all' && !ctype_digit($category_filter)) {
    $category_filter = 'all';
  }

  $action = (string) $post['action'];
  $redirect = admin_menu_link($q, $type_filter, $category_filter);

  if ($action === 'toggle' && isset($post['product_id'])) {
    toggle_item_availability($conn, (int) $post['product_id']);
    return $redirect;
  }

  if ($action === 'create') {
    if (!create_menu_item($conn, $post, $has_stock_qty)) {
      return admin_menu_link($q, $type_filter, $category_filter, ['show_form' => '1']);
    }
    return $redirect;
  }

  if ($action === 'update') {
    $pid = (int) ($post['product_id'] ?? 0);
    if (!update_menu_item($conn, $post, $has_stock_qty)) {
      $extra = ['show_form' => '1'];
      if ($pid > 0) {
        $extra['edit'] = (string) $pid;
      }
      return admin_menu_link($q, $type_filter, $category_filter, $extra);
    }
    return $redirect;
  }

  return null;
}

/**
 * Dữ liệu trang menu: categories, filter state, form edit, danh sách món, stats.
 *
 * @param array<string, mixed> $get
 * @return array<string, mixed>
 */
function get_menu_page_data(mysqli $conn, array $get, bool $has_stock_qty): array {
  $q = trim((string) ($get['q'] ?? ''));
  $type_filter = trim((string) ($get['type'] ?? 'all'));
  if (!in_array($type_filter, ['all', 'prepared', 'instant'], true)) {
    $type_filter = 'all';
  }
  $category_filter = trim((string) ($get['category'] ?? 'all'));
  if ($category_filter !== 'all' && !ctype_digit($category_filter)) {
    $category_filter = 'all';
  }
  $edit_id = (int) ($get['edit'] ?? 0);
  $show_form = isset($get['show_form']) || $edit_id > 0;

  $flash = '';
  $flash_type = 'success';
  if (!empty($_SESSION['qb_admin_menu_flash'])) {
    $flash = (string) $_SESSION['qb_admin_menu_flash'];
    $flash_type = (string) ($_SESSION['qb_admin_menu_flash_type'] ?? 'success');
    unset($_SESSION['qb_admin_menu_flash'], $_SESSION['qb_admin_menu_flash_type']);
  }

  $categories = [];
  $res = $conn->query('SELECT id, name FROM categories ORDER BY name ASC');
  if ($res) {
    while ($r = $res->fetch_assoc()) {
      $categories[] = $r;
    }
  }

  $category_ids = array_map(static fn ($c) => (int) $c['id'], $categories);
  if ($category_filter !== 'all' && !in_array((int) $category_filter, $category_ids, true)) {
    $category_filter = 'all';
  }
  $default_category_id = $category_ids !== [] ? (int) $category_ids[0] : 0;

  $form_item = [
    'id' => 0,
    'name' => '',
    'slug' => '',
    'price_cents' => '',
    'product_type' => 'prepared',
    'description' => '',
    'image_path' => '',
    'category_id' => $default_category_id > 0 ? $default_category_id : '',
    'stock_qty' => 0,
  ];

  if ($edit_id > 0) {
    $edit_cols = 'id, category_id, name, slug, description, image_path, price_cents, product_type';
    if ($has_stock_qty) {
      $edit_cols .= ', stock_qty';
    }
    $stmt = $conn->prepare('SELECT ' . $edit_cols . ' FROM products WHERE id = ? LIMIT 1');
    if ($stmt) {
      $stmt->bind_param('i', $edit_id);
      $stmt->execute();
      $resEdit = $stmt->get_result();
      $rowEdit = $resEdit ? $resEdit->fetch_assoc() : null;
      $stmt->close();
      if ($rowEdit) {
        $form_item = $rowEdit;
        $show_form = true;
      } else {
        $edit_id = 0;
      }
    }
  }

  $products = get_all_menu_items($conn, $q, $type_filter, $category_filter, $has_stock_qty);

  $stat_total = 0;
  $stat_prepared = 0;
  $stat_instant = 0;
  $resStats = $conn->query(
    'SELECT COUNT(*) AS total_items,
            SUM(CASE WHEN product_type = "prepared" THEN 1 ELSE 0 END) AS prepared_items,
            SUM(CASE WHEN product_type = "instant" THEN 1 ELSE 0 END) AS instant_items
     FROM products'
  );
  if ($resStats && ($rs = $resStats->fetch_assoc())) {
    $stat_total = (int) ($rs['total_items'] ?? 0);
    $stat_prepared = (int) ($rs['prepared_items'] ?? 0);
    $stat_instant = (int) ($rs['instant_items'] ?? 0);
  }

  $page_date = date('l, F j, Y');

  return [
    'has_stock_qty' => $has_stock_qty,
    'q' => $q,
    'type_filter' => $type_filter,
    'category_filter' => $category_filter,
    'edit_id' => $edit_id,
    'show_form' => $show_form,
    'flash' => $flash,
    'flash_type' => $flash_type,
    'categories' => $categories,
    'form_item' => $form_item,
    'products' => $products,
    'stat_total' => $stat_total,
    'stat_prepared' => $stat_prepared,
    'stat_instant' => $stat_instant,
    'page_date' => $page_date,
  ];
}

/** @deprecated Dùng get_menu_page_data() */
function admin_menu_page_data(mysqli $conn, array $get, bool $has_stock_qty): array {
  return get_menu_page_data($conn, $get, $has_stock_qty);
}
