<?php
require __DIR__ . '/../app/bootstrap.php';

if (current_user()) {
    redirect(home_path_for_current_user());
}

if (is_post()) {
    csrf_verify();
    $v = new Validator($_POST);
    $v->required('first_name', 'First name')->length('first_name', 'First name', 2, 60)->name('first_name', 'First name')
      ->required('last_name', 'Last name')->length('last_name', 'Last name', 2, 60)->name('last_name', 'Last name')
      ->required('email', 'Email')->email('email', 'Email')
      ->required('contact_no', 'Mobile number')->phMobile('contact_no', 'Mobile number')
      ->required('purok_id', 'Purok')->exists('purok_id', 'purok', 'SELECT 1 FROM puroks WHERE purok_id = ?')
      ->required('street_address', 'House no. and street')->length('street_address', 'House no. and street', 3, 150)
      ->password('password', 'Password')
      ->matches('password_confirm', 'password', 'The two passwords do not match.')
      ->custom(isset($_POST['consent']), 'consent', 'You must agree to the data privacy notice to create an account.');

    $email = mb_strtolower($v->value('email'));
    if (!$v->fails() && db_value('SELECT 1 FROM users WHERE email = ?', [$email])) {
        $v->custom(false, 'email', 'An account with this email already exists. Sign in instead, or use another email.');
    }
    if ($v->fails()) {
        back_with_errors($v->errors(), 'auth/register.php');
    }

    $userId = db_insert(
        'INSERT INTO users (role_id, purok_id, first_name, last_name, email, contact_no, street_address, password_hash)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [role_id(ROLE_RESIDENT), (int) $v->value('purok_id'), $v->value('first_name'), $v->value('last_name'),
         $email, $v->value('contact_no'), $v->value('street_address'), password_hash($_POST['password'], PASSWORD_DEFAULT)]
    );
    log_activity('account_registered', 'user', $userId, "Resident self-registration: $email", $userId);

    $user = db_one('SELECT user_id, first_name, last_name, email FROM users WHERE user_id = ?', [$userId]);
    $sent = send_verification_email($user);
    clear_old_input();
    login_user($user);

    if ($sent) {
        flash('success', "Your account was created. We sent a verification link to $email.");
    } else {
        flash('warning', 'Your account was created, but the verification email could not be sent right now. Use "Resend verification email" below.');
    }
    redirect('auth/verify-notice.php');
}

$errors = take_errors();
$puroks = db_all('SELECT purok_id, purok_name FROM puroks ORDER BY purok_id');
$pageTitle = 'Create account';
$activeNav = 'register';
require APP_ROOT . '/app/views/header.php';
?>
<div class="form-narrow mx-auto">
  <div class="page-head">
    <div>
      <h1>Create a resident account</h1>
      <p>For residents of <?= e(barangay_name()) ?>. You will need to confirm your email address before you can submit requests or report concerns.</p>
    </div>
  </div>
  <form method="post" class="panel" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
      <div class="col-sm-6">
        <label class="form-label required" for="first_name">First name</label>
        <input class="form-control<?= invalid_class($errors, 'first_name') ?>" id="first_name" name="first_name" maxlength="60" required autocomplete="given-name" value="<?= e(old('first_name')) ?>"<?= aria_invalid($errors, 'first_name') ?>>
        <?= field_error($errors, 'first_name') ?>
      </div>
      <div class="col-sm-6">
        <label class="form-label required" for="last_name">Last name</label>
        <input class="form-control<?= invalid_class($errors, 'last_name') ?>" id="last_name" name="last_name" maxlength="60" required autocomplete="family-name" value="<?= e(old('last_name')) ?>"<?= aria_invalid($errors, 'last_name') ?>>
        <?= field_error($errors, 'last_name') ?>
      </div>
      <div class="col-sm-6">
        <label class="form-label required" for="email">Email</label>
        <input type="email" class="form-control<?= invalid_class($errors, 'email') ?>" id="email" name="email" maxlength="120" required autocomplete="email" value="<?= e(old('email')) ?>"<?= aria_invalid($errors, 'email') ?>>
        <?= field_error($errors, 'email') ?>
      </div>
      <div class="col-sm-6">
        <label class="form-label required" for="contact_no">Mobile number</label>
        <input class="form-control<?= invalid_class($errors, 'contact_no') ?>" id="contact_no" name="contact_no" maxlength="13" required inputmode="tel" placeholder="09171234567" autocomplete="tel" value="<?= e(old('contact_no')) ?>"<?= aria_invalid($errors, 'contact_no') ?>>
        <?= field_error($errors, 'contact_no') ?>
      </div>
      <div class="col-sm-5">
        <label class="form-label required" for="purok_id">Purok</label>
        <select class="form-select<?= invalid_class($errors, 'purok_id') ?>" id="purok_id" name="purok_id" required<?= aria_invalid($errors, 'purok_id') ?>>
          <option value="">Choose…</option>
          <?php foreach ($puroks as $p): ?>
            <option value="<?= (int) $p['purok_id'] ?>" <?= (string) old('purok_id') === (string) $p['purok_id'] ? 'selected' : '' ?>><?= e($p['purok_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'purok_id') ?>
      </div>
      <div class="col-sm-7">
        <label class="form-label required" for="street_address">House no. and street</label>
        <input class="form-control<?= invalid_class($errors, 'street_address') ?>" id="street_address" name="street_address" maxlength="150" required autocomplete="street-address" value="<?= e(old('street_address')) ?>"<?= aria_invalid($errors, 'street_address') ?>>
        <?= field_error($errors, 'street_address') ?>
      </div>
      <div class="col-sm-6">
        <label class="form-label required" for="password">Password</label>
        <input type="password" class="form-control<?= invalid_class($errors, 'password') ?>" id="password" name="password" minlength="8" maxlength="72" required autocomplete="new-password"<?= aria_invalid($errors, 'password') ?>>
        <?= field_error($errors, 'password') ?: '<div class="form-text">At least 8 characters, with a letter and a number.</div>' ?>
      </div>
      <div class="col-sm-6">
        <label class="form-label required" for="password_confirm">Confirm password</label>
        <input type="password" class="form-control<?= invalid_class($errors, 'password_confirm') ?>" id="password_confirm" name="password_confirm" maxlength="72" required autocomplete="new-password"<?= aria_invalid($errors, 'password_confirm') ?>>
        <?= field_error($errors, 'password_confirm') ?>
      </div>
      <div class="col-12">
        <div class="form-check">
          <input class="form-check-input<?= invalid_class($errors, 'consent') ?>" type="checkbox" id="consent" name="consent" value="1" <?= old('consent') ? 'checked' : '' ?><?= aria_invalid($errors, 'consent') ?>>
          <label class="form-check-label" for="consent">I agree that the barangay may store and use my information to process my requests and reports, in line with the Data Privacy Act of 2012.</label>
          <?= field_error($errors, 'consent') ?>
        </div>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center mt-4">
      <button type="submit" class="btn btn-primary">Create account</button>
      <span class="text-muted">Already registered? <a href="<?= e(url('auth/login.php')) ?>">Sign in</a></span>
    </div>
  </form>
</div>
<?php clear_old_input(); require APP_ROOT . '/app/views/footer.php'; ?>
