<?php

namespace CargoDocsStudio\Cli;

class ReleaseRunCommand
{
    public function run(array $args, array $assocArgs): void
    {
        $output = isset($assocArgs['output']) ? (string) $assocArgs['output'] : null;

        $steps = [
            [
                'label' => 'Preflight',
                'fn' => static function () use ($assocArgs): void {
                    (new PreflightCommand())->run([], $assocArgs);
                },
            ],
            [
                'label' => 'Smoke',
                'fn' => static function (): void {
                    (new SmokeTestCommand())->run([], []);
                },
            ],
            [
                'label' => 'Permission Regression',
                'fn' => static function (): void {
                    (new PermissionsRegressionCommand())->run([], []);
                },
            ],
            [
                'label' => 'Package',
                'fn' => static function () use ($output): void {
                    $assoc = [];
                    if ($output !== null && $output !== '') {
                        $assoc['output'] = $output;
                    }
                    (new PackageCommand())->run([], $assoc);
                },
            ],
        ];

        \WP_CLI::log('CargoDocs Studio release run started.');
        foreach ($steps as $step) {
            \WP_CLI::log('==> ' . $step['label']);
            try {
                $step['fn']();
            } catch (\Throwable $e) {
                \WP_CLI::error('Release step failed: ' . $step['label'] . ' | ' . $e->getMessage());
            }
        }

        \WP_CLI::success('Release run completed successfully.');
    }
}
