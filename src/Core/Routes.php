<?php

namespace CargoDocsStudio\Core;

use CargoDocsStudio\Http\Rest\AuditController;
use CargoDocsStudio\Http\Rest\DocumentsController;
use CargoDocsStudio\Http\Rest\SchemasController;
use CargoDocsStudio\Http\Rest\SettingsController;
use CargoDocsStudio\Http\Rest\TemplatesController;
use CargoDocsStudio\Http\Rest\TrackingController;

class Routes
{
    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            (new DocumentsController())->registerRoutes();
            (new TrackingController())->registerRoutes();
            (new TemplatesController())->registerRoutes();
            (new SchemasController())->registerRoutes();
            (new SettingsController())->registerRoutes();
            (new AuditController())->registerRoutes();
        });
    }
}
