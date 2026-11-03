<?php
require __DIR__ . '/../app/bootstrap.php';

$user = require_role(ROLE_RESIDENT);
$uid = (int) $user['user_id'];

$req = db_one(
    "SELECT COUNT(*) AS total,
            SUM(status IN ('Pending', 'Under Review', 'Approved', 'Processing')) AS in_progress,
            SUM(status = 'Ready for Pickup') AS ready,
            SUM(status = 'Released') AS released
       FROM document_requests WHERE resident_id = ?",
    [$uid]
);
$con = db_one(
    "SELECT COUNT(*) AS total,
            SUM(status IN ('Submitted', 'Under Review', 'Assigned', 'In Progress')) AS open,
            SUM(status IN ('Resolved', 'Closed')) AS resolved
       FROM concerns WHERE resident_id = ?",
    [$uid]
);
$recentRequests = db_all(
    'SELECT dr.request_id, dr.reference_no, dr.status, dr.updated_at, dt.name AS doc_name
       FROM document_requests dr JOIN document_types dt ON dt.doc_type_id = dr.doc_type_id
      WHERE dr.resident_id = ? ORDER BY dr.updated_at DESC LIMIT 5',
    [$uid]
);
$recentConcerns = db_all(
    'SELECT concern_id, reference_no, title, status, updated_at FROM concerns WHERE resident_id = ? ORDER BY updated_at DESC LIMIT 5',
    [$uid]
);
$ready = db_all(
    "SELECT dr.request_id, dr.reference_no, dr.fee_amount, dt.name AS doc_name
       FROM document_requests dr JOIN document_types dt ON dt.doc_type_id = dr.doc_type_id
      WHERE dr.resident_id = ? AND dr.status = 'Ready for Pickup'",
    [$uid]
);

$welcomeMessage = $ready
    ? count($ready) . ' document(s) are ready for pickup at the barangay hall.'
    : ((int) $req['in_progress'] ? (int) $req['in_progress'] . ' of your request(s) are being processed.' : 'You have no requests in progress.');

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require APP_ROOT . '/app/views/header.php';
require APP_ROOT . '/app/views/welcome.php';
?>
<div class="d-flex flex-wrap gap-2 mb-3">
  <a class="btn btn-primary" href="<?= e(url('resident/request-form.php')) ?>">Request a document</a>
  <a class="btn btn-outline-primary" href="<?= e(url('resident/concern-form.php')) ?>">Report a concern</a>
  <a class="btn btn-link" href="<?= e(url('services.php')) ?>">See document requirements</a>
</div>

<?php foreach ($ready as $r): ?>
  <div class="alert alert-warning" role="status">
    <strong><?= e($r['doc_name']) ?> (<?= e($r['reference_no']) ?>) is ready for pickup.</strong>
    Claim it at the barangay hall with a valid ID and <?= e(peso($r['fee_amount'])) ?>.
    <a href="<?= e(url('request.php?id=' . (int) $r['request_id'])) ?>">View request</a>
  </div>
<?php endforeach; ?>

<div class="figures">
  <a class="figure" href="<?= e(url('requests.php')) ?>"><span class="figure-value"><?= (int) $req['in_progress'] ?></span><span class="figure-label">Requests in progress</span></a>
  <a class="figure" href="<?= e(url('requests.php?status=Ready+for+Pickup')) ?>"><span class="figure-value"><?= (int) $req['ready'] ?></span><span class="figure-label">Ready for pickup</span></a>
  <a class="figure" href="<?= e(url('requests.php?status=Released')) ?>"><span class="figure-value"><?= (int) $req['released'] ?></span><span class="figure-label">Documents released</span></a>
  <a class="figure" href="<?= e(url('concerns.php')) ?>"><span class="figure-value"><?= (int) $con['open'] ?></span><span class="figure-label">Open concerns</span></a>
  <a class="figure" href="<?= e(url('concerns.php?status=Resolved')) ?>"><span class="figure-value"><?= (int) $con['resolved'] ?></span><span class="figure-label">Concerns resolved</span></a>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <section class="panel panel-flush">
      <h2>Recent requests</h2>
      <?php if (!$recentRequests): ?>
        <div class="empty"><p>You have not requested any documents yet.</p><a class="btn btn-sm btn-primary" href="<?= e(url('services.php')) ?>">See available documents</a></div>
      <?php else: ?>
      <table class="table mt-2">
        <thead><tr><th scope="col">Reference no.</th><th scope="col">Document</th><th scope="col">Status</th></tr></thead>
        <tbody>
        <?php foreach ($recentRequests as $r): ?>
          <tr><td class="ref"><a href="<?= e(url('request.php?id=' . (int) $r['request_id'])) ?>"><?= e($r['reference_no']) ?></a></td><td><?= e($r['doc_name']) ?></td><td><?= status_badge($r['status']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </section>
  </div>
  <div class="col-lg-6">
    <section class="panel panel-flush">
      <h2>Recent concerns</h2>
      <?php if (!$recentConcerns): ?>
        <div class="empty"><p>You have not reported any concerns.</p><a class="btn btn-sm btn-primary" href="<?= e(url('resident/concern-form.php')) ?>">Report a concern</a></div>
      <?php else: ?>
      <table class="table mt-2">
        <thead><tr><th scope="col">Reference no.</th><th scope="col">Concern</th><th scope="col">Status</th></tr></thead>
        <tbody>
        <?php foreach ($recentConcerns as $c): ?>
          <tr><td class="ref"><a href="<?= e(url('concern.php?id=' . (int) $c['concern_id'])) ?>"><?= e($c['reference_no']) ?></a></td><td><?= e($c['title']) ?></td><td><?= status_badge($c['status']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
