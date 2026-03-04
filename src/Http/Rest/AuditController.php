<?php

namespace CargoDocsStudio\Http\Rest;

use CargoDocsStudio\Database\Repository\AuditRepository;

class AuditController
{
    public function registerRoutes(): void
    {
        register_rest_route('cds/v1', '/audit', [
            'methods' => 'GET',
            'callback' => [$this, 'index'],
            'permission_callback' => [$this, 'canViewAudit'],
        ]);
    }

    public function canViewAudit(): bool
    {
        return current_user_can('cds_view_audit') || current_user_can('manage_options');
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $limit = (int) ($request->get_param('limit') ?: 50);
        $eventType = sanitize_key((string) ($request->get_param('event_type') ?: ''));

        $repo = new AuditRepository();
        return new \WP_REST_Response([
            'ok' => true,
            'events' => $repo->listRecent($limit, $eventType ?: null),
        ]);
    }
}
