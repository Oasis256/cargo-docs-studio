<?php

namespace CargoDocsStudio\Core;

use CargoDocsStudio\Domain\Update\GitHubReleaseClient;
use CargoDocsStudio\Domain\Update\VersionComparator;

class Updater
{
    private const TRANSIENT_KEY = 'cds_github_release_latest';

    private GitHubReleaseClient $client;
    private VersionComparator $versions;
    private string $pluginBasename;
    private string $slug;

    public function __construct(?GitHubReleaseClient $client = null, ?VersionComparator $versions = null)
    {
        $this->client = $client ?: new GitHubReleaseClient(CDS_GH_OWNER, CDS_GH_REPO, CDS_GH_RELEASE_ASSET_PATTERN);
        $this->versions = $versions ?: new VersionComparator();
        $this->pluginBasename = plugin_basename(CDS_PLUGIN_FILE);
        $this->slug = dirname($this->pluginBasename);
    }

    public function register(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'injectUpdate']);
        add_filter('plugins_api', [$this, 'pluginsApi'], 20, 3);
        add_action('upgrader_process_complete', [$this, 'clearCacheAfterUpgrade'], 10, 2);
    }

    public function injectUpdate(mixed $transient): mixed
    {
        if (!is_object($transient)) {
            return $transient;
        }
        if (!isset($transient->checked) || !is_array($transient->checked) || !isset($transient->checked[$this->pluginBasename])) {
            return $transient;
        }

        $release = $this->getLatestRelease();
        if (is_wp_error($release)) {
            return $transient;
        }

        $latest = (string) ($release['tag_name'] ?? '');
        if (!$this->versions->isNewer($latest, CDS_VERSION)) {
            return $transient;
        }

        $newVersion = $this->versions->normalizeTagVersion($latest);
        if ($newVersion === '') {
            return $transient;
        }

        $update = new \stdClass();
        $update->id = 'github.com/' . CDS_GH_OWNER . '/' . CDS_GH_REPO;
        $update->slug = $this->slug;
        $update->plugin = $this->pluginBasename;
        $update->new_version = $newVersion;
        $update->url = (string) ($release['release_url'] ?? '');
        $update->package = (string) ($release['zip_url'] ?? '');
        $update->tested = get_bloginfo('version');
        $update->requires = '6.5';
        $update->requires_php = '8.1';

        $transient->response[$this->pluginBasename] = $update;

        return $transient;
    }

    public function pluginsApi(mixed $result, string $action, mixed $args): mixed
    {
        if ($action !== 'plugin_information' || !is_object($args) || empty($args->slug) || (string) $args->slug !== $this->slug) {
            return $result;
        }

        $release = $this->getLatestRelease();
        if (is_wp_error($release)) {
            return $result;
        }

        $latest = (string) ($release['tag_name'] ?? '');
        $newVersion = $this->versions->normalizeTagVersion($latest);
        if ($newVersion === '') {
            return $result;
        }

        $info = new \stdClass();
        $info->name = 'CargoDocs Studio';
        $info->slug = $this->slug;
        $info->version = $newVersion;
        $info->author = '<a href="https://github.com/' . esc_attr(CDS_GH_OWNER) . '">' . esc_html(CDS_GH_OWNER) . '</a>';
        $info->homepage = (string) ($release['release_url'] ?? ('https://github.com/' . CDS_GH_OWNER . '/' . CDS_GH_REPO));
        $info->download_link = (string) ($release['zip_url'] ?? '');
        $info->sections = [
            'description' => 'CargoDocs Studio release delivered from GitHub.',
            'changelog' => nl2br(esc_html((string) ($release['changelog'] ?? 'No changelog provided.'))),
        ];
        $info->last_updated = (string) ($release['published_at'] ?? '');

        return $info;
    }

    public function clearCacheAfterUpgrade(\WP_Upgrader $upgrader, array $options): void
    {
        if (($options['action'] ?? '') !== 'update' || ($options['type'] ?? '') !== 'plugin') {
            return;
        }
        $plugins = $options['plugins'] ?? [];
        if (is_array($plugins) && in_array($this->pluginBasename, $plugins, true)) {
            delete_site_transient(self::TRANSIENT_KEY);
        }
    }

    private function getLatestRelease(): array|\WP_Error
    {
        $cached = get_site_transient(self::TRANSIENT_KEY);
        if (is_array($cached) && !empty($cached['tag_name']) && !empty($cached['zip_url'])) {
            return $cached;
        }

        $release = $this->client->fetchLatestStableRelease();
        if (is_wp_error($release)) {
            return $release;
        }

        set_site_transient(self::TRANSIENT_KEY, $release, CDS_UPDATER_CACHE_TTL);

        return $release;
    }
}

