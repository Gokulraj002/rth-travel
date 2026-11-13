<?php

declare(strict_types=1);

namespace App\Core;

/** Audit trail shown on the dashboard ("Anita updated package Kashmir Paradise"). */
final class Activity
{
    public static function log(string $action, string $entity, ?int $entityId = null, string $summary = ''): void
    {
        try {
            Database::insert('activity_log', [
                'admin_id' => Session::get('admin_id'),
                'action' => mb_substr($action, 0, 40),
                'entity' => mb_substr($entity, 0, 40),
                'entity_id' => $entityId,
                'summary' => mb_substr($summary, 0, 255),
            ]);
        } catch (\Throwable $e) {
            error_log('Activity log failed: ' . $e->getMessage());
        }
    }
}
