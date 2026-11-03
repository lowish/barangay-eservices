<?php
require __DIR__ . '/../app/bootstrap.php';

$user = require_role(ROLE_ADMIN);

$res = db_one(
    "SELECT COUNT(*) AS total,
            SUM(u.email_verified_at IS NOT NULL) AS verified,
            SUM(u.email_verified_at IS NULL) AS unverified,
            SUM(u.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS new_month
       FROM users u JOIN roles r ON r.role_id = u.role_id
      WHERE r.role_key = 'resident' AND u.is_active = 1"
);
$hall = db_one(
    "SELECT (SELECT COUNT(*) FROM users u JOIN roles r ON r.role_id = u.role_id WHERE r.role_key = 'staff' AND u.is_active = 1) AS staff,
            (SELECT COUNT(*) FROM document_requests WHERE status = 'Released') AS released,
            (SELECT COUNT(*) FROM concerns WHERE status IN ('Resolved', 'Closed')) AS resolved,
            (SELECT COALESCE(SUM(fee_amount), 0) FROM document_requests WHERE status = 'Released' AND updated_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS fees_month"
);
$waiting = db_one(
    "SELECT (SELECT COUNT(*) FROM document_requests WHERE status IN ('Pending', 'Under Review') AND is_archived = 0) AS to_review,
            (SELECT COUNT(*) FROM document_requests WHERE status = 'Ready for Pickup' AND is_archived = 0) AS ready,
            (SELECT COUNT(*) FROM concerns WHERE status IN ('Submitted', 'Under Review') AND assigned_to IS NULL AND is_archived = 0) AS unassigned,
            (SELECT COUNT(*) FROM concerns WHERE status IN ('Assigned', 'In Progress') AND is_archived = 0) AS in_work"
);
$recentRequests = db_all(
    'SELECT dr.request_id, dr.reference_no, dr.status, dr.created_at, dt.name AS doc_name, u.first_name, u.last_name
       FROM document_requests dr JOIN document_types dt ON dt.doc_type_id = dr.doc_type_id JOIN users u ON u.user_id = dr.resident_id
      ORDER BY dr.created_at DESC, dr.request_id DESC LIMIT 5'
);
$recentConcerns = db_all(
    'SELECT c.concern_id, c.reference_no, c.title, c.priority, c.status, c.created_at, cc.name AS category_name
       FROM concerns c JOIN concern_categories cc ON cc.category_id = c.category_id
      ORDER BY c.created_at DESC, c.concern_id DESC LIMIT 5'
);
$logs = db_all(
    'SELECT l.action, l.details, l.created_at, u.first_name, u.last_name
       FROM activity_logs l LEFT JOIN users u ON u.user_id = l.user_id ORDER BY l.log_id DESC LIMIT 6'
);
$share = fn ($n) => $res['total'] ? round($n / $res['total'] * 100) . '%' : '0%';

$pending = (int) $waiting['to_review'] + (int) $waiting['unassigned'];
$welcomeMessage = $pending
    ? "{$waiting['to_review']} document request(s) and {$waiting['unassigned']} concern(s) are waiting for action."
    : 'Nothing is waiting at the desk right now.';

$pageTitle = 'Administrator dashboard';
$activeNav = 'dashboard';
require APP_ROOT . '/app/views/header.php';
require APP_ROOT . '/app/views/welcome.php';
?>
<div class="row g-4 mb-4">
  <div class="col-xl-7">
    <section class="panel h-100 mb-0">
      <h2>Residents</h2>
      <ul class="stat-list">
        <li><span class="stat-label">Total residents<span class="stat-desc">Active accounts</span></span><span class="stat-value"><a href="<?= e(url('residents.php')) ?>"><?= (int) $res['total'] ?></a></span><span class="stat-share"></span></li>
        <li><span class="stat-label">Email verified<span class="stat-desc">Can use the services</span></span><span class="stat-value"><?= (int) $res['verified'] ?></span><span class="stat-share"><?= $share($res['verified']) ?></span></li>
        <li><span class="stat-label">Not yet verified<span class="stat-desc">Waiting for email confirmation</span></span><span class="stat-value"><a href="<?= e(url('residents.php?verified=0')) ?>"><?= (int) $res['unverified'] ?></a></span><span class="stat-share"><?= $share($res['unverified']) ?></span></li>
        <li><span class="stat-label">New this month<span class="stat-desc">Registered since the 1st</span></span><span class="stat-value"><?= (int) $res['new_month'] ?></span><span class="stat-share"></span></li>
      </ul>
      <h2 class="mt-4">Barangay hall</h2>
      <ul class="stat-list">
        <li><span class="stat-label">Active staff<span class="stat-desc">Officials and staff accounts</span></span><span class="stat-value"><a href="<?= e(url('admin/users.php?role=staff&status=active')) ?>"><?= (int) $hall['staff'] ?></a></span><span class="stat-share"></span></li>
        <li><span class="stat-label">Documents released<span class="stat-desc">All time</span></span><span class="stat-value"><?= (int) $hall['released'] ?></span><span class="stat-share"></span></li>
        <li><span class="stat-label">Concerns resolved<span class="stat-desc">Resolved or closed, all time</span></span><span class="stat-value"><?= (int) $hall['resolved'] ?></span><span class="stat-share"></span></li>
        <li><span class="stat-label">Fees collected<span class="stat-desc">Released documents this month</span></span><span class="stat-value"><?= e(peso($hall['fees_month'])) ?></span><span class="stat-share"></span></li>
      </ul>
    </section>
  </div>
  <div class="col-xl-5">
    <?php
    $cards = [
        ['Requests to review', (int) $waiting['to_review'], 'Pending or under review.', 'No requests waiting for review.', 'requests.php?status=Pending', 'Open document requests'],
        ['Unassigned concerns', (int) $waiting['unassigned'], 'Reported but not yet assigned to staff.', 'Every concern has someone assigned.', 'concerns.php?status=Submitted', 'Open community concerns'],
        ['Ready for pickup', (int) $waiting['ready'], 'Documents waiting to be claimed.', 'No documents waiting at the front desk.', 'requests.php?status=Ready+for+Pickup', 'See who to expect'],
        ['Concerns being worked on', (int) $waiting['in_work'], 'Assigned or in progress.', 'No concerns in progress.', 'concerns.php?status=In+Progress', 'Open concerns in progress'],
    ];
    foreach ($cards as [$title, $n, $busy, $clear, $link, $linkText]): ?>
      <section class="action-card<?= $n ? '' : ' is-clear' ?>">
        <div>
          <h2><?= e($title) ?></h2>
          <p><?= e($n ? $busy : $clear) ?></p>
          <a href="<?= e(url($link)) ?>"><?= e($linkText) ?></a>
        </div>
        <span class="action-card-value"><?= $n ?></span>
      </section>
    <?php endforeach; ?>
  </div>
</div>

<?php require APP_ROOT . '/app/views/monthly-chart.php'; ?>

<div class="row g-4">
  <div class="col-xl-6">
    <section class="panel panel-flush">
      <div class="panel-head"><h2>Recent document requests</h2><a href="<?= e(url('requests.php')) ?>">View all</a></div>
      <div class="table-responsive"><table class="table">
        <thead><tr><th scope="col">Reference no.</th><th scope="col">Document</th><th scope="col">Resident</th><th scope="col">Status</th></tr></thead>
        <tbody>
        <?php foreach ($recentRequests as $r): ?>
          <tr><td class="ref"><a href="<?= e(url('request.php?id=' . (int) $r['request_id'])) ?>"><?= e($r['reference_no']) ?></a><div class="small text-muted"><?= e(format_date($r['created_at'])) ?></div></td><td><?= e($r['doc_name']) ?></td><td><?= e(full_name($r)) ?></td><td><?= status_badge($r['status']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$recentRequests): ?><tr><td colspan="4" class="empty">No requests yet.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </section>
  </div>
  <div class="col-xl-6">
    <section class="panel panel-flush">
      <div class="panel-head"><h2>Recent community concerns</h2><a href="<?= e(url('concerns.php')) ?>">View all</a></div>
      <div class="table-responsive"><table class="table">
        <thead><tr><th scope="col">Reference no.</th><th scope="col">Concern</th><th scope="col">Priority</th><th scope="col">Status</th></tr></thead>
        <tbody>
        <?php foreach ($recentConcerns as $c): ?>
          <tr><td class="ref"><a href="<?= e(url('concern.php?id=' . (int) $c['concern_id'])) ?>"><?= e($c['reference_no']) ?></a><div class="small text-muted"><?= e(format_date($c['created_at'])) ?></div></td><td><?= e($c['title']) ?><div class="small text-muted"><?= e($c['category_name']) ?></div></td><td><?= priority_badge((int) $c['priority']) ?></td><td><?= status_badge($c['status']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$recentConcerns): ?><tr><td colspan="4" class="empty">No concerns yet.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </section>
  </div>
</div>

<section class="panel panel-flush">
  <div class="panel-head"><h2>Recent activity</h2><a href="<?= e(url('admin/logs.php')) ?>">View all activity logs</a></div>
  <div class="table-responsive"><table class="table">
    <thead><tr><th scope="col">When</th><th scope="col">User</th><th scope="col">Action</th><th scope="col">Details</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
      <tr><td class="nowrap"><?= e(format_datetime($l['created_at'])) ?></td><td><?= $l['first_name'] ? e(full_name($l)) : '<span class="text-muted">Guest</span>' ?></td><td class="nowrap"><?= e(str_replace('_', ' ', $l['action'])) ?></td><td><?= e($l['details']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
