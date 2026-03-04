<?php

namespace CargoDocsStudio\Cli;

class ReleaseReportCommand
{
    public function run(array $args, array $assocArgs): void
    {
        $pluginDir = rtrim(CDS_PLUGIN_DIR, '/\\');
        $requiredDocs = [
            'README.md',
            'CHANGELOG.md',
            'MIGRATIONS.md',
            'RUNBOOK.md',
            'QA_CHECKLIST.md',
            'SCREENSHOTS.md',
        ];
        $requiredScreens = [
            'screenshots/template-studio-editor.png',
            'screenshots/template-studio-compare.png',
            'screenshots/documents-generator-result.png',
            'screenshots/documents-list-pagination-search.png',
            'screenshots/tracking-operations.png',
            'screenshots/settings-retention.png',
            'screenshots/audit-events.png',
            'screenshots/public-tracking-map.png',
        ];

        $missingDocs = [];
        foreach ($requiredDocs as $doc) {
            if (!file_exists($pluginDir . DIRECTORY_SEPARATOR . $doc)) {
                $missingDocs[] = $doc;
            }
        }

        $missingScreens = [];
        foreach ($requiredScreens as $file) {
            $path = $pluginDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
            if (!file_exists($path)) {
                $missingScreens[] = $file;
            }
        }

        \WP_CLI::log('CargoDocs Studio Release Report');
        \WP_CLI::log('--------------------------------');
        \WP_CLI::log('Plugin path: ' . $pluginDir);
        \WP_CLI::log('Required docs: ' . count($requiredDocs) . ' | Missing: ' . count($missingDocs));
        \WP_CLI::log('Required screenshots: ' . count($requiredScreens) . ' | Missing: ' . count($missingScreens));
        \WP_CLI::log('');

        if (!empty($missingDocs)) {
            \WP_CLI::warning('Missing docs:');
            foreach ($missingDocs as $doc) {
                \WP_CLI::log(' - ' . $doc);
            }
            \WP_CLI::log('');
        }

        if (!empty($missingScreens)) {
            \WP_CLI::warning('Missing screenshots:');
            foreach ($missingScreens as $file) {
                \WP_CLI::log(' - ' . $file);
            }
            \WP_CLI::log('');
        }

        \WP_CLI::log('Recommended release run:');
        \WP_CLI::log('  1) wp cds preflight');
        \WP_CLI::log('  2) wp cds smoke');
        \WP_CLI::log('  3) wp cds test-permissions');
        \WP_CLI::log('  4) wp cds package --output=path/to/cargo-docs-studio.zip');

        if (empty($missingDocs) && empty($missingScreens)) {
            \WP_CLI::success('Release artifacts look complete.');
            return;
        }

        \WP_CLI::warning('Release artifacts are incomplete. Resolve missing items before packaging.');
    }
}
