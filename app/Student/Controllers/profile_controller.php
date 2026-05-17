<?php
declare(strict_types=1);

require_once __DIR__ . '/../student_context.php';
require_once __DIR__ . '/../Services/profile_service.php';
$ctx = student_ctx();
$conn = $ctx['conn'];
$user = $ctx['user'];

$data = get_profile_display_data($conn, $user, $_GET);
$data['flash_ok'] = '';
$data['flash_err'] = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $which = (string) ($_POST['which'] ?? 'profile');
  $result = $which === 'password'
    ? change_password($conn, $user, $_POST)
    : update_profile($conn, $user, $_POST);
  $data['flash_ok'] = $result['flash_ok'];
  $data['flash_err'] = $result['flash_err'];
}

extract($data, EXTR_SKIP);
require_once __DIR__ . '/../Views/profile.php';
