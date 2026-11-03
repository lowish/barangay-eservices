<?php if (($pages ?? 1) > 1): ?>
<nav aria-label="Pages" class="mt-3">
  <ul class="pagination pagination-sm flex-wrap">
    <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= e(query_with(['page' => max(1, $page - 1)])) ?>">Previous</a></li>
    <?php for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++): ?>
      <li class="page-item<?= $i === $page ? ' active' : '' ?>"><a class="page-link" href="<?= e(query_with(['page' => $i])) ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a></li>
    <?php endfor; ?>
    <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>"><a class="page-link" href="<?= e(query_with(['page' => min($pages, $page + 1)])) ?>">Next</a></li>
  </ul>
</nav>
<?php endif; ?>
