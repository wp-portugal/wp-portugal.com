<?php

namespace DeliciousBrains\WPMDB\Pro\Addon;

use DeliciousBrains\WPMDB\Pro\License;
use DeliciousBrains\WPMDB\Common\Util\Util;

class AddonsFacade
{
    private $addons_schema_option   = 'wp_migrate_addon_schema';
    private $current_schema_version = 1;
    const LEGACY_ADDONS = [
        'wp-migrate-db-pro-media-files/wp-migrate-db-pro-media-files.php',
        'wp-migrate-db-pro-cli/wp-migrate-db-pro-cli.php',
        'wp-migrate-db-pro-multisite-tools/wp-migrate-db-pro-multisite-tools.php',
        'wp-migrate-db-pro-theme-plugin-files/wp-migrate-db-pro-theme-plugin-files.php',
    ];

    /**
     * @var AddonManagerInterface[]
     */
    private $addons;

    /**
     * @var License
     */
    private $license;

    /**
     * @var bool
     */
    private static $initialized = false;



    /**
     * @param License $license
     * @param array $addons
     */
    public function __construct(License $license, array $addons = [])
    {
        $this->addons = $addons;
        $this->license = $license;

        if(false === self::$initialized) {
            add_action('activate_plugin', [$this, 'prevent_legacy_addon_activation']);
            add_action('admin_notices', [$this, 'legacy_addon_notice']);
            add_action('plugins_loaded', [$this, 'initialize_addons'], PHP_INT_MAX);
            add_action('plugins_loaded', [$this, 'upgrade_routine'], PHP_INT_MAX);

            if (false === get_site_transient('wpmdb_disabled_legacy_addons')) {
                add_action('plugins_loaded', [$this, 'disable_legacy_addons'], PHP_INT_MAX);
                set_site_transient('wpmdb_disabled_legacy_addons', true);
            }

            self::$initialized = true;
        }

    }


    /**
     * Initializes registered addons
     *
     * @return void
     */
    public function initialize_addons()
    {
        $addons_list = $this->license->get_available_addons_list(get_current_user_id());
        if (false === $addons_list) {
            $this->license->check_license_status();
            $addons_list = $this->license->get_available_addons_list(get_current_user_id());
        }

        if ( is_array($addons_list)) {
            foreach ($this->addons as $addon) {
                if (array_key_exists($addon->get_license_response_key(), $addons_list)) {
                    $addon->register();
                }
            }
        }
    }


    /**
     * Deactivates legacy addons on upgrade
     *
     * @return void
     */
    public static function disable_legacy_addons() {
        Util::disable_legacy_addons();
    }

    /**
     * Prevents legacy addons from being activated
     *
     * @return void
     */
    public function prevent_legacy_addon_activation($plugin) {
        if (in_array($plugin, self::LEGACY_ADDONS)) {
            $redirect = self_admin_url('plugins.php?legacyaddon=1');
            wp_redirect($redirect);
            exit;
        }
    }

    /**
     * Notice when trying to activate addon
     *
     * @return void
     */
    public function legacy_addon_notice() {
        if (isset($_GET['legacyaddon'])) {
            $message = __('Legacy addons cannot be activated alongside WP Migrate version 2.3.0 or above. These features have been moved to WP Migrate.', 'wp-migrate-db');
            echo '<div class="updated" style="border-left: 4px solid #ffba00;"><p>'.$message.'</p></div>';
        }
    }

    /**
     * Executes upgrade routines for the addons
     *
     * @return void
     */
    public function upgrade_routine() {
        $addons_schema_version = get_option( $this->addons_schema_option, 0 );
        if ( (int)$addons_schema_version !== $this->current_schema_version ) {
            $this->license->check_licence( $this->license->get_licence_key() );
            update_option( $this->addons_schema_option, $this->current_schema_version );
        }
    }
}
