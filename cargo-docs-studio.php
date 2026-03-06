<?php
/**
 * Plugin Name: CargoDocs Studio
 * Plugin URI: https://github.com/Oasis256/cargo-docs-studio
 * Description: Secure cargo document operations suite for invoice, receipt, and SKR generation with template governance, tracking workflows, and QR-enabled outputs.
 * Version: 0.1.8
 * Author: Oasis Innocent
 * License: GPLv2 or later
 * Update URI: https://github.com/Oasis256/cargo-docs-studio
 * Text Domain: cargo-docs-studio
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CDS_VERSION', '0.1.8');
define('CDS_PLUGIN_FILE', __FILE__);
define('CDS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CDS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CDS_GH_OWNER', 'Oasis256');
define('CDS_GH_REPO', 'cargo-docs-studio');
define('CDS_GH_RELEASE_ASSET_PATTERN', 'cargo-docs-studio.zip');
define('CDS_UPDATER_CACHE_TTL', 6 * HOUR_IN_SECONDS);

spl_autoload_register(static function ($class) {
    $prefix = 'CargoDocsStudio\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = CDS_PLUGIN_DIR . 'src/' . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($path)) {
        require_once $path;
    }
});

use CargoDocsStudio\Core\Plugin;

register_activation_hook(__FILE__, [Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [Plugin::class, 'deactivate']);

add_action('plugins_loaded', static function () {
    Plugin::instance()->boot();
});
