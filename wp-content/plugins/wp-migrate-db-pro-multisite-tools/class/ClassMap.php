<?php

namespace DeliciousBrains\WPMDBMST;

use DeliciousBrains\WPMDBMST\CliCommand\MultisiteToolsAddonCli;

class ClassMap extends \DeliciousBrains\WPMDB\Pro\ClassMap
{

    /**
     * @var MultisiteToolsAddon
     */
    public $mst_addon;
    /**
     * @var MultisiteToolsAddonCli
     */
    public $mst_addon_cli;

    /**
     * @var MediaFilesCompat
     */
    public $media_files_compat;


    public function __construct()
    {
        parent::__construct();
        $this->media_files_compat = new MediaFilesCompat(
            $this->util,
            $this->filesystem
        );

        $this->mst_addon = new MultisiteToolsAddon(
            $this->addon,
            $this->properties,
            $this->multisite,
            $this->util,
            $this->migration_state_manager,
            $this->table,
            $this->table_helper,
            $this->form_data,
            $this->template,
            $this->profile_manager,
	        $this->dynamic_props,
            $this->filesystem,
            $this->media_files_compat
        );

        $this->mst_addon_cli = new MultisiteToolsAddonCli(
            $this->addon,
            $this->properties,
            $this->multisite,
            $this->util,
            $this->migration_state_manager,
            $this->table,
            $this->table_helper,
            $this->form_data,
            $this->template,
            $this->profile_manager,
            $this->cli,
	        $this->dynamic_props,
            $this->filesystem,
            $this->media_files_compat
        );
    }
}
