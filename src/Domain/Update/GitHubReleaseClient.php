<?php

namespace CargoDocsStudio\Domain\Update;

class GitHubReleaseClient
{
    private string $owner;
    private string $repo;
    private string $assetName;

    public function __construct(string $owner, string $repo, string $assetName)
    {
        $this->owner = trim($owner);
        $this->repo = trim($repo);
        $this->assetName = trim($assetName);
    }

    public function fetchLatestStableRelease(): array|\WP_Error
    {
        if ($this->owner === '' || $this->repo === '' || $this->assetName === '') {
            return new \WP_Error('cds_updater_invalid_config', 'GitHub updater configuration is incomplete.');
        }

        $apiUrl = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            rawurlencode($this->owner),
            rawurlencode($this->repo)
        );

        $response = wp_remote_get($apiUrl, [
            'timeout' => 15,
            'redirection' => 3,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'CargoDocsStudio-Updater/' . CDS_VERSION,
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            return new \WP_Error('cds_updater_http', 'GitHub release API request failed with status ' . $status);
        }

        $payload = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($payload)) {
            return new \WP_Error('cds_updater_invalid_json', 'GitHub release API returned invalid JSON.');
        }

        if (!empty($payload['draft']) || !empty($payload['prerelease'])) {
            return new \WP_Error('cds_updater_no_stable', 'Latest release is not stable.');
        }

        $assetUrl = '';
        if (!empty($payload['assets']) && is_array($payload['assets'])) {
            foreach ($payload['assets'] as $asset) {
                if (!is_array($asset)) {
                    continue;
                }
                $name = (string) ($asset['name'] ?? '');
                if ($name === $this->assetName) {
                    $assetUrl = (string) ($asset['browser_download_url'] ?? '');
                    break;
                }
            }
        }

        if ($assetUrl === '') {
            return new \WP_Error('cds_updater_asset_missing', 'Expected release ZIP asset is missing.');
        }

        return [
            'tag_name' => (string) ($payload['tag_name'] ?? ''),
            'version' => (string) ($payload['name'] ?? ''),
            'zip_url' => esc_url_raw($assetUrl),
            'release_url' => esc_url_raw((string) ($payload['html_url'] ?? '')),
            'changelog' => (string) ($payload['body'] ?? ''),
            'published_at' => (string) ($payload['published_at'] ?? ''),
        ];
    }
}

