<?php
/**
 * Kết nối MySQL.
 */
date_default_timezone_set('Asia/Ho_Chi_Minh');

$conn = mysqli_connect('localhost', 'root', '', 'QuickBite');
if (!$conn) {
  die('Connection failed: ' . mysqli_connect_error());
}
mysqli_set_charset($conn, 'utf8mb4');
