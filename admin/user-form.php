<?php
require __DIR__ . '/../app/bootstrap.php';

// Create or edit a user account (any role). New accounts must verify their email before use.
$admin = require_role(ROLE_ADMIN);
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$existing = $id ? db_one('SELECT u.*, r.role_key FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = ?', [$id]) : null;
if ($id && !$existing) {
    not_found();
}
$isSelf = $existing && (int) $existing['user_id'] === (int) $admin['user_id'];

if (is_post()) {
    csrf_verify();
    $v = new Validator($_POST);
    $v->required('first_name', 'First name')->length('first_name', 'First name', 2, 60)->name('first_name', 'First name')
      ->required('last_name', 'Last name')->length('last_name', 'Last name', 2, 60)->name('last_name', 'Last name')
      ->required('email', 'Email')->email('email', 'Email')
      ->required('contact_no', 'Mobile number')->phMobile('contact_no', 'Mobile number')
      ->required('role', 'Role')->in('role', 'role', [ROLE_ADMIN, ROLE_STAFF, ROLE_RESIDENT])
      ->length('street_address', 'House no. and street', 3, 150);
    if (post_param('purok_id') !== '') {
        $v->exists('purok_id', 'purok', 'SELECT 1 FROM puroks WHERE purok_id = ?');
    }
    if (!$existing || ($_POST['password'] ?? '') !== '') {
        $v->password('password', 'Password');
    }
    $email = mb_strtolower($v->value('email'));
    if (!$v->fails() && db_value('SELECT 1 FROM users WHERE email = ? AND user_id <> ?', [$email, $id])) {
        $v->custom(false, 'email', 'Another account already uses this email.');
    }
    $v->custom(!($isSelf && $v->value('role') !== ROLE_ADMIN), 'role', 'You cannot remove your own administrator role.');
    if ($v->fails()) {
        back_with_errors($v->errors(), 'admin/user-form.php' . ($id ? '?id=' . $id : ''));
    }

    $roleId = role_id($v->value('role'));
    $purok = $v->value('purok_id') !== '' ? (int) $v->value('purok_id') : null;
    $street = $v->value('street_address') !== '' ? $v->value('street_address') : null;

    if ($existing) {
        $emailChanged = $email !== $existing['email'];
        db_exec(
            'UPDATE users SET role_id = ?, purok_id = ?, first_name = ?, last_name = ?, email = ?, contact_no = ?, street_address = ?,
                    email_verified_at = IF(?, NULL, email_verified_at) WHERE user_id = ?',
            [$roleId, $purok, $v->value('first_name'), $v->value('last_name'), $email, $v->value('contact_no'), $street, $emailChanged ? 1 : 0, $id]
        );
        if (($_POST['password'] ?? '') !== '') {
            db_exec('UPDATE users SET password_hash = ?, failed_logins = 0, locked_until = NULL WHERE user_id = ?', [password_hash($_POST['password'], PASSWORD_DEFAULT), $id]);
        }
        $changes = [];
        if ($existing['role_key'] !== $v->value('role')) { $changes[] = "role {$existing['role_key']} -> {$v->value('role')}"; }
        if ($emailChanged) { $changes[] = 'email changed (verification required)'; }
        if (($_POST['password'] ?? '') !== '') { $changes[] = 'password reset'; }
        log_activity('user_updated', 'user', $id, $email . ($changes ? ': ' . implode(', ', $changes) : ''));
        if ($emailChanged) {
            send_verification_email(['user_id' => $id, 'first_name' => $v->value('first_name'), 'last_name' => $v->value('last_name'), 'email' => $email]);
        }
        flash('success', 'The account was updated.' . ($emailChanged ? ' A verification email was sent to the new address.' : ''));
    } else {
        $id = db_insert(
            'INSERT INTO users (role_id, purok_id, first_name, last_name, email, contact_no, street_address, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$roleId, $purok, $v->value('first_name'), $v->value('last_name'), $email, $v->value('contact_no'), $street, password_hash($_POST['password'], PASSWORD_DEFAULT)]
        );
        log_activity('user_created', 'user', $id, "$email created as {$v->value('role')}");
        $sent = send_verification_email(['user_id' => $id, 'first_name' => $v->value('first_name'), 'last_name' => $v->value('last_name'), 'email' => $email]);
        flash($sent ? 'success' : 'warning', $sent
            ? "The account was created. A verification email was sent to $email; the account works after it is verified."
            : 'The account was created, but the verification email could not be sent. Use "Resend email" on the list.');
    }
    clear_old_input();
    redirect('admin/users.php');
}

$errors = take_errors();
$puroks = db_all('SELECT purok_id, purok_name FROM puroks ORDER BY purok_id');
$val = fn ($k, $d = '') => old($k, $existing[$k] ?? $d);
$roleFromLink = in_array(query_param('role'), [ROLE_ADMIN, ROLE_STAFF, ROLE_RESIDENT], true) ? query_param('role') : ROLE_STAFF;
$selRole = (string) old('role', $existing['role_key'] ?? $roleFromLink);
$pageTitle = $existing ? 'Edit account' : 'Add account';
$activeNav = 'users';
require APP_ROOT . '/app/views/header.php';
?>
<div class="form-narrow">
  <div class="page-head"><div><p class="mb-1"><a href="<?= e(url('admin/users.php')) ?>">User accounts</a></p><h1><?= e($pageTitle) ?></h1>
    <?php if ($existing): ?><p><?= e(full_name($existing)) ?>, <?= $existing['email_verified_at'] ? 'email verified ' . e(format_date($existing['email_verified_at'])) : 'email not verified' ?></p><?php endif; ?></div></div>
  <form method="post" class="panel" novalidate>
    <?= csrf_field() ?>
    <?php if ($existing): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
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
        <label class="form-label required" for="email">Email</label>
        <input type="email" class="form-control<?= invalid_class($errors, 'email') ?>" id="email" name="email" maxlength="120" value="<?= e($val('email')) ?>"<?= aria_invalid($errors, 'email') ?>>
        <?= field_error($errors, 'email') ?: ($existing ? '<div class="form-text">Changing the email requires verifying the new address.</div>' : '') ?>
      </div>
      <div class="col-sm-6">
        <label class="form-label required" for="contact_no">Mobile number</label>
        <input class="form-control<?= invalid_class($errors, 'contact_no') ?>" id="contact_no" name="contact_no" maxlength="13" value="<?= e($val('contact_no')) ?>"<?= aria_invalid($errors, 'contact_no') ?>>
        <?= field_error($errors, 'contact_no') ?>
      </div>
      <div class="col-sm-6">
        <label class="form-label required" for="role">Role</label>
        <select class="form-select<?= invalid_class($errors, 'role') ?>" id="role" name="role"<?= aria_invalid($errors, 'role') ?>>
          <?php foreach ([ROLE_RESIDENT => 'Resident', ROLE_STAFF => 'Barangay Official / Staff', ROLE_ADMIN => 'Administrator'] as $k => $label): ?>
            <option value="<?= $k ?>" <?= $selRole === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'role') ?>
      </div>
      <div class="col-sm-6">
        <label class="form-label" for="purok_id">Purok</label>
        <select class="form-select<?= invalid_class($errors, 'purok_id') ?>" id="purok_id" name="purok_id">
          <option value="">None</option>
          <?php foreach ($puroks as $p): ?>
            <option value="<?= (int) $p['purok_id'] ?>" <?= (string) $val('purok_id') === (string) $p['purok_id'] ? 'selected' : '' ?>><?= e($p['purok_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'purok_id') ?>
      </div>
      <div class="col-12">
        <label class="form-label" for="street_address">House no. and street</label>
        <input class="form-control<?= invalid_class($errors, 'street_address') ?>" id="street_address" name="street_address" maxlength="150" value="<?= e($val('street_address')) ?>"<?= aria_invalid($errors, 'street_address') ?>>
        <?= field_error($errors, 'street_address') ?>
      </div>
      <div class="col-sm-6">
        <label class="form-label<?= $existing ? '' : ' required' ?>" for="password"><?= $existing ? 'New password' : 'Temporary password' ?></label>
        <input type="password" class="form-control<?= invalid_class($errors, 'password') ?>" id="password" name="password" maxlength="72" autocomplete="new-password"<?= aria_invalid($errors, 'password') ?>>
        <?= field_error($errors, 'password') ?: '<div class="form-text">' . ($existing ? 'Leave blank to keep the current password.' : 'At least 8 characters, with a letter and a number.') . '</div>' ?>
      </div>
    </div>
    <div class="mt-4">
      <button type="submit" class="btn btn-primary"><?= $existing ? 'Save changes' : 'Create account' ?></button>
      <a class="btn btn-link" href="<?= e(url('admin/users.php')) ?>">Cancel</a>
    </div>
  </form>
</div>
<?php clear_old_input(); require APP_ROOT . '/app/views/footer.php'; ?>
