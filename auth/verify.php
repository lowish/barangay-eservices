<?php
require __DIR__ . '/../app/bootstrap.php';

/*
 * Verification link handler: auth/verify.php?token=<64 hex chars>
 * - unknown token      -> invalid
 * - token already used -> cannot be reused
 * - token expired      -> ask for a new link
 * - otherwise mark the token used and the account verified, in one transaction.
 */
$token = query_param('token');
$state = 'invalid';

if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $row = db_one(
        'SELECT ev.verification_id, ev.user_id, ev.expires_at, ev.used_at, u.email_verified_at
           FROM email_verifications ev JOIN users u ON u.user_id = ev.user_id
          WHERE ev.token_hash = ?',
        [hash('sha256', $token)]
    );
    if ($row) {
        if ($row['used_at'] !== null) {
            $state = $row['email_verified_at'] ? 'already' : 'replaced';
        } elseif (strtotime($row['expires_at']) < time()) {
            $state = 'expired';
        } else {
            $ok = db_transaction(function () use ($row) {
                $marked = db_exec('UPDATE email_verifications SET used_at = NOW() WHERE verification_id = ? AND used_at IS NULL', [(int) $row['verification_id']]);
                if ($marked !== 1) {
                    return false;
                }
                db_exec('UPDATE users SET email_verified_at = NOW() WHERE user_id = ? AND email_verified_at IS NULL', [(int) $row['user_id']]);
                return true;
            });
            $state = $ok ? 'verified' : 'already';
            if ($ok) {
                log_activity('email_verified', 'user', (int) $row['user_id'], 'Email address verified', (int) $row['user_id']);
            }
        }
    }
}

$signedIn = current_user();
if ($state === 'verified') {
    flash('success', 'Your email is verified. Your account is now active.');
    redirect($signedIn ? home_path_for_current_user() : 'auth/login.php');
}

$messages = [
    'invalid'  => ['This verification link is not valid', 'Check that you opened the full link from the latest email. You can request a new link after signing in.'],
    'already'  => ['This link was already used', 'Your email is already verified. You can sign in.'],
    'replaced' => ['This link is no longer valid', 'A newer verification email was sent, or this link was already used. Open the most recent email, or request a new link after signing in.'],
    'expired'  => ['This verification link has expired', 'Links are valid for ' . (int) config('verification_ttl_minutes', 60) . ' minutes. Sign in and request a new verification email.'],
];
[$title, $text] = $messages[$state];
$pageTitle = $title;
require APP_ROOT . '/app/views/header.php';
?>
<div class="auth-wrap">
  <div class="page-head"><div><h1><?= e($title) ?></h1></div></div>
  <div class="panel">
    <p><?= e($text) ?></p>
    <?php if ($signedIn): ?>
      <a class="btn btn-primary" href="<?= e(url(is_verified() ? home_path_for_current_user() : 'auth/verify-notice.php')) ?>">Continue</a>
    <?php else: ?>
      <a class="btn btn-primary" href="<?= e(url('auth/login.php')) ?>">Sign in</a>
    <?php endif; ?>
  </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
