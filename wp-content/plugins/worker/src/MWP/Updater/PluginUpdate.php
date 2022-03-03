<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Updater_PluginUpdate
{

    /**
     * Numeric value, but let's play it safe.
     *
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $slug;

    /**
     * @var string
     */
    public $basename;

    /**
     * @var string
     */
    public $version;

    /**
     * @var string
     */
    public $url;

    /**
     * @var string
     */
    public $package;
}
