<?php
require __DIR__ . '/app/bootstrap.php';

// Handles status changes, archive and restore for document requests. POST + CSRF only.
$user = require_role(ROLE_RESIDENT, ROLE_STAFF, ROLE_ADMIN);
require_post();

$id = (int) post_param('id');
$op = post_param('op');

if ($op === 'archive' || $op === 'restore') {
    $r = db_one('SELECT request_id, reference_no, status, is_archived FROM document_requests WHERE request_id = ?', [$id]);
    if (!$r) {
        not_found();
    }
    if ($op === 'archive') {
        // Staff and admins may archive; only finished requests can be archived.
        if ($user['role_key'] === ROLE_RESIDENT) {
            log_activity('access_denied', 'document_request', $id, 'Resident tried to archive a request');
            flash('danger', 'Your account cannot archive requests.');
        } elseif (!in_array($r['status'], ['Released', 'Rejected', 'Cancelled'], true)) {
            flash('danger', 'Only released, rejected or cancelled requests can be archived.');
        } elseif (db_exec('UPDATE document_requests SET is_archived = 1 WHERE request_id = ? AND is_archived = 0', [$id]) === 1) {
            log_activity('request_archived', 'document_request', $id, $r['reference_no']);
            flash('success', "Request {$r['reference_no']} was archived.");
        } else {
            flash('warning', 'This request is already archived.');
        }
    } else {
        if ($user['role_key'] !== ROLE_ADMIN) {
            log_activity('access_denied', 'document_request', $id, 'Non-admin tried to restore a request');
            flash('danger', 'Only administrators can restore archived requests.');
        } elseif (db_exec('UPDATE document_requests SET is_archived = 0 WHERE request_id = ? AND is_archived = 1', [$id]) === 1) {
            log_activity('request_restored', 'document_request', $id, $r['reference_no']);
            flash('success', "Request {$r['reference_no']} was restored.");
        }
    }
    redirect('request.php?id=' . $id);
}

[$ok, $message] = change_request_status($id, post_param('expected'), post_param('to'), post_param('remarks'), $user);
flash($ok ? 'success' : 'danger', $message);
redirect('request.php?id=' . $id);
