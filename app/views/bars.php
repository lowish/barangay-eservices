<?php
/** Renders $barRows (each ['label' => ..., 'count' => ...]) as a simple horizontal bar list. */
$barMax = max(1, ...array_map(fn ($r) => (int) $r['count'], $barRows ?: [['count' => 0]]));
?>
<ul class="bars">
  <?php foreach ($barRows as $b): ?>
    <?php $w = (int) (round(((int) $b['count'] / $barMax) * 20) * 5); ?>
    <li>
      <span><?= e($b['label']) ?></span>
      <span class="bar-track" aria-hidden="true"><span class="bar-fill w-<?= $w ?>"></span></span>
      <span class="bar-count"><?= (int) $b['count'] ?></span>
    </li>
  <?php endforeach; ?>
  <?php if (!$barRows): ?><li class="text-muted">No records yet.</li><?php endif; ?>
</ul>
