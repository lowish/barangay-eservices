<?php
require __DIR__ . '/app/bootstrap.php';

/*
 * Resident directory for staff and administrators (read-only).
 * Shows each registered resident with how many requests and concerns they have filed.
 * Residents cannot open this page; account changes stay in Admin > User Accounts.
 */
$user = require_role(ROLE_STAFF, ROLE_ADMIN);

$f = [
    'q'        => query_param('q'),
    'purok'    => ctype_digit(query_param('purok')) ? query_param('purok') : '',
    'verified' => in_array(query_param('verified'), ['0', '1'], true) ? query_param('verified') : '',
];
$where = ['r.role_key = ?'];
$params = [ROLE_RESIDENT];
if ($f['q'] !== '') {
    $where[] = '(CONCAT(u.first_name, \' \', u.last_name) LIKE ? OR u.email LIKE ? OR u.contact_no LIKE ? OR u.street_address LIKE ?)';
    $like = '%' . $f['q'] . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($f['purok'] !== '')    { $where[] = 'u.purok_id = ?'; $params[] = (int) $f['purok']; }
if ($f['verified'] !== '') { $where[] = $f['verified'] === '1' ? 'u.email_verified_at IS NOT NULL' : 'u.email_verified_at IS NULL'; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$from = "FROM users u JOIN roles r ON r.role_id = u.role_id LEFT JOIN puroks p ON p.purok_id = u.purok_id $whereSql";
$total = (int) db_value("SELECT COUNT(*) $from", $params);
[$limit, $offset, $page, $pages] = paginate($total, 20);
$rows = db_all(
    "SELECT u.user_id, u.first_name, u.last_name, u.email, u.contact_no, u.street_address, u.email_verified_at, u.is_active, u.created_at,
            p.purok_name,
            (SELECT COUNT(*) FROM document_requests d WHERE d.resident_id = u.user_id) AS request_count,
            (SELECT COUNT(*) FROM document_requests d WHERE d.resident_id = u.user_id AND d.status NOT IN ('Released', 'Rejected', 'Cancelled')) AS request_open,
            (SELECT COUNT(*) FROM concerns c WHERE c.resident_id = u.user_id) AS concern_count
     $from ORDER BY u.last_name, u.first_name LIMIT ? OFFSET ?",
    array_merge($params, [$limit, $offset])
);
$puroks = db_all('SELECT purok_id, purok_name FROM puroks ORDER BY purok_id');
$isAdmin = $user['role_key'] === ROLE_ADMIN;

$pageTitle = 'Residents';
$activeNav = 'residents';
require APP_ROOT . '/app/views/header.php';
?>
<div class="page-head">
  <div>
    <h1>Residents</h1>
    <p>Residents with an E-Services account, and how many document requests and concerns each has filed.</p>
  </div>
  <?php if ($isAdmin): ?>
    <div class="page-actions"><a class="btn btn-primary" href="<?= e(url('admin/user-form.php?role=resident')) ?>">Add resident account</a></div>
  <?php endif; ?>
</div>

<form class="filters" method="get" role="search" aria-label="Filter residents">
  <div class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label" for="q">Search</label>
      <input class="form-control" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Name, email, mobile or street">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label" for="purok">Purok</label>
      <select class="form-select" id="purok" name="purok">
        <option value="">All puroks</option>
        <?php foreach ($puroks as $p): ?><option value="<?= (int) $p['purok_id'] ?>" <?= $f['purok'] === (string) $p['purok_id'] ? 'selected' : '' ?>><?= e($p['purok_name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label" for="verified">Email</label>
      <select class="form-select" id="verified" name="verified">
        <option value="">Any</option>
        <option value="1" <?= $f['verified'] === '1' ? 'selected' : '' ?>>Verified</option>
        <option value="0" <?= $f['verified'] === '0' ? 'selected' : '' ?>>Not verified</option>
      </select>
    </div>
    <div class="col-auto d-flex gap-2">
      <button class="btn btn-primary" type="submit">Apply</button>
      <a class="btn btn-link" href="<?= e(url('residents.php')) ?>">Reset</a>
    </div>
  </div>
</form>

<div class="result-meta"><span><?= $total ?> resident(s)</span><?php if ($pages > 1): ?><span>Page <?= $page ?> of <?= $pages ?></span><?php endif; ?></div>
<div class="table-wrap">
  <?php if (!$rows): ?>
    <div class="empty"><p>No residents match these filters.</p></div>
  <?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th scope="col">Resident</th>
        <th scope="col">Address</th>
        <th scope="col">Mobile</th>
        <th scope="col">Account</th>
        <th scope="col">Requests</th>
        <th scope="col">Concerns</th>
        <th scope="col">Registered</th>
        <?php if ($isAdmin): ?><th scope="col"><span class="visually-hidden">Actions</span></th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><strong><?= e(full_name($r)) ?></strong><div class="small text-muted"><?= e($r['email']) ?></div></td>
        <td><?= e($r['street_address']) ?><div class="small text-muted"><?= e($r['purok_name']) ?></div></td>
        <td class="nowrap"><?= e($r['contact_no']) ?></td>
        <td>
          <?= $r['email_verified_at'] ? '<span class="status status-done">Verified</span>' : '<span class="status status-ready">Not verified</span>' ?>
          <?= $r['is_active'] ? '' : ' <span class="status status-muted">Deactivated</span>' ?>
        </td>
        <td class="nowrap">
          <?php if ($r['request_count']): ?>
            <a href="<?= e(url('requests.php?resident=' . (int) $r['user_id'] . '&archived=all')) ?>"><?= (int) $r['request_count'] ?></a>
            <?php if ($r['request_open']): ?><span class="small text-muted">(<?= (int) $r['request_open'] ?> open)</span><?php endif; ?>
          <?php else: ?><span class="text-muted">0</span><?php endif; ?>
        </td>
        <td><?= $r['concern_count'] ? '<a href="' . e(url('concerns.php?resident=' . (int) $r['user_id'] . '&archived=all')) . '">' . (int) $r['concern_count'] . '</a>' : '<span class="text-muted">0</span>' ?></td>
        <td class="nowrap"><?= e(format_date($r['created_at'])) ?></td>
        <?php if ($isAdmin): ?>
          <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/user-form.php?id=' . (int) $r['user_id'])) ?>">Edit<span class="visually-hidden"> <?= e(full_name($r)) ?></span></a></td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php require APP_ROOT . '/app/views/pagination.php'; ?>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
