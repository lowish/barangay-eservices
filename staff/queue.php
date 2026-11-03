<?php
require __DIR__ . '/../app/bootstrap.php';
require_once APP_ROOT . '/app/lib/ConcernTriageQueue.php';

/*
 * Triage queue for unassigned concerns, backed by ConcernTriageQueue (SplPriorityQueue / binary heap).
 * "Take next concern" extracts the most urgent concern and assigns it to the signed-in staff member.
 */
$user = require_role(ROLE_STAFF);

function load_waiting_concerns(): array
{
    return db_all(
        "SELECT c.concern_id, c.reference_no, c.title, c.location, c.priority, c.status, c.created_at,
                cc.name AS category_name, p.purok_name
           FROM concerns c
           JOIN concern_categories cc ON cc.category_id = c.category_id
           JOIN puroks p ON p.purok_id = c.purok_id
          WHERE c.status IN ('Submitted', 'Under Review') AND c.assigned_to IS NULL AND c.is_archived = 0"
    );
}

if (is_post()) {
    csrf_verify();
    $queue = ConcernTriageQueue::fromRows(load_waiting_concerns());   // n inserts: O(n log n)
    $taken = null;
    // Extract until one is successfully assigned (another staff member may have taken the top one).
    while (($top = $queue->extract()) !== null) {                     // each extract: O(log n)
        if ($top['status'] === 'Submitted') {
            [$ok] = change_concern_status((int) $top['concern_id'], 'Submitted', 'Under Review', 'Taken from the triage queue', $user);
            if (!$ok) {
                continue;
            }
        }
        [$ok] = change_concern_status((int) $top['concern_id'], 'Under Review', 'Assigned', 'Taken from the triage queue', $user, ['assignee' => $user['user_id']]);
        if ($ok) {
            $taken = $top;
            break;
        }
    }
    if ($taken) {
        log_activity('queue_take_next', 'concern', (int) $taken['concern_id'],
            "{$taken['reference_no']} extracted from triage queue (effective priority " . PRIORITY_LABELS[$taken['effective_priority']] . ')');
        flash('success', "You took {$taken['reference_no']} ({$taken['title']}). It is now assigned to you.");
        redirect('concern.php?id=' . (int) $taken['concern_id']);
    }
    flash('info', 'The queue is empty. There are no unassigned concerns waiting.');
    redirect('staff/queue.php');
}

$rows = load_waiting_concerns();
$queue = ConcernTriageQueue::fromRows($rows);
$next = $queue->peek();                 // O(1)
$ordered = $queue->toOrderedArray();    // O(n log n) on a copy

// Arrival order (plain FIFO) for comparison with the heap order.
$arrival = $rows;
usort($arrival, fn ($a, $b) => [$a['created_at'], $a['concern_id']] <=> [$b['created_at'], $b['concern_id']]);
$arrivalPos = [];
foreach ($arrival as $i => $r) {
    $arrivalPos[$r['concern_id']] = $i + 1;    // hash map: concern id => arrival position
}

$pageTitle = 'Triage queue';
$activeNav = 'queue';
require APP_ROOT . '/app/views/header.php';
?>
<div class="page-head">
  <div>
    <h1>Triage queue</h1>
    <p>Unassigned concerns in the order they should be handled: most urgent first, and oldest first within the same priority. Concerns waiting longer than <?= ConcernTriageQueue::AGING_DAYS ?> days move up one level so routine reports are not left behind.</p>
  </div>
  <form method="post" class="page-actions">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-primary" <?= $next ? '' : 'disabled' ?>>Take next concern</button>
  </form>
</div>

<?php if ($next): ?>
<section class="panel queue-next">
  <h2>Next in line</h2>
  <p class="mb-1"><strong><?= e($next['reference_no']) ?>: <?= e($next['title']) ?></strong></p>
  <p class="mb-0 text-muted"><?= e($next['category_name']) ?>, <?= e($next['location']) ?>, <?= e($next['purok_name']) ?>. Waiting <?= (int) $next['days_waiting'] ?> day(s). Queue priority: <?= priority_badge((int) $next['effective_priority']) ?></p>
</section>
<?php endif; ?>

<div class="result-meta"><span><?= count($queue) ?> concern(s) waiting</span></div>
<div class="table-wrap mb-4">
  <?php if (!$ordered): ?>
    <div class="empty"><p>No unassigned concerns are waiting.</p></div>
  <?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th scope="col">Queue position</th>
        <th scope="col">Arrival order</th>
        <th scope="col">Concern</th>
        <th scope="col">Set priority</th>
        <th scope="col">Days waiting</th>
        <th scope="col">Queue priority</th>
        <th scope="col">Status</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($ordered as $i => $c): ?>
      <tr>
        <td><strong><?= $i + 1 ?></strong></td>
        <td class="text-muted"><?= (int) $arrivalPos[$c['concern_id']] ?></td>
        <td><a href="<?= e(url('concern.php?id=' . (int) $c['concern_id'])) ?>"><?= e($c['reference_no']) ?></a> <?= e($c['title']) ?><div class="small text-muted"><?= e($c['category_name']) ?>, <?= e($c['purok_name']) ?></div></td>
        <td><?= priority_badge((int) $c['priority']) ?></td>
        <td><?= (int) $c['days_waiting'] ?></td>
        <td><?= priority_badge((int) $c['effective_priority']) ?></td>
        <td><?= status_badge($c['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <section class="panel">
      <h2>How the queue works</h2>
      <p>This page builds a <strong>priority queue</strong> (a binary max-heap, PHP <code>SplPriorityQueue</code>) from the waiting concerns. Each concern goes into the heap with the key <code>[queue priority, −submitted time, −id]</code>. PHP compares the key arrays element by element, so a higher priority always comes out first, and among equal priorities the oldest report comes out first.</p>
      <p><strong>Take next concern</strong> calls <code>extract()</code> to remove the root of the heap and assigns that concern to you. If another staff member took it a moment earlier, the next item is extracted instead, so two people never get the same concern.</p>
      <p class="mb-0">The "Arrival order" column shows where each concern would be in a plain first-come, first-served list. The difference between the two columns is what the priority queue changes.</p>
    </section>
  </div>
  <div class="col-lg-5">
    <section class="panel">
      <h2>Time complexity</h2>
      <table class="table complexity">
        <thead><tr><th scope="col">Operation</th><th scope="col">Used for</th><th scope="col">Cost</th></tr></thead>
        <tbody>
          <tr><td><code>insert()</code></td><td>Add a concern to the heap</td><td>O(log n)</td></tr>
          <tr><td><code>extract()</code></td><td>Take next concern</td><td>O(log n)</td></tr>
          <tr><td><code>peek()</code></td><td>"Next in line" panel</td><td>O(1)</td></tr>
          <tr><td><code>count()</code></td><td>Waiting count</td><td>O(1)</td></tr>
          <tr><td>Build from n rows</td><td>Loading this page</td><td>O(n log n)</td></tr>
        </tbody>
      </table>
      <p class="small text-muted mb-0">Without the heap, finding the most urgent concern means scanning the whole list each time: O(n) per take.</p>
    </section>
  </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
