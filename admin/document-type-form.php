<?php
require __DIR__ . '/../app/bootstrap.php';

$user = require_role(ROLE_ADMIN);
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$existing = $id ? db_one('SELECT * FROM document_types WHERE doc_type_id = ?', [$id]) : null;
if ($id && !$existing) {
    not_found();
}

if (is_post()) {
    csrf_verify();
    $v = new Validator($_POST);
    $v->required('name', 'Name')->length('name', 'Name', 3, 100)
      ->required('description', 'Description')->length('description', 'Description', 10, 500)
      ->required('fee', 'Fee')->decimalBetween('fee', 'Fee', 0, 5000)
      ->required('processing_days', 'Processing days')->intBetween('processing_days', 'Processing days', 1, 30);

    $requirements = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($_POST['requirements'] ?? '')))));
    $v->custom(count($requirements) <= 15, 'requirements', 'List at most 15 requirements.');
    foreach ($requirements as $line) {
        $v->custom(mb_strlen($line) <= 200, 'requirements', 'Each requirement must be 200 characters or fewer.');
    }
    if (!$v->fails() && db_value('SELECT 1 FROM document_types WHERE name = ? AND doc_type_id <> ?', [$v->value('name'), $id])) {
        $v->custom(false, 'name', 'Another document type already has this name.');
    }
    if ($v->fails()) {
        back_with_errors($v->errors(), 'admin/document-type-form.php' . ($id ? '?id=' . $id : ''));
    }

    $savedId = db_transaction(function () use ($v, $id, $existing, $requirements) {
        $fields = [$v->value('name'), $v->value('description'), (float) $v->value('fee'), (int) $v->value('processing_days')];
        if ($existing) {
            db_exec('UPDATE document_types SET name = ?, description = ?, fee = ?, processing_days = ? WHERE doc_type_id = ?', array_merge($fields, [$id]));
            db_exec('DELETE FROM document_requirements WHERE doc_type_id = ?', [$id]);
        } else {
            $id = db_insert('INSERT INTO document_types (name, description, fee, processing_days) VALUES (?, ?, ?, ?)', $fields);
        }
        foreach ($requirements as $i => $text) {
            db_query('INSERT INTO document_requirements (doc_type_id, requirement_text, sort_order) VALUES (?, ?, ?)', [$id, $text, $i + 1]);
        }
        return $id;
    });
    log_activity($existing ? 'document_type_updated' : 'document_type_created', 'document_type', $savedId, $v->value('name'));
    clear_old_input();
    flash('success', '"' . $v->value('name') . '" was ' . ($existing ? 'updated.' : 'added.'));
    redirect('admin/document-types.php');
}

$errors = take_errors();
$reqText = $existing
    ? implode("\n", array_column(db_all('SELECT requirement_text FROM document_requirements WHERE doc_type_id = ? ORDER BY sort_order', [$id]), 'requirement_text'))
    : '';
$pageTitle = $existing ? 'Edit ' . $existing['name'] : 'Add document type';
$activeNav = 'doctypes';
require APP_ROOT . '/app/views/header.php';
?>
<div class="form-narrow">
  <div class="page-head"><div><p class="mb-1"><a href="<?= e(url('admin/document-types.php')) ?>">Document types</a></p><h1><?= e($pageTitle) ?></h1></div></div>
  <form method="post" class="panel" novalidate>
    <?= csrf_field() ?>
    <?php if ($existing): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
    <div class="mb-3">
      <label class="form-label required" for="name">Name</label>
      <input class="form-control<?= invalid_class($errors, 'name') ?>" id="name" name="name" maxlength="100" required value="<?= e(old('name', $existing['name'] ?? '')) ?>"<?= aria_invalid($errors, 'name') ?>>
      <?= field_error($errors, 'name') ?>
    </div>
    <div class="mb-3">
      <label class="form-label required" for="description">Description</label>
      <textarea class="form-control<?= invalid_class($errors, 'description') ?>" id="description" name="description" rows="3" maxlength="500" required<?= aria_invalid($errors, 'description') ?>><?= e(old('description', $existing['description'] ?? '')) ?></textarea>
      <?= field_error($errors, 'description') ?>
    </div>
    <div class="row g-3 mb-3">
      <div class="col-sm-6">
        <label class="form-label required" for="fee">Fee per copy (₱)</label>
        <input class="form-control<?= invalid_class($errors, 'fee') ?>" id="fee" name="fee" inputmode="decimal" required value="<?= e(old('fee', $existing['fee'] ?? '0.00')) ?>"<?= aria_invalid($errors, 'fee') ?>>
        <?= field_error($errors, 'fee') ?: '<div class="form-text">Enter 0 for free documents.</div>' ?>
      </div>
      <div class="col-sm-6">
        <label class="form-label required" for="processing_days">Processing days</label>
        <input type="number" min="1" max="30" class="form-control<?= invalid_class($errors, 'processing_days') ?>" id="processing_days" name="processing_days" required value="<?= e(old('processing_days', $existing['processing_days'] ?? '1')) ?>"<?= aria_invalid($errors, 'processing_days') ?>>
        <?= field_error($errors, 'processing_days') ?>
      </div>
    </div>
    <div class="mb-4">
      <label class="form-label" for="requirements">Requirements</label>
      <textarea class="form-control<?= invalid_class($errors, 'requirements') ?>" id="requirements" name="requirements" rows="5"<?= aria_invalid($errors, 'requirements') ?>><?= e(old('requirements', $reqText)) ?></textarea>
      <?= field_error($errors, 'requirements') ?: '<div class="form-text">One requirement per line, in the order residents should prepare them.</div>' ?>
    </div>
    <button type="submit" class="btn btn-primary"><?= $existing ? 'Save changes' : 'Add document type' ?></button>
    <a class="btn btn-link" href="<?= e(url('admin/document-types.php')) ?>">Cancel</a>
  </form>
</div>
<?php clear_old_input(); require APP_ROOT . '/app/views/footer.php'; ?>
