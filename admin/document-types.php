<?php
require __DIR__ . '/../app/bootstrap.php';

// Document types (service information): list, archive, restore, delete. Create/edit is in document-type-form.php.
$user = require_role(ROLE_ADMIN);

if (is_post()) {
    csrf_verify();
    $id = (int) post_param('id');
    $op = post_param('op');
    $dt = db_one('SELECT doc_type_id, name, is_archived FROM document_types WHERE doc_type_id = ?', [$id]);
    if (!$dt) {
        flash('danger', 'That document type no longer exists.');
        redirect('admin/document-types.php');
    }
    $used = (int) db_value('SELECT COUNT(*) FROM document_requests WHERE doc_type_id = ?', [$id]);

    if ($op === 'archive') {
        db_exec('UPDATE document_types SET is_archived = 1 WHERE doc_type_id = ?', [$id]);
        log_activity('document_type_archived', 'document_type', $id, $dt['name']);
        flash('success', "\"{$dt['name']}\" was archived. Residents can no longer request it; existing requests are kept.");
    } elseif ($op === 'restore') {
        db_exec('UPDATE document_types SET is_archived = 0 WHERE doc_type_id = ?', [$id]);
        log_activity('document_type_restored', 'document_type', $id, $dt['name']);
        flash('success', "\"{$dt['name']}\" is available again.");
    } elseif ($op === 'delete') {
        // Only never-used types may be deleted; used ones must be archived to keep request history intact.
        if ($used > 0) {
            flash('danger', "\"{$dt['name']}\" has $used request(s) on record, so it can only be archived, not deleted.");
        } else {
            db_exec('DELETE FROM document_types WHERE doc_type_id = ?', [$id]);
            log_activity('document_type_deleted', 'document_type', $id, $dt['name']);
            flash('success', "\"{$dt['name']}\" was deleted.");
        }
    }
    redirect('admin/document-types.php');
}

$q = query_param('q');
$show = in_array(query_param('show'), ['archived', 'all'], true) ? query_param('show') : '';
$where = $show === 'archived' ? 'WHERE dt.is_archived = 1' : ($show === 'all' ? 'WHERE 1=1' : 'WHERE dt.is_archived = 0');
$params = [];
if ($q !== '') {
    $where .= ' AND (dt.name LIKE ? OR dt.description LIKE ?)';
    $params = ['%' . $q . '%', '%' . $q . '%'];
}
$rows = db_all(
    "SELECT dt.*, (SELECT COUNT(*) FROM document_requirements r WHERE r.doc_type_id = dt.doc_type_id) AS req_count,
            (SELECT COUNT(*) FROM document_requests x WHERE x.doc_type_id = dt.doc_type_id) AS used
       FROM document_types dt $where ORDER BY dt.name",
    $params
);

$pageTitle = 'Document types';
$activeNav = 'doctypes';
require APP_ROOT . '/app/views/header.php';
?>
<div class="page-head">
  <div><h1>Document types</h1><p>Documents residents can request, with their fees, processing times and requirements.</p></div>
  <div class="page-actions"><a class="btn btn-primary" href="<?= e(url('admin/document-type-form.php')) ?>">Add document type</a></div>
</div>

<form class="filters" method="get" role="search">
  <div class="row g-2 align-items-end">
    <div class="col-md-4"><label class="form-label" for="q">Search</label><input class="form-control" id="q" name="q" value="<?= e($q) ?>"></div>
    <div class="col-6 col-md-2"><label class="form-label" for="show">Show</label>
      <select class="form-select" id="show" name="show">
        <option value="">Active</option>
        <option value="archived" <?= $show === 'archived' ? 'selected' : '' ?>>Archived</option>
        <option value="all" <?= $show === 'all' ? 'selected' : '' ?>>All</option>
      </select></div>
    <div class="col-auto"><button class="btn btn-primary" type="submit">Apply</button> <a class="btn btn-link" href="<?= e(url('admin/document-types.php')) ?>">Reset</a></div>
  </div>
</form>

<div class="table-wrap">
  <?php if (!$rows): ?><div class="empty"><p>No document types found.</p></div><?php else: ?>
  <table class="table">
    <thead><tr><th scope="col">Document</th><th scope="col">Fee</th><th scope="col">Processing</th><th scope="col">Requirements</th><th scope="col">Requests</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><strong><?= e($r['name']) ?></strong><div class="small text-muted text-truncate cell-clip"><?= e($r['description']) ?></div></td>
        <td class="nowrap"><?= e(peso($r['fee'])) ?></td>
        <td class="nowrap"><?= (int) $r['processing_days'] ?> day(s)</td>
        <td><?= (int) $r['req_count'] ?></td>
        <td><?= (int) $r['used'] ?></td>
        <td><?= $r['is_archived'] ? '<span class="status status-muted">Archived</span>' : '<span class="status status-done">Available</span>' ?></td>
        <td class="text-end nowrap">
          <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/document-type-form.php?id=' . (int) $r['doc_type_id'])) ?>">Edit<span class="visually-hidden"> <?= e($r['name']) ?></span></a>
          <form method="post" class="d-inline">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['doc_type_id'] ?>">
            <?php if ($r['is_archived']): ?>
              <button class="btn btn-sm btn-outline-secondary" name="op" value="restore">Restore</button>
            <?php else: ?>
              <button class="btn btn-sm btn-outline-secondary" name="op" value="archive" data-confirm="Archive <?= e($r['name']) ?>? Residents will no longer be able to request it.">Archive</button>
            <?php endif; ?>
            <?php if (!$r['used']): ?>
              <button class="btn btn-sm btn-outline-danger" name="op" value="delete" data-confirm="Delete <?= e($r['name']) ?> permanently?">Delete</button>
            <?php endif; ?>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
