<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Updater_AutoUpdateManager
{

    private $context;

    private $pluginList;

    private $themeList;

    public function __construct(MWP_WordPress_Context $context)
    {
        $this->context = $context;
    }

    /**
     * @param string $pluginSlug Ie. 'worker/init.php';
     *
     * @return bool
     */
    public function isEnabledForPlugin($pluginSlug)
    {
        return in_array($pluginSlug, $this->getPluginList());
    }

    /**
     * @param string $themeName Ie. 'Twenty Fourteen'.
     *
     * @return bool
     */
    public function isEnabledForTheme($themeName)
    {
        return in_array($themeName, $this->getThemeList());
    }

    private function getPluginList()
    {
        if ($this->pluginList === null) {
            $this->pluginList = $this->context->optionGet('mwp_active_autoupdate_plugins', array(), null, false);
        }

        return $this->pluginList;
    }

    private function getThemeList()
    {
        if ($this->themeList === null) {
            $this->themeList = $this->context->optionGet('mwp_active_autoupdate_themes', array(), null, false);
        }

        return $this->themeList;
    }
}
