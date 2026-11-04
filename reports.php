<?php
require __DIR__ . '/app/bootstrap.php';

/*
 * Filtered, printable reports for staff and administrators.
 *   type=requests : document requests by date range, status, document type
 *   type=concerns : community concerns by date range, status, category, purok
 * The summary figures and the detail rows come from the same filtered query.
 */
$user = require_role(ROLE_STAFF, ROLE_ADMIN);
$type = query_param('type') === 'concerns' ? 'concerns' : 'requests';

$defaultFrom = date('Y-m-01');
$defaultTo = date('Y-m-d');
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', query_param('from')) ? query_param('from') : $defaultFrom;
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', query_param('to')) ? query_param('to') : $defaultTo;
$error = '';
if ($from > $to) {
    $error = 'The start date must be on or before the end date. Showing the current month instead.';
    [$from, $to] = [$defaultFrom, $defaultTo];
}

$reqStatuses = ['Pending', 'Under Review', 'Approved', 'Rejected', 'Processing', 'Ready for Pickup', 'Released', 'Cancelled'];
$conStatuses = ['Submitted', 'Under Review', 'Assigned', 'In Progress', 'Resolved', 'Closed', 'Rejected', 'Withdrawn'];
$status = query_param('status');
$docType = ctype_digit(query_param('doc_type')) ? query_param('doc_type') : '';
$category = ctype_digit(query_param('category')) ? query_param('category') : '';
$purok = ctype_digit(query_param('purok')) ? query_param('purok') : '';

$params = [$from . ' 00:00:00', $to . ' 23:59:59'];
if ($type === 'requests') {
    $status = in_array($status, $reqStatuses, true) ? $status : '';
    $where = 'dr.created_at BETWEEN ? AND ?';
    if ($status !== '')  { $where .= ' AND dr.status = ?'; $params[] = $status; }
    if ($docType !== '') { $where .= ' AND dr.doc_type_id = ?'; $params[] = (int) $docType; }
    $rows = db_all(
        "SELECT dr.reference_no, dr.status, dr.copies, dr.fee_amount, dr.purpose, dr.created_at, dr.updated_at,
                dt.name AS doc_name, u.first_name, u.last_name, p.purok_name
           FROM document_requests dr
           JOIN document_types dt ON dt.doc_type_id = dr.doc_type_id
           JOIN users u ON u.user_id = dr.resident_id
           LEFT JOIN puroks p ON p.purok_id = u.purok_id
          WHERE $where ORDER BY dr.created_at",
        $params
    );
} else {
    $status = in_array($status, $conStatuses, true) ? $status : '';
    $where = 'c.created_at BETWEEN ? AND ?';
    if ($status !== '')   { $where .= ' AND c.status = ?'; $params[] = $status; }
    if ($category !== '') { $where .= ' AND c.category_id = ?'; $params[] = (int) $category; }
    if ($purok !== '')    { $where .= ' AND c.purok_id = ?'; $params[] = (int) $purok; }
    $rows = db_all(
        "SELECT c.reference_no, c.title, c.location, c.priority, c.status, c.created_at, c.updated_at,
                cc.name AS category_name, p.purok_name, u.first_name, u.last_name,
                a.first_name AS a_first_name, a.last_name AS a_last_name
           FROM concerns c
           JOIN concern_categories cc ON cc.category_id = c.category_id
           JOIN puroks p ON p.purok_id = c.purok_id
           JOIN users u ON u.user_id = c.resident_id
           LEFT JOIN users a ON a.user_id = c.assigned_to
          WHERE $where ORDER BY c.created_at",
        $params
    );
}

// Summary: count rows per status with an associative array (hash map) in one pass, O(n).
$byStatus = [];
$feesReleased = 0.0;
foreach ($rows as $r) {
    $byStatus[$r['status']] = ($byStatus[$r['status']] ?? 0) + 1;
    if ($type === 'requests' && $r['status'] === 'Released') {
        $feesReleased += (float) $r['fee_amount'];
    }
}

$docTypes = db_all('SELECT doc_type_id, name FROM document_types ORDER BY name');
$categories = db_all('SELECT category_id, name FROM concern_categories ORDER BY name');
$puroks = db_all('SELECT purok_id, purok_name FROM puroks ORDER BY purok_id');
$filterText = [];
if ($status !== '') { $filterText[] = 'Status: ' . $status; }
if ($docType !== '') { $filterText[] = 'Document: ' . (array_column($docTypes, 'name', 'doc_type_id')[$docType] ?? ''); }
if ($category !== '') { $filterText[] = 'Category: ' . (array_column($categories, 'name', 'category_id')[$category] ?? ''); }
if ($purok !== '') { $filterText[] = 'Purok: ' . (array_column($puroks, 'purok_name', 'purok_id')[$purok] ?? ''); }

if (query_param('print') === '1') {
    log_activity('report_printed', null, null, ucfirst($type) . " report $from to $to" . ($filterText ? ' (' . implode(', ', $filterText) . ')' : ''));
}

$title = $type === 'requests' ? 'Document request report' : 'Community concern report';
$pageTitle = $title;
$activeNav = 'reports';
require APP_ROOT . '/app/views/header.php';
?>
<div class="page-head no-print">
  <div><h1>Reports</h1><p>Choose a report and filters, then print it or save it as PDF from the print dialog.</p></div>
  <div class="page-actions">
    <a class="btn <?= $type === 'requests' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= e(url('reports.php?type=requests')) ?>" <?= $type === 'requests' ? 'aria-current="page"' : '' ?>>Document requests</a>
    <a class="btn <?= $type === 'concerns' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= e(url('reports.php?type=concerns')) ?>" <?= $type === 'concerns' ? 'aria-current="page"' : '' ?>>Community concerns</a>
  </div>
</div>

<?php if ($error): ?><div class="alert alert-warning" role="alert"><?= e($error) ?></div><?php endif; ?>

<form class="filters" method="get">
  <input type="hidden" name="type" value="<?= e($type) ?>">
  <div class="row g-2 align-items-end">
    <div class="col-6 col-md-2"><label class="form-label" for="from">From</label><input type="date" class="form-control" id="from" name="from" value="<?= e($from) ?>" required></div>
    <div class="col-6 col-md-2"><label class="form-label" for="to">To</label><input type="date" class="form-control" id="to" name="to" value="<?= e($to) ?>" required></div>
    <div class="col-6 col-md-2"><label class="form-label" for="status">Status</label>
      <select class="form-select" id="status" name="status"><option value="">All statuses</option>
        <?php foreach ($type === 'requests' ? $reqStatuses : $conStatuses as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
    <?php if ($type === 'requests'): ?>
      <div class="col-6 col-md-3"><label class="form-label" for="doc_type">Document</label>
        <select class="form-select" id="doc_type" name="doc_type"><option value="">All documents</option>
          <?php foreach ($docTypes as $d): ?><option value="<?= (int) $d['doc_type_id'] ?>" <?= $docType === (string) $d['doc_type_id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
    <?php else: ?>
      <div class="col-6 col-md-2"><label class="form-label" for="category">Category</label>
        <select class="form-select" id="category" name="category"><option value="">All categories</option>
          <?php foreach ($categories as $c): ?><option value="<?= (int) $c['category_id'] ?>" <?= $category === (string) $c['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-6 col-md-2"><label class="form-label" for="purok">Purok</label>
        <select class="form-select" id="purok" name="purok"><option value="">All puroks</option>
          <?php foreach ($puroks as $p): ?><option value="<?= (int) $p['purok_id'] ?>" <?= $purok === (string) $p['purok_id'] ? 'selected' : '' ?>><?= e($p['purok_name']) ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <div class="col-auto d-flex gap-2">
      <button class="btn btn-primary" type="submit">Generate</button>
      <a class="btn btn-outline-primary" href="<?= e(url('reports.php' . query_with(['type' => $type, 'from' => $from, 'to' => $to, 'print' => '1']))) ?>">Printable view</a>
    </div>
  </div>
</form>

<div class="report-head">
  <div class="print-only"><strong><?= e(barangay_display_name()) ?></strong><?= barangay_location() !== '' ? ', ' . e(barangay_location()) : '' ?></div>
  <h2 class="mb-1"><?= e($title) ?></h2>
  <p class="text-muted mb-2">
    <?= e(format_date($from)) ?> to <?= e(format_date($to)) ?><?= $filterText ? '. ' . e(implode('; ', $filterText)) : '' ?>.
    Generated <?= e(format_datetime(date('Y-m-d H:i:s'))) ?> by <?= e(full_name($user)) ?>.
  </p>
</div>

<?php if (query_param('print') === '1'): ?>
  <div class="no-print mb-3 d-flex gap-2">
    <button type="button" class="btn btn-primary" data-print>Print this report</button>
    <a class="btn btn-link" href="<?= e(url('reports.php' . query_with(['print' => null]))) ?>">Back to filters</a>
  </div>
<?php endif; ?>

<div class="figures">
  <div class="figure"><span class="figure-value"><?= count($rows) ?></span><span class="figure-label">Total <?= $type === 'requests' ? 'requests' : 'concerns' ?></span></div>
  <?php foreach ($byStatus as $s => $n): ?>
    <div class="figure"><span class="figure-value"><?= (int) $n ?></span><span class="figure-label"><?= e($s) ?></span></div>
  <?php endforeach; ?>
  <?php if ($type === 'requests'): ?>
    <div class="figure"><span class="figure-value"><?= e(peso($feesReleased)) ?></span><span class="figure-label">Fees from released documents</span></div>
  <?php endif; ?>
</div>

<div class="table-wrap">
  <?php if (!$rows): ?><div class="empty"><p>No records in this period with these filters.</p></div><?php elseif ($type === 'requests'): ?>
  <table class="table">
    <thead><tr><th scope="col">#</th><th scope="col">Reference no.</th><th scope="col">Date filed</th><th scope="col">Resident</th><th scope="col">Purok</th><th scope="col">Document</th><th scope="col">Copies</th><th scope="col">Fee</th><th scope="col">Status</th><th scope="col">Last update</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $r): ?>
      <tr><td><?= $i + 1 ?></td><td class="ref"><?= e($r['reference_no']) ?></td><td class="nowrap"><?= e(format_date($r['created_at'])) ?></td><td><?= e(full_name($r)) ?></td><td><?= e($r['purok_name']) ?></td><td><?= e($r['doc_name']) ?></td><td><?= (int) $r['copies'] ?></td><td class="nowrap"><?= e(peso($r['fee_amount'])) ?></td><td><?= status_badge($r['status']) ?></td><td class="nowrap"><?= e(format_date($r['updated_at'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <table class="table">
    <thead><tr><th scope="col">#</th><th scope="col">Reference no.</th><th scope="col">Date reported</th><th scope="col">Concern</th><th scope="col">Category</th><th scope="col">Purok</th><th scope="col">Priority</th><th scope="col">Assigned to</th><th scope="col">Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $r): ?>
      <tr><td><?= $i + 1 ?></td><td class="ref"><?= e($r['reference_no']) ?></td><td class="nowrap"><?= e(format_date($r['created_at'])) ?></td><td><?= e($r['title']) ?><div class="small text-muted"><?= e($r['location']) ?></div></td><td><?= e($r['category_name']) ?></td><td><?= e($r['purok_name']) ?></td><td><?= e(PRIORITY_LABELS[(int) $r['priority']]) ?></td><td><?= $r['a_first_name'] ? e(full_name($r, 'a_')) : '—' ?></td><td><?= status_badge($r['status']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<p class="print-only mt-4">Prepared by: ______________________________ &nbsp;&nbsp; Noted by: ______________________________<br><span class="small">Punong Barangay</span></p>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
