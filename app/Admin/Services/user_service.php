<?php
/**
 * QuickBite Admin — User management: list + filter, toggle active, lịch sử đơn theo user.
 *
 * Khóa tài khoản chỉ đặt is_active = 0 (không DELETE): giữ khóa ngoại tới orders và toàn bộ lịch sử đơn hàng.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../Helpers/load.php';

/** Root admin — không được khóa (giữ đường vào hệ thống). */
const ADMIN_ROOT_USER_ID = 1;

/**
 * @param array<string, mixed> $extra
 */
function admin_users_link(string $q, string $role_filter, string $status_filter, string $sort, array $extra = []): string {
  $params = [
    'q' => $q !== '' ? $q : null,
    'role' => $role_filter !== 'all' ? $role_filter : null,
    'status' => $status_filter !== 'all' ? $status_filter : null,
    'sort' => $sort !== 'name' ? $sort : null,
  ];
  foreach ($extra as $k => $v) {
    $params[$k] = $v;
  }
  $filtered = array_filter($params, static fn ($v) => $v !== null && $v !== '');
  return admin_url('users', $filtered);
}

/**
 * Chuẩn hóa filter từ query string (tìm kiếm, role, status, sort).
 *
 * @param array<string, mixed> $get
 * @return array{q:string,q_like:?string,role_filter:string,status_filter:string,sort:string}
 */
function admin_users_parse_list_filters(array $get): array {
  $q = trim((string) ($get['q'] ?? ''));
  $q_like = $q !== '' ? '%' . $q . '%' : null;
  $role_filter = trim((string) ($get['role'] ?? 'all'));
  if (!in_array($role_filter, ['all', 'student', 'admin'], true)) {
    $role_filter = 'all';
  }
  $status_filter = trim((string) ($get['status'] ?? 'all'));
  if (!in_array($status_filter, ['all', 'active', 'locked'], true)) {
    $status_filter = 'all';
  }
  $sort = trim((string) ($get['sort'] ?? 'name'));
  if (!in_array($sort, ['name', 'most_orders', 'most_cancelled', 'highest_cancel_rate'], true)) {
    $sort = 'name';
  }

  return [
    'q' => $q,
    'q_like' => $q_like,
    'role_filter' => $role_filter,
    'status_filter' => $status_filter,
    'sort' => $sort,
  ];
}

/**
 * Thống kê nhanh cho card filter (tổng / student / admin / active / locked).
 *
 * @return array{total:int,students:int,admins:int,active:int,locked:int}
 */
function get_users_dashboard_stats(mysqli $conn): array {
  $stats = ['total' => 0, 'students' => 0, 'admins' => 0, 'active' => 0, 'locked' => 0];
  $res = $conn->query(
    "SELECT
      COUNT(*) AS total,
      SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) AS students,
      SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS admins,
      SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_count,
      SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS locked_count
   FROM users"
  );
  if ($res) {
    $row = $res->fetch_assoc();
    if ($row) {
      $stats['total'] = (int) $row['total'];
      $stats['students'] = (int) $row['students'];
      $stats['admins'] = (int) $row['admins'];
      $stats['active'] = (int) $row['active_count'];
      $stats['locked'] = (int) $row['locked_count'];
    }
  }
  return $stats;
}

/**
 * Danh sách user (student + admin) với tìm kiếm, lọc role/status, sort — kèm aggregate đơn.
 *
 * @param array{q:string,q_like:?string,role_filter:string,status_filter:string,sort:string} $filters
 * @return list<array<string,mixed>>
 */
function get_all_users_with_filter(mysqli $conn, array $filters, bool $qb_um_has_phone): array {
  $q_like = $filters['q_like'];
  $role_filter = $filters['role_filter'];
  $status_filter = $filters['status_filter'];
  $sort = $filters['sort'];

  $sql = "SELECT
          u.id,
          u.full_name,
          u.student_id,
          u.email";
  if ($qb_um_has_phone) {
    $sql .= ",
          u.phone";
  }
  $sql .= ",
          u.role,
          u.is_active,
          u.created_at,
          COALESCE(o.total_orders, 0) AS total_orders,
          COALESCE(o.cancelled_orders, 0) AS cancelled_orders
        FROM users u
        LEFT JOIN (
          SELECT
            user_id,
            COUNT(*) AS total_orders,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders
          FROM orders
          GROUP BY user_id
        ) o ON o.user_id = u.id
        WHERE 1=1";
  $types = '';
  $params = [];
  if ($q_like !== null) {
    $sql .= " AND (u.full_name LIKE ? OR u.student_id LIKE ? OR u.email LIKE ?";
    $types .= 'sss';
    $like = (string) $q_like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    if ($qb_um_has_phone) {
      $sql .= " OR u.phone LIKE ?";
      $types .= 's';
      $params[] = $like;
    }
    $sql .= ')';
  }
  if ($role_filter !== 'all') {
    $sql .= ' AND u.role = ?';
    $types .= 's';
    $params[] = $role_filter;
  }
  if ($status_filter !== 'all') {
    $sql .= $status_filter === 'active' ? ' AND u.is_active = 1' : ' AND u.is_active = 0';
  }

  $sql .= match ($sort) {
    'most_orders' => ' ORDER BY total_orders DESC, u.created_at DESC',
    'most_cancelled' => ' ORDER BY cancelled_orders DESC, u.created_at DESC',
    'highest_cancel_rate' => ' ORDER BY (COALESCE(o.cancelled_orders,0) / NULLIF(COALESCE(o.total_orders,0),0)) DESC, u.created_at DESC',
    default => ' ORDER BY u.full_name ASC',
  };

  $users = [];
  if ($types === '') {
    $res = $conn->query($sql);
    if ($res) {
      while ($r = $res->fetch_assoc()) {
        $users[] = $r;
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
          $users[] = $r;
        }
      }
      $stmt->close();
    }
  }

  return $users;
}

/**
 * Đảo is_active (không xóa user — chỉ khóa/mở đăng nhập; đơn hàng vẫn gắn user_id).
 *
 * @param array<string, mixed> $admin_user
 */
function toggle_user_active_status(mysqli $conn, int $user_id, array $admin_user): void {
  if ($user_id <= 0) {
    $_SESSION['qb_admin_users_flash'] = 'User not found.';
    $_SESSION['qb_admin_users_flash_type'] = 'error';
    return;
  }

  $row = null;
  $st = $conn->prepare('SELECT is_active FROM users WHERE id = ? LIMIT 1');
  if ($st) {
    $st->bind_param('i', $user_id);
    $st->execute();
    $rr = $st->get_result();
    $row = $rr ? $rr->fetch_assoc() : null;
    $st->close();
  }
  if (!$row) {
    $_SESSION['qb_admin_users_flash'] = 'User not found.';
    $_SESSION['qb_admin_users_flash_type'] = 'error';
    return;
  }

  $was_active = (int) $row['is_active'] === 1;
  $admin_self_id = (int) ($admin_user['id'] ?? 0);
  if ($was_active && $user_id === ADMIN_ROOT_USER_ID) {
    $_SESSION['qb_admin_users_flash'] = 'The root administrator account cannot be locked.';
    $_SESSION['qb_admin_users_flash_type'] = 'error';
    return;
  }
  if ($was_active && $user_id === $admin_self_id) {
    $_SESSION['qb_admin_users_flash'] = 'You cannot lock your own account.';
    $_SESSION['qb_admin_users_flash_type'] = 'error';
    return;
  }

  $stmt = $conn->prepare('UPDATE users SET is_active = (1 - is_active) WHERE id = ? LIMIT 1');
  if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $ok = $stmt->execute();
    $stmt->close();
    $_SESSION['qb_admin_users_flash'] = $ok ? 'User status updated.' : 'Could not update user.';
    $_SESSION['qb_admin_users_flash_type'] = $ok ? 'success' : 'error';
  }
}

/**
 * Lịch sử đơn của một user (SV hoặc admin có đơn).
 *
 * @return list<array<string,mixed>>
 */
function get_user_order_history(mysqli $conn, int $user_id, int $limit = 100): array {
  if ($user_id <= 0) {
    return [];
  }
  $limit = max(1, min(500, $limit));
  $sql = 'SELECT id, order_code, status, payment_status, total_cents, created_at
          FROM orders
          WHERE user_id = ?
          ORDER BY created_at DESC
          LIMIT ' . $limit;
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return [];
  }
  $stmt->bind_param('i', $user_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = [];
  if ($res) {
    while ($r = $res->fetch_assoc()) {
      $rows[] = $r;
    }
  }
  $stmt->close();
  return $rows;
}

/**
 * POST toggle user active — trả về URL redirect hoặc null.
 *
 * @param array<string, mixed> $post
 * @param array<string, mixed> $admin_user
 */
function admin_users_process_post(mysqli $conn, array $post, array $admin_user): ?string {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($post['user_id'])) {
    return null;
  }

  $uid = (int) $post['user_id'];
  toggle_user_active_status($conn, $uid, $admin_user);

  $target = trim((string) ($post['return_url'] ?? ''));
  return $target !== '' ? $target : admin_url('users');
}

/**
 * @param array<string, mixed> $get
 * @return array<string, mixed>
 */
function admin_users_page_data(mysqli $conn, array $get, bool $qb_um_has_phone): array {
  $filters = admin_users_parse_list_filters($get);

  $flash = '';
  $flash_type = 'success';
  if (!empty($_SESSION['qb_admin_users_flash'])) {
    $flash = (string) $_SESSION['qb_admin_users_flash'];
    $flash_type = (string) ($_SESSION['qb_admin_users_flash_type'] ?? 'success');
    unset($_SESSION['qb_admin_users_flash'], $_SESSION['qb_admin_users_flash_type']);
  }

  $stats = get_users_dashboard_stats($conn);
  $users = get_all_users_with_filter($conn, $filters, $qb_um_has_phone);

  $history_user_id = (int) ($get['history'] ?? 0);
  $order_history_rows = [];
  $history_user_label = '';
  if ($history_user_id > 0) {
    $order_history_rows = get_user_order_history($conn, $history_user_id);
    $st = $conn->prepare('SELECT full_name, student_id FROM users WHERE id = ? LIMIT 1');
    if ($st) {
      $st->bind_param('i', $history_user_id);
      $st->execute();
      $hr = $st->get_result();
      $urow = $hr ? $hr->fetch_assoc() : null;
      $st->close();
      if ($urow) {
        $history_user_label = trim((string) ($urow['full_name'] ?? ''))
          . ($urow['student_id'] !== null && (string) $urow['student_id'] !== ''
            ? ' · ' . (string) $urow['student_id']
            : '');
      }
    }
  }

  $page_date = date('l, F j, Y');

  $link_extra = $history_user_id > 0 ? ['history' => (string) $history_user_id] : [];

  return [
    'qb_um_has_phone' => $qb_um_has_phone,
    'q' => $filters['q'],
    'role_filter' => $filters['role_filter'],
    'status_filter' => $filters['status_filter'],
    'sort' => $filters['sort'],
    'flash' => $flash,
    'flash_type' => $flash_type,
    'stats' => $stats,
    'users' => $users,
    'page_date' => $page_date,
    'history_user_id' => $history_user_id,
    'order_history_rows' => $order_history_rows,
    'history_user_label' => $history_user_label,
    'users_link_extra' => $link_extra,
  ];
}
