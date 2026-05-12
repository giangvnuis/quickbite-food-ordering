<?php
/**
 * QuickBite Admin — Hồ sơ admin (view). Logic: profile_controller + profile_service
 * (get_admin_profile, update_admin_profile_info, update_admin_password).
 *
 * @var string $full_name $email $phone $flash_ok $flash_err $has_phone
 */
declare(strict_types=1);

qb_admin_shell_start('profile', 'My Profile', 'Manage personal information and account security.');
$profile_action = htmlspecialchars(admin_url('profile'), ENT_QUOTES, 'UTF-8');
?>
<section class="qb-admin-card">
  <?php if ($flash_ok !== ''): ?>
    <div class="qb-flash success" role="status"><?php echo htmlspecialchars($flash_ok); ?></div>
  <?php endif; ?>
  <?php if ($flash_err !== ''): ?>
    <div class="qb-flash error" role="alert"><?php echo htmlspecialchars($flash_err); ?></div>
  <?php endif; ?>

  <div class="qb-admin-grid-2">
    <div class="qb-admin-card">
      <div class="qb-admin-card-head qb-admin-card-head-stack">
        <div>
          <h2>Personal information</h2>
        </div>
      </div>
      <form method="post" action="<?php echo $profile_action; ?>" class="qb-mm-form">
        <input type="hidden" name="which" value="profile" />
        <div class="qb-mm-form-grid">
          <div class="qb-mm-field qb-mm-field--full">
            <label for="apf-name">Full name</label>
            <input id="apf-name" type="text" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required autocomplete="name" />
          </div>
          <div class="qb-mm-field qb-mm-field--full">
            <label for="apf-email">Email</label>
            <input id="apf-email" type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required autocomplete="email" />
          </div>
          <?php if ($has_phone): ?>
            <div class="qb-mm-field qb-mm-field--full">
              <label for="apf-phone">Phone number</label>
              <input id="apf-phone" type="tel" name="phone" inputmode="numeric" value="<?php echo htmlspecialchars($phone); ?>" required autocomplete="tel" placeholder="10-11 digits" />
            </div>
          <?php endif; ?>
        </div>
        <div class="qb-mm-form-actions">
          <button type="submit" class="qb-mm-form-submit">Save changes</button>
        </div>
      </form>
    </div>

    <div class="qb-admin-card">
      <div class="qb-admin-card-head qb-admin-card-head-stack">
        <div>
          <h2>Password</h2>
        </div>
      </div>
      <form method="post" action="<?php echo $profile_action; ?>" class="qb-mm-form">
        <input type="hidden" name="which" value="password" />
        <div class="qb-mm-form-grid">
          <div class="qb-mm-field qb-mm-field--full">
            <label for="apf-cur">Current password</label>
            <input id="apf-cur" type="password" name="current_password" required autocomplete="current-password" />
          </div>
          <div class="qb-mm-field qb-mm-field--full">
            <label for="apf-new">New password</label>
            <input id="apf-new" type="password" name="new_password" required minlength="8" autocomplete="new-password" />
          </div>
          <div class="qb-mm-field qb-mm-field--full">
            <label for="apf-new2">Confirm new password</label>
            <input id="apf-new2" type="password" name="new_password_confirm" required minlength="8" autocomplete="new-password" />
          </div>
        </div>
        <div class="qb-mm-form-actions">
          <button type="submit" class="qb-mm-form-submit">Update password</button>
        </div>
      </form>
    </div>
  </div>
</section>
<?php qb_admin_shell_end(); ?>
