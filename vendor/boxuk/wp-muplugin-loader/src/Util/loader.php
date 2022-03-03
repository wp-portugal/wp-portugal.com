<?php
/**
 * All the methods to properly load any Must-Use plugins into WordPress.
 *
 * @license MIT
 * @copyright Luke Woodward
 * @package WP_MUPlugin_Loader
 */

namespace LkWdwrd\MuPluginLoader\Util;

/**
 * The main loader method to get available Must-Use plugins and require them.
 *
 * @param  array|bool $plugins The array of plugins to install, or false. When
 *                             false it will gather plugins using the
 *                             `get_muplugins()` function. Default: false.
 * @param  string     $ps      The path separator to use when combining paths.
 *                             Default: DIRECTORY_SEPARATOR
 * @param  string     $mudir   The absolute Must-Use Plugins directory string.
 *                             Default: WPMU_PLUGIN_DIR
 * @return void
 */
function mu_loader($plugins = false, string $ps = DIRECTORY_SEPARATOR, string $mudir = WPMU_PLUGIN_DIR): void
{
    if (! $plugins) {
        $plugins = get_muplugins(ABSPATH, WP_PLUGIN_DIR, $mudir);
    }
    foreach ($plugins as $plugin) {
        // Conditionally register the MU plugin in WordPress 3.9 or newer.
        if (function_exists('wp_register_plugin_realpath')) {
            wp_register_plugin_realpath($mudir . $ps . $plugin);
        }
        require_once $mudir . $ps . $plugin;
    }

    do_action('lkwdwrd_mupluginloader_muplugins_loaded');
}

/**
 * Gets a list of the available plugins in the Must-Use Plugins directory.
 *
 * An attempt is made to load the plugin list from the cache. If the cache is
 * not available, it will load and run the WordPress core `get_plugins` function
 * to gather all plugins with the appropriate headers, compiling them into a
 * an array of fully qualified plugin paths.
 *
 * @param  string $abs   The WordPress Abosolute Path. Default: ABSPATH
 * @param  string $pdir  The WordPress Plugins Directory. Default: WP_PLUGIN_DIR
 * @param  string $mudir The WordPress MU Plugins Directory. Default:
 *                       WPMU_PLUGIN_DIR
 * @param  string $ps    The path seperator to use. Default: DIRECTORY_SEPARATOR
 * @return array         An array of aboslute paths to the plugin files.
 */
function get_muplugins(string $abs = ABSPATH, string $pdir = WP_PLUGIN_DIR, string $mudir = WPMU_PLUGIN_DIR, string $ps = DIRECTORY_SEPARATOR): array
{
    $key = get_muloader_key($mudir);
    // Try to get the plugin list from the cache
    $plugins = get_site_transient($key);
    // If the cache missed, regenerate it.
    if ($plugins === false) {
        if (! function_exists('get_plugins')) {
            // get_plugins is not included by default
            require $abs . 'wp-admin/includes/plugin.php';
        }
        $plugins = [];
        $rel_path = rel_path($pdir, $mudir);
        foreach (get_plugins($ps . $rel_path) as $plugin_file => $data) {
            // skip files directly at root
            if (dirname($plugin_file) !== '.') {
                $plugins[] = $plugin_file;
            }
        }
        set_site_transient($key, $plugins);
    }
    return $plugins;
}

/**
 * Gets a unique key to use in caching the MU-Plugins list.
 *
 * Because this uses transients, we can't simply let the key change for
 * invalidation. To that end, we store the used key as a transient and then
 * pull that transient. We then create the cache key using an MD5 hash of The
 * files in the Must-Use plugins directory. If the files change, the key will
 * also change. If it does not match the old key, the previous cache entry is
 * removed and the new key is stored for future comparisons.
 *
 * Doing this ensures as the MU-Plugins directory changes, regardless of the
 * caching mechanism, even the options table, the data will not build up over
 * time. Especially important when the wp_options table is used.
 *
 * @param string $mudir The MU Plugins Directory. Default: WPMU_PLUGIN_DIR
 *
 * @return string        An MD5 cache key to use.
 * @throws \JsonException If the directory listing can't be json encoded.
 */
function get_muloader_key(string $mudir = WPMU_PLUGIN_DIR): string
{
    $old_key = get_site_transient('lkw_mu_loader_key');

    $key = md5(json_encode(scandir($mudir), JSON_THROW_ON_ERROR));
    if ($old_key !== $key) {
        if ($old_key) {
            delete_site_transient($old_key);
        }
        set_site_transient('lkw_mu_loader_key', $key);
    }
    return $key;
}
