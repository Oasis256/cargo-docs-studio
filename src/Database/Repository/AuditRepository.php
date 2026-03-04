<?php

namespace CargoDocsStudio\Database\Repository;

class AuditRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'cds_audit_events';
    }

    public function log(string $eventType, ?int $actorId = null, ?string $refType = null, ?int $refId = null, array $meta = []): bool
    {
        global $wpdb;

        $eventType = sanitize_key($eventType);
        if ($eventType === '') {
            return false;
        }

        $inserted = $wpdb->insert(
            $this->table,
            [
                'event_type' => $eventType,
                'actor_id' => $actorId ?: get_current_user_id(),
                'ref_type' => $refType ? sanitize_key($refType) : null,
                'ref_id' => $refId ?: null,
                'meta_json' => wp_json_encode($meta),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%d', '%s', '%d', '%s', '%s']
        );

        return $inserted !== false;
    }

    public function listRecent(int $limit = 50, ?string $eventType = null): array
    {
        global $wpdb;

        $limit = max(1, min($limit, 200));
        $where = '';
        $params = [];
        if ($eventType !== null && $eventType !== '') {
            $where = 'WHERE event_type = %s';
            $params[] = sanitize_key($eventType);
        }

        $sql = "SELECT id, event_type, actor_id, ref_type, ref_id, meta_json, created_at
                FROM {$this->table}
                {$where}
                ORDER BY id DESC
                LIMIT %d";
        $params[] = $limit;

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) ?: [];
        foreach ($rows as &$row) {
            $row['meta_json'] = !empty($row['meta_json']) ? json_decode((string) $row['meta_json'], true) : [];
        }
        unset($row);

        return $rows;
    }
}
