<?php
/**
 * Monthly activity chart: document requests and community concerns filed per month this year.
 * Server-rendered SVG (no chart library, works with the strict Content-Security-Policy).
 * Hovering a month shows its numbers; "Show as table" gives the same data as text.
 */
$year = (int) date('Y');
$requestsByMonth = array_fill(1, 12, 0);
$concernsByMonth = array_fill(1, 12, 0);
foreach (db_all('SELECT MONTH(created_at) AS m, COUNT(*) AS n FROM document_requests WHERE YEAR(created_at) = ? GROUP BY m', [$year]) as $r) {
    $requestsByMonth[(int) $r['m']] = (int) $r['n'];
}
foreach (db_all('SELECT MONTH(created_at) AS m, COUNT(*) AS n FROM concerns WHERE YEAR(created_at) = ? GROUP BY m', [$year]) as $r) {
    $concernsByMonth[(int) $r['m']] = (int) $r['n'];
}
$monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// Geometry
$W = 760; $H = 250; $left = 34; $right = 8; $top = 12; $bottom = 28;
$plotW = $W - $left - $right;
$plotH = $H - $top - $bottom;
$maxValue = max(1, max($requestsByMonth), max($concernsByMonth));
$step = max(1, (int) ceil($maxValue / 4));
$axisMax = $step * (int) ceil($maxValue / $step);
$groupW = $plotW / 12;
$barW = min(18, ($groupW - 12) / 2);
$y = fn (int $v) => $top + $plotH - ($v / $axisMax) * $plotH;

/** Bar with 4px rounded top corners, square at the baseline. */
$barPath = function (float $x, float $value) use ($y, $barW, $top, $plotH): string {
    $y0 = $top + $plotH;
    $y1 = $y((int) $value);
    $h = $y0 - $y1;
    $r = min(4, $barW / 2, $h);
    return sprintf('M%.1f %.1fV%.1fQ%.1f %.1f %.1f %.1fH%.1fQ%.1f %.1f %.1f %.1fV%.1fZ',
        $x, $y0, $y1 + $r, $x, $y1, $x + $r, $y1, $x + $barW - $r, $x + $barW, $y1, $x + $barW, $y1 + $r, $y0);
};
?>
<section class="panel">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
    <div>
      <h2 class="mb-1">Monthly activity, <?= $year ?></h2>
      <p class="text-muted small mb-0">Document requests and community concerns filed each month.</p>
    </div>
    <div class="chart-legend" aria-hidden="true">
      <span><i class="swatch swatch-requests"></i>Document requests</span>
      <span><i class="swatch swatch-concerns"></i>Community concerns</span>
    </div>
  </div>
  <svg class="chart" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Bar chart of document requests and community concerns per month in <?= $year ?>. The same data is in the table below.">
    <?php for ($v = 0; $v <= $axisMax; $v += $step): ?>
      <line class="<?= $v === 0 ? 'baseline' : 'grid' ?>" x1="<?= $left ?>" x2="<?= $W - $right ?>" y1="<?= round($y($v), 1) ?>" y2="<?= round($y($v), 1) ?>"/>
      <text class="axis-text" x="<?= $left - 8 ?>" y="<?= round($y($v) + 4, 1) ?>" text-anchor="end"><?= $v ?></text>
    <?php endfor; ?>
    <?php for ($m = 1; $m <= 12; $m++): ?>
      <?php
        $gx = $left + ($m - 1) * $groupW;
        $bx = $gx + ($groupW - (2 * $barW + 2)) / 2;
      ?>
      <g class="bar-group">
        <title><?= e($monthNames[$m] . ' ' . $year . ': ' . $requestsByMonth[$m] . ' document request(s), ' . $concernsByMonth[$m] . ' concern(s)') ?></title>
        <rect class="bar-hit" x="<?= round($gx, 1) ?>" y="<?= $top ?>" width="<?= round($groupW, 1) ?>" height="<?= $plotH ?>"/>
        <?php if ($requestsByMonth[$m] > 0): ?><path class="bar-requests" d="<?= $barPath($bx, $requestsByMonth[$m]) ?>"/><?php endif; ?>
        <?php if ($concernsByMonth[$m] > 0): ?><path class="bar-concerns" d="<?= $barPath($bx + $barW + 2, $concernsByMonth[$m]) ?>"/><?php endif; ?>
        <text class="axis-text" x="<?= round($gx + $groupW / 2, 1) ?>" y="<?= $H - 8 ?>" text-anchor="middle"><?= $monthNames[$m] ?></text>
      </g>
    <?php endfor; ?>
  </svg>
  <details class="chart-table mt-2">
    <summary>Show as table</summary>
    <div class="table-responsive mt-2">
      <table class="table table-sm">
        <thead><tr><th scope="col">Month</th><?php for ($m = 1; $m <= 12; $m++): ?><th scope="col"><?= $monthNames[$m] ?></th><?php endfor; ?><th scope="col">Total</th></tr></thead>
        <tbody>
          <tr><th scope="row">Document requests</th><?php for ($m = 1; $m <= 12; $m++): ?><td><?= $requestsByMonth[$m] ?></td><?php endfor; ?><td><strong><?= array_sum($requestsByMonth) ?></strong></td></tr>
          <tr><th scope="row">Community concerns</th><?php for ($m = 1; $m <= 12; $m++): ?><td><?= $concernsByMonth[$m] ?></td><?php endfor; ?><td><strong><?= array_sum($concernsByMonth) ?></strong></td></tr>
        </tbody>
      </table>
    </div>
  </details>
</section>
