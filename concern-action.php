<?php
require __DIR__ . '/app/bootstrap.php';

// Handles concern status changes, reassignment, archive and restore. POST + CSRF only.
$user = require_role(ROLE_RESIDENT, ROLE_STAFF, ROLE_ADMIN);
require_post();

$id = (int) post_param('id');
$op = post_param('op');

if ($op === 'reassign') {
    $c = db_one('SELECT concern_id, reference_no, status, assigned_to, is_archived FROM concerns WHERE concern_id = ?', [$id]);
    $assignee = (int) post_param('assignee');
    $staff = db_one(
        'SELECT u.user_id, u.first_name, u.last_name FROM users u JOIN roles r ON r.role_id = u.role_id
          WHERE u.user_id = ? AND r.role_key = ? AND u.is_active = 1',
        [$assignee, ROLE_STAFF]
    );
    if (!$c) {
        not_found();
    } elseif ($user['role_key'] !== ROLE_STAFF) {
        log_activity('access_denied', 'concern', $id, 'Non-staff tried to reassign a concern');
        flash('danger', 'Only barangay staff can reassign concerns.');
    } elseif (!in_array($c['status'], ['Assigned', 'In Progress'], true) || $c['is_archived']) {
        flash('danger', 'Only assigned or in-progress concerns can be reassigned.');
    } elseif (!$staff || $assignee === (int) $c['assigned_to']) {
        flash('danger', 'Choose a different active staff member.');
    } else {
        db_transaction(function () use ($c, $id, $assignee, $staff, $user) {
            db_exec('UPDATE concerns SET assigned_to = ? WHERE concern_id = ? AND status = ?', [$assignee, $id, $c['status']]);
            db_query(
                'INSERT INTO concern_updates (concern_id, from_status, to_status, note, changed_by) VALUES (?, ?, ?, ?, ?)',
                [$id, $c['status'], $c['status'], 'Reassigned to ' . full_name($staff), (int) $user['user_id']]
            );
        });
        log_activity('concern_reassigned', 'concern', $id, "{$c['reference_no']} reassigned to " . full_name($staff));
        flash('success', "Concern {$c['reference_no']} was reassigned to " . full_name($staff) . '.');
    }
    redirect('concern.php?id=' . $id);
}

if ($op === 'archive' || $op === 'restore') {
    $c = db_one('SELECT concern_id, reference_no, status, is_archived FROM concerns WHERE concern_id = ?', [$id]);
    if (!$c) {
        not_found();
    }
    if ($op === 'archive') {
        if ($user['role_key'] === ROLE_RESIDENT) {
            log_activity('access_denied', 'concern', $id, 'Resident tried to archive a concern');
            flash('danger', 'Your account cannot archive concerns.');
        } elseif (!in_array($c['status'], ['Closed', 'Rejected', 'Withdrawn'], true)) {
            flash('danger', 'Only closed, rejected or withdrawn concerns can be archived.');
        } elseif (db_exec('UPDATE concerns SET is_archived = 1 WHERE concern_id = ? AND is_archived = 0', [$id]) === 1) {
            log_activity('concern_archived', 'concern', $id, $c['reference_no']);
            flash('success', "Concern {$c['reference_no']} was archived.");
        }
    } else {
        if ($user['role_key'] !== ROLE_ADMIN) {
            log_activity('access_denied', 'concern', $id, 'Non-admin tried to restore a concern');
            flash('danger', 'Only administrators can restore archived concerns.');
        } elseif (db_exec('UPDATE concerns SET is_archived = 0 WHERE concern_id = ? AND is_archived = 1', [$id]) === 1) {
            log_activity('concern_restored', 'concern', $id, $c['reference_no']);
            flash('success', "Concern {$c['reference_no']} was restored.");
        }
    }
    redirect('concern.php?id=' . $id);
}

[$ok, $message] = change_concern_status(
    $id, post_param('expected'), post_param('to'), post_param('note'), $user,
    ['assignee' => post_param('assignee'), 'priority' => post_param('priority')]
);
flash($ok ? 'success' : 'danger', $message);
redirect('concern.php?id=' . $id);
