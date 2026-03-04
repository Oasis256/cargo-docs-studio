<?php

namespace CargoDocsStudio\Http\Rest;

use CargoDocsStudio\Database\Repository\AuditRepository;
use CargoDocsStudio\Domain\Tracking\PublicTrackingQuery;
use CargoDocsStudio\Domain\Tracking\StopUpdateService;

class TrackingController
{
    private const PUBLIC_TRACK_WINDOW_SECONDS = 60;
    private const PUBLIC_TRACK_MAX_REQUESTS = 30;

    public function registerRoutes(): void
    {
        register_rest_route('cds/v1', '/tracking/(?P<tracking_code>[A-Za-z0-9\-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getPublicTracking'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('cds/v1', '/tracking/(?P<tracking_code>[A-Za-z0-9\-]+)/stops', [
            'methods' => 'POST',
            'callback' => [$this, 'addStop'],
            'permission_callback' => [$this, 'canUpdateTracking'],
        ]);
    }

    public function canUpdateTracking(): bool
    {
        return current_user_can('cds_update_tracking') || current_user_can('manage_options');
    }

    public function getPublicTracking(\WP_REST_Request $request): \WP_REST_Response
    {
        $trackingCode = sanitize_text_field((string) $request->get_param('tracking_code'));
        $token = sanitize_text_field((string) $request->get_param('t'));

        $throttle = $this->enforcePublicTrackingRateLimit($trackingCode);
        if ($throttle instanceof \WP_REST_Response) {
            return $throttle;
        }

        if ($trackingCode === '' || $token === '') {
            return $this->errorResponse(400, 'validation_error', 'tracking_code and token are required');
        }

        $query = new PublicTrackingQuery();
        $result = $query->getPublicTracking($trackingCode, $token);

        if ($result instanceof \WP_Error) {
            return $this->errorResponse(403, 'tracking_forbidden', $result->get_error_message());
        }

        return new \WP_REST_Response([
            'ok' => true,
            'tracking' => $result,
        ]);
    }

    public function addStop(\WP_REST_Request $request): \WP_REST_Response
    {
        $trackingCode = sanitize_text_field((string) $request->get_param('tracking_code'));
        $payload = (array) $request->get_json_params();

        $stopName = sanitize_text_field($payload['stop_name'] ?? '');
        $status = sanitize_text_field($payload['status'] ?? 'In Transit');
        $notes = sanitize_textarea_field($payload['notes'] ?? '');
        $lat = isset($payload['lat']) && $payload['lat'] !== '' ? (float) $payload['lat'] : null;
        $lng = isset($payload['lng']) && $payload['lng'] !== '' ? (float) $payload['lng'] : null;
        $geoSource = sanitize_key((string) ($payload['geo_source'] ?? 'manual'));
        $geoAccuracy = isset($payload['geo_accuracy']) && $payload['geo_accuracy'] !== '' ? (float) $payload['geo_accuracy'] : null;
        $geoCapturedAt = sanitize_text_field((string) ($payload['geo_captured_at'] ?? ''));

        if ($geoSource !== 'gps') {
            $geoSource = 'manual';
        }

        if ($trackingCode === '' || $stopName === '') {
            return $this->errorResponse(400, 'validation_error', 'tracking_code and stop_name are required');
        }

        if (($lat === null) xor ($lng === null)) {
            return $this->errorResponse(400, 'validation_error', 'lat and lng must both be provided');
        }

        if (($lat !== null && ($lat < -90 || $lat > 90)) || ($lng !== null && ($lng < -180 || $lng > 180))) {
            return $this->errorResponse(400, 'validation_error', 'Latitude/Longitude values are out of range');
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

        $service = new StopUpdateService();
        $result = $service->addStopByTrackingCode($trackingCode, $stopName, $status, $notes, $lat, $lng);

        if ($result instanceof \WP_Error) {
            $this->logAudit('tracking_stop_update_failed', 'shipment', 0, [
                'tracking_code' => $trackingCode,
                'stop_name' => $stopName,
                'status' => $status,
                'error' => $result->get_error_message(),
            ]);
            return $this->errorResponse(500, 'stop_update_failed', $result->get_error_message());
        }

        $this->logAudit('tracking_stop_updated', 'shipment', (int) ($result['shipment_id'] ?? 0), [
            'tracking_code' => $trackingCode,
            'stop_name' => $stopName,
            'status' => $status,
            'lat' => $lat,
            'lng' => $lng,
            'geo_source' => $geoSource,
            'geo_accuracy' => $geoAccuracy,
            'geo_captured_at' => $geoCapturedAt,
        ]);

        return new \WP_REST_Response([
            'ok' => true,
            'result' => $result,
        ], 201);
    }

    private function getClientIp(): string
    {
        $ip = '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim((string) ($parts[0] ?? ''));
        }
        if ($ip === '' && !empty($_SERVER['REMOTE_ADDR'])) {
            $ip = trim((string) $_SERVER['REMOTE_ADDR']);
        }
        if ($ip === '') {
            $ip = 'unknown';
        }

        return sanitize_text_field($ip);
    }

    private function enforcePublicTrackingRateLimit(string $trackingCode): ?\WP_REST_Response
    {
        $ip = $this->getClientIp();
        $keyRaw = 'cds_track_rl_' . $ip . '_' . sanitize_key($trackingCode);
        $key = substr(md5($keyRaw), 0, 32);
        $transientKey = 'cds_rl_' . $key;
        $bucket = get_transient($transientKey);
        $now = time();

        if (!is_array($bucket) || !isset($bucket['count'], $bucket['start'])) {
            $bucket = ['count' => 0, 'start' => $now];
        }

        $start = (int) $bucket['start'];
        $count = (int) $bucket['count'];
        $elapsed = $now - $start;
        if ($elapsed >= self::PUBLIC_TRACK_WINDOW_SECONDS) {
            $start = $now;
            $count = 0;
            $elapsed = 0;
        }

        $count++;
        $ttl = max(1, self::PUBLIC_TRACK_WINDOW_SECONDS - $elapsed);
        set_transient($transientKey, ['count' => $count, 'start' => $start], $ttl);

        if ($count > self::PUBLIC_TRACK_MAX_REQUESTS) {
            return new \WP_REST_Response([
                'ok' => false,
                'code' => 'rate_limited',
                'error' => 'Too many tracking requests. Please try again shortly.',
                'message' => 'Too many tracking requests. Please try again shortly.',
                'retry_after' => $ttl,
            ], 429);
        }

        return null;
    }

    private function errorResponse(int $status, string $code, string $message): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'ok' => false,
            'code' => $code,
            'error' => $message,
            'message' => $message,
        ], $status);
    }

    private function logAudit(string $eventType, string $refType, int $refId, array $meta = []): void
    {
        try {
            (new AuditRepository())->log($eventType, get_current_user_id(), $refType, $refId > 0 ? $refId : null, $meta);
        } catch (\Throwable $e) {
            // Audit logging must not break primary workflow.
        }
    }
}
