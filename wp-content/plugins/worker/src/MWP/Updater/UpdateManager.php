<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Updater_UpdateManager
{

    private $context;

    private $coreInfo;

    private $pluginInfo;

    private $themeInfo;

    public function __construct(MWP_WordPress_Context $context)
    {
        $this->context = $context;
        $this->reload();
    }

    /**
     * @return int|null UNIX timestamp of last update check.
     */
    public function getLastChecked()
    {
        if (empty($this->pluginInfo->last_checked)) {
            return null;
        }

        return $this->pluginInfo->last_checked;
    }

    /**
     * @param string $pluginBasename Ie. 'worker/init.php'
     *
     * @return MWP_Updater_PluginUpdate|null
     */
    public function getPluginUpdate($pluginBasename)
    {
        if (empty($this->pluginInfo->response[$pluginBasename])) {
            return null;
        }

        $info = $this->pluginInfo->response[$pluginBasename];

        $update           = new MWP_Updater_PluginUpdate();
        $update->id       = $info->id;
        $update->version  = $info->new_version;
        $update->basename = $pluginBasename;
        $update->url      = $info->url;
        $update->package  = $info->package;
        $update->slug     = $info->plugin;

        return $update;
    }

    /**
     * @param string $themeSlug Ie. 'twentyeleven'.
     *
     * @return MWP_Updater_ThemeUpdate|null
     */
    public function getThemeUpdate($themeSlug)
    {
        if (empty($this->themeInfo->response[$themeSlug])) {
            return null;
        }

        $info = $this->themeInfo->response[$themeSlug];

        $update          = new MWP_Updater_ThemeUpdate();
        $update->slug    = $info['theme']; // === $themeSlug
        $update->version = $info['new_version'];
        $update->url     = $info['url'];
        $update->package = $info['package'];

        return $update;
    }

    /**
     * @return MWP_Updater_PluginUpdate[]
     */
    public function getPluginUpdates()
    {
        if (empty($this->pluginInfo->response)) {
            return array();
        }

        $updates = array();
        foreach (array_keys($this->pluginInfo->response) as $basename) {
            $updates[] = $this->getPluginUpdate($basename);
        }

        return $updates;
    }

    /**
     * @return MWP_Updater_ThemeUpdate[]
     */
    public function getThemeUpdates()
    {
        if (empty($this->themeInfo->response)) {
            return array();
        }

        $updates = array();
        foreach (array_keys($this->themeInfo->response) as $slug) {
            $updates[] = $this->getThemeUpdate($slug);
        }

        return $updates;
    }

    /**
     * @return MWP_Updater_CoreUpdate[]
     *
     * @see core_upgrade_preamble
     */
    public function getCoreUpdates()
    {
        if (empty($this->coreInfo->updates)) {
            return array();
        }

        $firstUpdate = $this->coreInfo->updates[0];

        if (!isset($firstUpdate->response) || 'latest' === $firstUpdate->response) {
            return array();
        }

        $locale       = $this->context->getLocale();
        $latestUpdate = null;

        foreach ($this->coreInfo->updates as $update) {
            if ($update->response === 'upgrade' && $update->locale === $locale) {
                $latestUpdate = $update;
            }
        }

        $updates = array();

        if ($latestUpdate !== null) {
            $showUpdate               = new MWP_Updater_CoreUpdate();
            $showUpdate->locale       = $latestUpdate->locale;
            $showUpdate->type         = $latestUpdate->response;
            $showUpdate->version      = $latestUpdate->version;
            $showUpdate->phpVersion   = $latestUpdate->php_version;
            $showUpdate->mysqlVersion = $latestUpdate->mysql_version;

            $updates[] = $showUpdate;
        }

        return $updates;
    }

    private function reload()
    {
        $this->coreInfo   = $this->context->transientGet('update_core');
        $this->pluginInfo = $this->context->transientGet('update_plugins');
        $this->themeInfo  = $this->context->transientGet('update_themes');
    }
}
