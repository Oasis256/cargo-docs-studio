<?php

namespace CargoDocsStudio\Cli;

use CargoDocsStudio\Database\Repository\SettingsRepository;
use CargoDocsStudio\Domain\Render\MpdfAdapter;
use CargoDocsStudio\Domain\Render\RenderPipeline;
use CargoDocsStudio\Domain\Render\TcpdfAdapter;

class SmokeTestCommand
{
    public function run(array $args, array $assocArgs): void
    {
        $failures = [];
        $passes = [];

        $this->checkTables($passes, $failures);
        $this->checkRoutes($passes, $failures);
        $this->checkSettings($passes, $failures);
        $this->checkCapabilities($passes, $failures);
        $this->checkRestValidationSmoke($passes, $failures);
        $this->checkRestPermissionSmoke($passes, $failures);
        $this->checkRendererSmoke($passes, $failures);
        $this->checkRendererFallbackSmoke($passes, $failures);

        foreach ($passes as $line) {
            \WP_CLI::log('[PASS] ' . $line);
        }
        foreach ($failures as $line) {
            \WP_CLI::warning('[FAIL] ' . $line);
        }

        if (!empty($failures)) {
            \WP_CLI::error('CargoDocs Studio smoke test failed with ' . count($failures) . ' issue(s).');
        }

        \WP_CLI::success('CargoDocs Studio smoke test passed.');
    }

    private function checkTables(array &$passes, array &$failures): void
    {
        global $wpdb;

        $required = [
            'cds_documents',
            'cds_shipments',
            'cds_shipment_stops',
            'cds_templates',
            'cds_template_revisions',
            'cds_settings',
            'cds_audit_events',
        ];

        foreach ($required as $suffix) {
            $table = $wpdb->prefix . $suffix;
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists === $table) {
                $passes[] = 'Table exists: ' . $table;
            } else {
                $failures[] = 'Missing table: ' . $table;
            }
        }
    }

    private function checkRoutes(array &$passes, array &$failures): void
    {
        $routes = rest_get_server()->get_routes();
        $required = [
            '/cds/v1/documents',
            '/cds/v1/documents/invoice/generate',
            '/cds/v1/templates',
            '/cds/v1/templates/preview',
            '/cds/v1/tracking/(?P<tracking_code>[A-Za-z0-9\-]+)',
            '/cds/v1/audit',
        ];

        foreach ($required as $route) {
            if (array_key_exists($route, $routes)) {
                $passes[] = 'Route registered: ' . $route;
            } else {
                $failures[] = 'Missing route: ' . $route;
            }
        }
    }

    private function checkSettings(array &$passes, array &$failures): void
    {
        $repo = new SettingsRepository();
        $pdfEngine = (string) $repo->get('pdf_engine', 'tcpdf');
        if (in_array($pdfEngine, ['tcpdf', 'mpdf'], true)) {
            $passes[] = 'PDF engine setting valid: ' . $pdfEngine;
        } else {
            $failures[] = 'Invalid pdf_engine value: ' . $pdfEngine;
        }

        $policy = $repo->get('retention_policy', []);
        $pdfDays = isset($policy['pdf_days']) ? (int) $policy['pdf_days'] : 90;
        $maxDrafts = isset($policy['max_draft_revisions']) ? (int) $policy['max_draft_revisions'] : 20;
        if ($pdfDays > 0 && $maxDrafts > 0) {
            $passes[] = 'Retention policy appears valid (pdf_days=' . $pdfDays . ', max_draft_revisions=' . $maxDrafts . ')';
        } else {
            $failures[] = 'Retention policy invalid.';
        }
    }

    private function checkRestValidationSmoke(array &$passes, array &$failures): void
    {
        $adminUserId = $this->resolveAdministratorUserId();
        if ($adminUserId <= 0) {
            $failures[] = 'No administrator user found for REST smoke checks.';
            return;
        }

        $previousUser = get_current_user_id();
        wp_set_current_user($adminUserId);

        try {
            $req1 = new \WP_REST_Request('GET', '/cds/v1/templates/revisions');
            $res1 = rest_do_request($req1);
            $data1 = $res1->get_data();
            if ((int) $res1->get_status() === 400 && is_array($data1) && (($data1['code'] ?? '') === 'validation_error')) {
                $passes[] = 'REST smoke: template revisions validation works.';
            } else {
                $failures[] = 'REST smoke: unexpected templates/revisions validation response.';
            }

            $req2 = new \WP_REST_Request('POST', '/cds/v1/documents/invoice/generate');
            $req2->set_body(wp_json_encode([]));
            $req2->set_header('content-type', 'application/json');
            $res2 = rest_do_request($req2);
            $data2 = $res2->get_data();
            if ((int) $res2->get_status() === 400 && is_array($data2) && (($data2['code'] ?? '') === 'validation_error')) {
                $passes[] = 'REST smoke: document generation validation works.';
            } else {
                $failures[] = 'REST smoke: unexpected documents validation response.';
            }
        } catch (\Throwable $e) {
            $failures[] = 'REST smoke checks threw exception: ' . $e->getMessage();
        } finally {
            wp_set_current_user($previousUser);
        }
    }

    private function checkCapabilities(array &$passes, array &$failures): void
    {
        $role = get_role('administrator');
        if (!$role) {
            $failures[] = 'Administrator role not found.';
            return;
        }

        $required = [
            'cds_manage_settings',
            'cds_manage_templates',
            'cds_publish_templates',
            'cds_generate_documents',
            'cds_view_documents',
            'cds_update_tracking',
            'cds_view_tracking_admin',
            'cds_view_audit',
        ];

        foreach ($required as $cap) {
            if ($role->has_cap($cap)) {
                $passes[] = 'Capability present on administrator: ' . $cap;
            } else {
                $failures[] = 'Missing capability on administrator: ' . $cap;
            }
        }
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

    private function checkRendererSmoke(array &$passes, array &$failures): void
    {
        $html = '<html><body><h1>CDS Smoke</h1><p>Renderer smoke test.</p></body></html>';
        $tests = [
            'tcpdf' => new TcpdfAdapter(),
            'mpdf' => new MpdfAdapter(),
        ];

        foreach ($tests as $name => $adapter) {
            $filename = 'cds-smoke-' . $name . '-' . time() . '.pdf';
            try {
                $result = $adapter->render($html, $filename, ['page_format' => 'A4']);
                if (!empty($result['success']) && !empty($result['file_path'])) {
                    $passes[] = 'Renderer smoke: ' . $name . ' generated PDF.';
                    $path = (string) $result['file_path'];
                    if ($path !== '' && file_exists($path) && is_file($path)) {
                        @unlink($path);
                    }
                    continue;
                }

                $error = (string) ($result['error'] ?? '');
                if ($name === 'mpdf' && str_contains(strtolower($error), 'not available')) {
                    $passes[] = 'Renderer smoke: mPDF unavailable (skipped).';
                    continue;
                }
                $failures[] = 'Renderer smoke: ' . $name . ' failed: ' . ($error !== '' ? $error : 'Unknown error');
            } catch (\Throwable $e) {
                $failures[] = 'Renderer smoke: ' . $name . ' threw exception: ' . $e->getMessage();
            }
        }
    }

    private function checkRestPermissionSmoke(array &$passes, array &$failures): void
    {
        $previousUser = get_current_user_id();
        wp_set_current_user(0);

        try {
            $requests = [
                ['GET', '/cds/v1/templates'],
                ['POST', '/cds/v1/documents/invoice/generate'],
                ['GET', '/cds/v1/audit'],
            ];

            foreach ($requests as [$method, $route]) {
                $req = new \WP_REST_Request($method, $route);
                if ($method === 'POST') {
                    $req->set_body(wp_json_encode([]));
                    $req->set_header('content-type', 'application/json');
                }
                $res = rest_do_request($req);
                $status = (int) $res->get_status();
                if (in_array($status, [401, 403], true)) {
                    $passes[] = 'REST permission smoke: guest denied for ' . $route;
                } else {
                    $failures[] = 'REST permission smoke: expected guest deny for ' . $route . ', got status ' . $status;
                }
            }
        } catch (\Throwable $e) {
            $failures[] = 'REST permission smoke exception: ' . $e->getMessage();
        } finally {
            wp_set_current_user($previousUser);
        }
    }

    private function checkRendererFallbackSmoke(array &$passes, array &$failures): void
    {
        $repo = new SettingsRepository();
        $original = (string) $repo->get('pdf_engine', 'tcpdf');
        $setOk = $repo->set('pdf_engine', 'mpdf');
        if (!$setOk) {
            $failures[] = 'Renderer fallback smoke: failed to set pdf_engine=mpdf for test.';
            return;
        }

        try {
            $pipeline = new RenderPipeline();
            $result = $pipeline->generateInvoicePreviewPdf([
                'doc_type_key' => 'invoice',
                'schema' => [],
                'theme' => [],
                'layout' => ['page' => 'A4'],
            ], [
                'tracking_code' => 'SMOKE-FALLBACK',
                'client_name' => 'Smoke',
                'client_email' => 'smoke@example.com',
                'cargo_type' => 'Cargo',
            ]);

            if (empty($result['success'])) {
                $failures[] = 'Renderer fallback smoke: preview generation failed.';
                return;
            }

            $selected = (string) ($result['selected_engine'] ?? '');
            $used = (string) ($result['engine_used'] ?? '');
            if ($selected !== 'mpdf') {
                $failures[] = 'Renderer fallback smoke: expected selected_engine=mpdf, got ' . $selected;
            } else {
                $passes[] = 'Renderer fallback smoke: selected engine captured as mpdf.';
            }

            if ($used === 'mpdf' || $used === 'tcpdf') {
                $passes[] = 'Renderer fallback smoke: engine_used reported as ' . $used . '.';
            } else {
                $failures[] = 'Renderer fallback smoke: unexpected engine_used value "' . $used . '".';
            }

            $path = (string) ($result['file_path'] ?? '');
            if ($path !== '' && file_exists($path) && is_file($path)) {
                @unlink($path);
            }
        } catch (\Throwable $e) {
            $failures[] = 'Renderer fallback smoke exception: ' . $e->getMessage();
        } finally {
            $repo->set('pdf_engine', in_array($original, ['tcpdf', 'mpdf'], true) ? $original : 'tcpdf');
        }
    }
}
