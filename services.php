<?php
require __DIR__ . '/app/bootstrap.php';

// Public page: anyone can view document requirements (no sign-in needed).
$q = query_param('q');
$sql = 'SELECT doc_type_id, name, description, fee, processing_days FROM document_types WHERE is_archived = 0';
$params = [];
if ($q !== '') {
    $sql .= ' AND (name LIKE ? OR description LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like];
}
$docTypes = db_all($sql . ' ORDER BY name', $params);

// Group requirements by document type with an associative array (hash map): O(1) lookup per type.
$requirements = [];
foreach (db_all('SELECT doc_type_id, requirement_text FROM document_requirements ORDER BY doc_type_id, sort_order') as $r) {
    $requirements[$r['doc_type_id']][] = $r['requirement_text'];
}

$user = current_user();
$canRequest = $user && is_verified($user) && $user['role_key'] === ROLE_RESIDENT;
$pageTitle = 'Services and requirements';
$activeNav = 'services';
require APP_ROOT . '/app/views/header.php';
?>
<div class="page-head">
  <div>
    <h1>Services and requirements</h1>
    <p>What each barangay document is for, how much it costs, how long it takes, and what to bring when you claim it.</p>
  </div>
</div>

<form class="filters" method="get" role="search">
  <div class="row g-2 align-items-end">
    <div class="col-sm-8 col-md-6">
      <label class="form-label" for="q">Search documents</label>
      <input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="e.g. clearance, residency, indigency">
    </div>
    <div class="col-auto"><button class="btn btn-primary" type="submit">Search</button></div>
    <?php if ($q !== ''): ?><div class="col-auto"><a class="btn btn-link" href="<?= e(url('services.php')) ?>">Clear</a></div><?php endif; ?>
  </div>
</form>

<?php if (!$docTypes): ?>
  <div class="panel empty"><p>No documents match "<?= e($q) ?>".</p><a href="<?= e(url('services.php')) ?>">Show all documents</a></div>
<?php endif; ?>

<?php foreach ($docTypes as $d): ?>
  <article class="service" id="doc-<?= (int) $d['doc_type_id'] ?>">
    <h2><?= e($d['name']) ?></h2>
    <p class="mb-0"><?= e($d['description']) ?></p>
    <div class="service-meta">
      <span>Fee: <strong><?= e(peso($d['fee'])) ?></strong></span>
      <span>Processing time: <strong><?= (int) $d['processing_days'] ?> working day(s)</strong></span>
    </div>
    <h3 class="h6">Requirements</h3>
    <ul>
      <?php foreach ($requirements[$d['doc_type_id']] ?? ['No additional requirements.'] as $req): ?>
        <li><?= e($req) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($canRequest): ?>
      <a class="btn btn-sm btn-primary mt-3" href="<?= e(url('resident/request-form.php?type=' . (int) $d['doc_type_id'])) ?>">Request this document</a>
    <?php elseif (!$user): ?>
      <a class="btn btn-sm btn-outline-primary mt-3" href="<?= e(url('resident/request-form.php?type=' . (int) $d['doc_type_id'])) ?>">Sign in to request<span class="visually-hidden"> <?= e($d['name']) ?></span></a>
    <?php endif; ?>
  </article>
<?php endforeach; ?>

<?php if (!$user): ?>
  <p class="text-muted">To request a document online, <a href="<?= e(url('auth/register.php')) ?>">create a resident account</a> or <a href="<?= e(url('auth/login.php')) ?>">sign in</a>.</p>
<?php endif; ?>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
