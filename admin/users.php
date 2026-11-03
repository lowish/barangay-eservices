<?php
require __DIR__ . '/../app/bootstrap.php';

// User accounts: search/filter, activate/deactivate, resend verification. Create/edit in user-form.php.
$admin = require_role(ROLE_ADMIN);

if (is_post()) {
    csrf_verify();
    $id = (int) post_param('id');
    $op = post_param('op');
    $u = db_one('SELECT user_id, first_name, last_name, email, email_verified_at, is_active FROM users WHERE user_id = ?', [$id]);
    if (!$u) {
        flash('danger', 'That account no longer exists.');
    } elseif ($id === (int) $admin['user_id'] && $op === 'deactivate') {
        flash('danger', 'You cannot deactivate your own account.');
    } elseif ($op === 'deactivate' || $op === 'activate') {
        db_exec('UPDATE users SET is_active = ? WHERE user_id = ?', [$op === 'activate' ? 1 : 0, $id]);
        log_activity('user_' . $op . 'd', 'user', $id, $u['email']);
        flash('success', full_name($u) . ' was ' . ($op === 'activate' ? 'activated.' : 'deactivated and can no longer sign in.'));
    } elseif ($op === 'resend') {
        if ($u['email_verified_at']) {
            flash('info', 'This account is already verified.');
        } else {
            $sent = send_verification_email($u);
            flash($sent ? 'success' : 'danger', $sent
                ? 'A new verification email was sent to ' . $u['email'] . '.'
                : 'The verification email could not be sent. Check the mail settings in config/config.php.');
        }
    }
    redirect('admin/users.php' . (post_param('back') !== '' ? '?' . post_param('back') : ''));
}


$f = [
    'q'        => query_param('q'),
    'role'     => in_array(query_param('role'), [ROLE_ADMIN, ROLE_STAFF, ROLE_RESIDENT], true) ? query_param('role') : '',
    'status'   => in_array(query_param('status'), ['active', 'inactive'], true) ? query_param('status') : '',
    'verified' => in_array(query_param('verified'), ['0', '1'], true) ? query_param('verified') : '',
];
$where = [];
$params = [];
if ($f['q'] !== '') {
    $where[] = '(CONCAT(u.first_name, \' \', u.last_name) LIKE ? OR u.email LIKE ? OR u.contact_no LIKE ?)';
    $like = '%' . $f['q'] . '%';
    array_push($params, $like, $like, $like);
}
if ($f['role'] !== '')     { $where[] = 'r.role_key = ?'; $params[] = $f['role']; }
if ($f['status'] !== '')   { $where[] = 'u.is_active = ?'; $params[] = $f['status'] === 'active' ? 1 : 0; }
if ($f['verified'] !== '') { $where[] = $f['verified'] === '1' ? 'u.email_verified_at IS NOT NULL' : 'u.email_verified_at IS NULL'; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$from = "FROM users u JOIN roles r ON r.role_id = u.role_id LEFT JOIN puroks p ON p.purok_id = u.purok_id $whereSql";
$total = (int) db_value("SELECT COUNT(*) $from", $params);
[$limit, $offset, $page, $pages] = paginate($total);
$rows = db_all(
    "SELECT u.user_id, u.first_name, u.last_name, u.email, u.contact_no, u.email_verified_at, u.is_active, u.created_at,
            r.role_key, r.role_name, p.purok_name $from ORDER BY u.created_at DESC, u.user_id DESC LIMIT ? OFFSET ?",
    array_merge($params, [$limit, $offset])
);
$back = ltrim(query_with([]), '?');

$pageTitle = 'User accounts';
$activeNav = 'users';
require APP_ROOT . '/app/views/header.php';
?>
<div class="page-head">
  <div><h1>User accounts</h1><p>Residents, barangay staff and administrators. Accounts are deactivated, never deleted, so their records stay traceable.</p></div>
  <div class="page-actions"><a class="btn btn-primary" href="<?= e(url('admin/user-form.php')) ?>">Add account</a></div>
</div>

<form class="filters" method="get" role="search">
  <div class="row g-2 align-items-end">
    <div class="col-md-4"><label class="form-label" for="q">Search</label><input class="form-control" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Name, email or mobile number"></div>
    <div class="col-6 col-md-2"><label class="form-label" for="role">Role</label>
      <select class="form-select" id="role" name="role"><option value="">All roles</option>
        <option value="resident" <?= $f['role'] === 'resident' ? 'selected' : '' ?>>Resident</option>
        <option value="staff" <?= $f['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
        <option value="admin" <?= $f['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option></select></div>
    <div class="col-6 col-md-2"><label class="form-label" for="status">Account</label>
      <select class="form-select" id="status" name="status"><option value="">Any</option>
        <option value="active" <?= $f['status'] === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $f['status'] === 'inactive' ? 'selected' : '' ?>>Deactivated</option></select></div>
    <div class="col-6 col-md-2"><label class="form-label" for="verified">Email</label>
      <select class="form-select" id="verified" name="verified"><option value="">Any</option>
        <option value="1" <?= $f['verified'] === '1' ? 'selected' : '' ?>>Verified</option>
        <option value="0" <?= $f['verified'] === '0' ? 'selected' : '' ?>>Not verified</option></select></div>
    <div class="col-auto"><button class="btn btn-primary" type="submit">Apply</button> <a class="btn btn-link" href="<?= e(url('admin/users.php')) ?>">Reset</a></div>
  </div>
</form>

<div class="result-meta"><span><?= $total ?> account(s)</span></div>
<div class="table-wrap">
  <?php if (!$rows): ?><div class="empty"><p>No accounts match these filters.</p></div><?php else: ?>
  <table class="table">
    <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Email status</th><th scope="col">Account</th><th scope="col">Created</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><strong><?= e(full_name($r)) ?></strong><div class="small text-muted"><?= e($r['contact_no']) ?><?= $r['purok_name'] ? ', ' . e($r['purok_name']) : '' ?></div></td>
        <td><?= e($r['email']) ?></td>
        <td><?= e($r['role_name']) ?></td>
        <td><?= $r['email_verified_at'] ? '<span class="status status-done">Verified</span>' : '<span class="status status-ready">Not verified</span>' ?></td>
        <td><?= $r['is_active'] ? '<span class="status status-done">Active</span>' : '<span class="status status-muted">Deactivated</span>' ?></td>
        <td class="nowrap"><?= e(format_date($r['created_at'])) ?></td>
        <td class="text-end nowrap">
          <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/user-form.php?id=' . (int) $r['user_id'])) ?>">Edit<span class="visually-hidden"> <?= e(full_name($r)) ?></span></a>
          <form method="post" class="d-inline">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['user_id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
            <?php if (!$r['email_verified_at']): ?><button class="btn btn-sm btn-outline-secondary" name="op" value="resend">Resend email</button><?php endif; ?>
            <?php if ((int) $r['user_id'] !== (int) $admin['user_id']): ?>
              <?php if ($r['is_active']): ?>
                <button class="btn btn-sm btn-outline-danger" name="op" value="deactivate" data-confirm="Deactivate <?= e(full_name($r)) ?>? They will not be able to sign in.">Deactivate</button>
              <?php else: ?>
                <button class="btn btn-sm btn-outline-secondary" name="op" value="activate">Activate</button>
              <?php endif; ?>
            <?php endif; ?>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php require APP_ROOT . '/app/views/pagination.php'; ?>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
