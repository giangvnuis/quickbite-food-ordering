<?php
/**
 * QuickBite Student — Trang chủ: chỉ HTML + echo biến (logic/data trong home_service).
 */
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>QuickBite • Home</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(site_url('assets/css/student.css'), ENT_QUOTES, 'UTF-8'); ?>" />
  </head>
  <body>
    <header class="qb-topbar">
      <div class="qb-topbar-inner">
        <a class="qb-brand" href="<?php echo htmlspecialchars(student_url('home'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="QuickBite Home">
          <div class="qb-brand-mark" aria-hidden="true">
            <span>Q</span>
          </div>
          <div class="qb-brand-text">
            <div class="qb-brand-name">QuickBite</div>
            <div class="qb-brand-sub">IS-VNU</div>
          </div>
        </a>

        <form class="qb-search" method="get" action="<?php echo htmlspecialchars(site_url('student.php'), ENT_QUOTES, 'UTF-8'); ?>" role="search" aria-label="Search">
          <input type="hidden" name="page" value="home" />
          <?php if ($active_cat_id > 0): ?>
            <input type="hidden" name="cat_id" value="<?php echo (int)$active_cat_id; ?>" />
          <?php endif; ?>
          <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>" />
          <span class="qb-search-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
              <path d="M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z" stroke="currentColor" stroke-width="2"/>
              <path d="m21 21-4.3-4.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </span>
          <input
            type="text"
            name="q"
            placeholder="Search food..."
            aria-label="Search food"
            value="<?php echo htmlspecialchars($q); ?>"
          />
        </form>

        <div class="qb-actions">
          <div class="qb-slot-wrap">
            <button
              type="button"
              class="qb-slot-btn qb-slot-link<?php echo $is_admin_view ? ' qb-slot-admin-preview' : ''; ?>"
              id="qbSlotOpen"
              aria-haspopup="dialog"
              aria-expanded="false"
              aria-controls="qbSlotModal"
              aria-label="<?php echo $is_admin_view ? 'View pickup slot capacity' : 'Select pickup time slot'; ?>"
            >
              <div class="qb-slot-text">
                <div class="qb-slot-time"><?php echo htmlspecialchars($selected_slot_label); ?></div>
                <div class="qb-slot-sub <?php echo htmlspecialchars($selected_slot_sub_class); ?>">
                  <?php echo $is_admin_view ? 'Tap to preview slots' : htmlspecialchars($selected_slot_sub); ?>
                </div>
              </div>
              <span class="qb-slot-caret" aria-hidden="true">▾</span>
            </button>
          </div>
          <?php if ($is_admin_view): ?>
            <a class="qb-orders-back qb-admin-back-dashboard" href="<?php echo htmlspecialchars(admin_url('dashboard'), ENT_QUOTES, 'UTF-8'); ?>">Back to Dashboard</a>
          <?php endif; ?>

          <?php if (!$is_admin_view): ?>
            <a class="qb-cart-btn" id="qbCartFlowBtn" href="<?php echo htmlspecialchars(student_url('cart', [], true), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Open cart" aria-haspopup="dialog" aria-controls="qbFlowModal" aria-expanded="false">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 6h15l-1.5 9h-12L6 6Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                <path d="M6 6 5 3H2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <path d="M9 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2ZM18 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" stroke="currentColor" stroke-width="2"/>
              </svg>
              <?php if ($cart_count > 0): ?>
                <span class="qb-cart-count" id="qbCartFlowBadge"><?php echo (int)$cart_count; ?></span>
              <?php endif; ?>
            </a>
          <?php endif; ?>
          <div class="qb-user-wrap">
            <button class="qb-user" type="button" id="qbUserBtn" aria-haspopup="menu" aria-expanded="false" title="<?php echo htmlspecialchars((string)$user['full_name']); ?>">
              <?php echo htmlspecialchars($user_initials); ?>
            </button>
            <div class="qb-user-menu" id="qbUserMenu" role="menu" aria-label="User menu">
              <?php if (!$is_admin_view): ?>
                <a role="menuitem" href="<?php echo htmlspecialchars(student_url('profile'), ENT_QUOTES, 'UTF-8'); ?>">My profile</a>
                <a role="menuitem" href="<?php echo htmlspecialchars(student_url('orders'), ENT_QUOTES, 'UTF-8'); ?>">My Orders</a>
              <?php else: ?>
                <a role="menuitem" class="qb-admin-back-dashboard-menu" href="<?php echo htmlspecialchars(admin_url('dashboard'), ENT_QUOTES, 'UTF-8'); ?>">Back to Dashboard</a>
              <?php endif; ?>
              <div class="qb-user-sep" aria-hidden="true"></div>
              <a role="menuitem" class="danger" href="<?php echo htmlspecialchars(site_url('logout.php'), ENT_QUOTES, 'UTF-8'); ?>">Log out</a>
            </div>
          </div>
        </div>
      </div>
    </header>

    <main class="qb-page">
      <?php if ($is_admin_view): ?>
        <section class="qb-banner qb-banner--admin qb-admin-home-banner" role="note" aria-label="Administrator notice">
          <div class="qb-banner-icon" aria-hidden="true">A</div>
          <div class="qb-banner-text">
            <div class="qb-banner-title">Administrator view</div>
            <div class="qb-banner-sub">
              Add to cart, checkout, and student-only pages are disabled for this login.
              Order Management lists <strong class="qb-banner-strong">student</strong> orders for the selected date (default: today). If you don’t see a new order, check filters, try Custom range, or confirm you ordered while logged in as a student.
            </div>
          </div>
        </section>
      <?php endif; ?>
      <section class="qb-hero">
        <h1>Order Your Favorite Food</h1>
        <p>Fast ordering for students during break time at IS-VNU</p>
      </section>

      <section class="qb-grid-wrap">
        <div class="qb-banner" role="note">
          <div class="qb-banner-icon" aria-hidden="true">
            i
          </div>
          <div class="qb-banner-text">
            <div class="qb-banner-title">Slot limit = orders with prepared food</div>
            <div class="qb-banner-sub">
              Each order that has at least one prepared dish uses one slot. Instant-only orders don’t use a slot.
              Current slot:
              <span class="qb-banner-strong">
                <?php echo (int)$selected_slot_used; ?> / <?php echo (int)$selected_slot_cap; ?>
              </span>
              orders with prepared (max capacity).
            </div>
          </div>
        </div>

        <div class="qb-controls">
          <div class="qb-tabs" role="tablist" aria-label="Categories">
            <?php foreach ($category_tabs as $tab): ?>
              <a class="qb-tab <?php echo !empty($tab['active']) ? 'active' : ''; ?>" role="tab"
                 href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars((string)$tab['name']); ?>
              </a>
            <?php endforeach; ?>
          </div>
          <form class="qb-sort" method="get" action="<?php echo htmlspecialchars(site_url('student.php'), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="page" value="home" />
            <?php if ($active_cat_id > 0): ?>
              <input type="hidden" name="cat_id" value="<?php echo (int)$active_cat_id; ?>" />
            <?php endif; ?>
            <label class="qb-sort-label" for="sort">Sort</label>
            <select id="sort" name="sort" onchange="this.form.submit()">
              <?php foreach ($sort_options as $opt): ?>
                <option value="<?php echo htmlspecialchars($opt['value'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo !empty($opt['selected']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>

        <?php if ($flash_msg !== ''): ?>
          <div class="qb-flash <?php echo $flash_type === 'success' ? 'success' : 'error'; ?>" role="status">
            <?php echo htmlspecialchars($flash_msg); ?>
          </div>
        <?php endif; ?>

        <?php if (empty($product_card_rows)): ?>
          <div class="qb-empty">
            <div class="qb-empty-title">No products available</div>
            <div class="qb-empty-sub">Please ask an admin to add items to the menu.</div>
          </div>
        <?php else: ?>
          <div class="qb-grid" aria-label="Products">
            <?php foreach ($product_card_rows as $card): ?>
              <article class="qb-card">
                <div class="qb-card-media">
                  <?php if ($card['show_type_badge']): ?>
                    <span class="qb-card-type <?php echo htmlspecialchars($card['type_class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($card['type_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                  <?php endif; ?>
                  <?php if ($card['has_image']): ?>
                    <img src="<?php echo htmlspecialchars($card['img_src']); ?>" alt="<?php echo htmlspecialchars($card['name']); ?>" loading="lazy" />
                  <?php else: ?>
                    <div class="qb-card-media-fallback" aria-hidden="true"></div>
                  <?php endif; ?>
                </div>
                <div class="qb-card-body">
                  <div class="qb-card-title-row">
                    <div class="qb-card-title"><?php echo htmlspecialchars($card['name']); ?></div>
                    <?php if ($card['show_instant_stock']): ?>
                      <span class="qb-card-stock-inline <?php echo $card['out_of_instant'] ? 'is-out' : ''; ?>" aria-label="<?php echo $card['out_of_instant'] ? 'Out of stock' : 'Stock'; ?>">
                        <?php if ($card['out_of_instant']): ?>
                          Sold out
                        <?php else: ?>
                          <?php echo (int)$card['stock_val']; ?> left
                        <?php endif; ?>
                      </span>
                    <?php endif; ?>
                  </div>
                  <?php if ($card['has_description']): ?>
                    <p class="qb-card-desc"><?php echo htmlspecialchars($card['description']); ?></p>
                  <?php endif; ?>
                  <div class="qb-card-row qb-card-row--actions">
                    <div class="qb-card-price"><?php echo htmlspecialchars(format_vnd((int)$card['price_cents'])); ?></div>
                    <div class="qb-card-actions-btns">
                      <button type="button" class="qb-card-detail-btn" data-food-detail="<?php echo (int)$card['id']; ?>">
                        Details
                      </button>
                      <?php if ($is_admin_view): ?>
                        <button class="qb-add" type="button" disabled title="Admin can only preview home">
                          View only
                        </button>
                      <?php else: ?>
                        <form method="post" action="<?php echo htmlspecialchars(student_url('cart'), ENT_QUOTES, 'UTF-8'); ?>" class="qb-add-form">
                          <input type="hidden" name="product_id" value="<?php echo (int)$card['id']; ?>" />
                          <button class="qb-add" type="submit" <?php echo $card['out_of_instant'] ? 'disabled title="Out of stock"' : ''; ?>>
                            <span aria-hidden="true">
                              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M12 5v14M5 12h14" stroke="white" stroke-width="2" stroke-linecap="round"/>
                              </svg>
                            </span>
                            Add
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </main>

    <div class="qb-slot-modal" id="qbSlotModal" role="presentation" aria-hidden="true">
      <button type="button" class="qb-slot-modal-backdrop" id="qbSlotBackdrop" aria-label="Close"></button>
      <div class="qb-slot-card qb-slot-modal-card" role="dialog" aria-modal="true" aria-labelledby="qb-slot-modal-title">
        <button type="button" class="qb-slot-modal-close" id="qbSlotCloseX" aria-label="Close">×</button>
        <div class="qb-slot-head">
          <div class="qb-slot-head-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
              <path d="M12 8v5l3 2" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M21 12a9 9 0 1 1-9-9 9 9 0 0 1 9 9Z" stroke="white" stroke-width="2"/>
            </svg>
          </div>
          <div>
            <div class="qb-slot-head-title" id="qb-slot-modal-title"><?php echo $is_admin_view ? 'Pickup slots (preview)' : 'Select Pickup Time Slot'; ?></div>
            <div class="qb-slot-head-sub"><?php echo $is_admin_view ? 'Capacity per window — selection is disabled for admins.' : "Choose when you'd like to pick up your order"; ?></div>
          </div>
        </div>
        <div class="qb-slot-list">
          <?php if (empty($slot_modal_rows)): ?>
            <div class="qb-slot-empty-big">No time slots available.</div>
          <?php else: ?>
            <?php foreach ($slot_modal_rows as $row): ?>
              <?php if ($is_admin_view): ?>
                <div class="qb-slot-row qb-slot-row--preview <?php echo $row['active_m'] ? 'active' : ''; ?>">
                  <div class="qb-slot-row-btn qb-slot-row-btn--preview" role="group" aria-label="<?php echo htmlspecialchars($row['slot_label'] . ' — ' . $row['status_m'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="qb-slot-row-top">
                      <div class="qb-slot-dot <?php echo htmlspecialchars($row['cls_m'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></div>
                      <div class="qb-slot-row-time"><?php echo htmlspecialchars($row['slot_label']); ?></div>
                      <?php if ($row['active_m']): ?>
                        <div class="qb-slot-check" aria-hidden="true">✓</div>
                      <?php endif; ?>
                    </div>
                    <div class="qb-slot-row-status"><?php echo htmlspecialchars($row['status_m']); ?></div>
                    <div class="qb-slot-row-mid">
                      <div class="qb-slot-row-label">Orders w/ prepared</div>
                      <div class="qb-slot-row-count"><?php echo (int)$row['used_m']; ?> / <?php echo (int)$row['cap_m']; ?></div>
                    </div>
                    <div class="qb-slot-bar">
                      <div class="qb-slot-bar-fill <?php echo htmlspecialchars($row['cls_m'], ENT_QUOTES, 'UTF-8'); ?>" style="width: <?php echo (int)$row['pct_m']; ?>%"></div>
                    </div>
                  </div>
                </div>
              <?php else: ?>
                <form method="post" action="<?php echo htmlspecialchars(student_url('slot'), ENT_QUOTES, 'UTF-8'); ?>" class="qb-slot-row <?php echo $row['active_m'] ? 'active' : ''; ?>">
                  <input type="hidden" name="time_slot_id" value="<?php echo (int)$row['sid']; ?>" />
                  <input type="hidden" name="return_to" value="home" />
                  <button type="submit" class="qb-slot-row-btn" <?php echo $row['slot_btn_disabled_m'] ? 'disabled' : ''; ?>>
                    <div class="qb-slot-row-top">
                      <div class="qb-slot-dot <?php echo htmlspecialchars($row['cls_m'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></div>
                      <div class="qb-slot-row-time"><?php echo htmlspecialchars($row['slot_label']); ?></div>
                      <?php if ($row['active_m']): ?>
                        <div class="qb-slot-check" aria-hidden="true">✓</div>
                      <?php endif; ?>
                    </div>
                    <div class="qb-slot-row-status"><?php echo htmlspecialchars($row['status_m']); ?></div>
                    <div class="qb-slot-row-mid">
                      <div class="qb-slot-row-label">Orders w/ prepared</div>
                      <div class="qb-slot-row-count"><?php echo (int)$row['used_m']; ?> / <?php echo (int)$row['cap_m']; ?></div>
                    </div>
                    <div class="qb-slot-bar">
                      <div class="qb-slot-bar-fill <?php echo htmlspecialchars($row['cls_m'], ENT_QUOTES, 'UTF-8'); ?>" style="width: <?php echo (int)$row['pct_m']; ?>%"></div>
                    </div>
                  </button>
                </form>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <div class="qb-slot-footer">
          <button type="button" class="qb-slot-back" id="qbSlotCloseBtn">Close</button>
        </div>
      </div>
    </div>

    <?php if (!$is_admin_view): ?>
      <div class="qb-flow-modal" id="qbFlowModal" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Cart and checkout">
        <button type="button" class="qb-flow-modal-backdrop" id="qbFlowModalBackdrop" aria-label="Close cart"></button>
        <div class="qb-flow-modal-panel">
          <button type="button" class="qb-flow-modal-x" id="qbFlowModalClose" aria-label="Close">×</button>
          <iframe class="qb-flow-modal-frame" id="qbFlowFrame" title="Cart and checkout" src="about:blank"></iframe>
        </div>
      </div>
    <?php endif; ?>

    <?php require __DIR__ . '/food-detail.php'; ?>

  <script>
    (function () {
      const btn = document.getElementById('qbUserBtn');
      const menu = document.getElementById('qbUserMenu');
      if (!btn || !menu) return;

      function closeMenu() {
        menu.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
      }

      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        const isOpen = menu.classList.toggle('open');
        btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });

      document.addEventListener('click', function () {
        closeMenu();
      });

      menu.addEventListener('click', function (e) {
        e.stopPropagation();
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeMenu();
      });
    })();
  </script>
  <script>
    (function () {
      const modal = document.getElementById('qbSlotModal');
      const openBtn = document.getElementById('qbSlotOpen');
      const backdrop = document.getElementById('qbSlotBackdrop');
      const closeX = document.getElementById('qbSlotCloseX');
      const closeBtn = document.getElementById('qbSlotCloseBtn');
      if (!modal || !openBtn) return;

      function openModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('qb-slot-modal-open');
        openBtn.setAttribute('aria-expanded', 'true');
      }

      function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('qb-slot-modal-open');
        openBtn.setAttribute('aria-expanded', 'false');
        openBtn.focus();
      }

      openBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        openModal();
      });

      [backdrop, closeX, closeBtn].forEach(function (el) {
        if (el) el.addEventListener('click', function () { closeModal(); });
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
          closeModal();
        }
      });
    })();
  </script>
  <?php if (!$is_admin_view): ?>
  <script>
    (function () {
      var flowModal = document.getElementById('qbFlowModal');
      var flowFrame = document.getElementById('qbFlowFrame');
      var flowBackdrop = document.getElementById('qbFlowModalBackdrop');
      var flowClose = document.getElementById('qbFlowModalClose');
      var cartBtn = document.getElementById('qbCartFlowBtn');
      if (!flowModal || !flowFrame) return;

      var qbAutoOpenFlow = <?php echo json_encode($qb_open_flow === 'cart'); ?>;

      function openFlow(url) {
        flowFrame.src = url;
        flowModal.classList.add('is-open');
        flowModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('qb-flow-open');
        if (cartBtn) cartBtn.setAttribute('aria-expanded', 'true');
      }

      function closeFlow() {
        flowModal.classList.remove('is-open');
        flowModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('qb-flow-open');
        flowFrame.src = 'about:blank';
        if (cartBtn) cartBtn.setAttribute('aria-expanded', 'false');
        window.location.reload();
      }

      window.qbCloseFlow = closeFlow;

      var qbFlowCartUrl = <?php echo json_encode(student_url('cart', [], true), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

      if (qbAutoOpenFlow) {
        openFlow(qbFlowCartUrl);
        try {
          var u = new URL(window.location.href);
          u.searchParams.delete('open_flow');
          var clean = u.pathname + (u.search ? u.search : '') + u.hash;
          window.history.replaceState({}, '', clean);
        } catch (e) {}
      }

      window.addEventListener('message', function (ev) {
        if (ev.data && ev.data.type === 'quickbite-flow-close') {
          closeFlow();
        }
      });

      if (cartBtn) {
        cartBtn.addEventListener('click', function (e) {
          if (e.defaultPrevented) return;
          if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
          e.preventDefault();
          openFlow(qbFlowCartUrl);
        });
      }

      [flowBackdrop, flowClose].forEach(function (el) {
        if (el) el.addEventListener('click', function () { closeFlow(); });
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && flowModal.classList.contains('is-open')) {
          closeFlow();
        }
      });
    })();
  </script>
  <?php endif; ?>
  </body>
</html>
