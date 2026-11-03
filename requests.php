<?php
require __DIR__ . '/app/bootstrap.php';

/*
 * Document request list for all roles.
 * Residents: only their own requests (enforced in the SQL WHERE clause).
 * Staff and administrators: all requests, with archive filter.
 */
$user = require_role(ROLE_RESIDENT, ROLE_STAFF, ROLE_ADMIN);
$isResident = $user['role_key'] === ROLE_RESIDENT;

$statuses = ['Pending', 'Under Review', 'Approved', 'Rejected', 'Processing', 'Ready for Pickup', 'Released', 'Cancelled'];
$f = [
    'q'        => query_param('q'),
    'status'   => in_array(query_param('status'), $statuses, true) ? query_param('status') : '',
    'type'     => ctype_digit(query_param('type')) ? query_param('type') : '',
    'from'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', query_param('from')) ? query_param('from') : '',
    'to'       => preg_match('/^\d{4}-\d{2}-\d{2}$/', query_param('to')) ? query_param('to') : '',
    'archived' => in_array(query_param('archived'), ['1', 'all'], true) ? query_param('archived') : '',
];

$where = [];
$params = [];
if ($isResident) {
    $where[] = 'dr.resident_id = ?';
    $params[] = (int) $user['user_id'];
} elseif ($f['archived'] === '1') {
    $where[] = 'dr.is_archived = 1';
} elseif ($f['archived'] === '') {
    $where[] = 'dr.is_archived = 0';
}
// Staff and admins can narrow the list to one resident (from the Residents page).
$residentFilter = null;
if (!$isResident && ctype_digit(query_param('resident'))) {
    $residentFilter = db_one(
        'SELECT u.user_id, u.first_name, u.last_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = ? AND r.role_key = ?',
        [(int) query_param('resident'), ROLE_RESIDENT]
    );
    if ($residentFilter) {
        $where[] = 'dr.resident_id = ?';
        $params[] = (int) $residentFilter['user_id'];
    }
}
if ($f['q'] !== '') {
    $where[] = '(dr.reference_no LIKE ? OR dr.purpose LIKE ? OR CONCAT(u.first_name, \' \', u.last_name) LIKE ?)';
    $like = '%' . $f['q'] . '%';
    array_push($params, $like, $like, $like);
}
if ($f['status'] !== '') { $where[] = 'dr.status = ?'; $params[] = $f['status']; }
if ($f['type'] !== '')   { $where[] = 'dr.doc_type_id = ?'; $params[] = (int) $f['type']; }
if ($f['from'] !== '')   { $where[] = 'dr.created_at >= ?'; $params[] = $f['from'] . ' 00:00:00'; }
if ($f['to'] !== '')     { $where[] = 'dr.created_at <= ?'; $params[] = $f['to'] . ' 23:59:59'; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$from = "FROM document_requests dr
         JOIN document_types dt ON dt.doc_type_id = dr.doc_type_id
         JOIN users u ON u.user_id = dr.resident_id $whereSql";
$total = (int) db_value("SELECT COUNT(*) $from", $params);
[$limit, $offset, $page, $pages] = paginate($total);
$rows = db_all(
    "SELECT dr.request_id, dr.reference_no, dr.status, dr.copies, dr.fee_amount, dr.purpose, dr.created_at, dr.updated_at, dr.is_archived,
            dt.name AS doc_name, u.first_name, u.last_name
     $from ORDER BY dr.created_at DESC, dr.request_id DESC LIMIT ? OFFSET ?",
    array_merge($params, [$limit, $offset])
);
$docTypes = db_all('SELECT doc_type_id, name FROM document_types ORDER BY name');

$pageTitle = $isResident ? 'My document requests' : 'Document requests';
$activeNav = 'requests';
require APP_ROOT . '/app/views/header.php';
?>
<div class="page-head">
  <div>
    <h1><?= e($pageTitle) ?></h1>
    <p><?= $isResident ? 'Track the status of every document you have requested.' : 'Review and process document requests from residents.' ?></p>
  </div>
  <?php if ($isResident): ?>
    <div class="page-actions"><a class="btn btn-primary" href="<?= e(url('resident/request-form.php')) ?>">Request a document</a></div>
  <?php endif; ?>
</div>

<?php if ($residentFilter): ?>
  <div class="alert alert-info d-flex flex-wrap justify-content-between gap-2" role="status">
    <span>Showing requests filed by <strong><?= e(full_name($residentFilter)) ?></strong>.</span>
    <a href="<?= e(url('requests.php')) ?>">Show all requests</a>
  </div>
<?php endif; ?>

<form class="filters" method="get" role="search" aria-label="Filter requests">
  <?php if ($residentFilter): ?><input type="hidden" name="resident" value="<?= (int) $residentFilter['user_id'] ?>"><?php endif; ?>
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label" for="q">Search</label>
      <input class="form-control" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="<?= $isResident ? 'Reference no. or purpose' : 'Reference no., resident, purpose' ?>">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label" for="status">Status</label>
      <select class="form-select" id="status" name="status">
        <option value="">All statuses</option>
        <?php foreach ($statuses as $s): ?><option <?= $f['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label" for="type">Document</label>
      <select class="form-select" id="type" name="type">
        <option value="">All documents</option>
        <?php foreach ($docTypes as $d): ?><option value="<?= (int) $d['doc_type_id'] ?>" <?= $f['type'] === (string) $d['doc_type_id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
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
    <div class="col-6 col-md-1">
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
      <a class="btn btn-link" href="<?= e(url('requests.php')) ?>">Reset</a>
    </div>
  </div>
</form>

<div class="result-meta"><span><?= $total ?> request(s) found</span><?php if ($pages > 1): ?><span>Page <?= $page ?> of <?= $pages ?></span><?php endif; ?></div>

<div class="table-wrap">
  <?php if (!$rows): ?>
    <div class="empty">
      <p>No document requests match these filters.</p>
      <?php if ($isResident): ?><a class="btn btn-primary btn-sm" href="<?= e(url('resident/request-form.php')) ?>">Request a document</a><?php endif; ?>
    </div>
  <?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th scope="col">Reference no.</th>
        <th scope="col">Document</th>
        <?php if (!$isResident): ?><th scope="col">Resident</th><?php endif; ?>
        <th scope="col">Copies</th>
        <th scope="col">Fee</th>
        <th scope="col">Status</th>
        <th scope="col">Submitted</th>
        <th scope="col"><span class="visually-hidden">Actions</span></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="ref"><?= e($r['reference_no']) ?></td>
        <td><?= e($r['doc_name']) ?><div class="small text-muted text-truncate cell-clip"><?= e($r['purpose']) ?></div></td>
        <?php if (!$isResident): ?><td><?= e(full_name($r)) ?></td><?php endif; ?>
        <td><?= (int) $r['copies'] ?></td>
        <td class="nowrap"><?= e(peso($r['fee_amount'])) ?></td>
        <td><?= status_badge($r['status']) ?><?= $r['is_archived'] ? ' <span class="status status-muted">Archived</span>' : '' ?></td>
        <td class="nowrap"><?= e(format_date($r['created_at'])) ?></td>
        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('request.php?id=' . (int) $r['request_id'])) ?>">Open<span class="visually-hidden"> <?= e($r['reference_no']) ?></span></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php require APP_ROOT . '/app/views/pagination.php'; ?>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
