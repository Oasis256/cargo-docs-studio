<?php

namespace CargoDocsStudio\Core;

class Assets
{
    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdmin']);
    }

    public function enqueueAdmin(string $hook): void
    {
        if (strpos($hook, 'cargo-docs-studio') === false) {
            return;
        }

        $page = sanitize_key((string) ($_GET['page'] ?? ''));
        wp_enqueue_style('cds-admin', CDS_PLUGIN_URL . 'assets/admin/admin.css', [], $this->assetVersion('assets/admin/admin.css'));

        $localize = [
            'rest_base' => esc_url_raw(rest_url('cds/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'page' => $page,
        ];

        wp_enqueue_script('cds-admin-api', CDS_PLUGIN_URL . 'assets/admin/api.js', [], $this->assetVersion('assets/admin/api.js'), true);
        wp_localize_script('cds-admin-api', 'CDS_ADMIN', $localize);

        if ($page === 'cargo-docs-studio-templates') {
            wp_enqueue_script('cds-admin', CDS_PLUGIN_URL . 'assets/admin/admin.js', ['jquery', 'cds-admin-api'], $this->assetVersion('assets/admin/admin.js'), true);
        }

        if ($page === 'cargo-docs-studio-documents') {
            wp_enqueue_script('cds-admin-documents-state', CDS_PLUGIN_URL . 'assets/admin/documents/state.js', ['cds-admin-api'], $this->assetVersion('assets/admin/documents/state.js'), true);
            wp_enqueue_script('cds-admin-documents-defaults', CDS_PLUGIN_URL . 'assets/admin/documents/defaults.js', ['cds-admin-documents-state'], $this->assetVersion('assets/admin/documents/defaults.js'), true);
            wp_enqueue_script('cds-admin-documents-sections', CDS_PLUGIN_URL . 'assets/admin/documents/sections.js', ['cds-admin-documents-defaults'], $this->assetVersion('assets/admin/documents/sections.js'), true);
            wp_enqueue_script('cds-admin-documents-utils', CDS_PLUGIN_URL . 'assets/admin/documents/utils.js', ['cds-admin-documents-sections'], $this->assetVersion('assets/admin/documents/utils.js'), true);
            wp_enqueue_script('cds-admin-documents-form-renderer', CDS_PLUGIN_URL . 'assets/admin/documents/form-renderer.js', ['cds-admin-documents-utils'], $this->assetVersion('assets/admin/documents/form-renderer.js'), true);
            wp_enqueue_script('cds-admin-documents-payload-sync', CDS_PLUGIN_URL . 'assets/admin/documents/payload-sync.js', ['cds-admin-documents-form-renderer'], $this->assetVersion('assets/admin/documents/payload-sync.js'), true);
            wp_enqueue_script('cds-admin-documents-validation', CDS_PLUGIN_URL . 'assets/admin/documents/validation.js', ['cds-admin-documents-payload-sync'], $this->assetVersion('assets/admin/documents/validation.js'), true);
            wp_enqueue_script('cds-admin-documents-list', CDS_PLUGIN_URL . 'assets/admin/documents/documents-list.js', ['cds-admin-documents-validation'], $this->assetVersion('assets/admin/documents/documents-list.js'), true);
            wp_enqueue_script('cds-admin-documents-generation', CDS_PLUGIN_URL . 'assets/admin/documents/generation.js', ['cds-admin-documents-list'], $this->assetVersion('assets/admin/documents/generation.js'), true);
            wp_enqueue_script('cds-admin-documents-events-form', CDS_PLUGIN_URL . 'assets/admin/documents/events-form.js', ['cds-admin-documents-generation'], $this->assetVersion('assets/admin/documents/events-form.js'), true);
            wp_enqueue_script('cds-admin-documents-events-list', CDS_PLUGIN_URL . 'assets/admin/documents/events-list.js', ['cds-admin-documents-events-form'], $this->assetVersion('assets/admin/documents/events-list.js'), true);
            wp_enqueue_script('cds-admin-documents-events-result', CDS_PLUGIN_URL . 'assets/admin/documents/events-result.js', ['cds-admin-documents-events-list'], $this->assetVersion('assets/admin/documents/events-result.js'), true);
            wp_enqueue_script('cds-admin-documents-events', CDS_PLUGIN_URL . 'assets/admin/documents/events.js', ['cds-admin-documents-events-result'], $this->assetVersion('assets/admin/documents/events.js'), true);
            wp_enqueue_script('cds-admin-documents-index', CDS_PLUGIN_URL . 'assets/admin/documents/index.js', ['jquery', 'cds-admin-documents-events'], $this->assetVersion('assets/admin/documents/index.js'), true);
            wp_enqueue_script('cds-admin-documents', CDS_PLUGIN_URL . 'assets/admin/documents.js', ['cds-admin-documents-index'], $this->assetVersion('assets/admin/documents.js'), true);
        }

        if ($page === 'cargo-docs-studio-tracking') {
            wp_enqueue_script('cds-admin-tracking', CDS_PLUGIN_URL . 'assets/admin/tracking.js', ['cds-admin-api'], $this->assetVersion('assets/admin/tracking.js'), true);
        }
    }

    private function assetVersion(string $relativePath): string
    {
        $absolute = CDS_PLUGIN_DIR . ltrim($relativePath, '/\\');
        if (file_exists($absolute)) {
            return (string) filemtime($absolute);
        }

        return (string) CDS_VERSION;
    }
}
