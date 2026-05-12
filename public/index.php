<?php
/**
 * Entry root — redirect về trang đăng nhập (session đã có thì login.php chuyển tiếp student/admin).
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/Helpers/url_helper.php';

header('Location: ' . site_url('login.php'));
exit;
