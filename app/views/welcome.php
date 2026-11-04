<?php
/**
 * Dashboard welcome banner: barangay identity from Settings, a greeting, what is waiting, today's date.
 * Expects $welcomeMessage (plain text). Uses the signed-in user.
 */
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Magandang umaga' : ($hour < 18 ? 'Magandang hapon' : 'Magandang gabi');
$me = current_user();
?>
<section class="welcome" aria-labelledby="welcome-title">
  <?= render_seal('welcome-seal') ?>
  <div class="welcome-body">
    <h1 class="welcome-title" id="welcome-title"><?= e(barangay_display_name()) ?></h1>
    <?php if (barangay_location() !== ''): ?><p class="welcome-sub"><?= e(barangay_location()) ?></p><?php endif; ?>
    <p class="welcome-greet"><strong><?= e($greeting) ?>, <?= e($me['first_name']) ?>.</strong> <?= e($welcomeMessage ?? '') ?></p>
  </div>
  <div class="welcome-side">
    <div class="welcome-day"><?= e(date('l')) ?></div>
    <div class="welcome-date"><?= e(date('M j, Y')) ?></div>
    <?php if (office_hours() !== ''): ?><div class="welcome-hours">Office hours: <?= e(office_hours()) ?></div><?php endif; ?>
    <?php if (($me['role_key'] ?? '') === ROLE_ADMIN): ?>
      <a href="<?= e(url('admin/settings.php')) ?>">Edit settings</a>
    <?php endif; ?>
  </div>
</section>
