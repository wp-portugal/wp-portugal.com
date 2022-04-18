<?php

namespace DeliciousBrains\WPMDB\Pro\Compatibility\Layers\Addons;

class Addons {

    public static function substitute_classes(&$classes) {
        if ( ! isset($classes[\DeliciousBrains\WPMDB\Pro\MST\MultisiteToolsAddon::class])) {
            return;
        }

        $classes[\DeliciousBrains\WPMDBMST\MultisiteToolsAddon::class] = $classes[\DeliciousBrains\WPMDB\Pro\MST\MultisiteToolsAddon::class];
        $classes[\DeliciousBrains\WPMDBMST\CliCommand\MultisiteToolsAddonCli::class] = $classes[\DeliciousBrains\WPMDB\Pro\MST\CliCommand\MultisiteToolsAddonCli::class];
        $classes[\DeliciousBrains\WPMDBCli\Cli::class] = $classes[\DeliciousBrains\WPMDB\Pro\Cli\Extra\Cli::class];
        $classes[\DeliciousBrains\WPMDBCli\CliAddon::class] = $classes[\DeliciousBrains\WPMDB\Pro\Cli\Extra\CliAddon::class];
        $classes[\DeliciousBrains\WPMDBMF\MediaFilesAddon::class] = $classes[\DeliciousBrains\WPMDB\Pro\MF\MediaFilesAddon::class];
        $classes[\DeliciousBrains\WPMDBMF\MediaFilesRemote::class] = $classes[\DeliciousBrains\WPMDB\Pro\MF\MediaFilesRemote::class];
        $classes[\DeliciousBrains\WPMDBMF\MediaFilesLocal::class] = $classes[\DeliciousBrains\WPMDB\Pro\MF\MediaFilesLocal::class];
        $classes[\DeliciousBrains\WPMDBMF\CliCommand\MediaFilesCli::class] = $classes[\DeliciousBrains\WPMDB\Pro\MF\CliCommand\MediaFilesCli::class];
        $classes[\DeliciousBrains\WPMDBTP\ThemePluginFilesAddon::class] = $classes[\DeliciousBrains\WPMDB\Pro\TPF\ThemePluginFilesAddon::class];
        $classes[\DeliciousBrains\WPMDBTP\ThemePluginFilesLocal::class] = $classes[\DeliciousBrains\WPMDB\Pro\TPF\ThemePluginFilesLocal::class];
        $classes[\DeliciousBrains\WPMDBTP\ThemePluginFilesRemote::class] = $classes[\DeliciousBrains\WPMDB\Pro\TPF\ThemePluginFilesRemote::class];
        $classes[\DeliciousBrains\WPMDBTP\Cli\ThemePluginFilesCli::class] = $classes[\DeliciousBrains\WPMDB\Pro\TPF\Cli\ThemePluginFilesCli::class];
    }
}
