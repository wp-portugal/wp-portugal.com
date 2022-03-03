<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_DownloadFile extends MWP_Action_Abstract
{
    const DOWNLOAD_FAILED = 12;

    public function execute(array $params)
    {
        $requestedFiles = $params['files'];

        if (count($params['files']) > 1 || is_dir($requestedFiles[0])) {
            $requestedFile = $this->archiveFiles($params['files']);
        } else {
            $requestedFile = $requestedFiles[0];
        }

        $fp = fopen($requestedFile, "r");
        if (!$fp) {
            return array('message' => self::DOWNLOAD_FAILED);
        }

        $result = new MWP_FileManager_Model_DownloadFilesResult();
        $file   = new MWP_FileManager_Model_Files();
        $file->setPathname($requestedFile);
        $file->setStream(MWP_Stream_Stream::factory($fp));
        $result->addFile($file);

        return $result;
    }

    private function archiveFiles($files)
    {
        $filePath = WP_CONTENT_DIR."/mwp-download/";
        if (!file($filePath)) {
            mkdir($filePath);
            $indexPHP = fopen($filePath."index.php", 'w+');
            fwrite($indexPHP, "<?php \n\n // Silence is golden. \n");
            fclose($indexPHP);
        }

        $randomString = mwp_generate_uuid4();

        $zipName = $filePath.$randomString.".zip";
        if (!class_exists('ZipArchive')) {
            $escapedFiles = array();
            foreach ($files as $file) {
                $escapedFiles[] = escapeshellarg($file);
            }

            exec('zip -r ' . $zipName . ' ' . join(' ', $escapedFiles), $output, $exitCode);
            return $zipName;
        }

        /** @handled class */
        $zip     = new ZipArchive();

        /** @handled static */
        $zip->open($zipName, ZipArchive::CREATE);

        foreach ($files as $filePath) {
            if (!is_dir($filePath)) {
                $zip->addFile($filePath);
                continue;
            }

            $filesFromDir = $this->getFilesRecursive($filePath);
            foreach ($filesFromDir as $file) {
                if (is_dir($file)) {
                    continue;
                }
                $zip->addFile($file->getRealPath(), $file->getPath()."/".$file->getFilename());
            }
        }
        $zip->close();
        return $zipName;
    }

    private function getFilesRecursive($path)
    {
        return new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::LEAVES_ONLY);
    }
}
