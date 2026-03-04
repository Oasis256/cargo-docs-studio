<?php

namespace CargoDocsStudio\Admin\Pages;

use CargoDocsStudio\Database\Repository\AuditRepository;

class AuditPage
{
    public function render(): void
    {
        if (!current_user_can('cds_view_audit') && !current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'cargo-docs-studio'));
        }

        $repo = new AuditRepository();
        $events = $repo->listRecent(100);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Audit Events', 'cargo-docs-studio') . '</h1>';
        echo '<p>' . esc_html__('Recent security and workflow events from CargoDocs Studio.', 'cargo-docs-studio') . '</p>';

        if (empty($events)) {
            echo '<p>' . esc_html__('No audit events found yet.', 'cargo-docs-studio') . '</p>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('ID', 'cargo-docs-studio') . '</th>';
        echo '<th>' . esc_html__('Event Type', 'cargo-docs-studio') . '</th>';
        echo '<th>' . esc_html__('Actor', 'cargo-docs-studio') . '</th>';
        echo '<th>' . esc_html__('Reference', 'cargo-docs-studio') . '</th>';
        echo '<th>' . esc_html__('Meta', 'cargo-docs-studio') . '</th>';
        echo '<th>' . esc_html__('Created', 'cargo-docs-studio') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($events as $event) {
            $ref = trim((string) ($event['ref_type'] ?? '')) . '#' . (int) ($event['ref_id'] ?? 0);
            $meta = wp_json_encode($event['meta_json'] ?? [], JSON_UNESCAPED_SLASHES);
            if (!is_string($meta)) {
                $meta = '{}';
            }
            echo '<tr>';
            echo '<td>' . esc_html((string) ($event['id'] ?? '')) . '</td>';
            echo '<td><code>' . esc_html((string) ($event['event_type'] ?? '')) . '</code></td>';
            echo '<td>' . esc_html((string) ($event['actor_id'] ?? '')) . '</td>';
            echo '<td>' . esc_html($ref) . '</td>';
            echo '<td><code>' . esc_html($meta) . '</code></td>';
            echo '<td>' . esc_html((string) ($event['created_at'] ?? '')) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }
}
