<?php
require __DIR__ . '/app/bootstrap.php';
require_once APP_ROOT . '/app/lib/ConcernTriageQueue.php';

$user = require_role(ROLE_RESIDENT, ROLE_STAFF, ROLE_ADMIN);
$id = (int) query_param('id');

$c = db_one(
    'SELECT c.*, cc.name AS category_name, p.purok_name,
            u.first_name, u.last_name, u.email, u.contact_no,
            a.first_name AS a_first_name, a.last_name AS a_last_name
       FROM concerns c
       JOIN concern_categories cc ON cc.category_id = c.category_id
       JOIN puroks p ON p.purok_id = c.purok_id
       JOIN users u ON u.user_id = c.resident_id
       LEFT JOIN users a ON a.user_id = c.assigned_to
      WHERE c.concern_id = ?',
    [$id]
);
if (!$c || ($user['role_key'] === ROLE_RESIDENT && (int) $c['resident_id'] !== (int) $user['user_id'])) {
    if ($c) {
        log_activity('access_denied', 'concern', $id, 'Resident tried to open another resident\'s concern');
    }
    not_found();
}

$history = db_all(
    'SELECT h.*, u.first_name, u.last_name, r.role_name
       FROM concern_updates h JOIN users u ON u.user_id = h.changed_by JOIN roles r ON r.role_id = u.role_id
      WHERE h.concern_id = ? ORDER BY h.changed_at, h.update_id',
    [$id]
);
$next = $c['is_archived'] ? [] : concern_allowed_next($c, $user);
$isStaff = $user['role_key'] === ROLE_STAFF;
$staffList = $isStaff ? db_all(
    'SELECT u.user_id, u.first_name, u.last_name,
            (SELECT COUNT(*) FROM concerns x WHERE x.assigned_to = u.user_id AND x.status IN (\'Assigned\', \'In Progress\')) AS open_count
       FROM users u JOIN roles r ON r.role_id = u.role_id
      WHERE r.role_key = ? AND u.is_active = 1 AND u.email_verified_at IS NOT NULL ORDER BY u.first_name',
    [ROLE_STAFF]
) : [];
$canReassign = $isStaff && in_array($c['status'], ['Assigned', 'In Progress'], true) && !$c['is_archived'];
$terminal = in_array($c['status'], ['Closed', 'Rejected', 'Withdrawn'], true);
$canArchive = $user['role_key'] !== ROLE_RESIDENT && $terminal && !$c['is_archived'];
$canRestore = $user['role_key'] === ROLE_ADMIN && $c['is_archived'];
$canEdit = $user['role_key'] === ROLE_RESIDENT && $c['status'] === 'Submitted' && !$c['is_archived'];
$effective = ConcernTriageQueue::effectivePriority((int) $c['priority'], $c['created_at']);

$pageTitle = 'Concern ' . $c['reference_no'];
$activeNav = 'concerns';
require APP_ROOT . '/app/views/header.php';
?>
<div class="page-head">
  <div>
    <p class="mb-1"><a href="<?= e(url('concerns.php')) ?>">Community concerns</a></p>
    <h1><?= e($c['title']) ?></h1>
    <p>Reference no. <?= e($c['reference_no']) ?>, reported <?= e(format_datetime($c['created_at'])) ?></p>
  </div>
  <div class="page-actions">
    <?= priority_badge((int) $c['priority']) ?>
    <?= status_badge($c['status']) ?>
    <?php if ($c['is_archived']): ?><span class="status status-muted">Archived</span><?php endif; ?>
  </div>
</div>

<?= render_tracker(CONCERN_STEPS, $c['status'], $history) ?>

<div class="row g-4">
  <div class="col-lg-7">
    <section class="panel">
      <h2>Report</h2>
      <dl class="details">
        <dt>Category</dt><dd><?= e($c['category_name']) ?></dd>
        <dt>Location</dt><dd><?= e($c['location']) ?>, <?= e($c['purok_name']) ?></dd>
        <dt>Description</dt><dd class="history-note"><?= e($c['description']) ?></dd>
        <dt>Priority</dt><dd><?= priority_badge((int) $c['priority']) ?>
          <?php if (!$terminal && $effective > (int) $c['priority'] && $user['role_key'] !== ROLE_RESIDENT): ?>
            <span class="small text-muted">raised to <?= e(PRIORITY_LABELS[$effective]) ?> in the queue because it has waited <?= (int) floor((time() - strtotime($c['created_at'])) / 86400) ?> days</span>
          <?php endif; ?></dd>
        <dt>Assigned to</dt><dd><?= $c['a_first_name'] ? e(full_name($c, 'a_')) : 'Not yet assigned' ?></dd>
        <?php if ($c['resolution']): ?><dt>Resolution</dt><dd class="history-note"><?= e($c['resolution']) ?></dd><?php endif; ?>
        <?php if ($user['role_key'] !== ROLE_RESIDENT): ?>
          <dt>Reported by</dt><dd><?= e(full_name($c)) ?>, <?= e($c['contact_no']) ?>, <?= e($c['email']) ?></dd>
        <?php endif; ?>
      </dl>
      <?php if ($canEdit): ?>
        <a class="btn btn-sm btn-outline-primary mt-3" href="<?= e(url('resident/concern-form.php?id=' . $id)) ?>">Edit report</a>
      <?php endif; ?>
    </section>
  </div>

  <div class="col-lg-5">
    <?php if ($next): ?>
    <section class="panel">
      <h2><?= $isStaff ? 'Update status' : 'Actions' ?></h2>
      <form method="post" action="<?= e(url('concern-action.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="expected" value="<?= e($c['status']) ?>">
        <?php if (in_array('Assigned', $next, true)): ?>
          <div class="mb-3">
            <label class="form-label" for="assignee">Assign to</label>
            <select class="form-select" id="assignee" name="assignee">
              <?php foreach ($staffList as $s): ?>
                <option value="<?= (int) $s['user_id'] ?>" <?= (int) $s['user_id'] === (int) $user['user_id'] ? 'selected' : '' ?>><?= e(full_name($s)) ?> (<?= (int) $s['open_count'] ?> open)</option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <?php if (array_intersect(['Under Review', 'Assigned'], $next)): ?>
          <div class="mb-3">
            <label class="form-label" for="priority">Priority</label>
            <select class="form-select" id="priority" name="priority">
              <?php foreach (PRIORITY_LABELS as $k => $label): ?>
                <option value="<?= $k ?>" <?= (int) $c['priority'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Set during review. Urgent concerns are handed out first in the triage queue.</div>
          </div>
        <?php endif; ?>
        <?php if ($isStaff): ?>
          <div class="mb-3">
            <label class="form-label" for="note"><?= in_array('Resolved', $next, true) ? 'How was it resolved?' : 'Note' ?>
              <span class="text-muted fw-normal"><?= in_array('Resolved', $next, true) ? '(required)' : (in_array('Rejected', $next, true) ? '(required when rejecting)' : '(optional)') ?></span></label>
            <textarea class="form-control" id="note" name="note" rows="3" maxlength="1000" data-counter="note-count"></textarea>
            <div class="form-text" id="note-count"></div>
          </div>
        <?php endif; ?>
        <?php if ($c['status'] === 'Resolved' && !$isStaff): ?>
          <p class="text-muted small">Close this concern if the problem has been fixed.</p>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($next as $to): ?>
            <?php $danger = in_array($to, ['Rejected', 'Withdrawn'], true); ?>
            <button type="submit" name="to" value="<?= e($to) ?>" class="btn <?= $danger ? 'btn-outline-danger' : 'btn-primary' ?>"
              <?= $danger ? 'data-confirm="' . e(concern_action_label($to) . ' ' . $c['reference_no'] . '? This cannot be undone.') . '"' : '' ?>>
              <?= e(concern_action_label($to)) ?>
            </button>
          <?php endforeach; ?>
        </div>
      </form>
    </section>
    <?php elseif ($isStaff && in_array($c['status'], ['Assigned', 'In Progress'], true)): ?>
      <div class="alert alert-info" role="status">Only <?= e(full_name($c, 'a_')) ?> can update this concern while it is assigned to them. You can reassign it below.</div>
    <?php endif; ?>

    <?php if ($canReassign): ?>
    <section class="panel">
      <h2>Reassign</h2>
      <form method="post" action="<?= e(url('concern-action.php')) ?>" class="d-flex flex-wrap gap-2 align-items-end">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="op" value="reassign">
        <div class="flex-grow-1">
          <label class="form-label" for="reassign_to">New assignee</label>
          <select class="form-select" id="reassign_to" name="assignee">
            <?php foreach ($staffList as $s): ?>
              <?php if ((int) $s['user_id'] !== (int) $c['assigned_to']): ?>
                <option value="<?= (int) $s['user_id'] ?>"><?= e(full_name($s)) ?> (<?= (int) $s['open_count'] ?> open)</option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-outline-primary">Reassign</button>
      </form>
    </section>
    <?php endif; ?>

    <?php if ($canArchive || $canRestore): ?>
    <section class="panel">
      <h2>Record</h2>
      <form method="post" action="<?= e(url('concern-action.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <?php if ($canArchive): ?>
          <p class="text-muted small">Archiving hides this finished concern from the active list. It stays in reports and history.</p>
          <button type="submit" name="op" value="archive" class="btn btn-outline-secondary" data-confirm="Archive concern <?= e($c['reference_no']) ?>?">Archive concern</button>
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
            <div><?= $h['from_status'] && $h['from_status'] !== $h['to_status'] ? e($h['from_status']) . ' → ' : '' ?><strong><?= e($h['to_status']) ?></strong></div>
            <div class="history-meta"><?= e(format_datetime($h['changed_at'])) ?> by <?= e(full_name($h)) ?> (<?= e($h['role_name']) ?>)</div>
            <?php if ($h['note']): ?><p class="history-note"><?= e($h['note']) ?></p><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
