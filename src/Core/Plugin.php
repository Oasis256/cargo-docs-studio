<?php

namespace CargoDocsStudio\Core;

use CargoDocsStudio\Admin\Pages\AuditPage;
use CargoDocsStudio\Admin\Pages\DashboardPage;
use CargoDocsStudio\Admin\Pages\DocumentsPage;
use CargoDocsStudio\Admin\Pages\SettingsPage;
use CargoDocsStudio\Admin\Pages\TemplateStudioPage;
use CargoDocsStudio\Admin\Pages\TrackingPage;
use CargoDocsStudio\Cli\PackageCommand;
use CargoDocsStudio\Cli\PermissionsRegressionCommand;
use CargoDocsStudio\Cli\PreflightCommand;
use CargoDocsStudio\Cli\ReleaseReportCommand;
use CargoDocsStudio\Cli\ReleaseRunCommand;
use CargoDocsStudio\Cli\SmokeTestCommand;
use CargoDocsStudio\Database\Migrator;
use CargoDocsStudio\Domain\Maintenance\RetentionCleanupService;

class Plugin
{
    private const CLEANUP_HOOK = 'cds_daily_cleanup_event';

    private static ?self $instance = null;

    private Container $container;

    public static function instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate(): void
    {
        (new Capabilities())->register();
        (new Migrator())->run();
        self::scheduleCleanup();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        $timestamp = wp_next_scheduled(self::CLEANUP_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CLEANUP_HOOK);
        }
        flush_rewrite_rules();
    }

    public function boot(): void
    {
        $this->container = new Container();
        $this->container->set('caps', new Capabilities());
        $this->container->set('assets', new Assets());
        $this->container->set('routes', new Routes());
        $this->container->set('updater', new Updater());

        add_action('init', [$this, 'registerTextDomain']);
        add_action('init', [$this, 'registerRewriteRules']);
        add_action('init', [$this, 'ensureCleanupScheduled']);
        add_action('admin_menu', [$this, 'registerAdminMenu']);
        add_action('template_redirect', [$this, 'maybeRenderPublicTrackingPage']);
        add_filter('query_vars', [$this, 'registerQueryVars']);
        add_action(self::CLEANUP_HOOK, [$this, 'runDailyCleanup']);

        /** @var Assets $assets */
        $assets = $this->container->get('assets');
        $assets->register();

        /** @var Routes $routes */
        $routes = $this->container->get('routes');
        $routes->register();

        /** @var Updater $updater */
        $updater = $this->container->get('updater');
        $updater->register();

        $this->registerCliCommands();
    }

    public function registerTextDomain(): void
    {
        load_plugin_textdomain('cargo-docs-studio', false, dirname(plugin_basename(CDS_PLUGIN_FILE)) . '/languages');
    }

    public function registerRewriteRules(): void
    {
        add_rewrite_rule('^cargo-track/([A-Za-z0-9\-]+)/?$', 'index.php?cds_track=$matches[1]', 'top');
    }

    public function registerQueryVars(array $vars): array
    {
        $vars[] = 'cds_track';
        return $vars;
    }

    public function ensureCleanupScheduled(): void
    {
        self::scheduleCleanup();
    }

    public function runDailyCleanup(): void
    {
        (new RetentionCleanupService())->run();
    }

    private static function scheduleCleanup(): void
    {
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK);
        }
    }

    private function registerCliCommands(): void
    {
        if (!defined('WP_CLI') || !\WP_CLI) {
            return;
        }

        \WP_CLI::add_command('cds smoke', [new SmokeTestCommand(), 'run']);
        \WP_CLI::add_command('cds test-permissions', [new PermissionsRegressionCommand(), 'run']);
        \WP_CLI::add_command('cds package', [new PackageCommand(), 'run']);
        \WP_CLI::add_command('cds preflight', [new PreflightCommand(), 'run']);
        \WP_CLI::add_command('cds release-report', [new ReleaseReportCommand(), 'run']);
        \WP_CLI::add_command('cds release-run', [new ReleaseRunCommand(), 'run']);
    }

    public function maybeRenderPublicTrackingPage(): void
    {
        $trackingCode = get_query_var('cds_track');
        if (!$trackingCode) {
            return;
        }

        $trackingCode = sanitize_text_field((string) $trackingCode);
        $token = isset($_GET['t']) ? sanitize_text_field((string) $_GET['t']) : '';

        status_header(200);
        nocache_headers();

        $restUrl = esc_url_raw(rest_url('cds/v1/tracking/' . rawurlencode($trackingCode) . '?t=' . rawurlencode($token)));

        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>Cargo Tracking</title>';
        echo '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />';
        echo '<style>body{font-family:Arial,sans-serif;margin:0;padding:20px;}#map{height:50vh;min-height:320px;border-radius:8px;} .card{max-width:1000px;margin:0 auto;} .meta{margin:10px 0 20px;color:#333;}</style>';
        echo '</head><body><div class="card"><h1>Cargo Tracking</h1><div id="meta" class="meta">Loading latest location...</div><div id="map"></div><h3>Stops</h3><div id="stops"></div></div>';
        echo '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';
        $trackingScript = <<<'JS'
const restUrl = __REST_URL__;
const meta = document.getElementById("meta");
const stopsEl = document.getElementById("stops");
const map = L.map("map").setView([0, 0], 2);
L.tileLayer("https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}", {
  maxZoom: 19,
  attribution: "Tiles © Esri"
}).addTo(map);

fetch(restUrl)
  .then((r) => r.json())
  .then((data) => {
    if (!data.ok) {
      meta.textContent = data.error || "Tracking unavailable";
      return;
    }

    const t = data.tracking || {};
    meta.textContent = `Status: ${t.status || "Unknown"} | Current location: ${t.current_location || "Unknown"} | Last update: ${t.last_update_at || "-"}`;

    const coords = [];
    if (t.lat && t.lng) {
      coords.push([parseFloat(t.lat), parseFloat(t.lng)]);
      L.marker(coords[0]).addTo(map).bindPopup(t.current_location || "Current location").openPopup();
    }

    let html = "<ol>";
    for (const s of (t.stops || [])) {
      const hasCoords = s && s.lat !== null && s.lat !== "" && s.lng !== null && s.lng !== "";
      if (hasCoords) {
        const stopCoord = [parseFloat(s.lat), parseFloat(s.lng)];
        coords.push(stopCoord);
        L.circleMarker(stopCoord, { radius: 5 }).addTo(map).bindPopup(s.stop_name || "Stop");
      }
      html += `<li><strong>${s.stop_name || "Stop"}</strong> - ${s.status || "Unknown"} (${s.created_at || "-"}) ${s.notes ? "<br>" + s.notes : ""}</li>`;
    }
    html += "</ol>";
    stopsEl.innerHTML = html;

    if (coords.length > 1) {
      const route = coords.slice().reverse();
      L.polyline(route, { weight: 4, opacity: 0.75 }).addTo(map);
      map.fitBounds(route, { padding: [20, 20] });
    } else if (coords.length === 1) {
      map.setView(coords[0], 10);
    } else {
      meta.textContent += " | No GPS coordinates available yet.";
    }
  })
  .catch(() => {
    meta.textContent = "Failed to load tracking data";
  });
JS;
        $trackingScript = str_replace('__REST_URL__', wp_json_encode($restUrl), $trackingScript);
        echo '<script>' . $trackingScript . '</script>';
        echo '</body></html>';
        exit;
    }

    public function registerAdminMenu(): void
    {
        add_menu_page(
            __('CargoDocs Studio', 'cargo-docs-studio'),
            __('CargoDocs Studio', 'cargo-docs-studio'),
            'cds_view_documents',
            'cargo-docs-studio',
            [new DashboardPage(), 'render'],
            'dashicons-media-document',
            32
        );

        add_submenu_page('cargo-docs-studio', __('Documents', 'cargo-docs-studio'), __('Documents', 'cargo-docs-studio'), 'cds_view_documents', 'cargo-docs-studio-documents', [new DocumentsPage(), 'render']);
        add_submenu_page('cargo-docs-studio', __('Tracking', 'cargo-docs-studio'), __('Tracking', 'cargo-docs-studio'), 'cds_view_tracking_admin', 'cargo-docs-studio-tracking', [new TrackingPage(), 'render']);
        add_submenu_page('cargo-docs-studio', __('Template Studio', 'cargo-docs-studio'), __('Template Studio', 'cargo-docs-studio'), 'cds_manage_templates', 'cargo-docs-studio-templates', [new TemplateStudioPage(), 'render']);
        add_submenu_page('cargo-docs-studio', __('Settings', 'cargo-docs-studio'), __('Settings', 'cargo-docs-studio'), 'cds_manage_settings', 'cargo-docs-studio-settings', [new SettingsPage(), 'render']);
        add_submenu_page('cargo-docs-studio', __('Audit', 'cargo-docs-studio'), __('Audit', 'cargo-docs-studio'), 'cds_view_audit', 'cargo-docs-studio-audit', [new AuditPage(), 'render']);
    }
}
