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
            wp_enqueue_script('cds-admin-documents', CDS_PLUGIN_URL . 'assets/admin/documents.js', ['jquery', 'cds-admin-api'], $this->assetVersion('assets/admin/documents.js'), true);
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
