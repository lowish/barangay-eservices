<?php
require __DIR__ . '/../app/bootstrap.php';

$admin = require_role(ROLE_ADMIN);

$actions = array_column(db_all('SELECT DISTINCT action FROM activity_logs ORDER BY action'), 'action');
$entities = array_column(db_all('SELECT DISTINCT entity_type FROM activity_logs WHERE entity_type IS NOT NULL ORDER BY entity_type'), 'entity_type');
$f = [
    'q'      => query_param('q'),
    'action' => in_array(query_param('action'), $actions, true) ? query_param('action') : '',
    'entity' => in_array(query_param('entity'), $entities, true) ? query_param('entity') : '',
    'from'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', query_param('from')) ? query_param('from') : '',
    'to'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', query_param('to')) ? query_param('to') : '',
];
$where = [];
$params = [];
if ($f['q'] !== '') {
    $where[] = '(CONCAT(u.first_name, \' \', u.last_name) LIKE ? OR u.email LIKE ? OR l.details LIKE ?)';
    $like = '%' . $f['q'] . '%';
    array_push($params, $like, $like, $like);
}
if ($f['action'] !== '') { $where[] = 'l.action = ?'; $params[] = $f['action']; }
if ($f['entity'] !== '') { $where[] = 'l.entity_type = ?'; $params[] = $f['entity']; }
if ($f['from'] !== '')   { $where[] = 'l.created_at >= ?'; $params[] = $f['from'] . ' 00:00:00'; }
if ($f['to'] !== '')     { $where[] = 'l.created_at <= ?'; $params[] = $f['to'] . ' 23:59:59'; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$from = "FROM activity_logs l LEFT JOIN users u ON u.user_id = l.user_id LEFT JOIN roles r ON r.role_id = u.role_id $whereSql";
$total = (int) db_value("SELECT COUNT(*) $from", $params);
[$limit, $offset, $page, $pages] = paginate($total, 25);
$rows = db_all(
    "SELECT l.*, u.first_name, u.last_name, u.email, r.role_name $from ORDER BY l.log_id DESC LIMIT ? OFFSET ?",
    array_merge($params, [$limit, $offset])
);

function record_link(?string $type, ?int $id): string
{
    if (!$type || !$id) {
        return '';
    }
    $path = match ($type) {
        'document_request' => 'request.php?id=',
        'concern'          => 'concern.php?id=',
        'user'             => 'admin/user-form.php?id=',
        'document_type'    => 'admin/document-type-form.php?id=',
        'concern_category' => 'admin/categories.php?edit=',
        default            => null,
    };
    $label = e(str_replace('_', ' ', $type) . ' #' . $id);
    return $path ? '<a href="' . e(url($path . $id)) . '">' . $label . '</a>' : $label;
}

$pageTitle = 'Activity logs';
$activeNav = 'logs';
require APP_ROOT . '/app/views/header.php';
?>
<div class="page-head"><div><h1>Activity logs</h1><p>Every sign-in, account change, status change and archive, with who did it and when.</p></div></div>

<form class="filters" method="get" role="search">
  <div class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label" for="q">Search</label><input class="form-control" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="User, email or details"></div>
    <div class="col-6 col-md-2"><label class="form-label" for="action">Action</label>
      <select class="form-select" id="action" name="action"><option value="">All actions</option>
        <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>" <?= $f['action'] === $a ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $a)) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-2"><label class="form-label" for="entity">Record type</label>
      <select class="form-select" id="entity" name="entity"><option value="">All types</option>
        <?php foreach ($entities as $en): ?><option value="<?= e($en) ?>" <?= $f['entity'] === $en ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $en)) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-2"><label class="form-label" for="from">From</label><input type="date" class="form-control" id="from" name="from" value="<?= e($f['from']) ?>"></div>
    <div class="col-6 col-md-2"><label class="form-label" for="to">To</label><input type="date" class="form-control" id="to" name="to" value="<?= e($f['to']) ?>"></div>
    <div class="col-auto"><button class="btn btn-primary" type="submit">Apply</button> <a class="btn btn-link" href="<?= e(url('admin/logs.php')) ?>">Reset</a></div>
  </div>
</form>

<div class="result-meta"><span><?= $total ?> log entries</span><?php if ($pages > 1): ?><span>Page <?= $page ?> of <?= $pages ?></span><?php endif; ?></div>
<div class="table-wrap">
  <?php if (!$rows): ?><div class="empty"><p>No log entries match these filters.</p></div><?php else: ?>
  <table class="table">
    <thead><tr><th scope="col">Date and time</th><th scope="col">User</th><th scope="col">Action</th><th scope="col">Record</th><th scope="col">Details</th><th scope="col">IP address</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $l): ?>
      <tr>
        <td class="nowrap"><?= e(format_datetime($l['created_at'])) ?></td>
        <td><?php if ($l['first_name']): ?><?= e(full_name($l)) ?><div class="small text-muted"><?= e($l['role_name']) ?></div><?php else: ?><span class="text-muted">Guest</span><?php endif; ?></td>
        <td class="nowrap"><?= e(str_replace('_', ' ', $l['action'])) ?></td>
        <td class="nowrap"><?= record_link($l['entity_type'], $l['entity_id'] !== null ? (int) $l['entity_id'] : null) ?></td>
        <td><?= e($l['details']) ?></td>
        <td class="small text-muted"><?= e($l['ip_address']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php require APP_ROOT . '/app/views/pagination.php'; ?>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
