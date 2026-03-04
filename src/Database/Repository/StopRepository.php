<?php

namespace CargoDocsStudio\Database\Repository;

class StopRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'cds_shipment_stops';
    }

    public function create(int $shipmentId, string $stopName, string $status, string $notes = '', ?float $lat = null, ?float $lng = null, ?int $updatedBy = null): int|\WP_Error
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table,
            [
                'shipment_id' => $shipmentId,
                'stop_name' => sanitize_text_field($stopName),
                'status' => sanitize_text_field($status),
                'notes' => sanitize_textarea_field($notes),
                'lat' => $lat,
                'lng' => $lng,
                'updated_by' => $updatedBy ?: get_current_user_id(),
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%f', '%f', '%d', '%s']
        );

        if ($result === false) {
            return new \WP_Error('cds_stop_create_failed', $wpdb->last_error ?: 'Failed to create stop update');
        }

        return (int) $wpdb->insert_id;
    }

    public function listByShipment(int $shipmentId, int $limit = 100): array
    {
        global $wpdb;

        $limit = max(1, min($limit, 500));

        $query = "SELECT id, stop_name, status, notes, lat, lng, updated_by, created_at
                  FROM {$this->table}
                  WHERE shipment_id = %d
                  ORDER BY id DESC
                  LIMIT %d";

        return $wpdb->get_results($wpdb->prepare($query, $shipmentId, $limit), ARRAY_A) ?: [];
    }
}
