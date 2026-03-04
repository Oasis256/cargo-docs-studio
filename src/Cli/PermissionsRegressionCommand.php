<?php

namespace CargoDocsStudio\Cli;

use CargoDocsStudio\Http\Rest\AuditController;
use CargoDocsStudio\Http\Rest\DocumentsController;
use CargoDocsStudio\Http\Rest\TemplatesController;
use CargoDocsStudio\Http\Rest\TrackingController;

class PermissionsRegressionCommand
{
    public function run(array $args, array $assocArgs): void
    {
        $adminId = $this->resolveAdministratorUserId();
        if ($adminId <= 0) {
            \WP_CLI::error('No administrator user found for permission regression tests.');
        }

        $failures = [];
        $passes = [];

        $this->assertGuestDenied($passes, $failures);
        $this->assertAdminAllowed($adminId, $passes, $failures);

        foreach ($passes as $line) {
            \WP_CLI::log('[PASS] ' . $line);
        }
        foreach ($failures as $line) {
            \WP_CLI::warning('[FAIL] ' . $line);
        }

        if (!empty($failures)) {
            \WP_CLI::error('Permission regression failed with ' . count($failures) . ' issue(s).');
        }

        \WP_CLI::success('Permission regression passed.');
    }

    private function assertGuestDenied(array &$passes, array &$failures): void
    {
        $prev = get_current_user_id();
        wp_set_current_user(0);

        try {
            $docs = new DocumentsController();
            $templates = new TemplatesController();
            $tracking = new TrackingController();
            $audit = new AuditController();

            $this->assertFalse($docs->canGenerateDocuments(), 'Guest cannot generate documents', $passes, $failures);
            $this->assertFalse($docs->canViewDocuments(), 'Guest cannot view documents', $passes, $failures);
            $this->assertFalse($templates->canManageTemplates(), 'Guest cannot manage templates', $passes, $failures);
            $this->assertFalse($templates->canPublishTemplates(), 'Guest cannot publish templates', $passes, $failures);
            $this->assertFalse($tracking->canUpdateTracking(), 'Guest cannot update tracking', $passes, $failures);
            $this->assertFalse($audit->canViewAudit(), 'Guest cannot view audit', $passes, $failures);
        } finally {
            wp_set_current_user($prev);
        }
    }

    private function assertAdminAllowed(int $adminId, array &$passes, array &$failures): void
    {
        $prev = get_current_user_id();
        wp_set_current_user($adminId);

        try {
            $docs = new DocumentsController();
            $templates = new TemplatesController();
            $tracking = new TrackingController();
            $audit = new AuditController();

            $this->assertTrue($docs->canGenerateDocuments(), 'Admin can generate documents', $passes, $failures);
            $this->assertTrue($docs->canViewDocuments(), 'Admin can view documents', $passes, $failures);
            $this->assertTrue($templates->canManageTemplates(), 'Admin can manage templates', $passes, $failures);
            $this->assertTrue($templates->canPublishTemplates(), 'Admin can publish templates', $passes, $failures);
            $this->assertTrue($tracking->canUpdateTracking(), 'Admin can update tracking', $passes, $failures);
            $this->assertTrue($audit->canViewAudit(), 'Admin can view audit', $passes, $failures);
        } finally {
            wp_set_current_user($prev);
        }
    }

    private function assertTrue(bool $value, string $message, array &$passes, array &$failures): void
    {
        if ($value) {
            $passes[] = $message;
            return;
        }
        $failures[] = $message;
    }

    private function assertFalse(bool $value, string $message, array &$passes, array &$failures): void
    {
        $this->assertTrue(!$value, $message, $passes, $failures);
    }

    private function resolveAdministratorUserId(): int
    {
        $users = get_users([
            'role' => 'administrator',
            'number' => 1,
            'fields' => ['ID'],
        ]);

        if (empty($users) || !isset($users[0]->ID)) {
            return 0;
        }

        return (int) $users[0]->ID;
    }
}
