<?php

namespace CargoDocsStudio\Cli;

class PreflightCommand
{
    public function run(array $args, array $assocArgs): void
    {
        $pluginDir = rtrim(CDS_PLUGIN_DIR, '/\\');
        $missing = [];
        $passes = [];

        $requiredDocs = [
            'README.md',
            'CHANGELOG.md',
            'MIGRATIONS.md',
            'RUNBOOK.md',
            'QA_CHECKLIST.md',
            'SCREENSHOTS.md',
        ];
        foreach ($requiredDocs as $doc) {
            $path = $pluginDir . DIRECTORY_SEPARATOR . $doc;
            if (file_exists($path)) {
                $passes[] = 'Found document: ' . $doc;
            } else {
                $missing[] = 'Missing document: ' . $doc;
            }
        }

        $expectedScreens = [
            'template-studio-editor.png',
            'template-studio-compare.png',
            'documents-generator-result.png',
            'documents-list-pagination-search.png',
            'tracking-operations.png',
            'settings-retention.png',
            'audit-events.png',
            'public-tracking-map.png',
        ];
        $shotDir = $pluginDir . DIRECTORY_SEPARATOR . 'screenshots';
        foreach ($expectedScreens as $file) {
            $path = $shotDir . DIRECTORY_SEPARATOR . $file;
            if (file_exists($path)) {
                $passes[] = 'Found screenshot: screenshots/' . $file;
            } else {
                $missing[] = 'Missing screenshot: screenshots/' . $file;
            }
        }

        $entryFile = $pluginDir . DIRECTORY_SEPARATOR . 'cargo-docs-studio.php';
        if (file_exists($entryFile)) {
            $passes[] = 'Found plugin entrypoint: cargo-docs-studio.php';
        } else {
            $missing[] = 'Missing plugin entrypoint: cargo-docs-studio.php';
        }

        foreach ($passes as $line) {
            \WP_CLI::log('[PASS] ' . $line);
        }
        foreach ($missing as $line) {
            \WP_CLI::warning('[FAIL] ' . $line);
        }

        if (!empty($missing)) {
            \WP_CLI::error('Preflight failed with ' . count($missing) . ' issue(s).');
        }

        \WP_CLI::success('Preflight checks passed.');
    }
}
