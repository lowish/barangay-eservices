<?php
/**
 * ConcernTriageQueue — a priority queue (binary max-heap) for community concerns.
 *
 * WHY: Concerns are not equally urgent. A fallen electric post must be handled before a
 * clogged canal reported earlier, but a routine report must not wait forever either.
 * Staff take work from this queue with "Take next concern", which always hands out the
 * most urgent waiting concern.
 *
 * HOW: Built on PHP's SplPriorityQueue (a binary heap). Each concern's heap priority is the array
 *     [effective priority, -submitted timestamp, -concern id]
 * PHP compares arrays element by element, so:
 *   1. a higher effective priority comes out first;
 *   2. within the same priority, the oldest report comes out first (first come, first served);
 *   3. the id breaks any remaining tie, so the order is always deterministic.
 *
 * Effective priority = the priority set by staff + 1 level for every AGING_DAYS days the concern
 * has been waiting (capped at Urgent). This "aging" prevents low-priority reports from starving.
 *
 * TIME COMPLEXITY (n = concerns in the queue):
 *   insert()   O(log n)  — the new item sifts up the heap
 *   extract()  O(log n)  — remove the root, the last item sifts down
 *   peek()     O(1)      — the root is the most urgent item
 *   count()    O(1)
 *   build from n rows: O(n log n);  ordered listing (on a copy): O(n log n)
 * Compared with re-scanning an unsorted list for the most urgent item each time (O(n) per take),
 * the heap keeps each take at O(log n).
 */
class ConcernTriageQueue implements Countable
{
    public const AGING_DAYS = 3;

    private SplPriorityQueue $heap;

    /** Trace of operations, shown on the queue page to demonstrate the structure. */
    private array $trace = [];

    public function __construct()
    {
        $this->heap = new SplPriorityQueue();
        $this->heap->setExtractFlags(SplPriorityQueue::EXTR_DATA);
    }

    /** Build a queue from concern rows (must include concern_id, priority, created_at). */
    public static function fromRows(array $rows, ?int $now = null): self
    {
        $queue = new self();
        foreach ($rows as $row) {
            $queue->insert($row, $now);
        }
        return $queue;
    }

    public static function effectivePriority(int $basePriority, string $createdAt, ?int $now = null): int
    {
        $now = $now ?? time();
        $daysWaiting = max(0, intdiv($now - strtotime($createdAt), 86400));
        return min(4, $basePriority + intdiv($daysWaiting, self::AGING_DAYS));
    }

    /** O(log n) */
    public function insert(array $concern, ?int $now = null): void
    {
        $now = $now ?? time();
        $concern['days_waiting'] = max(0, intdiv($now - strtotime($concern['created_at']), 86400));
        $concern['effective_priority'] = self::effectivePriority((int) $concern['priority'], $concern['created_at'], $now);
        $key = [$concern['effective_priority'], -strtotime($concern['created_at']), -(int) $concern['concern_id']];
        $this->heap->insert($concern, $key);
        $this->trace[] = ['op' => 'insert', 'ref' => $concern['reference_no'] ?? $concern['concern_id'], 'key' => $concern['effective_priority']];
    }

    /** Remove and return the most urgent concern. O(log n) */
    public function extract(): ?array
    {
        if ($this->heap->isEmpty()) {
            return null;
        }
        $item = $this->heap->extract();
        $this->trace[] = ['op' => 'extract', 'ref' => $item['reference_no'] ?? $item['concern_id'], 'key' => $item['effective_priority']];
        return $item;
    }

    /** Look at the most urgent concern without removing it. O(1) */
    public function peek(): ?array
    {
        return $this->heap->isEmpty() ? null : $this->heap->top();
    }

    public function count(): int
    {
        return $this->heap->count();
    }

    public function isEmpty(): bool
    {
        return $this->heap->isEmpty();
    }

    /** The full queue in service order, taken from a copy so the queue itself is unchanged. O(n log n) */
    public function toOrderedArray(): array
    {
        $copy = clone $this->heap;
        $out = [];
        while (!$copy->isEmpty()) {
            $out[] = $copy->extract();
        }
        return $out;
    }

    public function trace(): array
    {
        return $this->trace;
    }
}
