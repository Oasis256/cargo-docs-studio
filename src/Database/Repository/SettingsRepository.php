<?php

namespace CargoDocsStudio\Database\Repository;

class SettingsRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'cds_settings';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT value_json FROM {$this->table} WHERE `key` = %s", sanitize_key($key)),
            ARRAY_A
        );

        if (!$row) {
            return $default;
        }

        $value = json_decode((string) $row['value_json'], true);

        return $value === null ? $default : $value;
    }

    public function set(string $key, mixed $value, bool $autoload = false): bool
    {
        global $wpdb;

        $payload = wp_json_encode($value);
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$this->table} WHERE `key` = %s", sanitize_key($key))
        );

        if ($existing) {
            $updated = $wpdb->update(
                $this->table,
                [
                    'value_json' => $payload,
                    'autoload' => $autoload ? 1 : 0,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => (int) $existing],
                ['%s', '%d', '%s'],
                ['%d']
            );

            return $updated !== false;
        }

        $inserted = $wpdb->insert(
            $this->table,
            [
                'key' => sanitize_key($key),
                'value_json' => $payload,
                'autoload' => $autoload ? 1 : 0,
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%s']
        );

        return $inserted !== false;
    }
}
