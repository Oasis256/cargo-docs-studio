<?php

namespace CargoDocsStudio\Cli;

class PackageCommand
{
    public function run(array $args, array $assocArgs): void
    {
        $pluginDir = rtrim(CDS_PLUGIN_DIR, '/\\');
        $pluginSlug = basename($pluginDir);
        $defaultOutput = dirname($pluginDir) . DIRECTORY_SEPARATOR . $pluginSlug . '-dist.zip';
        $output = isset($assocArgs['output']) ? (string) $assocArgs['output'] : $defaultOutput;
        $output = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $output);

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
            if (!file_exists($path)) {
                \WP_CLI::warning('Missing release document: ' . $doc);
            }
        }

        if (!class_exists('ZipArchive')) {
            \WP_CLI::error('ZipArchive is not available on this PHP installation.');
        }

        $zip = new \ZipArchive();
        if (file_exists($output)) {
            @unlink($output);
        }
        $open = $zip->open($output, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($open !== true) {
            \WP_CLI::error('Failed to open output zip: ' . $output);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($pluginDir, \FilesystemIterator::SKIP_DOTS)
        );

        $excluded = [
            DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . '.idea' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . '.vscode' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . 'checkpoints' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR,
        ];

        $count = 0;
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $fullPath = $file->getPathname();
            $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fullPath);
            $skip = false;
            foreach ($excluded as $pattern) {
                if (str_contains($normalized, $pattern)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $relative = ltrim(substr($normalized, strlen($pluginDir)), DIRECTORY_SEPARATOR);
            if (preg_match('/\.zip$/i', $relative)) {
                continue;
            }
            $zip->addFile($fullPath, $pluginSlug . '/' . $relative);
            $count++;
        }

        $zip->close();

        \WP_CLI::success('Package built: ' . $output . ' (' . $count . ' files)');
    }
}
