<?php

namespace CargoDocsStudio\Admin\Pages;

use CargoDocsStudio\Database\Repository\AuditRepository;
use CargoDocsStudio\Database\Repository\DocumentRepository;
use CargoDocsStudio\Database\Repository\ShipmentRepository;
use CargoDocsStudio\Database\Repository\TemplateRepository;

class DashboardPage
{
    public function render(): void
    {
        if (!current_user_can('cds_view_documents') && !current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'cargo-docs-studio'));
        }

        $documentsRepo = new DocumentRepository();
        $templatesRepo = new TemplateRepository();
        $shipmentsRepo = new ShipmentRepository();
        $auditRepo = new AuditRepository();

        $totals = $documentsRepo->listRecentPaged(1, 1, null, null);
        $invoiceTotals = $documentsRepo->listRecentPaged(1, 1, 'invoice', null);
        $receiptTotals = $documentsRepo->listRecentPaged(1, 1, 'receipt', null);
        $skrTotals = $documentsRepo->listRecentPaged(1, 1, 'skr', null);

        $templates = $templatesRepo->listTemplates();
        $templateStats = [
            'total' => count($templates),
            'invoice' => 0,
            'receipt' => 0,
            'skr' => 0,
            'published_latest' => 0,
        ];
        foreach ($templates as $template) {
            $docType = sanitize_key((string) ($template['doc_type_key'] ?? ''));
            if (isset($templateStats[$docType])) {
                $templateStats[$docType]++;
            }
            if ((int) ($template['latest_is_published'] ?? 0) === 1) {
                $templateStats['published_latest']++;
            }
        }

        $recentDocuments = $documentsRepo->listRecent(6);
        $recentShipments = $shipmentsRepo->listRecent(6);
        $recentAudit = $auditRepo->listRecent(6);

        $shipmentsCount = $this->countRows('cds_shipments');
        $stopsCount = $this->countRows('cds_shipment_stops');

        $linkDashboard = admin_url('admin.php?page=cargo-docs-studio');
        $linkDocuments = admin_url('admin.php?page=cargo-docs-studio-documents');
        $linkTracking = admin_url('admin.php?page=cargo-docs-studio-tracking');
        $linkTemplates = admin_url('admin.php?page=cargo-docs-studio-templates');
        $linkSettings = admin_url('admin.php?page=cargo-docs-studio-settings');
        $linkAudit = admin_url('admin.php?page=cargo-docs-studio-audit');

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('CargoDocs Studio', 'cargo-docs-studio') . '</h1>';
        echo '<p>' . esc_html__('Operational overview for documents, templates, tracking, and audit activity.', 'cargo-docs-studio') . '</p>';

        echo '<div class="cds-grid" style="grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;">';
        $this->renderKpiCard(__('Documents', 'cargo-docs-studio'), (string) ($totals['total'] ?? 0), '#2271b1');
        $this->renderKpiCard(__('Shipments', 'cargo-docs-studio'), (string) $shipmentsCount, '#008a20');
        $this->renderKpiCard(__('Tracking Stops', 'cargo-docs-studio'), (string) $stopsCount, '#b26200');
        $this->renderKpiCard(__('Templates', 'cargo-docs-studio'), (string) $templateStats['total'], '#7a00cc');
        echo '</div>';

        echo '<div class="cds-grid">';
        echo '<section class="cds-card">';
        echo '<h2>' . esc_html__('Quick Actions', 'cargo-docs-studio') . '</h2>';
        echo '<p><a class="button button-primary" href="' . esc_url($linkDocuments) . '">' . esc_html__('Generate Document', 'cargo-docs-studio') . '</a> ';
        echo '<a class="button" href="' . esc_url($linkTracking) . '">' . esc_html__('Open Tracking', 'cargo-docs-studio') . '</a> ';
        echo '<a class="button" href="' . esc_url($linkTemplates) . '">' . esc_html__('Template Studio', 'cargo-docs-studio') . '</a></p>';
        echo '<p><a class="button" href="' . esc_url($linkSettings) . '">' . esc_html__('Settings', 'cargo-docs-studio') . '</a> ';
        echo '<a class="button" href="' . esc_url($linkAudit) . '">' . esc_html__('Audit', 'cargo-docs-studio') . '</a> ';
        echo '<a class="button" href="' . esc_url($linkDashboard) . '">' . esc_html__('Refresh Dashboard', 'cargo-docs-studio') . '</a></p>';
        echo '</section>';

        echo '<section class="cds-card">';
        echo '<h2>' . esc_html__('Document Breakdown', 'cargo-docs-studio') . '</h2>';
        echo '<table class="widefat striped">';
        echo '<tbody>';
        echo '<tr><th>' . esc_html__('Invoices', 'cargo-docs-studio') . '</th><td>' . esc_html((string) ($invoiceTotals['total'] ?? 0)) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Receipts', 'cargo-docs-studio') . '</th><td>' . esc_html((string) ($receiptTotals['total'] ?? 0)) . '</td></tr>';
        echo '<tr><th>' . esc_html__('SKR', 'cargo-docs-studio') . '</th><td>' . esc_html((string) ($skrTotals['total'] ?? 0)) . '</td></tr>';
        echo '</tbody>';
        echo '</table>';
        echo '</section>';
        echo '</div>';

        echo '<div class="cds-grid">';
        echo '<section class="cds-card">';
        echo '<h2>' . esc_html__('Recent Documents', 'cargo-docs-studio') . '</h2>';
        if (empty($recentDocuments)) {
            echo '<p class="description">' . esc_html__('No documents generated yet.', 'cargo-docs-studio') . '</p>';
        } else {
            echo '<table class="widefat striped">';
            echo '<thead><tr><th>' . esc_html__('ID', 'cargo-docs-studio') . '</th><th>' . esc_html__('Type', 'cargo-docs-studio') . '</th><th>' . esc_html__('Tracking', 'cargo-docs-studio') . '</th><th>' . esc_html__('Created', 'cargo-docs-studio') . '</th></tr></thead><tbody>';
            foreach ($recentDocuments as $row) {
                echo '<tr>';
                echo '<td>' . esc_html((string) ($row['id'] ?? '')) . '</td>';
                echo '<td>' . esc_html(strtoupper((string) ($row['doc_type_key'] ?? ''))) . '</td>';
                echo '<td><code>' . esc_html((string) ($row['tracking_code'] ?? '')) . '</code></td>';
                echo '<td>' . esc_html((string) ($row['created_at'] ?? '')) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</section>';

        echo '<section class="cds-card">';
        echo '<h2>' . esc_html__('Tracking Snapshot', 'cargo-docs-studio') . '</h2>';
        if (empty($recentShipments)) {
            echo '<p class="description">' . esc_html__('No shipments available yet.', 'cargo-docs-studio') . '</p>';
        } else {
            echo '<table class="widefat striped">';
            echo '<thead><tr><th>' . esc_html__('Tracking', 'cargo-docs-studio') . '</th><th>' . esc_html__('Status', 'cargo-docs-studio') . '</th><th>' . esc_html__('Location', 'cargo-docs-studio') . '</th><th>' . esc_html__('Updated', 'cargo-docs-studio') . '</th></tr></thead><tbody>';
            foreach ($recentShipments as $row) {
                echo '<tr>';
                echo '<td><code>' . esc_html((string) ($row['tracking_code'] ?? '')) . '</code></td>';
                echo '<td>' . esc_html((string) ($row['current_status'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($row['current_location_text'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($row['last_update_at'] ?? '')) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</section>';
        echo '</div>';

        echo '<section class="cds-card">';
        echo '<h2>' . esc_html__('Recent Audit Events', 'cargo-docs-studio') . '</h2>';
        if (empty($recentAudit)) {
            echo '<p class="description">' . esc_html__('No audit events captured yet.', 'cargo-docs-studio') . '</p>';
        } else {
            echo '<table class="widefat striped">';
            echo '<thead><tr><th>' . esc_html__('Event', 'cargo-docs-studio') . '</th><th>' . esc_html__('Actor', 'cargo-docs-studio') . '</th><th>' . esc_html__('Reference', 'cargo-docs-studio') . '</th><th>' . esc_html__('Created', 'cargo-docs-studio') . '</th></tr></thead><tbody>';
            foreach ($recentAudit as $event) {
                $reference = trim((string) ($event['ref_type'] ?? '')) . '#' . (int) ($event['ref_id'] ?? 0);
                echo '<tr>';
                echo '<td><code>' . esc_html((string) ($event['event_type'] ?? '')) . '</code></td>';
                echo '<td>' . esc_html((string) ($event['actor_id'] ?? '')) . '</td>';
                echo '<td>' . esc_html($reference) . '</td>';
                echo '<td>' . esc_html((string) ($event['created_at'] ?? '')) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</section>';

        echo '</div>';
    }

    private function renderKpiCard(string $label, string $value, string $accent): void
    {
        echo '<section class="cds-card" style="margin:0;">';
        echo '<div style="font-size:12px;color:#646970;margin-bottom:6px;">' . esc_html($label) . '</div>';
        echo '<div style="font-size:30px;font-weight:700;line-height:1;color:' . esc_attr($accent) . ';">' . esc_html($value) . '</div>';
        echo '</section>';
    }

    private function countRows(string $tableSuffix): int
    {
        global $wpdb;

        $table = $wpdb->prefix . ltrim($tableSuffix, '_');
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if (!$exists) {
            return 0;
        }

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        return (int) ($count ?? 0);
    }
}
