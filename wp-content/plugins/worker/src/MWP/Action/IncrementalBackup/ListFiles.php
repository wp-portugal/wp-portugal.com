<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_IncrementalBackup_ListFiles extends MWP_Action_IncrementalBackup_AbstractFiles
{

    /** @var Symfony_Filesystem_Filesystem */
    protected $fileSystem;

    public function __construct()
    {
        parent::__construct();
        $this->fileSystem = new Symfony_Filesystem_Filesystem();
    }

    /**
     * Return a list of all files
     *
     * @param array              $params
     * @param MWP_Worker_Request $request
     *
     * @return array
     */
    public function queryFiles(array $params = array(), MWP_Worker_Request $request)
    {
        if (isset($params['query']) && is_array($params['query'])) {
            $files = $this->getFilesInfo($params['query']);
        } else {
            try {
                $files = $this->listFiles(ABSPATH, true);
            } catch (UnexpectedValueException $e) {
                $files = $this->listFilesWindowsPermissionsFix(ABSPATH, true);
            }
        }

        return $this->createResult(array('files' => $files));
    }

    /**
     * @param array              $params
     * @param MWP_Worker_Request $request
     *
     * @return array
     */
    public function listDirectories(array $params = array(), MWP_Worker_Request $request)
    {
        $directories = $params['directories'];

        $result = array();

        foreach ($directories as $directory) {
            $relativePath = $directory['path'];
            $decodedPath  = $directory['pathEncoded'] ? $this->pathDecode($relativePath) : $relativePath;
            $recursive    = isset($directory['recursive']) ? $directory['recursive'] : false;
            $offset       = isset($directory['offset']) ? $directory['offset'] : 0;
            $limit        = isset($directory['limit']) ? $directory['limit'] : 0;

            $realPath = $this->getRealPath($decodedPath, true);
            if (!file_exists($realPath)) {
                $result[$relativePath] = false;
                continue;
            }

            try {
                $filesInDirectory = $this->listFiles($realPath, $recursive, $offset, $limit);
            } catch (UnexpectedValueException $e) {
                if ($relativePath !== ".") {
                    throw $e;
                }

                $filesInDirectory = $this->listFilesWindowsPermissionsFix($realPath, $recursive, $offset, $limit);
            }

            $result[$relativePath] = $filesInDirectory;
        }

        return $this->createResult(array('directories' => $result));
    }

    /**
     * Return a list of files in the WordPress root directory, recursively
     *
     * @param string $rootPath
     * @param bool   $recursive
     * @param int    $offset
     * @param int    $limit
     *
     * @return array
     */
    private function listFiles($rootPath, $recursive = false, $offset = 0, $limit = 0)
    {
        $result = array();

        $iterator = $this->createIterator($recursive, $rootPath);

        $i = 0;

        foreach ($iterator as $file) {
            if ($i++ < $offset) {
                continue;
            }

            if ($limit !== 0 && $i > $offset + $limit) {
                break;
            }

            /** @var SplFileInfo $file */
            $fileResult = $this->createFileResult($file);

            $result[] = $fileResult;
        }

        return $result;
    }

    /**
     * This is a special case of listFiles function, with the purpose of bypassing the
     * Windows permissions issue when the php user does not have permission to list
     * the parent directory of "."
     *
     * See https://bugs.php.net/bug.php?id=43817 for the details of the issue.
     *
     * @param string $rootPath
     * @param bool   $recursive
     * @param int    $offset
     * @param int    $limit
     *
     * @return array
     */
    private function listFilesWindowsPermissionsFix($rootPath, $recursive = false, $offset = 0, $limit = 0)
    {
        $result = array();

        $iterator = $this->createIterator($recursive, $rootPath . DIRECTORY_SEPARATOR . 'wp-admin' . DIRECTORY_SEPARATOR . '..');

        $i = 0;

        foreach ($iterator as $file) {
            if ($i++ < $offset) {
                continue;
            }

            if ($limit !== 0 && $i > $offset + $limit) {
                break;
            }

            /** @var SplFileInfo $file */
            $fileResult = $this->createFileResult($file);
            $fileResult['path'] = str_replace("wp-admin/../", "", $fileResult['path']);

            $result[] = $fileResult;
        }

        return $result;
    }

    /**
     * Get a list of file stats for given $files
     *
     * @param array $files
     *
     * @return array
     */
    protected function getFilesInfo(array $files)
    {
        $result = array();

        foreach ($files as $file) {
            $relativePath = $file['path'];
            $decodedPath  = $file['pathEncoded'] ? $this->pathDecode($relativePath) : $relativePath;
            $realPath     = $this->getRealPath($decodedPath, true);

            if (!file_exists($realPath)) {
                $result[] = array(
                    'path'   => $relativePath,
                    'exists' => false,
                );
                continue;
            }

            $file     = new SplFileInfo($realPath);
            $result[] = $this->createFileResult($file);
        }

        return $result;
    }

    /**
     * @param string $realPath
     * @param string $rootPath
     *
     * @return string
     */
    private function getRelativePath($realPath, $rootPath)
    {
        $path = $this->fileSystem->makePathRelative($realPath, $rootPath);

        return rtrim($path, '/\\');
    }

    /**
     * @param SplFileInfo $file
     *
     * @return array
     */
    private function createFileResult(SplFileInfo $file)
    {
        $fileResult = array(
            'path'        => $this->replaceWindowsPath($this->getRelativePath($file->getPathname(), ABSPATH)),
            'pathEncoded' => false,
            'isLink'      => false,
            'exists'      => false,
            'isDirectory' => false,
            'owner'       => 0,
            'group'       => 0,
            'permissions' => 0,
        );
        if (!seems_utf8($fileResult['path'])) {
            $fileResult['path']        = $this->pathEncode($fileResult['path']);
            $fileResult['pathEncoded'] = true;
        }
        try {
            $fileResult['isLink']      = $file->isLink(); // need to be first
            $fileResult['size']        = $file->getSize();
            $fileResult['isDirectory'] = $file->isDir();
            $fileResult['owner']       = $file->getOwner();
            $fileResult['group']       = $file->getGroup();
            $fileResult['permissions'] = $file->getPerms();
            $fileResult['mTime']       = $file->getMTime();
            $fileResult['exists']      = true;
            if ($file->isLink()) {
                $fileResult['linkTarget'] = $file->getLinkTarget();
            };
        } catch (RuntimeException $e) {
        }

        return $fileResult;
    }

    /**
     * Create a recursive or non-recursive iterator for $path. Handles php 5.2 incompatibility.
     *
     * @param bool   $recursive
     * @param string $path
     *
     * @return Iterator
     */
    private function createIterator($recursive, $path)
    {
        if ($recursive) {
            // PHP 5.2.x does not have the SKIP_DOTS flag because it skips all dots by default
            // The behavior was changed in PHP 5.3+ and it does not skip dots without the SKIP_DOTS flag
            $php52 = version_compare(phpversion(), '5.3', '<');

            if ($php52) {
                $directory = new RecursiveDirectoryIterator($path);
            } else {
                /** @handled constant */
                $directory = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
            }

            $iterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD);
        } else {
            $directory = new Symfony_Filesystem_FilesystemIterator($path, Symfony_Filesystem_FilesystemIterator::SKIP_DOTS);
            $iterator  = new IteratorIterator($directory);
        }

        return $iterator;
    }
}
