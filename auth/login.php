<?php
require __DIR__ . '/../app/bootstrap.php';

const MAX_FAILED_LOGINS = 5;
const LOCK_MINUTES = 15;

if (current_user()) {
    redirect(is_verified() ? home_path_for_current_user() : 'auth/verify-notice.php');
}

$error = '';
if (is_post()) {
    csrf_verify();
    $email = mb_strtolower(post_param('email'));
    $password = $_POST['password'] ?? '';

    if ($email === '' || !is_string($password) || $password === '') {
        $error = 'Enter your email and password.';
    } else {
        $user = db_one('SELECT user_id, first_name, last_name, email, password_hash, is_active, failed_logins, locked_until FROM users WHERE email = ?', [$email]);

        if ($user && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $mins = (int) ceil((strtotime($user['locked_until']) - time()) / 60);
            $error = "Too many failed attempts. Try again in $mins minute(s).";
        } elseif ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['is_active']) {
                $error = 'This account is deactivated. Please contact the barangay hall.';
                log_activity('login_blocked_inactive', 'user', (int) $user['user_id'], $email, (int) $user['user_id']);
            } else {
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    db_exec('UPDATE users SET password_hash = ? WHERE user_id = ?', [password_hash($password, PASSWORD_DEFAULT), (int) $user['user_id']]);
                }
                db_exec('UPDATE users SET failed_logins = 0, locked_until = NULL WHERE user_id = ?', [(int) $user['user_id']]);
                login_user($user);
                log_activity('login', 'user', (int) $user['user_id'], 'Signed in', (int) $user['user_id']);

                $intended = $_SESSION['intended'] ?? '';
                unset($_SESSION['intended']);
                if (!is_verified(current_user(true))) {
                    redirect('auth/verify-notice.php');
                }
                // Only follow a same-site path that starts with the app base path.
                if (is_string($intended) && $intended !== '' && str_starts_with($intended, base_path() . '/') && !str_contains($intended, '//')) {
                    header('Location: ' . $intended);
                    exit;
                }
                redirect(home_path_for_current_user());
            }
        } else {
            if ($user) {
                $fails = (int) $user['failed_logins'] + 1;
                $lock = $fails >= MAX_FAILED_LOGINS ? date('Y-m-d H:i:s', time() + LOCK_MINUTES * 60) : null;
                db_exec('UPDATE users SET failed_logins = ?, locked_until = ? WHERE user_id = ?', [$lock ? 0 : $fails, $lock, (int) $user['user_id']]);
                log_activity('login_failed', 'user', (int) $user['user_id'], $lock ? 'Account locked for ' . LOCK_MINUTES . ' minutes' : "Failed attempt $fails", (int) $user['user_id']);
            } else {
                log_activity('login_failed', null, null, 'Unknown email: ' . mb_substr($email, 0, 120));
            }
            $error = 'The email or password is incorrect.';
        }
    }
}

$pageTitle = 'Sign in';
$activeNav = 'login';
require APP_ROOT . '/app/views/header.php';
?>
<div class="auth-wrap">
  <div class="page-head"><div><h1>Sign in</h1><p>Use the email and password of your E-Services account.</p></div></div>
  <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="panel" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label" for="email">Email</label>
      <input type="email" class="form-control" id="email" name="email" required autocomplete="email" autofocus value="<?= e(post_param('email')) ?>">
    </div>
    <div class="mb-4">
      <label class="form-label" for="password">Password</label>
      <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn btn-primary w-100">Sign in</button>
  </form>
  <p class="text-muted text-center">No account yet? <a href="<?= e(url('auth/register.php')) ?>">Create a resident account</a></p>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
