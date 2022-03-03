<?php

/*************************************************************
 * installer.class.php
 * Upgrade WordPress
 * Copyright (c) 2011 Prelovac Media
 * www.prelovac.com
 **************************************************************/
class MMB_Installer extends MMB_Core
{
    public function __construct()
    {
        @set_time_limit(600);
        parent::__construct();
        @include_once ABSPATH.'wp-admin/includes/file.php';
        @include_once ABSPATH.'wp-admin/includes/plugin.php';
        @include_once ABSPATH.'wp-admin/includes/theme.php';
        @include_once ABSPATH.'wp-admin/includes/misc.php';
        @include_once ABSPATH.'wp-admin/includes/template.php';
        @include_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';

        global $wp_filesystem;

        if (!$this->check_if_pantheon() && !$wp_filesystem) {
            WP_Filesystem();
        }
    }

    public function mmb_maintenance_mode($enable = false, $maintenance_message = '')
    {
        global $wp_filesystem;

        $maintenance_message .= '<?php $upgrading = '.time().'; ?>';

        $file = $wp_filesystem->abspath().'.maintenance';
        if ($enable) {
            $wp_filesystem->delete($file);
            $wp_filesystem->put_contents($file, $maintenance_message, FS_CHMOD_FILE);
        } else {
            $wp_filesystem->delete($file);
        }
    }

    public function install_remote_file($params)
    {
        global $wp_filesystem;
        extract($params);
        $network_activate = isset($params['network_activate']) ? $params['network_activate'] : false;

        if (!isset($package) || empty($package)) {
            return array(
                'error' => '<p>No files received. Internal error.</p>',
            );
        }

        if (!$this->is_server_writable()) {
            return array(
                'error' => 'Failed, please <a target="_blank" href="http://managewp.com/user-guide/faq/my-pluginsthemes-fail-to-update-or-i-receive-a-yellow-ftp-warning">add FTP details</a>',
            );
        }

        if (defined('WP_INSTALLING') && file_exists(ABSPATH.'.maintenance')) {
            return array(
                'error' => '<p>Site under maintanace.</p>',
            );
        }

        if (!class_exists('WP_Upgrader')) {
            include_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
        }

        /** @handled class */
        $upgrader = new WP_Upgrader(mwp_container()->getUpdaterSkin());
        $upgrader->init();
        $destination       = $type == 'themes' ? WP_CONTENT_DIR.'/themes' : WP_PLUGIN_DIR;
        $clear_destination = isset($clear_destination) ? $clear_destination : false;

        foreach ($package as $package_url) {
            $key                = basename($package_url);
            $install_info[$key] = @$upgrader->run(
                array(
                    'package'           => $package_url,
                    'destination'       => $destination,
                    'clear_destination' => $clear_destination, //Do not overwrite files.
                    'clear_working'     => true,
                    'hook_extra'        => array(),
                )
            );
        }

        if (defined('WP_ADMIN') && WP_ADMIN) {
            global $wp_current_filter;
            $wp_current_filter[] = 'load-update-core.php';

            if (function_exists('wp_clean_update_cache')) {
                /** @handled function */
                wp_clean_update_cache();
            }

            /** @handled function */
            wp_update_plugins();

            array_pop($wp_current_filter);

            /** @handled function */
            set_current_screen();
            do_action('load-update-core.php');

            /** @handled function */
            wp_version_check();

            /** @handled function */
            wp_version_check(array(), true);
        }

        $wrongFileType = false;
        if ($type == 'plugins') {
            $wrongFileType = true;
            include_once ABSPATH.'wp-admin/includes/plugin.php';
            $all_plugins = get_plugins();
            foreach ($all_plugins as $plugin_slug => $plugin) {
                $plugin_dir = preg_split('/\//', $plugin_slug);
                foreach ($install_info as $key => $install) {
                    if (!$install || is_wp_error($install)) {
                        continue;
                    }
                    if ($install['destination_name'] == $plugin_dir[0]) {
                        $wrongFileType = false;
                        if ($activate) {
                            $install_info[$key]['activated'] = activate_plugin($plugin_slug, '', $network_activate);
                        }

                        $install_info[$key]['basename']  = $plugin_slug;
                        $install_info[$key]['full_name'] = $plugin['Name'];
                        $install_info[$key]['version']   = $plugin['Version'];
                    }
                }
            }
        }

        if ($type == 'themes') {
            $wrongFileType = true;
            if (count($install_info) == 1) {
                global $wp_themes;
                include_once ABSPATH.'wp-includes/theme.php';

                $wp_themes = null;
                unset($wp_themes); //prevent theme data caching
                if (function_exists('wp_get_theme')) {
                    foreach ($install_info as $key => $install) {
                        if (!$install || is_wp_error($install)) {
                            continue;
                        }

                        $theme = wp_get_theme($install['destination_name']);
                        if ($theme->errors() !== false) {
                            $install_info[$key] = $theme->errors();
                            continue;
                        }

                        $wrongFileType = false;
                        if ($activate) {
                            $install_info[$key]['activated'] = switch_theme($theme->Template, $theme->Stylesheet);
                        }

                        $install_info[$key]['full_name'] = $theme->name;
                        $install_info[$key]['version']   = $theme->version;
                    }
                } else {
                    $all_themes = get_themes();
                    foreach ($all_themes as $theme_name => $theme_data) {
                        foreach ($install_info as $key => $install) {
                            if (!$install || is_wp_error($install)) {
                                continue;
                            }

                            if ($theme_data['Template'] == $install['destination_name'] || $theme_data['Stylesheet'] == $install['destination_name']) {
                                $wrongFileType = false;
                                if ($activate) {
                                    $install_info[$key]['activated'] = switch_theme($theme_data['Template'], $theme_data['Stylesheet']);
                                }
                                $install_info[$key]['full_name'] = $theme_data->name;
                                $install_info[$key]['version']   = $theme_data->version;
                            }
                        }
                    }
                }
            }
        }

        // Can generate "E_NOTICE: ob_clean(): failed to delete buffer. No buffer to delete."
        @ob_clean();
        $this->mmb_maintenance_mode(false);

        if (mwp_container()->getRequestStack()->getMasterRequest()->getProtocol() >= 1) {
            // WP_Error won't get JSON encoded, so unwrap the error here.
            foreach ($install_info as $key => $value) {
                if ($value instanceof WP_Error) {
                    $install_info[$key] = array(
                        'error' => $value->get_error_message(),
                        'code'  => $value->get_error_code(),
                    );
                } elseif ($wrongFileType) {
                    $otherType          = $type === 'themes' ? 'plugins' : $type;
                    $install_info[$key] = array(
                        'error' => 'You can\'t install '.$type.' on '.$otherType.' page.',
                        'code'  => 'wrong_type_of_file',
                    );
                }
            }
        }

        return $install_info;
    }

    private function ithemes_updater_compatiblity()
    {
        // Check for the iThemes updater class
        if (empty($GLOBALS['ithemes_updater_path']) ||
            !file_exists($GLOBALS['ithemes_updater_path'].'/settings.php')
        ) {
            return;
        }

        // Include iThemes updater
        require_once $GLOBALS['ithemes_updater_path'].'/settings.php';

        // Check if the updater is instantiated
        if (empty($GLOBALS['ithemes-updater-settings'])) {
            return;
        }

        // Update the download link
        $GLOBALS['ithemes-updater-settings']->flush('forced');
    }

    public function do_upgrade($params = null)
    {
        if ($params == null || empty($params)) {
            return array(
                'error' => 'No upgrades passed.',
            );
        }

        if (!$this->is_server_writable()) {
            return array(
                'error' => 'Failed, please <a target="_blank" href="http://managewp.com/user-guide/faq/my-pluginsthemes-fail-to-update-or-i-receive-a-yellow-ftp-warning">add FTP details</a>',
            );
        }

        $params = isset($params['upgrades_all']) ? $params['upgrades_all'] : $params;

        $core_upgrade         = isset($params['wp_upgrade']) ? $params['wp_upgrade'] : array();
        $upgrade_plugins      = isset($params['upgrade_plugins']) ? $params['upgrade_plugins'] : array();
        $upgrade_themes       = isset($params['upgrade_themes']) ? $params['upgrade_themes'] : array();
        $upgrade_translations = isset($params['upgrade_translations']) ? $params['upgrade_translations'] : false;

        $upgrades         = array();
        $premium_upgrades = array();
        if (!empty($core_upgrade)) {
            $upgrades['core'] = $this->upgrade_core($core_upgrade);
        }

        if (!empty($upgrade_plugins)) {
            $plugin_files = array();
            $this->ithemes_updater_compatiblity();
            foreach ($upgrade_plugins as $plugin) {
                if (isset($plugin['envatoPlugin']) && $plugin['envatoPlugin'] === true) {
                    $upgrades['plugins'] = $this->upgrade_envato_component($plugin);
                    continue;
                }

                if (isset($plugin['file'])) {
                    $plugin_files[$plugin['file']] = $plugin['old_version'];
                } else {
                    $premium_upgrades[md5($plugin['name'])] = $plugin;
                }
            }
            if (!empty($plugin_files)) {
                $upgrades['plugins'] = $this->upgrade_plugins($plugin_files);
            }
            $this->ithemes_updater_compatiblity();
        }

        if (!empty($upgrade_themes)) {
            $theme_temps = array();
            foreach ($upgrade_themes as $theme) {
                if (isset($theme['envatoTheme']) && $theme['envatoTheme'] === true) {
                    $upgrades['themes'] = $this->upgrade_envato_component($theme);
                    continue;
                }

                if (isset($theme['theme_tmp'])) {
                    $theme_temps[] = $theme['theme_tmp'];
                } else {
                    $premium_upgrades[md5($theme['name'])] = $theme;
                }
            }

            if (!empty($theme_temps)) {
                $upgrades['themes'] = $this->upgrade_themes($theme_temps);
            }
        }

        if (!empty($upgrade_translations)) {
            $upgrades['translations'] = $this->upgrade_translations();
        }

        @ob_clean();
        $this->mmb_maintenance_mode(false);

        return $upgrades;
    }

    /**
     * @param array $component
     *
     * @return array
     */
    private function upgrade_envato_component(array $component)
    {
        $result = $this->install_remote_file($component);
        $return = array();

        if (empty($result)) {
            return array(
                'error' => 'Upgrade failed.',
            );
        }

        foreach ($result as $component_slug => $component_info) {
            if (!$component_info || is_wp_error($component_info)) {
                $return[$component_slug] = $this->mmb_get_error($component_info);
                continue;
            }

            $return[$component_info['destination_name']] = 1;
        }

        return array(
            'upgraded' => $return,
        );
    }

    /**
     * Upgrades WordPress locally
     */
    public function upgrade_core($current)
    {
        ob_start();

        if (file_exists(ABSPATH.'/wp-admin/includes/update.php')) {
            include_once ABSPATH.'/wp-admin/includes/update.php';
        }

        $current_update = false;
        ob_end_flush();
        ob_end_clean();
        $core = $this->mmb_get_transient('update_core');

        if (isset($core->updates) && !empty($core->updates)) {
            $updates = $core->updates[0];
            $updated = $core->updates[0];
            if (!isset($updated->response) || $updated->response == 'latest') {
                return array(
                    'upgraded' => ' updated',
                );
            }

            if ($updated->response == "development" && $current['response'] == "upgrade") {
                return array(
                    'error' => '<font color="#900">Unexpected error. Please upgrade manually.</font>',
                );
            } else {
                if ($updated->response == $current['response'] || ($updated->response == "upgrade" && $current['response'] == "development")) {
                    if ($updated->locale != $current['locale']) {
                        foreach ($updates as $update) {
                            if ($update->locale == $current['locale']) {
                                $current_update = $update;
                                break;
                            }
                        }
                        if ($current_update == false) {
                            return array(
                                'error' => ' Localization mismatch. Try again.',
                            );
                        }
                    } else {
                        $current_update = $updated;
                    }
                } else {
                    return array(
                        'error' => ' Transient mismatch. Try again.',
                    );
                }
            }
        } else {
            return array(
                'error' => ' Refresh transient failed. Try again.',
            );
        }
        if ($current_update != false) {
            global $wp_filesystem, $wp_version;

            if (version_compare($wp_version, '3.1.9', '>')) {
                if (!class_exists('Core_Upgrader')) {
                    include_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
                }

                /** @handled class */
                $core   = new Core_Upgrader(mwp_container()->getUpdaterSkin());
                $result = $core->upgrade($current_update);
                $this->mmb_maintenance_mode(false);
                if (is_wp_error($result)) {
                    return array(
                        'error' => $this->mmb_get_error($result),
                    );
                } else {
                    return array(
                        'upgraded' => ' updated',
                    );
                }
            } else {
                if (!class_exists('WP_Upgrader')) {
                    include_once ABSPATH.'wp-admin/includes/update.php';
                    if (function_exists('wp_update_core')) {
                        $result = wp_update_core($current_update);
                        if (is_wp_error($result)) {
                            return array(
                                'error' => $this->mmb_get_error($result),
                            );
                        } else {
                            return array(
                                'upgraded' => ' updated',
                            );
                        }
                    }
                }

                if (class_exists('WP_Upgrader')) {
                    /** @handled class */
                    $upgrader_skin              = new WP_Upgrader_Skin();
                    $upgrader_skin->done_header = true;

                    /** @handled class */
                    $upgrader = new WP_Upgrader($upgrader_skin);

                    // Is an update available?
                    if (!isset($current_update->response) || $current_update->response == 'latest') {
                        return array(
                            'upgraded' => ' updated',
                        );
                    }

                    $res = $upgrader->fs_connect(
                        array(
                            ABSPATH,
                            WP_CONTENT_DIR,
                        )
                    );
                    if (is_wp_error($res)) {
                        return array(
                            'error' => $this->mmb_get_error($res),
                        );
                    }

                    $wp_dir = trailingslashit($wp_filesystem->abspath());

                    $core_package = false;
                    if (isset($current_update->package) && !empty($current_update->package)) {
                        $core_package = $current_update->package;
                    } elseif (isset($current_update->packages->full) && !empty($current_update->packages->full)) {
                        $core_package = $current_update->packages->full;
                    }

                    $download = $upgrader->download_package($core_package);
                    if (is_wp_error($download)) {
                        return array(
                            'error' => $this->mmb_get_error($download),
                        );
                    }

                    $working_dir = $upgrader->unpack_package($download);
                    if (is_wp_error($working_dir)) {
                        return array(
                            'error' => $this->mmb_get_error($working_dir),
                        );
                    }

                    if (!$wp_filesystem->copy($working_dir.'/wordpress/wp-admin/includes/update-core.php', $wp_dir.'wp-admin/includes/update-core.php', true)) {
                        $wp_filesystem->delete($working_dir, true);

                        return array(
                            'error' => 'Unable to move update files.',
                        );
                    }

                    $wp_filesystem->chmod($wp_dir.'wp-admin/includes/update-core.php', FS_CHMOD_FILE);

                    require ABSPATH.'wp-admin/includes/update-core.php';

                    $update_core = update_core($working_dir, $wp_dir);
                    ob_end_clean();

                    $this->mmb_maintenance_mode(false);
                    if (is_wp_error($update_core)) {
                        return array(
                            'error' => $this->mmb_get_error($update_core),
                        );
                    }
                    ob_end_flush();

                    return array(
                        'upgraded' => 'updated',
                    );
                } else {
                    return array(
                        'error' => 'failed',
                    );
                }
            }
        } else {
            return array(
                'error' => 'failed',
            );
        }
    }

    public function upgrade_plugins($plugins = false)
    {
        if (!$plugins || empty($plugins)) {
            return array(
                'error' => 'No plugin files for upgrade.',
            );
        }

        if (!function_exists('wp_update_plugins')) {
            include_once ABSPATH.'wp-includes/update.php';
        }

        $return = array();

        if (class_exists('Plugin_Upgrader')) {
            /** @handled class */
            $upgrader = new Plugin_Upgrader(mwp_container()->getUpdaterSkin());
            $result   = $upgrader->bulk_upgrade(array_keys($plugins));

            if (!empty($result)) {
                foreach ($result as $plugin_slug => $plugin_info) {
                    if (!$plugin_info || is_wp_error($plugin_info)) {
                        $return[$plugin_slug] = $this->mmb_get_error($plugin_info);
                        continue;
                    }

                    $return[$plugin_slug] = 1;
                }

                return array(
                    'upgraded'           => $return,
                    'additional_updates' => $this->get_additional_plugin_updates($result),
                );
            } else {
                return array(
                    'error' => 'Upgrade failed.',
                );
            }
        } else {
            return array(
                'error' => 'WordPress update required first.',
            );
        }
    }

    private function get_additional_plugin_updates($plugin_upgrades)
    {
        if (empty($plugin_upgrades)) {
            return array();
        }

        $additional_updates = array();

        if (array_key_exists('woocommerce/woocommerce.php', $plugin_upgrades) && is_plugin_active('woocommerce/woocommerce.php') && $this->has_woocommerce_db_update()) {
            $additional_updates['woocommerce/woocommerce.php'] = 1;
        }

        return $additional_updates;
    }

    private function has_woocommerce_db_update()
    {
        $current_db_version = get_option('woocommerce_db_version', null);
        $current_wc_version = get_option('woocommerce_version');
        if (version_compare($current_wc_version, '3.0.0', '<')) {
            return true;
        }

        $latestUpdate = $this->get_wc_db_latest_update();

        return !is_null($current_db_version) && !is_null($latestUpdate) &&
            version_compare($current_db_version, $latestUpdate, '<');
    }

    private function get_wc_db_latest_update()
    {
        $regexp   = "{'(\d+\.)(\d+\.)(\d+)'}"; // version in single quote '1.0.0', '2.1.3', '3.1.22' etc
        $fileName = WP_PLUGIN_DIR.'/woocommerce/includes/class-wc-install.php';

        if (file_exists($fileName)) {
            $fileContent = file_get_contents($fileName);
            preg_match_all($regexp, $fileContent, $matches);

            if (!empty($matches[0])) {
                $latestUpdate = trim(end($matches[0]), "'");
                return $latestUpdate;
            }
        }
        return null;
    }

    public function upgrade_themes($themes = false)
    {
        if (!$themes || empty($themes)) {
            return array(
                'error' => 'No theme files for upgrade.',
            );
        }

        if (!function_exists('wp_update_themes')) {
            include_once ABSPATH.'wp-includes/update.php';
        }

        if (class_exists('Theme_Upgrader')) {
            /** @handled class */
            $upgrader = new Theme_Upgrader(mwp_container()->getUpdaterSkin());
            $result   = $upgrader->bulk_upgrade($themes);

            $return = array();
            if (!empty($result)) {
                foreach ($result as $theme_tmp => $theme_info) {
                    if (is_wp_error($theme_info) || empty($theme_info)) {
                        $return[$theme_tmp] = $this->mmb_get_error($theme_info);
                        continue;
                    }

                    $return[$theme_tmp] = 1;
                }

                return array(
                    'upgraded' => $return,
                );
            } else {
                return array(
                    'error' => 'Upgrade failed.',
                );
            }
        } else {
            return array(
                'error' => 'WordPress update required first',
            );
        }
    }

    public function upgrade_translations()
    {
        include_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';

        if (class_exists('Language_Pack_Upgrader')) {
            /** @handled class */
            $upgrader = new Language_Pack_Upgrader(mwp_container()->getUpdaterSkin());
            $result   = $upgrader->bulk_upgrade();

            if (!empty($result)) {
                $return = 1;

                if (is_array($result)) {
                    foreach ($result as $translate_tmp => $translate_info) {
                        if (is_wp_error($translate_info) || empty($translate_info)) {
                            $return = $this->mmb_get_error($translate_info);
                            break;
                        }
                    }
                }

                return array('upgraded' => $return);
            } else {
                return array(
                    'error' => 'Upgrade failed.',
                );
            }
        } else {
            return array(
                'error' => 'WordPress update required first',
            );
        }
    }

    public function get_upgradable_plugins()
    {
        $current = $this->mmb_get_transient('update_plugins');

        $upgradable_plugins = array();
        if (!empty($current->response)) {
            if (!function_exists('get_plugin_data')) {
                include_once ABSPATH.'wp-admin/includes/plugin.php';
            }
            foreach ($current->response as $plugin_path => $plugin_data) {
                $data = get_plugin_data(WP_PLUGIN_DIR.'/'.$plugin_path, false, false);

                if (strlen($data['Name']) > 0 && strlen($data['Version']) > 0) {
                    $current->response[$plugin_path]->name        = $data['Name'];
                    $current->response[$plugin_path]->old_version = $data['Version'];
                    $current->response[$plugin_path]->file        = $plugin_path;
                    unset($current->response[$plugin_path]->upgrade_notice);
                    $upgradable_plugins[] = $current->response[$plugin_path];
                }
            }

            return $upgradable_plugins;
        } else {
            return array();
        }
    }

    public function get_upgradable_themes()
    {
        if (function_exists('wp_get_themes')) {
            $all_themes     = wp_get_themes();
            $upgrade_themes = array();

            $current = $this->mmb_get_transient('update_themes');

            if (empty($current->response)) {
                return $upgrade_themes;
            }

            foreach ((array)$all_themes as $theme_template => $theme_data) {
                foreach ($current->response as $current_themes => $theme) {
                    if ($theme_data->Stylesheet !== $current_themes) {
                        continue;
                    }

                    if (strlen($theme_data->Name) === 0 || strlen($theme_data->Version) === 0) {
                        continue;
                    }

                    $current->response[$current_themes]['name']        = $theme_data->Name;
                    $current->response[$current_themes]['old_version'] = $theme_data->Version;
                    $current->response[$current_themes]['theme_tmp']   = $theme_data->Stylesheet;

                    $upgrade_themes[] = $current->response[$current_themes];
                }
            }
        } else {
            $all_themes = get_themes();

            $upgrade_themes = array();

            $current = $this->mmb_get_transient('update_themes');
            if (!empty($current->response)) {
                foreach ((array)$all_themes as $theme_template => $theme_data) {
                    if (isset($theme_data['Parent Theme']) && !empty($theme_data['Parent Theme'])) {
                        continue;
                    }

                    if (isset($theme_data['Name']) && in_array($theme_data['Name'], $filter)) {
                        continue;
                    }

                    foreach ($current->response as $current_themes => $theme) {
                        if ($theme_data['Template'] == $current_themes) {
                            if (strlen($theme_data['Name']) > 0 && strlen($theme_data['Version']) > 0) {
                                $current->response[$current_themes]['name']        = $theme_data['Name'];
                                $current->response[$current_themes]['old_version'] = $theme_data['Version'];
                                $current->response[$current_themes]['theme_tmp']   = $theme_data['Template'];
                                $upgrade_themes[]                                  = $current->response[$current_themes];
                            }
                        }
                    }
                }
            }
        }

        return $upgrade_themes;
    }

    public function get_upgradable_translations()
    {
        $updates = array(
            'core'    => array(),
            'plugins' => array(),
            'themes'  => array(),
        );

        $transients = array('update_core' => 'core', 'update_plugins' => 'plugins', 'update_themes' => 'themes');

        foreach ($transients as $transient => $type) {
            $transient = get_site_transient($transient);

            if (empty($transient->translations)) {
                continue;
            }

            foreach ($transient->translations as $translation) {
                $updates[$type][] = (object)$translation;
            }
        }

        return $updates;
    }

    public function edit($args)
    {
        extract($args);
        $return = array();
        if ($type == 'plugins') {
            $return['plugins'] = $this->edit_plugins($args);
        } elseif ($type == 'themes') {
            $return['themes'] = $this->edit_themes($args);
        }

        return $return;
    }

    public function edit_plugins($args)
    {
        extract($args);
        $return = array();
        foreach ($items as $item) {
            switch ($items_edit_action) {
                case 'activate':
                    $result = activate_plugin($item['path'], '', $item['networkWide']);
                    break;
                case 'deactivate':
                    $result = deactivate_plugins(
                        array(
                            $item['path'],
                        ),
                        false,
                        $item['networkWide']
                    );
                    break;
                case 'delete':
                    $result = delete_plugins(
                        array(
                            $item['path'],
                        )
                    );
                    break;
                default:
                    break;
            }

            if (is_wp_error($result)) {
                $result = array(
                    'error' => $result->get_error_message(),
                );
            } elseif ($result === false) {
                $result = array(
                    'error' => "Failed to perform action.",
                );
            } else {
                $result = "OK";
            }
            $return[$item['name']] = $result;
        }

        return $return;
    }

    public function edit_themes($args)
    {
        extract($args);
        $return = array();
        foreach ($items as $item) {
            switch ($items_edit_action) {
                case 'activate':
                    switch_theme($item['path'], $item['stylesheet']);
                    break;
                case 'delete':
                    $result = delete_theme($item['stylesheet']);
                    break;
                default:
                    break;
            }

            if (is_wp_error($result)) {
                $result = array(
                    'error' => $result->get_error_message(),
                );
            } elseif ($result === false) {
                $result = array(
                    'error' => "Failed to perform action.",
                );
            } else {
                $result = "OK";
            }
            $return[$item['name']] = $result;
        }

        return $return;
    }
}
