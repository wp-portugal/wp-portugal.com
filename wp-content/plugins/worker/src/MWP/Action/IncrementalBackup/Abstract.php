<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_IncrementalBackup_Abstract extends MWP_Action_Abstract
{
    /**
     * @param array $result
     *
     * @return array
     */
    protected function createResult(array $result)
    {
        return array(
            'result' => $result,
            'server' => $this->getServerStatistics()->toArray(),
        );
    }

    /**
     * Get file real path given a path relative to WordPress root.
     *
     * @param string $relativePath
     *
     * @param bool   $virtual Don't do real filesystem touch, simulate instead
     *
     * @return string
     */
    protected function getRealPath($relativePath, $virtual = false)
    {
        if ($virtual) {
            return $this->virtualGetAbsolutePath(untrailingslashit(ABSPATH).'/'.$relativePath);
        }

        return realpath(untrailingslashit(ABSPATH).'/'.$relativePath);
    }

    /**
     * @return MWP_IncrementalBackup_Model_ServerStatistics
     */
    private function getServerStatistics()
    {
        return MWP_IncrementalBackup_Model_ServerStatistics::factory();
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private function virtualGetAbsolutePath($path)
    {
        $originalPath = $path;

        $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

        if ($isWindows) {
            $path = str_replace('\\', '/', $path);
        }

        $parts        = array_filter(explode('/', $path), 'strlen');
        $absolutes    = array();
        foreach ($parts as $part) {
            if ('.' == $part) {
                continue;
            }
            if ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }

        if (strpos($originalPath, '\\\\') === 0) { // NAS mount
            return '//'.implode(DIRECTORY_SEPARATOR, $absolutes);
        } elseif (strpos($path, '/') === 0) {
            return '/'.implode(DIRECTORY_SEPARATOR, $absolutes);
        } else {
            return implode(DIRECTORY_SEPARATOR, $absolutes);
        }
    }

}
