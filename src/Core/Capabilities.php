<?php

namespace CargoDocsStudio\Core;

class Capabilities
{
    public const CAPS = [
        'cds_manage_settings',
        'cds_manage_templates',
        'cds_publish_templates',
        'cds_manage_fields',
        'cds_generate_documents',
        'cds_view_documents',
        'cds_delete_documents',
        'cds_update_tracking',
        'cds_view_tracking_admin',
        'cds_view_audit',
    ];

    public function register(): void
    {
        $role = get_role('administrator');
        if (!$role) {
            return;
        }

        foreach (self::CAPS as $cap) {
            $role->add_cap($cap);
        }
    }
}
