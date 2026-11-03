<?php
require __DIR__ . '/app/bootstrap.php';

// Public landing page. Signed-in users go straight to their dashboard.
if (current_user()) {
    redirect(is_verified() ? home_path_for_current_user() : 'auth/verify-notice.php');
}

$docTypes = db_all('SELECT doc_type_id, name, description, fee, processing_days FROM document_types WHERE is_archived = 0 ORDER BY name');
$categories = db_all('SELECT name FROM concern_categories WHERE is_archived = 0 ORDER BY name');
$pageTitle = 'Home';
$activeNav = 'home';
require APP_ROOT . '/app/views/header.php';
?>
<section class="landing-hero">
  <div>
    <div class="landing-id">
      <?= render_seal('landing-seal') ?>
      <div><strong><?= e(barangay_name()) ?></strong><span><?= e(barangay_location()) ?></span></div>
    </div>
    <h1>Request barangay documents and report community concerns online</h1>
    <p class="lead">Check what you need before going to the barangay hall, send your document request ahead, and follow its status from your phone. You can also report problems in your purok, such as uncollected garbage, damaged roads or broken streetlights, and see when they are acted on.</p>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-primary" href="<?= e(url('auth/register.php')) ?>">Register as a resident</a>
      <a class="btn btn-outline-primary" href="<?= e(url('auth/login.php')) ?>">Sign in</a>
    </div>
  </div>
  <aside class="hall-card" aria-label="Barangay hall information">
    <h2>Barangay hall</h2>
    <dl class="details mb-3">
      <dt>Barangay</dt><dd><?= e(barangay_name()) ?></dd>
      <dt>Location</dt><dd><?= e(barangay_location()) ?></dd>
      <dt>Office hours</dt><dd><?= e(barangay()['office_hours']) ?></dd>
    </dl>
    <p class="text-muted small mb-0">Documents are released and paid for at the barangay hall during office hours. For fires, crimes and medical emergencies, call 911.</p>
  </aside>
</section>

<section class="landing-section" aria-labelledby="services-title">
  <h2 id="services-title">Available services</h2>
  <p>Documents you can request online. Anyone can view the requirements; you need a resident account to submit a request.</p>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th scope="col">Document</th><th scope="col">Fee</th><th scope="col">Processing time</th><th scope="col"><span class="visually-hidden">Requirements</span></th></tr></thead>
      <tbody>
      <?php foreach ($docTypes as $d): ?>
        <tr>
          <td><strong><?= e($d['name']) ?></strong><div class="small text-muted"><?= e($d['description']) ?></div></td>
          <td class="nowrap"><?= (float) $d['fee'] > 0 ? e(peso($d['fee'])) : 'Free' ?></td>
          <td class="nowrap"><?= (int) $d['processing_days'] ?> working day(s)</td>
          <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('services.php#doc-' . (int) $d['doc_type_id'])) ?>">Requirements<span class="visually-hidden"> for <?= e($d['name']) ?></span></a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$docTypes): ?><tr><td colspan="4" class="empty">No documents are available online yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="landing-section" aria-labelledby="request-title">
  <h2 id="request-title">How to request a document</h2>
  <ol class="steps">
    <li><strong>Check the requirements</strong><span>See the fee, processing time and what to bring on the services page.</span></li>
    <li><strong>Register and verify your email</strong><span>Create a resident account and open the link sent to your email.</span></li>
    <li><strong>Submit your request</strong><span>Choose the document and purpose. You get a reference number right away.</span></li>
    <li><strong>Claim it at the barangay hall</strong><span>When the status says Ready for Pickup, bring a valid ID and the fee.</span></li>
  </ol>
</section>

<section class="landing-section" aria-labelledby="concern-title">
  <h2 id="concern-title">How to report a community concern</h2>
  <ol class="steps">
    <li><strong>Sign in to your account</strong><span>Only verified residents can submit reports.</span></li>
    <li><strong>Describe the problem</strong><span>Choose a category, the purok, and the exact location or landmark.</span></li>
    <li><strong>The barangay reviews it</strong><span>Staff set its priority and assign someone to handle it.</span></li>
    <li><strong>Follow it until it is resolved</strong><span>You are notified by email when it is assigned and resolved.</span></li>
  </ol>
  <?php if ($categories): ?>
    <p class="mt-3 mb-0 text-muted">Concerns you can report:</p>
    <ul class="chip-list">
      <?php foreach ($categories as $c): ?><li><?= e($c['name']) ?></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<div class="landing-cta">
  <p><strong>Ready to start?</strong> Register once, then request documents and report concerns from any device.</p>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-primary" href="<?= e(url('auth/register.php')) ?>">Register</a>
    <a class="btn btn-outline-primary" href="<?= e(url('auth/login.php')) ?>">Sign in</a>
  </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
