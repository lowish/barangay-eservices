<?php
require __DIR__ . '/app/bootstrap.php';

$user = require_role(ROLE_RESIDENT, ROLE_STAFF, ROLE_ADMIN);
$uid = (int) $user['user_id'];

if (is_post()) {
    csrf_verify();
    $action = post_param('action');

    if ($action === 'profile') {
        $v = new Validator($_POST);
        $v->required('first_name', 'First name')->length('first_name', 'First name', 2, 60)->name('first_name', 'First name')
          ->required('last_name', 'Last name')->length('last_name', 'Last name', 2, 60)->name('last_name', 'Last name')
          ->required('contact_no', 'Mobile number')->phMobile('contact_no', 'Mobile number')
          ->length('street_address', 'House no. and street', 3, 150);
        if ($user['role_key'] === ROLE_RESIDENT) {
            $v->required('purok_id', 'Purok')->exists('purok_id', 'purok', 'SELECT 1 FROM puroks WHERE purok_id = ?')
              ->required('street_address', 'House no. and street');
        }
        if ($v->fails()) {
            back_with_errors($v->errors(), 'profile.php');
        }
        db_exec(
            'UPDATE users SET first_name = ?, last_name = ?, contact_no = ?, purok_id = ?, street_address = ? WHERE user_id = ?',
            [$v->value('first_name'), $v->value('last_name'), $v->value('contact_no'),
             $v->value('purok_id') !== '' ? (int) $v->value('purok_id') : null,
             $v->value('street_address') !== '' ? $v->value('street_address') : null, $uid]
        );
        log_activity('profile_updated', 'user', $uid, 'Updated own profile');
        flash('success', 'Your profile was saved.');
        redirect('profile.php');
    }

    if ($action === 'password') {
        $hash = db_value('SELECT password_hash FROM users WHERE user_id = ?', [$uid]);
        $v = new Validator($_POST);
        $v->custom(password_verify((string) ($_POST['current_password'] ?? ''), $hash), 'current_password', 'Your current password is incorrect.')
          ->password('password', 'New password')
          ->matches('password_confirm', 'password', 'The two new passwords do not match.');
        if ($v->fails()) {
            back_with_errors($v->errors(), 'profile.php');
        }
        db_exec('UPDATE users SET password_hash = ? WHERE user_id = ?', [password_hash($_POST['password'], PASSWORD_DEFAULT), $uid]);
        session_regenerate_id(true);
        log_activity('password_changed', 'user', $uid, 'Changed own password');
        flash('success', 'Your password was changed.');
        redirect('profile.php');
    }
}

$errors = take_errors();
$puroks = db_all('SELECT purok_id, purok_name FROM puroks ORDER BY purok_id');
$val = fn ($k) => old($k, $user[$k] ?? '');
$pageTitle = 'Profile';
$activeNav = 'profile';
require APP_ROOT . '/app/views/header.php';
?>
<div class="page-head"><div><h1>Profile</h1><p>Your account details and password.</p></div></div>
<div class="row g-4">
  <div class="col-lg-7">
    <form method="post" class="panel" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="profile">
      <h2>Personal details</h2>
      <div class="row g-3">
        <div class="col-sm-6">
          <label class="form-label required" for="first_name">First name</label>
          <input class="form-control<?= invalid_class($errors, 'first_name') ?>" id="first_name" name="first_name" maxlength="60" value="<?= e($val('first_name')) ?>"<?= aria_invalid($errors, 'first_name') ?>>
          <?= field_error($errors, 'first_name') ?>
        </div>
        <div class="col-sm-6">
          <label class="form-label required" for="last_name">Last name</label>
          <input class="form-control<?= invalid_class($errors, 'last_name') ?>" id="last_name" name="last_name" maxlength="60" value="<?= e($val('last_name')) ?>"<?= aria_invalid($errors, 'last_name') ?>>
          <?= field_error($errors, 'last_name') ?>
        </div>
        <div class="col-sm-6">
          <label class="form-label required" for="contact_no">Mobile number</label>
          <input class="form-control<?= invalid_class($errors, 'contact_no') ?>" id="contact_no" name="contact_no" maxlength="13" value="<?= e($val('contact_no')) ?>"<?= aria_invalid($errors, 'contact_no') ?>>
          <?= field_error($errors, 'contact_no') ?>
        </div>
        <div class="col-sm-6">
          <label class="form-label<?= $user['role_key'] === ROLE_RESIDENT ? ' required' : '' ?>" for="purok_id">Purok</label>
          <select class="form-select<?= invalid_class($errors, 'purok_id') ?>" id="purok_id" name="purok_id"<?= aria_invalid($errors, 'purok_id') ?>>
            <option value="">None</option>
            <?php foreach ($puroks as $p): ?>
              <option value="<?= (int) $p['purok_id'] ?>" <?= (string) $val('purok_id') === (string) $p['purok_id'] ? 'selected' : '' ?>><?= e($p['purok_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <?= field_error($errors, 'purok_id') ?>
        </div>
        <div class="col-12">
          <label class="form-label<?= $user['role_key'] === ROLE_RESIDENT ? ' required' : '' ?>" for="street_address">House no. and street</label>
          <input class="form-control<?= invalid_class($errors, 'street_address') ?>" id="street_address" name="street_address" maxlength="150" value="<?= e($val('street_address')) ?>"<?= aria_invalid($errors, 'street_address') ?>>
          <?= field_error($errors, 'street_address') ?>
        </div>
      </div>
      <button type="submit" class="btn btn-primary mt-3">Save profile</button>
    </form>
  </div>
  <div class="col-lg-5">
    <div class="panel">
      <h2>Account</h2>
      <dl class="details">
        <dt>Email</dt><dd><?= e($user['email']) ?></dd>
        <dt>Role</dt><dd><?= e($user['role_name']) ?></dd>
        <dt>Email status</dt><dd><span class="status status-done">Verified</span> <span class="text-muted small"><?= e(format_datetime($user['email_verified_at'])) ?></span></dd>
        <dt>Member since</dt><dd><?= e(format_date($user['created_at'])) ?></dd>
      </dl>
    </div>
    <form method="post" class="panel" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <h2>Change password</h2>
      <div class="mb-3">
        <label class="form-label" for="current_password">Current password</label>
        <input type="password" class="form-control<?= invalid_class($errors, 'current_password') ?>" id="current_password" name="current_password" autocomplete="current-password"<?= aria_invalid($errors, 'current_password') ?>>
        <?= field_error($errors, 'current_password') ?>
      </div>
      <div class="mb-3">
        <label class="form-label" for="password">New password</label>
        <input type="password" class="form-control<?= invalid_class($errors, 'password') ?>" id="password" name="password" maxlength="72" autocomplete="new-password"<?= aria_invalid($errors, 'password') ?>>
        <?= field_error($errors, 'password') ?>
      </div>
      <div class="mb-3">
        <label class="form-label" for="password_confirm">Confirm new password</label>
        <input type="password" class="form-control<?= invalid_class($errors, 'password_confirm') ?>" id="password_confirm" name="password_confirm" maxlength="72" autocomplete="new-password"<?= aria_invalid($errors, 'password_confirm') ?>>
        <?= field_error($errors, 'password_confirm') ?>
      </div>
      <button type="submit" class="btn btn-outline-primary">Change password</button>
    </form>
  </div>
</div>
<?php clear_old_input(); require APP_ROOT . '/app/views/footer.php'; ?>
