<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_IncrementalBackup_AbstractFiles extends MWP_Action_IncrementalBackup_Abstract
{

    /** @var bool */
    protected $isWindows;

    public function __construct()
    {
        $this->isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    }

    /**
     * Replace Windows \\ with / on set of files
     *
     * @param $files
     *
     * @return array
     */
    protected function replaceWindowsPaths($files)
    {
        if ($this->isWindows) {
            foreach ($files as $key => $file) {
                $files[$key]['path'] = str_replace('\\', '/', $file['path']);
            }
        }

        return $files;
    }

    /**
     * Replace Windows \\ with /
     *
     * @param $path
     *
     * @return string
     */
    protected function replaceWindowsPath($path)
    {
        if ($this->isWindows) {
            $path = str_replace('\\', '/', $path);
        }

        return $path;
    }

    /**
     * Return encoded path for user across systems
     *
     * @param $path
     *
     * @return string
     */
    protected function pathEncode($path)
    {
        $split        = explode('/', $path);
        $encodedPaths = array_map("urlencode", $split);
        $encodedPath  = implode('/', $encodedPaths);

        return $encodedPath;
    }

    /**
     * Return decoded path
     *
     * @param $path
     *
     * @return string
     */
    protected function pathDecode($path)
    {

        return urldecode($path);
    }
}
