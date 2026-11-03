<?php
require __DIR__ . '/../app/bootstrap.php';

// Concern categories: list, create, edit, archive, restore, delete (create/edit on the same page).
$user = require_role(ROLE_ADMIN);
$editId = (int) query_param('edit');

if (is_post()) {
    csrf_verify();
    $op = post_param('op');
    $id = (int) post_param('id');

    if ($op === 'save') {
        $v = new Validator($_POST);
        $v->required('name', 'Name')->length('name', 'Name', 3, 100)
          ->required('description', 'Description')->length('description', 'Description', 10, 500)
          ->required('default_priority', 'Default priority')->in('default_priority', 'default priority', array_keys(PRIORITY_LABELS));
        if (!$v->fails() && db_value('SELECT 1 FROM concern_categories WHERE name = ? AND category_id <> ?', [$v->value('name'), $id])) {
            $v->custom(false, 'name', 'Another category already has this name.');
        }
        if ($id && !db_value('SELECT 1 FROM concern_categories WHERE category_id = ?', [$id])) {
            not_found();
        }
        if ($v->fails()) {
            back_with_errors($v->errors(), 'admin/categories.php' . ($id ? '?edit=' . $id : ''));
        }
        $fields = [$v->value('name'), $v->value('description'), (int) $v->value('default_priority')];
        if ($id) {
            db_exec('UPDATE concern_categories SET name = ?, description = ?, default_priority = ? WHERE category_id = ?', array_merge($fields, [$id]));
            log_activity('category_updated', 'concern_category', $id, $v->value('name'));
        } else {
            $id = db_insert('INSERT INTO concern_categories (name, description, default_priority) VALUES (?, ?, ?)', $fields);
            log_activity('category_created', 'concern_category', $id, $v->value('name'));
        }
        clear_old_input();
        flash('success', '"' . $v->value('name') . '" was saved.');
        redirect('admin/categories.php');
    }

    $cat = db_one('SELECT category_id, name FROM concern_categories WHERE category_id = ?', [$id]);
    if (!$cat) {
        flash('danger', 'That category no longer exists.');
        redirect('admin/categories.php');
    }
    if ($op === 'archive' || $op === 'restore') {
        db_exec('UPDATE concern_categories SET is_archived = ? WHERE category_id = ?', [$op === 'archive' ? 1 : 0, $id]);
        log_activity('category_' . ($op === 'archive' ? 'archived' : 'restored'), 'concern_category', $id, $cat['name']);
        flash('success', "\"{$cat['name']}\" was " . ($op === 'archive' ? 'archived. Residents can no longer choose it.' : 'restored.'));
    } elseif ($op === 'delete') {
        $used = (int) db_value('SELECT COUNT(*) FROM concerns WHERE category_id = ?', [$id]);
        if ($used) {
            flash('danger', "\"{$cat['name']}\" is used by $used concern(s), so it can only be archived.");
        } else {
            db_exec('DELETE FROM concern_categories WHERE category_id = ?', [$id]);
            log_activity('category_deleted', 'concern_category', $id, $cat['name']);
            flash('success', "\"{$cat['name']}\" was deleted.");
        }
    }
    redirect('admin/categories.php');
}

$errors = take_errors();
$editing = $editId ? db_one('SELECT * FROM concern_categories WHERE category_id = ?', [$editId]) : null;
$rows = db_all(
    'SELECT cc.*, (SELECT COUNT(*) FROM concerns c WHERE c.category_id = cc.category_id) AS used
       FROM concern_categories cc ORDER BY cc.is_archived, cc.name'
);
$pageTitle = 'Concern categories';
$activeNav = 'categories';
require APP_ROOT . '/app/views/header.php';
?>
<div class="page-head"><div><h1>Concern categories</h1><p>Types of community problems residents can report. The default priority is used until staff review the report.</p></div></div>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th scope="col">Category</th><th scope="col">Default priority</th><th scope="col">Concerns</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><strong><?= e($r['name']) ?></strong><div class="small text-muted"><?= e($r['description']) ?></div></td>
            <td><?= priority_badge((int) $r['default_priority']) ?></td>
            <td><?= (int) $r['used'] ?></td>
            <td><?= $r['is_archived'] ? '<span class="status status-muted">Archived</span>' : '<span class="status status-done">Active</span>' ?></td>
            <td class="text-end nowrap">
              <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/categories.php?edit=' . (int) $r['category_id'])) ?>">Edit<span class="visually-hidden"> <?= e($r['name']) ?></span></a>
              <form method="post" class="d-inline">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['category_id'] ?>">
                <?php if ($r['is_archived']): ?>
                  <button class="btn btn-sm btn-outline-secondary" name="op" value="restore">Restore</button>
                <?php else: ?>
                  <button class="btn btn-sm btn-outline-secondary" name="op" value="archive" data-confirm="Archive <?= e($r['name']) ?>?">Archive</button>
                <?php endif; ?>
                <?php if (!$r['used']): ?><button class="btn btn-sm btn-outline-danger" name="op" value="delete" data-confirm="Delete <?= e($r['name']) ?> permanently?">Delete</button><?php endif; ?>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="col-lg-4">
    <form method="post" class="panel" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="save">
      <input type="hidden" name="id" value="<?= (int) ($editing['category_id'] ?? 0) ?>">
      <h2><?= $editing ? 'Edit category' : 'Add category' ?></h2>
      <div class="mb-3">
        <label class="form-label required" for="name">Name</label>
        <input class="form-control<?= invalid_class($errors, 'name') ?>" id="name" name="name" maxlength="100" value="<?= e(old('name', $editing['name'] ?? '')) ?>"<?= aria_invalid($errors, 'name') ?>>
        <?= field_error($errors, 'name') ?>
      </div>
      <div class="mb-3">
        <label class="form-label required" for="description">Description</label>
        <textarea class="form-control<?= invalid_class($errors, 'description') ?>" id="description" name="description" rows="3" maxlength="500"<?= aria_invalid($errors, 'description') ?>><?= e(old('description', $editing['description'] ?? '')) ?></textarea>
        <?= field_error($errors, 'description') ?>
      </div>
      <div class="mb-3">
        <label class="form-label required" for="default_priority">Default priority</label>
        <select class="form-select<?= invalid_class($errors, 'default_priority') ?>" id="default_priority" name="default_priority">
          <?php foreach (PRIORITY_LABELS as $k => $label): ?>
            <option value="<?= $k ?>" <?= (string) old('default_priority', $editing['default_priority'] ?? 2) === (string) $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'default_priority') ?>
      </div>
      <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Add category' ?></button>
      <?php if ($editing): ?><a class="btn btn-link" href="<?= e(url('admin/categories.php')) ?>">Cancel</a><?php endif; ?>
    </form>
  </div>
</div>
<?php clear_old_input(); require APP_ROOT . '/app/views/footer.php'; ?>
