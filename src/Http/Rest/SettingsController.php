<?php

namespace CargoDocsStudio\Http\Rest;

class SettingsController
{
    public function registerRoutes(): void
    {
        register_rest_route('cds/v1', '/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'index'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'ok' => true,
            'controller' => 'SettingsController',
            'message' => 'Skeleton route active.',
        ]);
    }
}
