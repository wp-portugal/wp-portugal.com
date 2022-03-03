<?php
/**
 * General utility functions for use throughout the plugin and loader.
 *
 * @license MIT
 * @copyright Luke Woodward
 * @package WP_MUPlugin_Loader
 */

namespace LkWdwrd\MuPluginLoader\Util;

/**
 * Creates additional list-table to display all loaded Must-Use Plugins.
 *
 * Grabs all the loaded must-use plugins, gets the data object for each, and
 * then uses the core list-table object to print additional rows for each of
 * the loaded plugins complete with all the plugin info normally available
 * in the list-table.
 *
 * Each name is prefixed with '+  ' to help indicate it was added through the
 * Must-Use Plugins Loader.
 * @param  \WP_Plugins_List_Table|string $lt    The core list table class.
 * @param  string                        $ps    The path separator to use.
 * @param  string                        $mudir The Must-Use Plugins directory.
 * @return void
 */
function list_table($lt = \WP_Plugins_List_Table::class, $ps = DIRECTORY_SEPARATOR, $mudir = WPMU_PLUGIN_DIR): void
{
    $table = new $lt();
    $spacer = '+&nbsp;&nbsp;';

    foreach (get_muplugins() as $plugin_file) {
        $plugin_data = get_plugin_data($mudir . $ps . $plugin_file, false);
        if (empty($plugin_data['Name'])) {
            $plugin_data['Name'] = $plugin_file;
        }
        $plugin_data['Name'] = $spacer . $plugin_data['Name'];
        $table->single_row([ $plugin_file, $plugin_data ]);
    }
}
