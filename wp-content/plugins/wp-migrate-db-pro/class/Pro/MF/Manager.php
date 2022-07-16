<?php

namespace DeliciousBrains\WPMDB\Pro\MF;

use DeliciousBrains\WPMDB\Pro\Addon\AddonManagerInterface;
use DeliciousBrains\WPMDB\Pro\MF\CliCommand\MediaFilesCli;
use DeliciousBrains\WPMDB\WPMDBDI;

class Manager implements AddonManagerInterface
{
    public function register()
    {
        global $wpmdbpro_media_files;

        if ( ! is_null($wpmdbpro_media_files) ) {
            return $wpmdbpro_media_files;
        }

        $container = WPMDBDI::getInstance();
        $container->get(MediaFilesAddon::class)->register();
        $container->get(MediaFilesLocal::class)->register();
        $container->get(MediaFilesRemote::class)->register();
        $container->get(MediaFilesCli::class)->register();

        add_filter('wpmdb_addon_registered_mf', '__return_true');

        return $container->get(MediaFilesAddon::class);
    }


    public function get_license_response_key()
    {
        return 'wp-migrate-db-pro-media-files';
    }
}
