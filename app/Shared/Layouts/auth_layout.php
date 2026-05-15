<?php
/**
 * Wrapper trang đăng nhập / đăng ký / quên mật khẩu — panel marketing trái + card phải.
 * View auth gọi tuần tự `render_*` rồi `require` partial form.
 */
declare(strict_types=1);

function render_auth_layout_head(string $documentTitle): void {
  $v = (string)@filemtime(dirname(__DIR__, 3) . '/public/assets/css/auth.css');
  ?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>QuickBite • <?php echo htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(site_url('assets/css/auth.css'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo urlencode($v); ?>" />
  </head>
  <body>
  <?php
}

/** Overlay khi session bị kết thúc do tài khoản suspended (`locked=1`). */
function render_auth_locked_overlay(string $lockedNotice): void {
  if (trim($lockedNotice) === '') {
    return;
  }
  ?>
    <div class="auth-locked-overlay" id="authLockedOverlay" role="alertdialog" aria-modal="true" aria-labelledby="authLockedTitle" aria-describedby="authLockedDesc">
      <div class="auth-locked-card">
        <h3 id="authLockedTitle" class="auth-locked-title">Account suspended</h3>
        <p id="authLockedDesc" class="auth-locked-text"><?php echo htmlspecialchars($lockedNotice, ENT_QUOTES, 'UTF-8'); ?></p>
        <button type="button" class="btn-primary auth-locked-btn" id="authLockedDismiss">OK</button>
      </div>
    </div>
  <?php
}

function render_auth_page_shell_open(): void {
  ?>
    <div class="auth-page">
      <div class="auth-shell">
  <?php
}

function render_auth_left_panel(): void {
  ?>
        <aside class="auth-left" aria-hidden="true">
          <div>
            <div class="brand">
              <div class="brand-logo" title="QuickBite">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M7 2v7M10 2v7M7 9c0 2 1.5 3 3 3V2" stroke="#2563eb" stroke-width="2" stroke-linecap="round"/>
                  <path d="M14 2v20M18 2v8c0 2-4 2-4 0V2" stroke="#2563eb" stroke-width="2" stroke-linecap="round"/>
                </svg>
              </div>
              <div class="brand-title">
                <strong>QuickBite</strong>
                <span>IS-VNU Campus</span>
              </div>
            </div>

            <div class="hero">
              <h1>Smart Food<br/>Ordering for<br/>Students</h1>
              <p>Order your meals in 30-60 seconds</p>
            </div>
          </div>

          <div class="feature-list">
            <div class="feature">
              <div class="feature-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M12 8v5l3 2" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M21 12a9 9 0 1 1-9-9 9 9 0 0 1 9 9Z" stroke="#2563eb" stroke-width="2"/>
                </svg>
              </div>
              <div>
                <h3>Time Slot Booking</h3>
                <p>Reserve your pickup time and skip the queue</p>
              </div>
            </div>
            <div class="feature">
              <div class="feature-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M13 2L3 14h7l-1 8 12-14h-7l-1-6Z" stroke="#2563eb" stroke-width="2" stroke-linejoin="round"/>
                </svg>
              </div>
              <div>
                <h3>Lightning Fast</h3>
                <p>Order in under a minute during busy break times</p>
              </div>
            </div>
            <div class="feature">
              <div class="feature-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M12 1 3 5v6c0 5.25 3.5 9.75 9 12 5.5-2.25 9-6.75 9-12V5l-9-4Z" stroke="#2563eb" stroke-width="2"/>
                  <path d="M9 12l2 2 4-4" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
              <div>
                <h3>Secure Payment</h3>
                <p>Pay online or at counter with full refund support</p>
              </div>
            </div>
          </div>

          <div class="stats">
            <div class="stat">
              <strong>500+</strong>
              <span>Active Students</span>
            </div>
            <div class="stat">
              <strong>2.5k+</strong>
              <span>Orders Daily</span>
            </div>
          </div>
        </aside>
  <?php
}

/** Mở `<main>` + card + `auth-surface` (form partial nằm trong surface). */
function render_auth_form_column_open(): void {
  ?>
        <main class="auth-right">
          <div class="auth-card">
            <div class="auth-surface">
  <?php
}

function render_auth_segment_nav(bool $isLogin, bool $isRegister): void {
  ?>
              <nav class="segmented" aria-label="Auth tabs">
                <a class="<?php echo $isLogin ? 'active' : ''; ?>" href="login.php?mode=login">Login</a>
                <a class="<?php echo $isRegister ? 'active' : ''; ?>" href="login.php?mode=register">Register</a>
              </nav>
  <?php
}

function render_auth_form_heading(string $title, string $subtitle): void {
  ?>
              <div class="auth-header">
                <h2><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h2>
                <p><?php echo htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8'); ?></p>
              </div>
  <?php
}

/** Gọi sau partial form — đóng `auth-surface`. */
function render_auth_surface_close(): void {
  ?>
            </div>
  <?php
}

/** Footer bản quyền + đóng card, main, shell trang. */
function render_auth_card_and_shell_close(): void {
  ?>
            <div class="auth-footer">
              © 2026 QuickBite - IS-VNU. All rights reserved.
            </div>
          </div>
        </main>
      </div>
    </div>
  <?php
}

function render_auth_layout_scripts(): void {
  ?>
    <script>
      (function () {
        var ov = document.getElementById('authLockedOverlay');
        var btn = document.getElementById('authLockedDismiss');
        if (ov && btn) {
          btn.addEventListener('click', function () { ov.remove(); });
        }
      })();
      document.querySelectorAll('[data-toggle="password"]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const wrap = btn.closest('.input');
          const input = wrap ? wrap.querySelector('input[type="password"], input[type="text"]') : null;
          if (!input) return;
          const isPassword = input.type === 'password';
          input.type = isPassword ? 'text' : 'password';
          btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
      });
    </script>
  </body>
</html>
  <?php
}
