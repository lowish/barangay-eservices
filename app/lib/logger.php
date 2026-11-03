<?php
/** Activity log: records the acting user, the action, the related record and the time. */

function log_activity(string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null, ?int $userId = null): void
{
    try {
        $userId = $userId ?? (user_id() ?: null);
        db_query(
            'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $action, $entityType, $entityId, $details !== null ? mb_substr($details, 0, 500) : null, client_ip()]
        );
    } catch (Throwable $e) {
        // Logging must never break the user's action.
        error_log('[barangay-eservices] activity log failed: ' . $e->getMessage());
    }
}
