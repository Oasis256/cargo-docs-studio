<?php

namespace CargoDocsStudio\Admin\Pages;

use CargoDocsStudio\Database\Repository\ShipmentRepository;
use CargoDocsStudio\Database\Repository\StopRepository;
use CargoDocsStudio\Domain\Tracking\StopUpdateService;

class TrackingPage
{
    public function render(): void
    {
        if (!current_user_can('cds_view_tracking_admin') && !current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'cargo-docs-studio'));
        }

        $notice = null;
        $noticeType = 'updated';
        $trackingCode = isset($_GET['tracking_code']) ? sanitize_text_field((string) $_GET['tracking_code']) : '';

        if (isset($_POST['cds_tracking_update_submit'])) {
            check_admin_referer('cds_update_tracking_stop');
            if (!current_user_can('cds_update_tracking') && !current_user_can('manage_options')) {
                $notice = esc_html__('You do not have permission to update tracking stops.', 'cargo-docs-studio');
                $noticeType = 'error';
            } else {
                $trackingCode = sanitize_text_field((string) ($_POST['tracking_code'] ?? ''));
                $stopName = sanitize_text_field((string) ($_POST['stop_name'] ?? ''));
                $status = sanitize_text_field((string) ($_POST['status'] ?? 'In Transit'));
                $notes = sanitize_textarea_field((string) ($_POST['notes'] ?? ''));
                $latRaw = trim((string) ($_POST['lat'] ?? ''));
                $lngRaw = trim((string) ($_POST['lng'] ?? ''));
                $geoSource = sanitize_key((string) ($_POST['geo_source'] ?? 'manual'));
                $geoAccuracyRaw = trim((string) ($_POST['geo_accuracy'] ?? ''));
                $geoCapturedAt = sanitize_text_field((string) ($_POST['geo_captured_at'] ?? ''));
                $lat = $latRaw === '' ? null : (float) $latRaw;
                $lng = $lngRaw === '' ? null : (float) $lngRaw;
                $geoAccuracy = $geoAccuracyRaw === '' ? null : (float) $geoAccuracyRaw;

                if ($geoSource !== 'gps') {
                    $geoSource = 'manual';
                }

                if ($geoSource === 'gps') {
                    $metaParts = [];
                    if ($geoAccuracy !== null && $geoAccuracy >= 0) {
                        $metaParts[] = 'accuracy ±' . number_format($geoAccuracy, 1) . 'm';
                    }
                    if ($geoCapturedAt !== '') {
                        $metaParts[] = 'captured ' . $geoCapturedAt;
                    }
                    if (!empty($metaParts)) {
                        $notes = trim($notes . "\n" . '[GPS auto: ' . implode(', ', $metaParts) . ']');
                    }
                }

                if ($trackingCode === '' || $stopName === '') {
                    $notice = esc_html__('Tracking code and stop name are required.', 'cargo-docs-studio');
                    $noticeType = 'error';
                } elseif (($lat === null) xor ($lng === null)) {
                    $notice = esc_html__('Latitude and longitude must both be provided.', 'cargo-docs-studio');
                    $noticeType = 'error';
                } elseif (($lat !== null && ($lat < -90 || $lat > 90)) || ($lng !== null && ($lng < -180 || $lng > 180))) {
                    $notice = esc_html__('Latitude/Longitude values are out of range.', 'cargo-docs-studio');
                    $noticeType = 'error';
                } else {
                    $service = new StopUpdateService();
                    $result = $service->addStopByTrackingCode($trackingCode, $stopName, $status, $notes, $lat, $lng);
                    if ($result instanceof \WP_Error) {
                        $notice = $result->get_error_message();
                        $noticeType = 'error';
                    } else {
                        $notice = esc_html__('Stop update recorded successfully.', 'cargo-docs-studio');
                        $noticeType = 'updated';
                    }
                }
            }
        }

        $shipmentRepo = new ShipmentRepository();
        $stopRepo = new StopRepository();
        $search = isset($_GET['q']) ? sanitize_text_field((string) $_GET['q']) : '';
        $recentShipments = $shipmentRepo->listRecent(25, $search ?: null);
        $shipment = $trackingCode !== '' ? $shipmentRepo->getByTrackingCode($trackingCode) : null;
        $stops = $shipment ? $stopRepo->listByShipment((int) $shipment['id'], 150) : [];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Tracking Operations', 'cargo-docs-studio') . '</h1>';
        echo '<p>' . esc_html__('Find shipments, post stop updates, and review location history.', 'cargo-docs-studio') . '</p>';

        if ($notice !== null) {
            $cls = $noticeType === 'error' ? 'notice notice-error' : 'notice notice-success';
            echo '<div class="' . esc_attr($cls) . ' is-dismissible"><p>' . esc_html($notice) . '</p></div>';
        }

        echo '<div class="cds-grid">';
        echo '<section class="cds-card">';
        echo '<h2>' . esc_html__('Find Shipment', 'cargo-docs-studio') . '</h2>';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="cargo-docs-studio-tracking" />';
        echo '<label for="cds-tracking-search">' . esc_html__('Search by Tracking Code', 'cargo-docs-studio') . '</label>';
        echo '<div class="cds-doc-search-row">';
        echo '<input id="cds-tracking-search" type="text" name="q" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('e.g. ESL20260205176', 'cargo-docs-studio') . '" />';
        echo '<button class="button" type="submit">' . esc_html__('Search', 'cargo-docs-studio') . '</button>';
        echo '</div>';
        echo '</form>';

        if (!empty($recentShipments)) {
            echo '<table class="widefat striped" style="margin-top:12px;">';
            echo '<thead><tr><th>' . esc_html__('Tracking Code', 'cargo-docs-studio') . '</th><th>' . esc_html__('Status', 'cargo-docs-studio') . '</th><th>' . esc_html__('Location', 'cargo-docs-studio') . '</th><th>' . esc_html__('Updated', 'cargo-docs-studio') . '</th><th>' . esc_html__('Action', 'cargo-docs-studio') . '</th></tr></thead><tbody>';
            foreach ($recentShipments as $row) {
                $code = (string) ($row['tracking_code'] ?? '');
                $link = add_query_arg([
                    'page' => 'cargo-docs-studio-tracking',
                    'tracking_code' => $code,
                ], admin_url('admin.php'));
                echo '<tr>';
                echo '<td><code>' . esc_html($code) . '</code></td>';
                echo '<td>' . esc_html((string) ($row['current_status'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($row['current_location_text'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($row['last_update_at'] ?? '')) . '</td>';
                echo '<td><a class="button button-small" href="' . esc_url($link) . '">' . esc_html__('Open', 'cargo-docs-studio') . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p class="description">' . esc_html__('No shipments found for current search.', 'cargo-docs-studio') . '</p>';
        }
        echo '</section>';

        echo '<section class="cds-card">';
        echo '<h2>' . esc_html__('Post Stop Update', 'cargo-docs-studio') . '</h2>';
        if (!current_user_can('cds_update_tracking') && !current_user_can('manage_options')) {
            echo '<p>' . esc_html__('Your account can view tracking but cannot post stop updates.', 'cargo-docs-studio') . '</p>';
        } else {
            echo '<form method="post">';
            wp_nonce_field('cds_update_tracking_stop');
            echo '<label for="cds-stop-tracking-code">' . esc_html__('Tracking Code', 'cargo-docs-studio') . '</label>';
            echo '<input id="cds-stop-tracking-code" type="text" name="tracking_code" value="' . esc_attr($trackingCode) . '" required />';
            echo '<label for="cds-stop-name">' . esc_html__('Stop Name', 'cargo-docs-studio') . '</label>';
            echo '<input id="cds-stop-name" type="text" name="stop_name" required />';
            echo '<label for="cds-stop-status">' . esc_html__('Status', 'cargo-docs-studio') . '</label>';
            echo '<input id="cds-stop-status" type="text" name="status" value="In Transit" />';
            echo '<label for="cds-stop-notes">' . esc_html__('Notes', 'cargo-docs-studio') . '</label>';
            echo '<textarea id="cds-stop-notes" name="notes" rows="4"></textarea>';
            echo '<div class="cds-grid" style="grid-template-columns:1fr 1fr;gap:8px;">';
            echo '<div><label for="cds-stop-lat">' . esc_html__('Latitude', 'cargo-docs-studio') . '</label><input id="cds-stop-lat" type="text" name="lat" placeholder="25.2048" /></div>';
            echo '<div><label for="cds-stop-lng">' . esc_html__('Longitude', 'cargo-docs-studio') . '</label><input id="cds-stop-lng" type="text" name="lng" placeholder="55.2708" /></div>';
            echo '</div>';
            echo '<div class="cds-track-geo-tools">';
            echo '<button id="cds-track-use-gps" type="button" class="button">' . esc_html__('Use Current GPS', 'cargo-docs-studio') . '</button>';
            echo '<span id="cds-track-geo-status" class="description">' . esc_html__('No GPS fix captured yet.', 'cargo-docs-studio') . '</span>';
            echo '<input type="hidden" id="cds-track-geo-source" name="geo_source" value="manual" />';
            echo '<input type="hidden" id="cds-track-geo-accuracy" name="geo_accuracy" value="" />';
            echo '<input type="hidden" id="cds-track-geo-captured-at" name="geo_captured_at" value="" />';
            echo '</div>';
            echo '<p><button class="button button-primary" type="submit" name="cds_tracking_update_submit" value="1">' . esc_html__('Save Stop Update', 'cargo-docs-studio') . '</button></p>';
            echo '</form>';
        }
        echo '</section>';
        echo '</div>';

        echo '<section class="cds-card">';
        echo '<h2>' . esc_html__('Shipment Timeline', 'cargo-docs-studio') . '</h2>';
        if (!$shipment) {
            echo '<p class="description">' . esc_html__('Open a tracking code above to view current shipment timeline.', 'cargo-docs-studio') . '</p>';
        } else {
            echo '<p><strong>' . esc_html__('Tracking Code:', 'cargo-docs-studio') . '</strong> <code>' . esc_html((string) $shipment['tracking_code']) . '</code></p>';
            echo '<p><strong>' . esc_html__('Current Status:', 'cargo-docs-studio') . '</strong> ' . esc_html((string) ($shipment['current_status'] ?? '')) . ' | <strong>' . esc_html__('Current Location:', 'cargo-docs-studio') . '</strong> ' . esc_html((string) ($shipment['current_location_text'] ?? '')) . '</p>';

            if (empty($stops)) {
                echo '<p class="description">' . esc_html__('No stops recorded yet for this shipment.', 'cargo-docs-studio') . '</p>';
            } else {
                echo '<table class="widefat striped">';
                echo '<thead><tr><th>' . esc_html__('Stop', 'cargo-docs-studio') . '</th><th>' . esc_html__('Status', 'cargo-docs-studio') . '</th><th>' . esc_html__('Coordinates', 'cargo-docs-studio') . '</th><th>' . esc_html__('Notes', 'cargo-docs-studio') . '</th><th>' . esc_html__('Updated', 'cargo-docs-studio') . '</th></tr></thead><tbody>';
                foreach ($stops as $stop) {
                    $coords = '';
                    if ($stop['lat'] !== null && $stop['lng'] !== null && $stop['lat'] !== '' && $stop['lng'] !== '') {
                        $coords = (string) $stop['lat'] . ', ' . (string) $stop['lng'];
                    }
                    echo '<tr>';
                    echo '<td>' . esc_html((string) ($stop['stop_name'] ?? '')) . '</td>';
                    echo '<td>' . esc_html((string) ($stop['status'] ?? '')) . '</td>';
                    echo '<td>' . esc_html($coords) . '</td>';
                    echo '<td>' . esc_html((string) ($stop['notes'] ?? '')) . '</td>';
                    echo '<td>' . esc_html((string) ($stop['created_at'] ?? '')) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
        }
        echo '</div>';
    }
}
