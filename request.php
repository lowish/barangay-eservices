<?php
require __DIR__ . '/app/bootstrap.php';

$user = require_role(ROLE_RESIDENT, ROLE_STAFF, ROLE_ADMIN);
$id = (int) query_param('id');

$r = db_one(
    'SELECT dr.*, dt.name AS doc_name, dt.processing_days,
            u.first_name, u.last_name, u.email, u.contact_no, u.street_address, p.purok_name,
            h.first_name AS h_first_name, h.last_name AS h_last_name
       FROM document_requests dr
       JOIN document_types dt ON dt.doc_type_id = dr.doc_type_id
       JOIN users u ON u.user_id = dr.resident_id
       LEFT JOIN puroks p ON p.purok_id = u.purok_id
       LEFT JOIN users h ON h.user_id = dr.handled_by
      WHERE dr.request_id = ?',
    [$id]
);
// Ownership check: a resident opening someone else's request gets the same "not found" page
// as a missing record, so record ids cannot be probed.
if (!$r || ($user['role_key'] === ROLE_RESIDENT && (int) $r['resident_id'] !== (int) $user['user_id'])) {
    if ($r) {
        log_activity('access_denied', 'document_request', $id, 'Resident tried to open another resident\'s request');
    }
    not_found();
}

$history = db_all(
    'SELECT h.*, u.first_name, u.last_name, r.role_name
       FROM request_status_history h JOIN users u ON u.user_id = h.changed_by JOIN roles r ON r.role_id = u.role_id
      WHERE h.request_id = ? ORDER BY h.changed_at, h.history_id',
    [$id]
);
$requirements = db_all('SELECT requirement_text FROM document_requirements WHERE doc_type_id = ? ORDER BY sort_order', [(int) $r['doc_type_id']]);
$next = $r['is_archived'] ? [] : request_allowed_next($r, $user['role_key']);
$terminal = in_array($r['status'], ['Released', 'Rejected', 'Cancelled'], true);
$canArchive = $user['role_key'] !== ROLE_RESIDENT && $terminal && !$r['is_archived'];
$canRestore = $user['role_key'] === ROLE_ADMIN && $r['is_archived'];
$canEdit = $user['role_key'] === ROLE_RESIDENT && $r['status'] === 'Pending' && !$r['is_archived'];

$pageTitle = 'Request ' . $r['reference_no'];
$activeNav = 'requests';
require APP_ROOT . '/app/views/header.php';
?>
<div class="page-head">
  <div>
    <p class="mb-1"><a href="<?= e(url('requests.php')) ?>">Document requests</a></p>
    <h1><?= e($r['doc_name']) ?></h1>
    <p>Reference no. <?= e($r['reference_no']) ?><?= $user['role_key'] !== ROLE_RESIDENT ? ', requested by ' . e(full_name($r)) : '' ?></p>
  </div>
  <div class="page-actions">
    <?= status_badge($r['status']) ?>
    <?php if ($r['is_archived']): ?><span class="status status-muted">Archived</span><?php endif; ?>
  </div>
</div>

<?= render_tracker(REQUEST_STEPS, $r['status'], $history) ?>

<div class="row g-4">
  <div class="col-lg-7">
    <section class="panel">
      <h2>Request details</h2>
      <dl class="details">
        <dt>Document</dt><dd><?= e($r['doc_name']) ?></dd>
        <dt>Purpose</dt><dd><?= e($r['purpose']) ?></dd>
        <dt>Copies</dt><dd><?= (int) $r['copies'] ?></dd>
        <dt>Fee</dt><dd><?= e(peso($r['fee_amount'])) ?> <span class="text-muted small">payable on release</span></dd>
        <dt>Submitted</dt><dd><?= e(format_datetime($r['created_at'])) ?></dd>
        <dt>Processing time</dt><dd><?= (int) $r['processing_days'] ?> working day(s) after approval</dd>
        <?php if ($r['remarks']): ?><dt>Latest remarks</dt><dd><?= e($r['remarks']) ?></dd><?php endif; ?>
        <?php if ($r['h_first_name']): ?><dt>Handled by</dt><dd><?= e(full_name($r, 'h_')) ?></dd><?php endif; ?>
      </dl>
      <?php if ($canEdit): ?>
        <a class="btn btn-sm btn-outline-primary mt-3" href="<?= e(url('resident/request-form.php?id=' . $id)) ?>">Edit request</a>
      <?php endif; ?>
    </section>

    <?php if ($user['role_key'] !== ROLE_RESIDENT): ?>
    <section class="panel">
      <h2>Resident</h2>
      <dl class="details">
        <dt>Name</dt><dd><?= e(full_name($r)) ?></dd>
        <dt>Address</dt><dd><?= e(trim(($r['street_address'] ?? '') . ', ' . ($r['purok_name'] ?? ''), ', ')) ?></dd>
        <dt>Email</dt><dd><?= e($r['email']) ?></dd>
        <dt>Mobile</dt><dd><?= e($r['contact_no']) ?></dd>
      </dl>
    </section>
    <?php endif; ?>

    <section class="panel">
      <h2>Bring when claiming</h2>
      <ul class="mb-0">
        <?php foreach ($requirements as $req): ?><li><?= e($req['requirement_text']) ?></li><?php endforeach; ?>
        <?php if (!$requirements): ?><li>No additional requirements.</li><?php endif; ?>
      </ul>
    </section>
  </div>

  <div class="col-lg-5">
    <?php if ($next): ?>
    <section class="panel">
      <h2><?= $user['role_key'] === ROLE_RESIDENT ? 'Actions' : 'Update status' ?></h2>
      <form method="post" action="<?= e(url('request-action.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="expected" value="<?= e($r['status']) ?>">
        <?php if ($user['role_key'] === ROLE_STAFF): ?>
        <div class="mb-3">
          <label class="form-label" for="remarks">Remarks <span class="text-muted fw-normal">(required when rejecting)</span></label>
          <textarea class="form-control" id="remarks" name="remarks" rows="3" maxlength="500" data-counter="remarks-count"></textarea>
          <div class="form-text" id="remarks-count"></div>
        </div>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($next as $to): ?>
            <?php $danger = in_array($to, ['Rejected', 'Cancelled'], true); ?>
            <button type="submit" name="to" value="<?= e($to) ?>" class="btn <?= $danger ? 'btn-outline-danger' : 'btn-primary' ?>"
              <?= $danger ? 'data-confirm="' . e(request_action_label($to) . ' ' . $r['reference_no'] . '? This cannot be undone.') . '"' : '' ?>>
              <?= e(request_action_label($to)) ?>
            </button>
          <?php endforeach; ?>
        </div>
      </form>
    </section>
    <?php endif; ?>

    <?php if ($canArchive || $canRestore): ?>
    <section class="panel">
      <h2>Record</h2>
      <form method="post" action="<?= e(url('request-action.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <?php if ($canArchive): ?>
          <p class="text-muted small">Archiving hides this completed request from the active list. It stays in reports and history.</p>
          <button type="submit" name="op" value="archive" class="btn btn-outline-secondary" data-confirm="Archive request <?= e($r['reference_no']) ?>?">Archive request</button>
        <?php else: ?>
          <button type="submit" name="op" value="restore" class="btn btn-outline-secondary">Restore to active list</button>
        <?php endif; ?>
      </form>
    </section>
    <?php endif; ?>

    <section class="panel">
      <h2>History</h2>
      <ol class="history">
        <?php foreach (array_reverse($history) as $h): ?>
          <li>
            <div><?= $h['from_status'] ? e($h['from_status']) . ' → ' : '' ?><strong><?= e($h['to_status']) ?></strong></div>
            <div class="history-meta"><?= e(format_datetime($h['changed_at'])) ?> by <?= e(full_name($h)) ?> (<?= e($h['role_name']) ?>)</div>
            <?php if ($h['remarks']): ?><p class="history-note"><?= e($h['remarks']) ?></p><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
