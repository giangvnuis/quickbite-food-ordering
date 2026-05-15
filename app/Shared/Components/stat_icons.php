<?php
/**
 * QuickBite Admin — SVG icon cho thẻ thống kê dashboard (stroke + tone trong admin.css).
 *
 * Số tiền KPI (doanh thu) trong DB là integer cents — view format qua format_vnd(int),
 * không nhét float vào icon; giữ đồng nhất với dashboard_service (SUM total_cents).
 */
declare(strict_types=1);

/**
 * SVG icon cho thẻ thống kê (stroke currentColor). Màu nền/viền: class .qb-stat-icon--{tone} trong admin.css.
 *
 * @param 'user'|'shield'|'user_check'|'user_x'|'box'|'dollar'|'bag'|'clock'|'check'|'trending'|'list' $kind
 * @param 'blue'|'purple'|'green'|'red'|'orange' $tone
 */
function qb_admin_stat_icon(string $kind, string $tone): string {
  $allowedTone = ['blue', 'purple', 'green', 'red', 'orange'];
  if (!in_array($tone, $allowedTone, true)) {
    $tone = 'blue';
  }
  $s = static function (string $body): string {
    return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
  };
  $inner = match ($kind) {
    'user' => $s('<circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.5 3.5-6 7-6s7 2.5 7 6"/>'),
    'shield' => $s('<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'),
    'user_check' => $s('<circle cx="9" cy="7" r="3.5"/><path d="M4 19c0-2.8 3.2-5 8-5 .9 0 1.8.1 2.6.2"/><path d="M17 11l2 2 4-4"/>'),
    'user_x' => $s('<circle cx="9" cy="7" r="3.5"/><path d="M4 19c0-2.8 3.2-5 8-5 .9 0 1.8.1 2.6.2"/><path d="M17 9l4 4M21 9l-4 4"/>'),
    'box' => $s('<path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05"/><path d="M12 22.08V12"/>'),
    'dollar' => $s('<path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>'),
    'bag' => $s('<path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 0-8 0"/>'),
    'clock' => $s('<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>'),
    'check' => $s('<path d="M22 12a10 10 0 11-20 0 10 10 0 0120 0z"/><path d="M8 12l3 3 5-6"/>'),
    'trending' => $s('<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>'),
    'list' => $s('<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>'),
    default => $s('<circle cx="12" cy="12" r="10"/>'),
  };
  $tc = htmlspecialchars($tone, ENT_QUOTES, 'UTF-8');
  return '<span class="qb-stat-icon qb-stat-icon--' . $tc . '" aria-hidden="true">' . $inner . '</span>';
}
