<?php
/**
 * Flash / inline alert — dùng chung auth form và student flow.
 *
 * @param string $type success | error | warning | info
 * @param string $skin student → `.qb-flash`; auth → `.alert` (auth.css)
 */
declare(strict_types=1);

function render_alert(string $message, string $type = 'info', string $skin = 'student'): void {
  $message = trim($message);
  if ($message === '') {
    return;
  }

  $type = strtolower($type);
  if ($skin === 'auth') {
    $cls = $type === 'success' ? 'alert success' : ($type === 'error' ? 'alert error' : 'alert');
    $role = $type === 'success' ? 'status' : 'alert';
    echo '<div class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '" role="' . htmlspecialchars($role, ENT_QUOTES, 'UTF-8') . '">';
    echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo '</div>';
    return;
  }

  $flashClass = match ($type) {
    'success' => 'success',
    'error' => 'error',
    'warning' => 'warn',
    default => 'muted',
  };
  $role = $type === 'success' ? 'status' : 'alert';
  echo '<div class="qb-flash ' . htmlspecialchars($flashClass, ENT_QUOTES, 'UTF-8') . '" role="' . htmlspecialchars($role, ENT_QUOTES, 'UTF-8') . '">';
  echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
  echo '</div>';
}
