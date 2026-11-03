<?php
require __DIR__ . '/../app/bootstrap.php';
require_once APP_ROOT . '/app/lib/ConcernTriageQueue.php';

$user = require_role(ROLE_STAFF);
$uid = (int) $user['user_id'];

$req = db_one(
    "SELECT SUM(status IN ('Pending', 'Under Review')) AS to_review,
            SUM(status IN ('Approved', 'Processing')) AS processing,
            SUM(status = 'Ready for Pickup') AS ready,
            SUM(status = 'Released' AND updated_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS released_month
       FROM document_requests WHERE is_archived = 0"
);
$con = db_one(
    "SELECT SUM(status IN ('Submitted', 'Under Review') AND assigned_to IS NULL) AS waiting,
            SUM(status IN ('Assigned', 'In Progress') AND assigned_to = ?) AS mine,
            SUM(status = 'Resolved' AND updated_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS resolved_month
       FROM concerns WHERE is_archived = 0",
    [$uid]
);
$waitingRows = db_all(
    "SELECT c.concern_id, c.reference_no, c.title, c.priority, c.status, c.created_at, cc.name AS category_name, p.purok_name
       FROM concerns c JOIN concern_categories cc ON cc.category_id = c.category_id JOIN puroks p ON p.purok_id = c.purok_id
      WHERE c.status IN ('Submitted', 'Under Review') AND c.assigned_to IS NULL AND c.is_archived = 0"
);
$topWaiting = array_slice(ConcernTriageQueue::fromRows($waitingRows)->toOrderedArray(), 0, 5);
$myConcerns = db_all(
    "SELECT c.concern_id, c.reference_no, c.title, c.priority, c.status, p.purok_name FROM concerns c JOIN puroks p ON p.purok_id = c.purok_id
      WHERE c.assigned_to = ? AND c.status IN ('Assigned', 'In Progress') AND c.is_archived = 0 ORDER BY c.priority DESC, c.created_at",
    [$uid]
);
$toReview = db_all(
    "SELECT dr.request_id, dr.reference_no, dr.status, dr.created_at, dt.name AS doc_name, u.first_name, u.last_name
       FROM document_requests dr JOIN document_types dt ON dt.doc_type_id = dr.doc_type_id JOIN users u ON u.user_id = dr.resident_id
      WHERE dr.status IN ('Pending', 'Under Review') AND dr.is_archived = 0 ORDER BY dr.created_at LIMIT 6"
);
$barRows = db_all(
    "SELECT cc.name AS label, COUNT(c.concern_id) AS count
       FROM concern_categories cc
       LEFT JOIN concerns c ON c.category_id = cc.category_id AND c.status IN ('Submitted', 'Under Review', 'Assigned', 'In Progress') AND c.is_archived = 0
      WHERE cc.is_archived = 0 GROUP BY cc.category_id, cc.name ORDER BY count DESC, cc.name"
);

$welcomeMessage = ((int) $req['to_review'] + (int) $con['waiting'] + (int) $con['mine'])
    ? "{$req['to_review']} request(s) to review, " . (int) $con['waiting'] . ' unassigned concern(s), and ' . (int) $con['mine'] . ' concern(s) assigned to you.'
    : 'Nothing is waiting at the desk right now.';

$pageTitle = 'Staff dashboard';
$activeNav = 'dashboard';
require APP_ROOT . '/app/views/header.php';
require APP_ROOT . '/app/views/welcome.php';

$cards = [
    ['Requests to review', (int) $req['to_review'], 'Pending or under review.', 'No requests waiting.', 'requests.php?status=Pending', 'Review requests'],
    ['Unassigned concerns', (int) $con['waiting'], 'Waiting in the triage queue.', 'The queue is empty.', 'staff/queue.php', 'Open triage queue'],
    ['Assigned to me', (int) $con['mine'], 'Concerns you need to work on.', 'Nothing assigned to you.', 'concerns.php?mine=1', 'See my concerns'],
    ['Ready for pickup', (int) $req['ready'], 'Documents waiting to be released.', 'No documents waiting.', 'requests.php?status=Ready+for+Pickup', 'Release documents'],
];
?>
<div class="row g-3 mb-2">
  <?php foreach ($cards as [$title, $n, $busy, $clear, $link, $linkText]): ?>
    <div class="col-sm-6 col-xl-3">
      <section class="action-card h-100 mb-0<?= $n ? '' : ' is-clear' ?>">
        <div>
          <h2><?= e($title) ?></h2>
          <p><?= e($n ? $busy : $clear) ?></p>
          <a href="<?= e(url($link)) ?>"><?= e($linkText) ?></a>
        </div>
        <span class="action-card-value"><?= $n ?></span>
      </section>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-4 mt-1">
  <div class="col-xl-7">
    <section class="panel panel-flush">
      <div class="panel-head"><h2>Requests to review</h2><a href="<?= e(url('requests.php?status=Pending')) ?>">View all</a></div>
      <?php if (!$toReview): ?><div class="empty"><p>No requests are waiting for review.</p></div><?php else: ?>
      <div class="table-responsive"><table class="table">
        <thead><tr><th scope="col">Reference no.</th><th scope="col">Document</th><th scope="col">Resident</th><th scope="col">Status</th><th scope="col">Submitted</th></tr></thead>
        <tbody>
        <?php foreach ($toReview as $r): ?>
          <tr><td class="ref"><a href="<?= e(url('request.php?id=' . (int) $r['request_id'])) ?>"><?= e($r['reference_no']) ?></a></td><td><?= e($r['doc_name']) ?></td><td><?= e(full_name($r)) ?></td><td><?= status_badge($r['status']) ?></td><td class="nowrap"><?= e(format_date($r['created_at'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </section>

    <section class="panel panel-flush">
      <div class="panel-head"><h2>Top of the triage queue</h2><a href="<?= e(url('staff/queue.php')) ?>">Open triage queue</a></div>
      <?php if (!$topWaiting): ?><div class="empty"><p>No unassigned concerns.</p></div><?php else: ?>
      <div class="table-responsive"><table class="table">
        <thead><tr><th scope="col">#</th><th scope="col">Concern</th><th scope="col">Queue priority</th><th scope="col">Waiting</th></tr></thead>
        <tbody>
        <?php foreach ($topWaiting as $i => $c): ?>
          <tr><td><?= $i + 1 ?></td><td><a href="<?= e(url('concern.php?id=' . (int) $c['concern_id'])) ?>"><?= e($c['reference_no']) ?></a> <?= e($c['title']) ?><div class="small text-muted"><?= e($c['category_name']) ?>, <?= e($c['purok_name']) ?></div></td><td><?= priority_badge((int) $c['effective_priority']) ?></td><td class="nowrap"><?= (int) $c['days_waiting'] ?> day(s)</td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </section>
  </div>
  <div class="col-xl-5">
    <section class="panel">
      <h2>My assigned concerns</h2>
      <?php if (!$myConcerns): ?><p class="text-muted mb-0">Nothing is assigned to you. Take the next concern from the triage queue.</p><?php else: ?>
      <ul class="history">
        <?php foreach ($myConcerns as $c): ?>
          <li><a href="<?= e(url('concern.php?id=' . (int) $c['concern_id'])) ?>"><?= e($c['reference_no']) ?></a> <?= e($c['title']) ?>
            <div class="history-meta"><?= priority_badge((int) $c['priority']) ?> <?= status_badge($c['status']) ?> <?= e($c['purok_name']) ?></div></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
    <section class="panel">
      <h2>Open concerns by category</h2>
      <?php require APP_ROOT . '/app/views/bars.php'; ?>
    </section>
    <section class="panel">
      <h2>This month</h2>
      <ul class="stat-list">
        <li><span class="stat-label">Documents released</span><span class="stat-value"><?= (int) $req['released_month'] ?></span></li>
        <li><span class="stat-label">Concerns resolved</span><span class="stat-value"><?= (int) $con['resolved_month'] ?></span></li>
        <li><span class="stat-label">Requests being processed</span><span class="stat-value"><?= (int) $req['processing'] ?></span></li>
      </ul>
    </section>
  </div>
</div>

<?php require APP_ROOT . '/app/views/monthly-chart.php'; ?>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
