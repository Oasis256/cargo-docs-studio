<?php

namespace CargoDocsStudio\Http\Rest;

class SchemasController
{
    public function registerRoutes(): void
    {
        register_rest_route('cds/v1', '/schemas', [
            'methods' => 'GET',
            'callback' => [$this, 'index'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'ok' => true,
            'controller' => 'SchemasController',
            'message' => 'Skeleton route active.',
        ]);
    }
}
