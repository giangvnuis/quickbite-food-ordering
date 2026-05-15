<?php
declare(strict_types=1);

require_once __DIR__ . '/../student_context.php';
require_once __DIR__ . '/../Services/slot_service.php';

$ctx = student_ctx();
$conn = $ctx['conn'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $slot_id = (int) ($_POST['time_slot_id'] ?? 0);
  $return_to = trim((string) ($_POST['return_to'] ?? 'home'));
  $modal = isset($_POST['modal']) && (string) $_POST['modal'] === '1';
  header('Location: ' . apply_slot_selection($conn, $slot_id, $return_to, $modal));
  exit;
}

extract(get_select_slot_display_data($conn, $_GET), EXTR_SKIP);
require_once __DIR__ . '/../Views/select-slot.php';
