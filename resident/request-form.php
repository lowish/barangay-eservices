<?php
require __DIR__ . '/../app/bootstrap.php';

// Create a new document request, or edit one while it is still Pending (owner only).
$user = require_role(ROLE_RESIDENT);
$uid = (int) $user['user_id'];
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$existing = null;

if ($id) {
    $existing = db_one('SELECT * FROM document_requests WHERE request_id = ? AND resident_id = ?', [$id, $uid]);
    if (!$existing) {
        not_found();
    }
    if ($existing['status'] !== 'Pending' || $existing['is_archived']) {
        flash('warning', 'This request can no longer be edited because the barangay has started reviewing it.');
        redirect('request.php?id=' . $id);
    }
}

if (is_post()) {
    csrf_verify();
    $v = new Validator($_POST);
    $v->required('doc_type_id', 'Document')
      ->exists('doc_type_id', 'document', 'SELECT 1 FROM document_types WHERE doc_type_id = ? AND is_archived = 0')
      ->required('purpose', 'Purpose')->length('purpose', 'Purpose', 5, 255)
      ->required('copies', 'Number of copies')->intBetween('copies', 'Number of copies', 1, 5);

    $typeId = (int) $v->value('doc_type_id');
    // Business rule: one open request per document type at a time.
    if (!$v->fails()) {
        $open = db_value(
            "SELECT reference_no FROM document_requests
              WHERE resident_id = ? AND doc_type_id = ? AND request_id <> ?
                AND status NOT IN ('Released', 'Rejected', 'Cancelled')",
            [$uid, $typeId, $id]
        );
        $v->custom(!$open, 'doc_type_id', "You already have an open request for this document ($open). Wait for it to be released or cancel it first.");
    }
    if ($v->fails()) {
        back_with_errors($v->errors(), 'resident/request-form.php' . ($id ? '?id=' . $id : ''));
    }

    $fee = (float) db_value('SELECT fee FROM document_types WHERE doc_type_id = ?', [$typeId]);

    if ($existing) {
        $changed = db_exec(
            "UPDATE document_requests SET doc_type_id = ?, purpose = ?, copies = ?, fee_amount = ?
              WHERE request_id = ? AND resident_id = ? AND status = 'Pending'",
            [$typeId, $v->value('purpose'), (int) $v->value('copies'), $fee * (int) $v->value('copies'), $id, $uid]
        );
        log_activity('request_updated', 'document_request', $id, "{$existing['reference_no']} edited by resident");
        flash($changed ? 'success' : 'info', $changed ? 'Your request was updated.' : 'No changes were made.');
        clear_old_input();
        redirect('request.php?id=' . $id);
    }

    $newId = db_transaction(function () use ($uid, $typeId, $v, $fee) {
        $newId = db_insert(
            'INSERT INTO document_requests (resident_id, doc_type_id, purpose, copies, fee_amount) VALUES (?, ?, ?, ?, ?)',
            [$uid, $typeId, $v->value('purpose'), (int) $v->value('copies'), $fee * (int) $v->value('copies')]
        );
        db_exec("UPDATE document_requests SET reference_no = CONCAT('REQ-', YEAR(created_at), '-', LPAD(request_id, 5, '0')) WHERE request_id = ?", [$newId]);
        db_query("INSERT INTO request_status_history (request_id, from_status, to_status, remarks, changed_by) VALUES (?, NULL, 'Pending', 'Request submitted', ?)", [$newId, $uid]);
        return $newId;
    });
    $ref = db_value('SELECT reference_no FROM document_requests WHERE request_id = ?', [$newId]);
    log_activity('request_created', 'document_request', $newId, "$ref submitted");
    clear_old_input();
    flash('success', "Your request was submitted. Your reference number is $ref.");
    redirect('request.php?id=' . $newId);
}

$errors = take_errors();
$docTypes = db_all('SELECT doc_type_id, name, fee, processing_days FROM document_types WHERE is_archived = 0 ORDER BY name');
$selectedType = (string) old('doc_type_id', $existing['doc_type_id'] ?? query_param('type'));
$pageTitle = $existing ? 'Edit request ' . $existing['reference_no'] : 'Request a document';
$activeNav = 'requests';
require APP_ROOT . '/app/views/header.php';
?>
<div class="form-narrow">
  <div class="page-head">
    <div>
      <p class="mb-1"><a href="<?= e(url('requests.php')) ?>">My requests</a></p>
      <h1><?= e($pageTitle) ?></h1>
      <p>The fee is paid at the barangay hall when you claim the document. <a href="<?= e(url('services.php')) ?>">See requirements for each document</a>.</p>
    </div>
  </div>
  <form method="post" class="panel" novalidate>
    <?= csrf_field() ?>
    <?php if ($existing): ?><input type="hidden" name="id" value="<?= (int) $existing['request_id'] ?>"><?php endif; ?>
    <div class="mb-3">
      <label class="form-label required" for="doc_type_id">Document</label>
      <select class="form-select<?= invalid_class($errors, 'doc_type_id') ?>" id="doc_type_id" name="doc_type_id" required<?= aria_invalid($errors, 'doc_type_id') ?>>
        <option value="">Choose a document…</option>
        <?php foreach ($docTypes as $d): ?>
          <option value="<?= (int) $d['doc_type_id'] ?>" <?= $selectedType === (string) $d['doc_type_id'] ? 'selected' : '' ?>>
            <?= e($d['name']) ?> (<?= e(peso($d['fee'])) ?>, <?= (int) $d['processing_days'] ?> day<?= $d['processing_days'] > 1 ? 's' : '' ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <?= field_error($errors, 'doc_type_id') ?>
    </div>
    <div class="mb-3">
      <label class="form-label required" for="purpose">Purpose</label>
      <input class="form-control<?= invalid_class($errors, 'purpose') ?>" id="purpose" name="purpose" maxlength="255" required placeholder="e.g. Employment requirement at ABC Company" value="<?= e(old('purpose', $existing['purpose'] ?? '')) ?>"<?= aria_invalid($errors, 'purpose') ?>>
      <?= field_error($errors, 'purpose') ?: '<div class="form-text">Where you will use the document. 5 to 255 characters.</div>' ?>
    </div>
    <div class="mb-4">
      <label class="form-label required" for="copies">Number of copies</label>
      <input type="number" class="form-control w-auto<?= invalid_class($errors, 'copies') ?>" id="copies" name="copies" min="1" max="5" required value="<?= e(old('copies', $existing['copies'] ?? '1')) ?>"<?= aria_invalid($errors, 'copies') ?>>
      <?= field_error($errors, 'copies') ?: '<div class="form-text">1 to 5 copies. The fee is per copy.</div>' ?>
    </div>
    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary"><?= $existing ? 'Save changes' : 'Submit request' ?></button>
      <a class="btn btn-link" href="<?= e(url($existing ? 'request.php?id=' . (int) $existing['request_id'] : 'requests.php')) ?>">Cancel</a>
    </div>
  </form>
</div>
<?php clear_old_input(); require APP_ROOT . '/app/views/footer.php'; ?>
