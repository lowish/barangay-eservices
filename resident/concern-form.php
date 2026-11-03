<?php
require __DIR__ . '/../app/bootstrap.php';

// Report a new community concern, or edit one while it is still Submitted (owner only).
$user = require_role(ROLE_RESIDENT);
$uid = (int) $user['user_id'];
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$existing = null;
const DAILY_LIMIT = 5;

if ($id) {
    $existing = db_one('SELECT * FROM concerns WHERE concern_id = ? AND resident_id = ?', [$id, $uid]);
    if (!$existing) {
        not_found();
    }
    if ($existing['status'] !== 'Submitted' || $existing['is_archived']) {
        flash('warning', 'This report can no longer be edited because the barangay has started reviewing it.');
        redirect('concern.php?id=' . $id);
    }
}

if (is_post()) {
    csrf_verify();
    $v = new Validator($_POST);
    $v->required('category_id', 'Category')
      ->exists('category_id', 'category', 'SELECT 1 FROM concern_categories WHERE category_id = ? AND is_archived = 0')
      ->required('purok_id', 'Purok')->exists('purok_id', 'purok', 'SELECT 1 FROM puroks WHERE purok_id = ?')
      ->required('location', 'Exact location')->length('location', 'Exact location', 5, 200)
      ->required('title', 'Short title')->length('title', 'Short title', 5, 120)
      ->required('description', 'Description')->length('description', 'Description', 15, 2000);

    if (!$existing) {
        $today = (int) db_value('SELECT COUNT(*) FROM concerns WHERE resident_id = ? AND created_at >= CURDATE()', [$uid]);
        $v->custom($today < DAILY_LIMIT, 'title', 'You can submit up to ' . DAILY_LIMIT . ' reports per day. For emergencies, call the barangay hall.');
    }
    if ($v->fails()) {
        back_with_errors($v->errors(), 'resident/concern-form.php' . ($id ? '?id=' . $id : ''));
    }

    $categoryId = (int) $v->value('category_id');
    $priority = (int) db_value('SELECT default_priority FROM concern_categories WHERE category_id = ?', [$categoryId]);
    $fields = [$categoryId, (int) $v->value('purok_id'), $v->value('title'), $v->value('description'), $v->value('location')];

    if ($existing) {
        $changed = db_exec(
            "UPDATE concerns SET category_id = ?, purok_id = ?, title = ?, description = ?, location = ?, priority = ?
              WHERE concern_id = ? AND resident_id = ? AND status = 'Submitted'",
            array_merge($fields, [$priority, $id, $uid])
        );
        log_activity('concern_updated', 'concern', $id, "{$existing['reference_no']} edited by resident");
        flash($changed ? 'success' : 'info', $changed ? 'Your report was updated.' : 'No changes were made.');
        clear_old_input();
        redirect('concern.php?id=' . $id);
    }

    $newId = db_transaction(function () use ($uid, $fields, $priority) {
        $newId = db_insert(
            'INSERT INTO concerns (resident_id, category_id, purok_id, title, description, location, priority) VALUES (?, ?, ?, ?, ?, ?, ?)',
            array_merge([$uid], $fields, [$priority])
        );
        db_exec("UPDATE concerns SET reference_no = CONCAT('CON-', YEAR(created_at), '-', LPAD(concern_id, 5, '0')) WHERE concern_id = ?", [$newId]);
        db_query("INSERT INTO concern_updates (concern_id, from_status, to_status, note, changed_by) VALUES (?, NULL, 'Submitted', 'Concern reported', ?)", [$newId, $uid]);
        return $newId;
    });
    $ref = db_value('SELECT reference_no FROM concerns WHERE concern_id = ?', [$newId]);
    log_activity('concern_created', 'concern', $newId, "$ref reported");
    clear_old_input();
    flash('success', "Your concern was reported. Your reference number is $ref.");
    redirect('concern.php?id=' . $newId);
}

$errors = take_errors();
$categories = db_all('SELECT category_id, name, description FROM concern_categories WHERE is_archived = 0 ORDER BY name');
$puroks = db_all('SELECT purok_id, purok_name FROM puroks ORDER BY purok_id');
$selCat = (string) old('category_id', $existing['category_id'] ?? '');
$selPurok = (string) old('purok_id', $existing['purok_id'] ?? $user['purok_id'] ?? '');
$pageTitle = $existing ? 'Edit concern ' . $existing['reference_no'] : 'Report a concern';
$activeNav = 'concerns';
require APP_ROOT . '/app/views/header.php';
?>
<div class="form-narrow">
  <div class="page-head">
    <div>
      <p class="mb-1"><a href="<?= e(url('concerns.php')) ?>">My concerns</a></p>
      <h1><?= e($pageTitle) ?></h1>
      <p>Tell the barangay what the problem is and exactly where it is. For fires, crimes and medical emergencies, call 911.</p>
    </div>
  </div>
  <form method="post" class="panel" novalidate>
    <?= csrf_field() ?>
    <?php if ($existing): ?><input type="hidden" name="id" value="<?= (int) $existing['concern_id'] ?>"><?php endif; ?>
    <div class="mb-3">
      <label class="form-label required" for="category_id">Category</label>
      <select class="form-select<?= invalid_class($errors, 'category_id') ?>" id="category_id" name="category_id" required<?= aria_invalid($errors, 'category_id') ?>>
        <option value="">Choose a category…</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['category_id'] ?>" <?= $selCat === (string) $c['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?= field_error($errors, 'category_id') ?>
    </div>
    <div class="mb-3">
      <label class="form-label required" for="title">Short title</label>
      <input class="form-control<?= invalid_class($errors, 'title') ?>" id="title" name="title" maxlength="120" required placeholder="e.g. Streetlight not working near the chapel" value="<?= e(old('title', $existing['title'] ?? '')) ?>"<?= aria_invalid($errors, 'title') ?>>
      <?= field_error($errors, 'title') ?>
    </div>
    <div class="row g-3 mb-3">
      <div class="col-sm-5">
        <label class="form-label required" for="purok_id">Purok</label>
        <select class="form-select<?= invalid_class($errors, 'purok_id') ?>" id="purok_id" name="purok_id" required<?= aria_invalid($errors, 'purok_id') ?>>
          <option value="">Choose…</option>
          <?php foreach ($puroks as $p): ?>
            <option value="<?= (int) $p['purok_id'] ?>" <?= $selPurok === (string) $p['purok_id'] ? 'selected' : '' ?>><?= e($p['purok_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'purok_id') ?>
      </div>
      <div class="col-sm-7">
        <label class="form-label required" for="location">Exact location or landmark</label>
        <input class="form-control<?= invalid_class($errors, 'location') ?>" id="location" name="location" maxlength="200" required placeholder="e.g. Corner of Mabini St. and Rizal St." value="<?= e(old('location', $existing['location'] ?? '')) ?>"<?= aria_invalid($errors, 'location') ?>>
        <?= field_error($errors, 'location') ?>
      </div>
    </div>
    <div class="mb-4">
      <label class="form-label required" for="description">Description</label>
      <textarea class="form-control<?= invalid_class($errors, 'description') ?>" id="description" name="description" rows="5" maxlength="2000" required data-counter="desc-count"<?= aria_invalid($errors, 'description') ?>><?= e(old('description', $existing['description'] ?? '')) ?></textarea>
      <?= field_error($errors, 'description') ?: '<div class="form-text">What is happening, since when, and who is affected. <span id="desc-count"></span></div>' ?>
    </div>
    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary"><?= $existing ? 'Save changes' : 'Submit report' ?></button>
      <a class="btn btn-link" href="<?= e(url($existing ? 'concern.php?id=' . (int) $existing['concern_id'] : 'concerns.php')) ?>">Cancel</a>
    </div>
  </form>
</div>
<?php clear_old_input(); require APP_ROOT . '/app/views/footer.php'; ?>
