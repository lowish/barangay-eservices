<?php
require __DIR__ . '/../app/bootstrap.php';

// Barangay profile: name, location, office hours and logo shown across the system.
$admin = require_role(ROLE_ADMIN);
$current = barangay();

if (is_post()) {
    csrf_verify();
    $v = new Validator($_POST);
    $placeName = "/^[\\p{L}0-9 .,'()\\-ñÑ]+$/u";
    $v->required('barangay_name', 'Barangay name')->length('barangay_name', 'Barangay name', 2, 100)
      ->custom(preg_match($placeName, $v->value('barangay_name')) === 1 || $v->value('barangay_name') === '', 'barangay_name', 'Barangay name may only contain letters, numbers, spaces and . , \' ( ) -')
      ->required('municipality', 'City or municipality')->length('municipality', 'City or municipality', 2, 100)
      ->custom(preg_match($placeName, $v->value('municipality')) === 1 || $v->value('municipality') === '', 'municipality', 'City or municipality may only contain letters, numbers, spaces and . , \' ( ) -')
      ->required('province', 'Province')->length('province', 'Province', 2, 100)
      ->custom(preg_match($placeName, $v->value('province')) === 1 || $v->value('province') === '', 'province', 'Province may only contain letters, numbers, spaces and . , \' ( ) -')
      ->required('office_hours', 'Office hours')->length('office_hours', 'Office hours', 5, 150);

    // Validate the logo even when other fields fail, so all errors show at once.
    $newLogo = null;
    if (!$v->fails()) {
        [$newLogo, $logoError] = store_logo_upload($_FILES['logo'] ?? []);
        $v->custom($logoError === null, 'logo', (string) $logoError);
    } elseif (($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $v->custom(false, 'logo', 'Fix the other fields, then choose the logo again.');
    }
    if ($v->fails()) {
        back_with_errors($v->errors(), 'admin/settings.php');
    }

    $removeLogo = isset($_POST['remove_logo']) && !$newLogo;
    $logoPath = $newLogo ?? ($removeLogo ? null : $current['logo_path']);

    db_exec(
        'UPDATE barangay_settings SET barangay_name = ?, municipality = ?, province = ?, office_hours = ?, logo_path = ?, updated_by = ?
          WHERE settings_id = 1',
        [$v->value('barangay_name'), $v->value('municipality'), $v->value('province'), $v->value('office_hours'), $logoPath, (int) $admin['user_id']]
    );
    if (($newLogo || $removeLogo) && $current['logo_path'] !== $logoPath) {
        delete_logo_file($current['logo_path']);
    }

    $changed = [];
    foreach (['barangay_name' => 'name', 'municipality' => 'municipality', 'province' => 'province', 'office_hours' => 'office hours'] as $field => $label) {
        if ($v->value($field) !== (string) $current[$field]) {
            $changed[] = $label;
        }
    }
    if ($newLogo) { $changed[] = 'logo uploaded'; }
    if ($removeLogo && $current['logo_path']) { $changed[] = 'logo removed'; }
    log_activity('settings_updated', 'barangay_settings', 1, $changed ? 'Changed: ' . implode(', ', $changed) : 'Saved without changes');

    clear_old_input();
    flash('success', $changed ? 'Barangay settings were saved.' : 'No changes were made.');
    redirect('admin/settings.php');
}

$errors = take_errors();
$editor = $current['updated_at'] ? db_one('SELECT u.first_name, u.last_name FROM barangay_settings s JOIN users u ON u.user_id = s.updated_by WHERE s.settings_id = 1') : null;
$val = fn ($k) => old($k, $current[$k] ?? '');
$pageTitle = 'Barangay settings';
$activeNav = 'settings';
require APP_ROOT . '/app/views/header.php';
?>
<div class="page-head">
  <div>
    <h1>Barangay settings</h1>
    <p>These details appear in the navigation bar, dashboards, footer, emails and printed reports.</p>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <form method="post" enctype="multipart/form-data" class="panel" novalidate>
      <?= csrf_field() ?>
      <h2>Barangay profile</h2>
      <div class="mb-3">
        <label class="form-label required" for="barangay_name">Barangay name</label>
        <input class="form-control<?= invalid_class($errors, 'barangay_name') ?>" id="barangay_name" name="barangay_name" maxlength="100" required value="<?= e($val('barangay_name')) ?>"<?= aria_invalid($errors, 'barangay_name') ?>>
        <?= field_error($errors, 'barangay_name') ?: '<div class="form-text">For example <em>San Isidro</em>. "Barangay" is added automatically.</div>' ?>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-sm-6">
          <label class="form-label required" for="municipality">City or municipality</label>
          <input class="form-control<?= invalid_class($errors, 'municipality') ?>" id="municipality" name="municipality" maxlength="100" required value="<?= e($val('municipality')) ?>"<?= aria_invalid($errors, 'municipality') ?>>
          <?= field_error($errors, 'municipality') ?>
        </div>
        <div class="col-sm-6">
          <label class="form-label required" for="province">Province</label>
          <input class="form-control<?= invalid_class($errors, 'province') ?>" id="province" name="province" maxlength="100" required value="<?= e($val('province')) ?>"<?= aria_invalid($errors, 'province') ?>>
          <?= field_error($errors, 'province') ?>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label required" for="office_hours">Office hours</label>
        <input class="form-control<?= invalid_class($errors, 'office_hours') ?>" id="office_hours" name="office_hours" maxlength="150" required value="<?= e($val('office_hours')) ?>"<?= aria_invalid($errors, 'office_hours') ?>>
        <?= field_error($errors, 'office_hours') ?: '<div class="form-text">For example <em>Monday to Friday, 8:00 AM to 5:00 PM</em>.</div>' ?>
      </div>
      <div class="mb-3">
        <label class="form-label" for="logo">Logo</label>
        <input type="file" class="form-control<?= invalid_class($errors, 'logo') ?>" id="logo" name="logo" accept="image/png,image/jpeg,image/webp"<?= aria_invalid($errors, 'logo') ?>>
        <?= field_error($errors, 'logo') ?: '<div class="form-text">PNG, JPG or WebP, up to 2 MB. A square image works best. Leave empty to keep the current logo.</div>' ?>
      </div>
      <?php if ($current['logo_path']): ?>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="remove_logo" name="remove_logo" value="1">
          <label class="form-check-label" for="remove_logo">Remove the current logo and show the initials instead</label>
        </div>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary">Save settings</button>
    </form>
  </div>
  <div class="col-lg-5">
    <section class="panel">
      <h2>How it looks</h2>
      <div class="seal-preview">
        <?= render_seal('brand-seal') ?>
        <span class="brand-text">
          <span class="brand-name"><?= e(barangay_name()) ?></span>
          <span class="brand-sub">E-Services</span>
        </span>
      </div>
      <dl class="details mt-3 mb-0">
        <dt>Location</dt><dd><?= e(barangay_location()) ?></dd>
        <dt>Office hours</dt><dd><?= e(barangay()['office_hours']) ?></dd>
        <dt>Logo</dt><dd><?= $current['logo_path'] ? 'Uploaded' : 'None (initials ' . e(barangay_initials()) . ' are shown)' ?></dd>
        <dt>Last saved</dt><dd><?= e(format_datetime($current['updated_at'])) ?><?= $editor ? ' by ' . e(full_name($editor)) : '' ?></dd>
      </dl>
    </section>
    <p class="text-muted small">This system serves one barangay. Changing the name here renames it everywhere; existing requests and concerns are kept.</p>
  </div>
</div>
<?php clear_old_input(); require APP_ROOT . '/app/views/footer.php'; ?>
