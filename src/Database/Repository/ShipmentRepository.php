<?php

namespace CargoDocsStudio\Database\Repository;

class ShipmentRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'cds_shipments';
    }

    public function create(string $trackingCode, string $tokenHash, int $documentId, string $status = 'Processing', string $location = 'Origin', ?float $lat = null, ?float $lng = null): int|\WP_Error
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table,
            [
                'tracking_code' => sanitize_text_field($trackingCode),
                'token_hash' => $tokenHash,
                'document_id' => $documentId,
                'current_status' => sanitize_text_field($status),
                'current_location_text' => sanitize_text_field($location),
                'current_lat' => $lat,
                'current_lng' => $lng,
                'last_update_at' => current_time('mysql'),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%s', '%s', '%f', '%f', '%s', '%s']
        );

        if ($result === false) {
            return new \WP_Error('cds_shipment_create_failed', $wpdb->last_error ?: 'Failed to create shipment');
        }

        return (int) $wpdb->insert_id;
    }

    public function getByTrackingCode(string $trackingCode): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE tracking_code = %s", sanitize_text_field($trackingCode)),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function updateLatest(int $shipmentId, string $status, string $location, ?float $lat = null, ?float $lng = null): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->table,
            [
                'current_status' => sanitize_text_field($status),
                'current_location_text' => sanitize_text_field($location),
                'current_lat' => $lat,
                'current_lng' => $lng,
                'last_update_at' => current_time('mysql'),
            ],
            ['id' => $shipmentId],
            ['%s', '%s', '%f', '%f', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function listRecent(int $limit = 20, ?string $search = null): array
    {
        global $wpdb;

        $limit = max(1, min($limit, 200));
        $searchTerm = trim((string) $search);
        $params = [];
        $where = '';
        if ($searchTerm !== '') {
            $where = 'WHERE tracking_code LIKE %s';
            $params[] = '%' . $wpdb->esc_like(sanitize_text_field($searchTerm)) . '%';
        }

        $sql = "SELECT id, tracking_code, document_id, current_status, current_location_text, current_lat, current_lng, last_update_at, created_at
                FROM {$this->table}
                {$where}
                ORDER BY id DESC
                LIMIT %d";
        $params[] = $limit;

        return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) ?: [];
    }
}
