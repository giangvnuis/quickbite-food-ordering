<?php
/**
 * QuickBite Admin — Menu view (controller + menu_service).
 */
declare(strict_types=1);

require_once __DIR__ . '/../admin_context.php';
require_once __DIR__ . '/../Services/admin_shell_service.php';

qb_admin_shell_start('menu', 'Today', $page_date);
?>
<?php if ($flash !== '' && !$show_form): ?>
  <div class="qb-flash <?php echo $flash_type === 'error' ? 'error' : 'success'; ?>"><?php echo htmlspecialchars($flash); ?></div>
<?php endif; ?>

<div class="qb-om-page">
<section class="qb-mm-head qb-om-heading qb-om-heading-row qb-om-heading-row--only-cta">
  <a class="qb-om-refresh qb-om-counter-btn" href="<?php echo htmlspecialchars(admin_menu_link($q, $type_filter, $category_filter, ['show_form' => '1']), ENT_QUOTES, 'UTF-8'); ?>">+ Add New Item</a>
</section>

<nav class="qb-mm-stats" aria-label="Quick filters">
  <a class="qb-mm-stat <?php echo $type_filter === 'all' && $category_filter === 'all' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(admin_menu_link($q, $type_filter, $category_filter, ['type' => null, 'category' => null]), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="qb-mm-stat-head"><?php echo qb_admin_stat_icon('box', 'blue'); ?><div class="qb-mm-stat-label">Total Items</div></div>
    <div class="qb-mm-stat-value"><?php echo $stat_total; ?></div>
  </a>
  <a class="qb-mm-stat <?php echo $type_filter === 'prepared' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(admin_menu_link($q, $type_filter, $category_filter, ['type' => 'prepared']), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="qb-mm-stat-head"><?php echo qb_admin_stat_icon('box', 'red'); ?><div class="qb-mm-stat-label">Prepared Items</div></div>
    <div class="qb-mm-stat-value"><?php echo $stat_prepared; ?></div>
  </a>
  <a class="qb-mm-stat <?php echo $type_filter === 'instant' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(admin_menu_link($q, $type_filter, $category_filter, ['type' => 'instant']), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="qb-mm-stat-head"><?php echo qb_admin_stat_icon('box', 'green'); ?><div class="qb-mm-stat-label">Instant Items</div></div>
    <div class="qb-mm-stat-value"><?php echo $stat_instant; ?></div>
  </a>
</nav>

<section class="qb-mm-toolbar">
  <!-- Toolbar: chỉ search + category (lọc type dùng thẻ phía trên, không lặp dropdown trong thanh). -->
  <form method="get" class="qb-mm-search" action="<?php echo htmlspecialchars(admin_url('menu'), ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="page" value="menu" />
    <?php if ($type_filter !== 'all'): ?>
      <input type="hidden" name="type" value="<?php echo htmlspecialchars($type_filter); ?>" />
    <?php endif; ?>
    <?php if ($category_filter !== 'all'): ?>
      <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>" />
    <?php endif; ?>
    <input type="search" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search by item name..." />
  </form>
  <form method="get" class="qb-om-mini-filter qb-mm-filter" action="<?php echo htmlspecialchars(admin_url('menu'), ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="page" value="menu" />
    <?php if ($q !== ''): ?>
      <input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>" />
    <?php endif; ?>
    <?php if ($type_filter !== 'all'): ?>
      <input type="hidden" name="type" value="<?php echo htmlspecialchars($type_filter); ?>" />
    <?php endif; ?>
    <span class="qb-om-mini-icon" aria-hidden="true">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 3h10v2H7zM5 7h14v14H5z"/></svg>
    </span>
    <select name="category" onchange="this.form.submit()">
      <option value="all">All Categories</option>
      <?php foreach ($categories as $c): ?>
        <?php $cid = (string)$c['id']; ?>
        <option value="<?php echo htmlspecialchars($cid); ?>" <?php echo $category_filter === $cid ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars((string)$c['name']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>
  <a class="qb-om-refresh" href="<?php echo htmlspecialchars(admin_url('menu'), ENT_QUOTES, 'UTF-8'); ?>">Refresh</a>
</section>

<section class="qb-admin-card qb-mm-table-card">
  <!-- Bảng danh sách menu items và thao tác Edit/Delete(soft toggle) -->
  <div class="qb-admin-table-wrap">
    <table class="qb-admin-table qb-mm-table">
      <thead>
        <tr>
          <th>Item Name</th>
          <th>Price</th>
          <th>Category</th>
          <th>Type</th>
          <th>Stock</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($products)): ?>
          <tr class="qb-mm-empty-row">
            <td colspan="6">
              <div class="qb-om-empty">No items found.</div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($products as $p): ?>
            <tr>
              <td class="qb-mm-name"><?php echo htmlspecialchars((string)$p['name']); ?></td>
              <td class="qb-mm-price"><?php echo htmlspecialchars(format_vnd((int)$p['price_cents'])); ?></td>
              <td><span class="qb-mm-chip qb-mm-chip-category"><?php echo htmlspecialchars((string)$p['category_name']); ?></span></td>
              <td>
                <?php
                  $ptype_raw = strtolower(trim((string)($p['product_type'] ?? '')));
                  $is_instant_row = ($ptype_raw === 'instant');
                ?>
                <span class="qb-mm-chip <?php echo $is_instant_row ? 'qb-mm-chip-instant' : 'qb-mm-chip-prepared'; ?>">
                  <?php echo $is_instant_row ? 'Instant' : 'Prepared'; ?>
                </span>
              </td>
              <td class="qb-mm-stock <?php echo $has_stock_qty && $is_instant_row ? 'qb-mm-stock--instant' : 'qb-mm-stock--na'; ?>">
                <?php
                  if (!$has_stock_qty) {
                    echo '—';
                  } elseif ($is_instant_row) {
                    echo (string)(int)($p['stock_qty'] ?? 0);
                  } else {
                    echo '—';
                  }
                ?>
              </td>
              <td class="qb-mm-actions-cell">
                <div class="qb-mm-actions">
                  <a class="qb-mm-btn qb-mm-btn-edit" href="<?php echo htmlspecialchars(admin_menu_link($q, $type_filter, $category_filter, ['edit' => (string)(int)$p['id'], 'show_form' => '1']), ENT_QUOTES, 'UTF-8'); ?>">Edit</a>
                  <form method="post" class="qb-mm-inline-form" action="<?php echo htmlspecialchars(admin_menu_link($q, $type_filter, $category_filter), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="toggle" />
                    <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>" />
                    <button type="submit" class="qb-mm-btn qb-mm-btn-delete"><?php echo (int)$p['is_active'] === 1 ? 'Delete' : 'Restore'; ?></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="qb-mm-foot">
    Showing <?php echo count($products); ?> items.
    <?php if (!$has_stock_qty): ?>
      <span class="qb-mm-stock-hint"> Stock column missing — run <code>migrations/add_products_stock_qty.sql</code> once to enable.</span>
    <?php endif; ?>
  </div>
</section>
</div>

<?php if ($show_form): ?>
  <?php
    $is_edit_form = $edit_id > 0;
    $form_title = $is_edit_form ? 'Edit item' : 'Add new item';
    $form_sub = $is_edit_form ? '' : '';
  ?>
  <section class="qb-mm-modal" id="item-form-modal" aria-modal="true" role="dialog" aria-labelledby="qb-mm-modal-title">
    <!-- Modal form tạo/sửa item -->
    <a class="qb-mm-modal-backdrop" href="<?php echo htmlspecialchars(admin_menu_link($q, $type_filter, $category_filter), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Close"></a>
    <div class="qb-mm-modal-card">
      <header class="qb-mm-modal-head">
        <div>
          <h2 id="qb-mm-modal-title"><?php echo htmlspecialchars($form_title); ?></h2>
          <p class="qb-mm-modal-sub"><?php echo htmlspecialchars($form_sub); ?></p>
        </div>
        <a class="qb-mm-modal-close" href="<?php echo htmlspecialchars(admin_menu_link($q, $type_filter, $category_filter), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Close">×</a>
      </header>
      <?php if ($flash !== '' && $show_form): ?>
        <div class="qb-flash qb-flash--modal <?php echo $flash_type === 'error' ? 'error' : 'success'; ?>" role="alert"><?php echo htmlspecialchars($flash); ?></div>
      <?php endif; ?>
      <form method="post" class="qb-mm-form">
        <input type="hidden" name="action" value="<?php echo $is_edit_form ? 'update' : 'create'; ?>" />
        <?php if ($is_edit_form): ?>
          <input type="hidden" name="product_id" value="<?php echo (int)$form_item['id']; ?>" />
        <?php endif; ?>
        <div class="qb-mm-modal-body">
          <div class="qb-mm-form-grid">
            <div class="qb-mm-field">
              <label for="qb-mm-name">Item name</label>
              <input id="qb-mm-name" type="text" name="name" value="<?php echo htmlspecialchars((string)$form_item['name']); ?>" placeholder="e.g. Grilled chicken rice" required autocomplete="off" />
            </div>
            <div class="qb-mm-field">
              <label for="qb-mm-price">Price (VND cents)</label>
              <input id="qb-mm-price" type="number" name="price_cents" min="1" step="1" value="<?php echo htmlspecialchars((string)$form_item['price_cents']); ?>" placeholder="25000" required />
            </div>
            <div class="qb-mm-field">
              <label for="qb-mm-type">Product type</label>
              <select id="qb-mm-type" name="product_type" class="qb-mm-select" required>
                <option value="prepared" <?php echo (string)$form_item['product_type'] === 'prepared' ? 'selected' : ''; ?>>Prepared</option>
                <option value="instant" <?php echo (string)$form_item['product_type'] === 'instant' ? 'selected' : ''; ?>>Instant</option>
              </select>
            </div>
            <?php if ($has_stock_qty): ?>
              <?php
                $ptype_form = strtolower(trim((string)($form_item['product_type'] ?? 'prepared')));
                $stock_for_instant = ($ptype_form === 'instant');
              ?>
              <div class="qb-mm-field qb-mm-field--stock-for-instant" id="qb-mm-stock-field"<?php echo $stock_for_instant ? '' : ' hidden'; ?>>
                <label for="qb-mm-stock">Stock quantity <span class="qb-mm-label-tag">Instant</span></label>
                <input
                  id="qb-mm-stock"
                  type="number"
                  name="stock_qty"
                  min="0"
                  step="1"
                  value="<?php echo htmlspecialchars((string)(int)($form_item['stock_qty'] ?? 0)); ?>"
                  placeholder="e.g. 24"
                  autocomplete="off"
                  <?php echo $stock_for_instant ? 'required' : 'disabled'; ?>
                />
                <span class="qb-mm-field-hint">Only for instant items — how many units are available now.</span>
              </div>
            <?php endif; ?>
            <div class="qb-mm-field">
              <label for="qb-mm-cat">Category</label>
              <select id="qb-mm-cat" name="category_id" class="qb-mm-select" required>
                <?php foreach ($categories as $c): ?>
                  <option value="<?php echo (int)$c['id']; ?>" <?php echo (int)$form_item['category_id'] === (int)$c['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string)$c['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="qb-mm-field qb-mm-field--full">
              <label for="qb-mm-img">Image path or URL</label>
              <input id="qb-mm-img" type="text" name="image_path" value="<?php echo htmlspecialchars((string)$form_item['image_path']); ?>" placeholder="assets/menu/photo.jpg or https://…" autocomplete="off" required />
              <span class="qb-mm-field-hint"></span>
            </div>
            <?php
              $pv_raw = trim((string)($form_item['image_path'] ?? ''));
              $pv_src = '';
              if ($pv_raw !== '') {
                $pv_src = preg_match('#^https?://#i', $pv_raw) ? $pv_raw : ltrim($pv_raw, '/');
              }
              $preview_has = $pv_src !== '';
            ?>
            <div class="qb-mm-field qb-mm-field--full qb-mm-img-preview-wrap">
              <span class="qb-mm-field-hint" id="qb-mm-preview-hint"><?php echo $preview_has ? 'Preview' : 'Preview (paste a path or URL)'; ?></span>
              <div class="qb-mm-img-preview" id="qb-mm-live-preview-wrap">
                <img
                  id="qb-mm-live-preview-img"
                  src="<?php echo $preview_has ? htmlspecialchars($pv_src) : ''; ?>"
                  alt=""
                  <?php echo $preview_has ? '' : 'hidden'; ?>
                />
              </div>
            </div>
            <div class="qb-mm-field qb-mm-field--full">
              <label for="qb-mm-desc">Description</label>
              <textarea id="qb-mm-desc" name="description" rows="3" placeholder="Short description"><?php echo htmlspecialchars((string)$form_item['description']); ?></textarea>
            </div>
          </div>
        </div>
        <div class="qb-mm-form-actions">
          <a class="qb-mm-form-cancel" href="<?php echo htmlspecialchars(admin_menu_link($q, $type_filter, $category_filter), ENT_QUOTES, 'UTF-8'); ?>">Cancel</a>
          <button type="submit" class="qb-mm-form-submit"><?php echo $is_edit_form ? 'Save changes' : 'Create item'; ?></button>
        </div>
      </form>
    </div>
  </section>
  <script>
    (function () {
      // UI helper: chỉ hiện stock input khi type=instant.
      var typeEl = document.getElementById('qb-mm-type');
      var wrap = document.getElementById('qb-mm-stock-field');
      var input = document.getElementById('qb-mm-stock');
      if (typeEl && wrap && input) {
        function sync() {
          var instant = typeEl.value === 'instant';
          if (instant) {
            wrap.removeAttribute('hidden');
            input.disabled = false;
            input.required = true;
          } else {
            wrap.setAttribute('hidden', '');
            input.disabled = true;
            input.required = false;
            input.value = '0';
          }
        }
        typeEl.addEventListener('change', sync);
        sync();
      }

      var urlEl = document.getElementById('qb-mm-img');
      var imgEl = document.getElementById('qb-mm-live-preview-img');
      var hintEl = document.getElementById('qb-mm-preview-hint');
      if (!urlEl || !imgEl) return;
      // UI helper: preview ảnh realtime từ image_path/url.
      function urlToPreviewSrc(v) {
        v = (v || '').trim();
        if (!v) return '';
        if (/^https?:\/\//i.test(v)) return v;
        return v.replace(/^\//, '');
      }
      function syncPreviewFromUrl() {
        var src = urlToPreviewSrc(urlEl.value);
        if (src) {
          imgEl.src = src;
          imgEl.removeAttribute('hidden');
          if (hintEl) hintEl.textContent = 'Preview';
        } else {
          imgEl.removeAttribute('src');
          imgEl.setAttribute('hidden', '');
          if (hintEl) hintEl.textContent = 'Preview (paste a path or URL)';
        }
      }
      urlEl.addEventListener('input', syncPreviewFromUrl);
      urlEl.addEventListener('change', syncPreviewFromUrl);
      syncPreviewFromUrl();
    })();
  </script>
<?php endif; ?>
<?php qb_admin_shell_end(); ?>
