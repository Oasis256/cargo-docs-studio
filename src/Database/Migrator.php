<?php

namespace CargoDocsStudio\Database;

use CargoDocsStudio\Database\Schema\AuditEventsTable;
use CargoDocsStudio\Database\Schema\DocumentTypesTable;
use CargoDocsStudio\Database\Schema\DocumentsTable;
use CargoDocsStudio\Database\Schema\FieldGroupsTable;
use CargoDocsStudio\Database\Schema\FieldsTable;
use CargoDocsStudio\Database\Schema\SettingsTable;
use CargoDocsStudio\Database\Schema\ShipmentStopsTable;
use CargoDocsStudio\Database\Schema\ShipmentsTable;
use CargoDocsStudio\Database\Schema\TemplateRevisionsTable;
use CargoDocsStudio\Database\Schema\TemplatesTable;

class Migrator
{
    public function run(): void
    {
        $tables = [
            new DocumentTypesTable(),
            new FieldsTable(),
            new FieldGroupsTable(),
            new TemplatesTable(),
            new TemplateRevisionsTable(),
            new DocumentsTable(),
            new ShipmentsTable(),
            new ShipmentStopsTable(),
            new AuditEventsTable(),
            new SettingsTable(),
        ];

        foreach ($tables as $table) {
            $table->create();
        }
    }
}
