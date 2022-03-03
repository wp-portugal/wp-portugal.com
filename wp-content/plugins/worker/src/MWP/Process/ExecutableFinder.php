<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Process_ExecutableFinder extends Symfony_Process_ExecutableFinder
{
    private $extraDirs = array();

    public function addExtraDir($dir)
    {
        $this->extraDirs[] = $dir;
    }

    public function find($name, $default = null, array $extraDirs = array())
    {
        return parent::find($name, $default, array_merge($extraDirs, $this->extraDirs));
    }
}
