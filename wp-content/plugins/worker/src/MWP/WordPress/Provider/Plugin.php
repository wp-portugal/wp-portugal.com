<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_WordPress_Provider_Plugin implements MWP_WordPress_Provider_Interface
{

    const STATUS_ACTIVE_NETWORK = 'active-network';

    const STATUS_ACTIVE = 'active';

    const STATUS_MUST_USE = 'must-use';

    const STATUS_INACTIVE = 'inactive';

    const STATUS_DROP_IN = 'drop-in';

    private $context;

    public function __construct(MWP_WordPress_Context $context)
    {
        $this->context = $context;
    }

    public function fetch(array $options = array())
    {
        $regularPlugins = $this->context->getPlugins();
        $mustUsePlugins = $this->context->getMustUsePlugins();
        $dropInPlugins  = $this->context->getDropInPlugins();
        $plugins        = array();

        $pluginInfo = array(
            'name'        => 'Name',
            'pluginUri'   => 'PluginURI',
            'version'     => 'Version',
            'description' => 'Description',
            'author'      => 'Author',
            'authorUri'   => 'AuthorURI',
        );

        if (empty($options['fetchDescription'])) {
            unset($pluginInfo['description']);
        }

        foreach ($regularPlugins as $basename => $details) {
            $plugin = array(
                // This is the plugin identifier; ie. "worker/init.php".
                'basename'    => $basename,
                // Plugin's own directory name (if it exists), or filename minus ".php" extension Ie. "worker".
                'slug'        => $this->getSlugFromBasename($basename),
                // 'Network' property can have a valid value or 'false' and is always present.
                // It signifies whether the plugin can only be activated network wide.
                'networkOnly' => $details['Network'],
            );

            foreach ($pluginInfo as $property => $info) {
                if (empty($details[$info])) {
                    $plugin[$property] = null;
                    continue;
                }

                $plugin[$property] = $this->context->seemsUtf8($details[$info]) ? $details[$info] : utf8_encode($details[$info]);
            }

            $plugin['status'] = $this->getPluginStatus($basename);

            $plugins[] = $plugin;
        }

        foreach ($mustUsePlugins as $basename => $details) {
            $plugin = array(
                'basename' => $basename,
                'slug'     => $this->getSlugFromBasename($basename),
            );

            foreach ($pluginInfo as $property => $info) {
                $plugin[$property] = !empty($details[$info]) ? $details[$info] : null;
            }

            $plugin['status'] = self::STATUS_MUST_USE;
            $plugins[]        = $plugin;
        }

        foreach ($dropInPlugins as $basename => $details) {
            $plugin = array(
                'basename' => $basename,
                'type'     => self::STATUS_DROP_IN,
            );

            foreach ($pluginInfo as $property => $info) {
                $plugin[$property] = !empty($details[$info]) ? $details[$info] : null;
            }

            $plugins[] = $plugin;
        }

        return $plugins;
    }

    private function getSlugFromBasename($file)
    {
        if (false === strpos($file, '/')) {
            $slug = basename($file, '.php');
        } else {
            $slug = dirname($file);
        }

        return $slug;
    }

    private function getPluginStatus($file)
    {
        if ($this->context->isPluginActiveForNetwork($file)) {
            return self::STATUS_ACTIVE_NETWORK;
        } elseif ($this->context->isPluginActive($file)) {
            return self::STATUS_ACTIVE;
        }

        return self::STATUS_INACTIVE;
    }
}
