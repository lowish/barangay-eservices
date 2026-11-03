<?php
require __DIR__ . '/../app/bootstrap.php';

// Signed-in but unverified users land here; every other protected page sends them back here.
$user = require_login(false);
if (is_verified($user)) {
    redirect(home_path_for_current_user());
}

if (is_post()) {
    csrf_verify();
    $cooldown = (int) config('resend_cooldown_seconds', 60);
    $last = db_value('SELECT MAX(created_at) FROM email_verifications WHERE user_id = ?', [(int) $user['user_id']]);
    $wait = $last ? $cooldown - (time() - strtotime($last)) : 0;
    if ($wait > 0) {
        flash('warning', "Please wait $wait more second(s) before requesting another email.");
    } elseif (send_verification_email($user)) {
        flash('success', 'A new verification link was sent to ' . $user['email'] . '. Earlier links no longer work.');
    } else {
        flash('danger', 'The email could not be sent right now. Please try again in a few minutes.');
    }
    redirect('auth/verify-notice.php');
}

$pageTitle = 'Verify your email';
$activeNav = 'verify';
require APP_ROOT . '/app/views/header.php';
?>
<div class="form-narrow mx-auto">
  <div class="page-head"><div><h1>Verify your email</h1><p>Your account is created but not yet active.</p></div></div>
  <div class="panel">
    <dl class="details mb-3">
      <dt>Email</dt><dd><?= e($user['email']) ?></dd>
      <dt>Status</dt><dd><span class="status status-ready">Not verified</span></dd>
    </dl>
    <p>Open the email we sent and select <strong>Verify my email</strong>. Until then you cannot submit document requests or report concerns. Check your spam folder if you do not see it.</p>
    <form method="post">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-outline-primary">Resend verification email</button>
    </form>
  </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
