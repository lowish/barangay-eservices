<?php
require __DIR__ . '/app/bootstrap.php';

/*
 * Community concern list for all roles.
 * Residents: only their own reports (enforced in SQL). Staff and admins: all reports.
 */
$user = require_role(ROLE_RESIDENT, ROLE_STAFF, ROLE_ADMIN);
$isResident = $user['role_key'] === ROLE_RESIDENT;

$statuses = ['Submitted', 'Under Review', 'Assigned', 'In Progress', 'Resolved', 'Closed', 'Rejected', 'Withdrawn'];
$f = [
    'q'        => query_param('q'),
    'status'   => in_array(query_param('status'), $statuses, true) ? query_param('status') : '',
    'category' => ctype_digit(query_param('category')) ? query_param('category') : '',
    'purok'    => ctype_digit(query_param('purok')) ? query_param('purok') : '',
    'priority' => in_array(query_param('priority'), ['1', '2', '3', '4'], true) ? query_param('priority') : '',
    'from'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', query_param('from')) ? query_param('from') : '',
    'to'       => preg_match('/^\d{4}-\d{2}-\d{2}$/', query_param('to')) ? query_param('to') : '',
    'mine'     => query_param('mine') === '1' ? '1' : '',
    'archived' => in_array(query_param('archived'), ['1', 'all'], true) ? query_param('archived') : '',
];

$where = [];
$params = [];
if ($isResident) {
    $where[] = 'c.resident_id = ?';
    $params[] = (int) $user['user_id'];
} elseif ($f['archived'] === '1') {
    $where[] = 'c.is_archived = 1';
} elseif ($f['archived'] === '') {
    $where[] = 'c.is_archived = 0';
}
// Staff and admins can narrow the list to one resident (from the Residents page).
$residentFilter = null;
if (!$isResident && ctype_digit(query_param('resident'))) {
    $residentFilter = db_one(
        'SELECT u.user_id, u.first_name, u.last_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = ? AND r.role_key = ?',
        [(int) query_param('resident'), ROLE_RESIDENT]
    );
    if ($residentFilter) {
        $where[] = 'c.resident_id = ?';
        $params[] = (int) $residentFilter['user_id'];
    }
}
if ($f['q'] !== '') {
    $where[] = '(c.reference_no LIKE ? OR c.title LIKE ? OR c.location LIKE ? OR CONCAT(u.first_name, \' \', u.last_name) LIKE ?)';
    $like = '%' . $f['q'] . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($f['status'] !== '')   { $where[] = 'c.status = ?'; $params[] = $f['status']; }
if ($f['category'] !== '') { $where[] = 'c.category_id = ?'; $params[] = (int) $f['category']; }
if ($f['purok'] !== '')    { $where[] = 'c.purok_id = ?'; $params[] = (int) $f['purok']; }
if ($f['priority'] !== '') { $where[] = 'c.priority = ?'; $params[] = (int) $f['priority']; }
if ($f['from'] !== '')     { $where[] = 'c.created_at >= ?'; $params[] = $f['from'] . ' 00:00:00'; }
if ($f['to'] !== '')       { $where[] = 'c.created_at <= ?'; $params[] = $f['to'] . ' 23:59:59'; }
if ($f['mine'] === '1' && $user['role_key'] === ROLE_STAFF) { $where[] = 'c.assigned_to = ?'; $params[] = (int) $user['user_id']; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$from = "FROM concerns c
         JOIN concern_categories cc ON cc.category_id = c.category_id
         JOIN puroks p ON p.purok_id = c.purok_id
         JOIN users u ON u.user_id = c.resident_id
         LEFT JOIN users a ON a.user_id = c.assigned_to $whereSql";
$total = (int) db_value("SELECT COUNT(*) $from", $params);
[$limit, $offset, $page, $pages] = paginate($total);
$rows = db_all(
    "SELECT c.concern_id, c.reference_no, c.title, c.location, c.priority, c.status, c.created_at, c.is_archived,
            cc.name AS category_name, p.purok_name, u.first_name, u.last_name,
            a.first_name AS a_first_name, a.last_name AS a_last_name
     $from ORDER BY c.created_at DESC, c.concern_id DESC LIMIT ? OFFSET ?",
    array_merge($params, [$limit, $offset])
);
$categories = db_all('SELECT category_id, name FROM concern_categories ORDER BY name');
$puroks = db_all('SELECT purok_id, purok_name FROM puroks ORDER BY purok_id');

$pageTitle = $isResident ? 'My community concerns' : 'Community concerns';
$activeNav = 'concerns';
require APP_ROOT . '/app/views/header.php';
?>
<div class="page-head">
  <div>
    <h1><?= e($pageTitle) ?></h1>
    <p><?= $isResident ? 'Problems you reported in the barangay and what has been done about them.' : 'Reports from residents. Use the triage queue to take the most urgent unassigned concern.' ?></p>
  </div>
  <div class="page-actions">
    <?php if ($isResident): ?>
      <a class="btn btn-primary" href="<?= e(url('resident/concern-form.php')) ?>">Report a concern</a>
    <?php elseif ($user['role_key'] === ROLE_STAFF): ?>
      <a class="btn btn-outline-primary" href="<?= e(url('concerns.php?mine=1')) ?>">Assigned to me</a>
      <a class="btn btn-primary" href="<?= e(url('staff/queue.php')) ?>">Open triage queue</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($residentFilter): ?>
  <div class="alert alert-info d-flex flex-wrap justify-content-between gap-2" role="status">
    <span>Showing concerns filed by <strong><?= e(full_name($residentFilter)) ?></strong>.</span>
    <a href="<?= e(url('concerns.php')) ?>">Show all concerns</a>
  </div>
<?php endif; ?>

<form class="filters" method="get" role="search" aria-label="Filter concerns">
  <?php if ($residentFilter): ?><input type="hidden" name="resident" value="<?= (int) $residentFilter['user_id'] ?>"><?php endif; ?>
  <?php if ($f['mine']): ?><input type="hidden" name="mine" value="1"><?php endif; ?>
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label" for="q">Search</label>
      <input class="form-control" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Reference no., title, location<?= $isResident ? '' : ', resident' ?>">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label" for="status">Status</label>
      <select class="form-select" id="status" name="status">
        <option value="">All statuses</option>
        <?php foreach ($statuses as $s): ?><option <?= $f['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label" for="category">Category</label>
      <select class="form-select" id="category" name="category">
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?><option value="<?= (int) $c['category_id'] ?>" <?= $f['category'] === (string) $c['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label" for="purok">Purok</label>
      <select class="form-select" id="purok" name="purok">
        <option value="">All puroks</option>
        <?php foreach ($puroks as $p): ?><option value="<?= (int) $p['purok_id'] ?>" <?= $f['purok'] === (string) $p['purok_id'] ? 'selected' : '' ?>><?= e($p['purok_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-1">
      <label class="form-label" for="priority">Priority</label>
      <select class="form-select" id="priority" name="priority">
        <option value="">Any</option>
        <?php foreach (PRIORITY_LABELS as $k => $label): ?><option value="<?= $k ?>" <?= $f['priority'] === (string) $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label" for="from">From</label>
      <input type="date" class="form-control" id="from" name="from" value="<?= e($f['from']) ?>">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label" for="to">To</label>
      <input type="date" class="form-control" id="to" name="to" value="<?= e($f['to']) ?>">
    </div>
    <?php if (!$isResident): ?>
    <div class="col-6 col-md-2">
      <label class="form-label" for="archived">Records</label>
      <select class="form-select" id="archived" name="archived">
        <option value="">Active</option>
        <option value="1" <?= $f['archived'] === '1' ? 'selected' : '' ?>>Archived</option>
        <option value="all" <?= $f['archived'] === 'all' ? 'selected' : '' ?>>All</option>
      </select>
    </div>
    <?php endif; ?>
    <div class="col-auto d-flex gap-2">
      <button class="btn btn-primary" type="submit">Apply</button>
      <a class="btn btn-link" href="<?= e(url('concerns.php')) ?>">Reset</a>
    </div>
  </div>
</form>

<div class="result-meta"><span><?= $total ?> concern(s) found<?= $f['mine'] ? ' assigned to you' : '' ?></span><?php if ($pages > 1): ?><span>Page <?= $page ?> of <?= $pages ?></span><?php endif; ?></div>

<div class="table-wrap">
  <?php if (!$rows): ?>
    <div class="empty">
      <p>No concerns match these filters.</p>
      <?php if ($isResident): ?><a class="btn btn-primary btn-sm" href="<?= e(url('resident/concern-form.php')) ?>">Report a concern</a><?php endif; ?>
    </div>
  <?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th scope="col">Reference no.</th>
        <th scope="col">Concern</th>
        <th scope="col">Location</th>
        <th scope="col">Priority</th>
        <th scope="col">Status</th>
        <?php if (!$isResident): ?><th scope="col">Assigned to</th><?php endif; ?>
        <th scope="col">Reported</th>
        <th scope="col"><span class="visually-hidden">Actions</span></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="ref"><?= e($r['reference_no']) ?></td>
        <td><?= e($r['title']) ?><div class="small text-muted"><?= e($r['category_name']) ?><?= $isResident ? '' : ' · reported by ' . e(full_name($r)) ?></div></td>
        <td><?= e($r['purok_name']) ?><div class="small text-muted text-truncate cell-clip"><?= e($r['location']) ?></div></td>
        <td><?= priority_badge((int) $r['priority']) ?></td>
        <td><?= status_badge($r['status']) ?><?= $r['is_archived'] ? ' <span class="status status-muted">Archived</span>' : '' ?></td>
        <?php if (!$isResident): ?><td><?= $r['a_first_name'] ? e(full_name($r, 'a_')) : '<span class="text-muted">Unassigned</span>' ?></td><?php endif; ?>
        <td class="nowrap"><?= e(format_date($r['created_at'])) ?></td>
        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('concern.php?id=' . (int) $r['concern_id'])) ?>">Open<span class="visually-hidden"> <?= e($r['reference_no']) ?></span></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php require APP_ROOT . '/app/views/pagination.php'; ?>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
