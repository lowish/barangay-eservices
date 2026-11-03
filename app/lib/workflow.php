<?php
/**
 * Transaction workflows for document requests and community concerns.
 *
 * Each workflow is a table of allowed transitions: current status => [next status => who may do it].
 * A status change is only applied when:
 *   1. the transition exists in the table (prevents e.g. releasing an unapproved request),
 *   2. the acting user's role is allowed to perform it (server-side authorization),
 *   3. the record is still in the status the user saw (UPDATE ... WHERE status = ?),
 *      so the same transaction cannot be processed twice, even by two staff members at once.
 * Every change is written to a history table and to the activity log in one DB transaction.
 */

require_once APP_ROOT . '/app/lib/mailer.php';

// ---------------------------------------------------------------- Document requests

const REQUEST_STEPS = ['Pending', 'Under Review', 'Approved', 'Processing', 'Ready for Pickup', 'Released'];

function request_transitions(): array
{
    return [
        'Pending'          => ['Under Review' => [ROLE_STAFF], 'Cancelled' => [ROLE_RESIDENT]],
        'Under Review'     => ['Approved' => [ROLE_STAFF], 'Rejected' => [ROLE_STAFF]],
        'Approved'         => ['Processing' => [ROLE_STAFF]],
        'Processing'       => ['Ready for Pickup' => [ROLE_STAFF]],
        'Ready for Pickup' => ['Released' => [ROLE_STAFF]],
    ];
}

function request_action_label(string $to): string
{
    return [
        'Under Review'     => 'Start review',
        'Approved'         => 'Approve',
        'Rejected'         => 'Reject',
        'Processing'       => 'Start processing',
        'Ready for Pickup' => 'Mark ready for pickup',
        'Released'         => 'Release document',
        'Cancelled'        => 'Cancel request',
    ][$to] ?? $to;
}

/** Next statuses the given role may move this request to. */
function request_allowed_next(array $request, string $role): array
{
    $next = [];
    foreach (request_transitions()[$request['status']] ?? [] as $to => $roles) {
        if (in_array($role, $roles, true)) {
            $next[] = $to;
        }
    }
    return $next;
}

/**
 * Change a request's status. Returns [ok, message].
 */
function change_request_status(int $requestId, string $expectedFrom, string $to, string $remarks, array $actor): array
{
    $request = db_one(
        'SELECT dr.*, dt.name AS doc_name, u.email, u.first_name, u.last_name
           FROM document_requests dr
           JOIN document_types dt ON dt.doc_type_id = dr.doc_type_id
           JOIN users u ON u.user_id = dr.resident_id
          WHERE dr.request_id = ?',
        [$requestId]
    );
    if (!$request || $request['is_archived']) {
        return [false, 'This request no longer exists.'];
    }
    // Ownership: a resident may only act on their own request.
    if ($actor['role_key'] === ROLE_RESIDENT && (int) $request['resident_id'] !== (int) $actor['user_id']) {
        log_activity('access_denied', 'document_request', $requestId, 'Resident tried to change another resident\'s request');
        return [false, 'You can only change your own requests.'];
    }
    if ($request['status'] !== $expectedFrom) {
        return [false, "This request was already updated to \"{$request['status']}\" by someone else. The page now shows its current status."];
    }
    if (!in_array($to, request_allowed_next($request, $actor['role_key']), true)) {
        log_activity('invalid_action', 'document_request', $requestId, "Blocked transition {$request['status']} -> $to");
        return [false, "A request that is \"{$request['status']}\" cannot be changed to \"$to\"."];
    }
    if ($to === 'Rejected' && mb_strlen($remarks) < 5) {
        return [false, 'Enter the reason for rejection (at least 5 characters) so the resident knows what to fix.'];
    }
    if (mb_strlen($remarks) > 500) {
        return [false, 'Remarks must be 500 characters or fewer.'];
    }

    $ok = db_transaction(function () use ($request, $requestId, $to, $remarks, $actor) {
        $handler = $actor['role_key'] === ROLE_STAFF ? (int) $actor['user_id'] : $request['handled_by'];
        $changed = db_exec(
            'UPDATE document_requests SET status = ?, remarks = COALESCE(NULLIF(?, \'\'), remarks), handled_by = ?
              WHERE request_id = ? AND status = ?',
            [$to, $remarks, $handler !== null ? (int) $handler : null, $requestId, $request['status']]
        );
        if ($changed !== 1) {
            return false; // someone else changed it first
        }
        db_query(
            'INSERT INTO request_status_history (request_id, from_status, to_status, remarks, changed_by) VALUES (?, ?, ?, ?, ?)',
            [$requestId, $request['status'], $to, $remarks !== '' ? $remarks : null, (int) $actor['user_id']]
        );
        log_activity('request_status_changed', 'document_request', $requestId,
            "{$request['reference_no']}: {$request['status']} -> $to" . ($remarks !== '' ? " ($remarks)" : ''));
        return true;
    });

    if (!$ok) {
        return [false, 'This request was just updated by someone else. Refresh to see its current status.'];
    }

    // Tell the resident about milestones that need their attention.
    if (in_array($to, ['Approved', 'Rejected', 'Ready for Pickup', 'Released'], true)) {
        notify_request_status($request, $to, $remarks);
    }
    return [true, "Request {$request['reference_no']} is now \"$to\"."];
}

function notify_request_status(array $request, string $status, string $remarks): void
{
    $lines = [
        'Approved'         => 'Your request has been approved and will be processed shortly.',
        'Rejected'         => 'Your request was not approved.',
        'Ready for Pickup' => 'Your document is ready. Please claim it at the barangay hall and bring a valid ID and the fee of ' . peso($request['fee_amount']) . '.',
        'Released'         => 'Your document has been released. Thank you.',
    ];
    $body = '<p>Good day, ' . e($request['first_name']) . '.</p>'
        . '<p>Request <strong>' . e($request['reference_no']) . '</strong> (' . e($request['doc_name']) . ') is now <strong>' . e($status) . '</strong>.</p>'
        . '<p>' . e($lines[$status] ?? '') . '</p>'
        . ($remarks !== '' ? '<p>Remarks from the barangay: ' . e($remarks) . '</p>' : '')
        . '<p><a href="' . e(absolute_url('request.php?id=' . (int) $request['request_id'])) . '">View your request</a></p>';
    send_mail($request['email'], full_name($request), "Request {$request['reference_no']}: $status", $body);
}

// ---------------------------------------------------------------- Community concerns

const CONCERN_STEPS = ['Submitted', 'Under Review', 'Assigned', 'In Progress', 'Resolved', 'Closed'];

const PRIORITY_LABELS = [1 => 'Low', 2 => 'Normal', 3 => 'High', 4 => 'Urgent'];

/**
 * 'assignee' means: only the staff member the concern is assigned to.
 * 'owner' means: only the resident who reported it.
 */
function concern_transitions(): array
{
    return [
        'Submitted'    => ['Under Review' => [ROLE_STAFF], 'Withdrawn' => ['owner']],
        'Under Review' => ['Assigned' => [ROLE_STAFF], 'Rejected' => [ROLE_STAFF]],
        'Assigned'     => ['In Progress' => ['assignee']],
        'In Progress'  => ['Resolved' => ['assignee']],
        'Resolved'     => ['Closed' => [ROLE_STAFF, 'owner']],
    ];
}

function concern_action_label(string $to): string
{
    return [
        'Under Review' => 'Start review',
        'Assigned'     => 'Assign',
        'Rejected'     => 'Reject as invalid',
        'In Progress'  => 'Start work',
        'Resolved'     => 'Mark resolved',
        'Closed'       => 'Close concern',
        'Withdrawn'    => 'Withdraw concern',
    ][$to] ?? $to;
}

function concern_allowed_next(array $concern, array $actor): array
{
    $next = [];
    foreach (concern_transitions()[$concern['status']] ?? [] as $to => $who) {
        foreach ($who as $w) {
            $allowed = match ($w) {
                'owner'    => $actor['role_key'] === ROLE_RESIDENT && (int) $concern['resident_id'] === (int) $actor['user_id'],
                'assignee' => $actor['role_key'] === ROLE_STAFF && (int) $concern['assigned_to'] === (int) $actor['user_id'],
                default    => $actor['role_key'] === $w,
            };
            if ($allowed) {
                $next[] = $to;
                break;
            }
        }
    }
    return $next;
}

/**
 * Change a concern's status. $options may contain 'assignee' (user id) for Assigned
 * and 'priority' (1-4) set during review. Returns [ok, message].
 */
function change_concern_status(int $concernId, string $expectedFrom, string $to, string $note, array $actor, array $options = []): array
{
    $concern = db_one(
        'SELECT c.*, cc.name AS category_name, u.email, u.first_name, u.last_name
           FROM concerns c
           JOIN concern_categories cc ON cc.category_id = c.category_id
           JOIN users u ON u.user_id = c.resident_id
          WHERE c.concern_id = ?',
        [$concernId]
    );
    if (!$concern || $concern['is_archived']) {
        return [false, 'This concern no longer exists.'];
    }
    if ($actor['role_key'] === ROLE_RESIDENT && (int) $concern['resident_id'] !== (int) $actor['user_id']) {
        log_activity('access_denied', 'concern', $concernId, 'Resident tried to change another resident\'s concern');
        return [false, 'You can only change your own concerns.'];
    }
    if ($concern['status'] !== $expectedFrom) {
        return [false, "This concern was already updated to \"{$concern['status']}\". The page now shows its current status."];
    }
    if (!in_array($to, concern_allowed_next($concern, $actor), true)) {
        log_activity('invalid_action', 'concern', $concernId, "Blocked transition {$concern['status']} -> $to");
        $hint = in_array($concern['status'], ['Assigned', 'In Progress'], true) ? ' Only the assigned staff member can update it.' : '';
        return [false, "A concern that is \"{$concern['status']}\" cannot be changed to \"$to\" by your account.$hint"];
    }
    if (mb_strlen($note) > 1000) {
        return [false, 'The note must be 1000 characters or fewer.'];
    }
    if ($to === 'Rejected' && mb_strlen($note) < 5) {
        return [false, 'Enter why the concern is being rejected (at least 5 characters).'];
    }
    if ($to === 'Resolved' && mb_strlen($note) < 5) {
        return [false, 'Describe how the concern was resolved (at least 5 characters).'];
    }

    $assignee = $concern['assigned_to'] !== null ? (int) $concern['assigned_to'] : null;
    $assigneeName = '';
    if ($to === 'Assigned') {
        $assignee = (int) ($options['assignee'] ?? 0);
        $staff = db_one(
            'SELECT u.user_id, u.first_name, u.last_name FROM users u JOIN roles r ON r.role_id = u.role_id
              WHERE u.user_id = ? AND r.role_key = ? AND u.is_active = 1',
            [$assignee, ROLE_STAFF]
        );
        if (!$staff) {
            return [false, 'Choose an active staff member to assign this concern to.'];
        }
        $assigneeName = full_name($staff);
    }
    $priority = (int) $concern['priority'];
    if (isset($options['priority']) && $options['priority'] !== '') {
        $priority = (int) $options['priority'];
        if (!isset(PRIORITY_LABELS[$priority])) {
            return [false, 'Choose a valid priority.'];
        }
    }

    $ok = db_transaction(function () use ($concern, $concernId, $to, $note, $actor, $assignee, $assigneeName, $priority) {
        $changed = db_exec(
            'UPDATE concerns
                SET status = ?, assigned_to = ?, priority = ?,
                    resolution = IF(? = \'Resolved\', ?, resolution)
              WHERE concern_id = ? AND status = ?',
            [$to, $assignee, $priority, $to, $note, $concernId, $concern['status']]
        );
        if ($changed !== 1) {
            return false;
        }
        $historyNote = $note;
        if ($to === 'Assigned') {
            $historyNote = 'Assigned to ' . $assigneeName . ($note !== '' ? '. ' . $note : '');
        }
        db_query(
            'INSERT INTO concern_updates (concern_id, from_status, to_status, note, changed_by) VALUES (?, ?, ?, ?, ?)',
            [$concernId, $concern['status'], $to, $historyNote !== '' ? $historyNote : null, (int) $actor['user_id']]
        );
        log_activity('concern_status_changed', 'concern', $concernId,
            "{$concern['reference_no']}: {$concern['status']} -> $to" . ($historyNote !== '' ? " ($historyNote)" : ''));
        return true;
    });

    if (!$ok) {
        return [false, 'This concern was just updated by someone else. Refresh to see its current status.'];
    }
    if (in_array($to, ['Assigned', 'Resolved', 'Rejected'], true) && $actor['role_key'] !== ROLE_RESIDENT) {
        notify_concern_status($concern, $to, $note);
    }
    return [true, "Concern {$concern['reference_no']} is now \"$to\"."];
}

function notify_concern_status(array $concern, string $status, string $note): void
{
    $lines = [
        'Assigned' => 'A barangay staff member has been assigned to your report.',
        'Resolved' => 'The barangay has marked your report as resolved. If the problem is fixed, you can close the concern from your account.',
        'Rejected' => 'The barangay could not act on this report.',
    ];
    $body = '<p>Good day, ' . e($concern['first_name']) . '.</p>'
        . '<p>Your concern <strong>' . e($concern['reference_no']) . '</strong> (' . e($concern['title']) . ') is now <strong>' . e($status) . '</strong>.</p>'
        . '<p>' . e($lines[$status] ?? '') . '</p>'
        . ($note !== '' && $status !== 'Assigned' ? '<p>Note from the barangay: ' . e($note) . '</p>' : '')
        . '<p><a href="' . e(absolute_url('concern.php?id=' . (int) $concern['concern_id'])) . '">View your concern</a></p>';
    send_mail($concern['email'], full_name($concern), "Concern {$concern['reference_no']}: $status", $body);
}

// ---------------------------------------------------------------- Shared display helpers

function status_badge(string $status): string
{
    $tone = match ($status) {
        'Pending', 'Submitted'                    => 'waiting',
        'Under Review', 'Assigned'                => 'review',
        'Approved', 'Processing', 'In Progress'   => 'active',
        'Ready for Pickup', 'Resolved'            => 'ready',
        'Released', 'Closed'                      => 'done',
        'Rejected'                                => 'stopped',
        'Cancelled', 'Withdrawn'                  => 'muted',
        default                                   => 'muted',
    };
    return '<span class="status status-' . $tone . '">' . e($status) . '</span>';
}

function priority_badge(int $priority): string
{
    return '<span class="prio prio-' . $priority . '">' . e(PRIORITY_LABELS[$priority] ?? '?') . '</span>';
}

/**
 * Status tracker: the happy-path steps, with completed/current/upcoming state.
 * Terminal side exits (Rejected, Cancelled, Withdrawn) are shown after the last reached step.
 */
function render_tracker(array $steps, string $status, array $history): string
{
    $reached = array_column($history, 'to_status');
    $reached[] = $status;
    $currentIndex = array_search($status, $steps, true);
    $lastReached = -1;
    foreach ($steps as $i => $s) {
        if (in_array($s, $reached, true)) {
            $lastReached = $i;
        }
    }
    $sideExit = $currentIndex === false ? $status : null;
    $dates = [];
    foreach ($history as $h) {
        $dates[$h['to_status']] = $h['changed_at'];
    }

    $html = '<ol class="tracker" aria-label="Progress">';
    foreach ($steps as $i => $step) {
        if ($sideExit !== null && $i > $lastReached) {
            break;
        }
        $state = $i < ($currentIndex === false ? $lastReached + 1 : $currentIndex) ? 'done'
            : ($i === $currentIndex ? 'current' : 'next');
        $aria = $state === 'current' ? ' aria-current="step"' : '';
        $html .= '<li class="tracker-step is-' . $state . '"' . $aria . '>'
            . '<span class="tracker-num">' . ($i + 1) . '</span>'
            . '<span class="tracker-label">' . e($step) . '</span>'
            . (isset($dates[$step]) ? '<span class="tracker-date">' . e(format_date($dates[$step], 'M j')) . '</span>' : '')
            . '</li>';
    }
    if ($sideExit !== null) {
        $html .= '<li class="tracker-step is-exit" aria-current="step"><span class="tracker-num">×</span>'
            . '<span class="tracker-label">' . e($sideExit) . '</span>'
            . (isset($dates[$sideExit]) ? '<span class="tracker-date">' . e(format_date($dates[$sideExit], 'M j')) . '</span>' : '')
            . '</li>';
    }
    return $html . '</ol>';
}
