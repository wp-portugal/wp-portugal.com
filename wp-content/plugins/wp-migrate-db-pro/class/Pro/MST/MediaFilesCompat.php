<?php

namespace DeliciousBrains\WPMDB\Pro\MST;

use DeliciousBrains\WPMDB\Common\Filesystem\Filesystem;
use DeliciousBrains\WPMDB\Common\Util\Util;

class MediaFilesCompat
{

    /**
     * @var Util
     */
    private $util;
    /**
     * @var Filesystem
     */
    private $filesystem;

    public function __construct(
        Util $util,
        Filesystem $filesystem

    ) {
        $this->util       = $util;
        $this->filesystem = $filesystem;
    }

    /**
     * Given the $state_data array, check if 'mst_selected_subsite' is 1
     *
     * @param $state_data
     *
     * @return int
     */
    public function get_subsite_from_state_data($state_data, $target = 'source')
    {
        $is_two_multisites = 'true' === $state_data['site_details']['local']['is_multisite'] && 'true' === $state_data['site_details']['remote']['is_multisite'];
        if ('destination' === $target && $is_two_multisites ) {
            return (int)isset($state_data['mst_destination_subsite']) ? $state_data['mst_destination_subsite'] : 0;
        }
        
        return (int)isset($state_data['mst_selected_subsite']) ? $state_data['mst_selected_subsite'] : 0;
    }

    /**
     *
     * Called from the 'wpmdb_mf_destination_uploads' hook
     *
     * @param $uploads_dir
     * @param $state_data
     *
     * @return mixed
     */
    public function filter_media_uploads($uploads_dir, $state_data)
    {
        if (!is_multisite() || !$this->is_subsite_migration($state_data)) {
            return $uploads_dir;
        }

        $site_id = $this->get_subsite_from_state_data($state_data, 'destination');

        if ($site_id === 0) {
            return $uploads_dir;
        }

        $uploads_info = $this->util->uploads_info($site_id);

        if (isset($uploads_info['basedir'])) {
            return $uploads_info['basedir'];
        }

        return $uploads_dir;
    }

    /**
     *
     * Called from the 'wpmdb_mf_destination_file' hook
     *
     * @param string $file
     * @param array  $state_data
     *
     * @return string
     */
    public function filter_media_destination($file, $state_data)
    {
        $target = 'push' === $state_data['intent'] ? 'source' : 'destination';
        $site_id = $this->get_subsite_from_state_data($state_data, $target);

        if ($site_id === 0 || !$this->is_subsite_migration($state_data)) {
            return $file;
        }

        $slashed_file = $this->filesystem->slash_one_direction($file);

        $pattern = '/^\\' . DIRECTORY_SEPARATOR . 'sites\\' . DIRECTORY_SEPARATOR . $site_id . '/';

        if (false !== strpos($slashed_file, 'blogs.dir')) {
            $pattern = '/^blogs.dir\\' . DIRECTORY_SEPARATOR . $site_id.'\\' . DIRECTORY_SEPARATOR . 'files/';
        }

        $file = preg_replace($pattern, '', $slashed_file);

        return $file;
    }

    /**
     *
     * Called from the 'wpmdb_mf_local_uploads_folder' hook
     *
     * @param $path
     * @param $state_data
     *
     * @return array|mixed
     */
    public function filter_uploads_path_local($path, $state_data)
    {
        return $this->filter_uploads_path($path, $state_data, 'local');
    }

    /**
     *
     * Called from the 'wpmdb_mf_remote_uploads_folder' hook
     *
     * @param $path
     * @param $state_data
     *
     * @return array|mixed
     */
    public function filter_uploads_path_remote($path, $state_data)
    {
        return $this->filter_uploads_path($path, $state_data, 'remote');
    }

    /**
     *
     * Given $state_data and an uploads file path, determine new uploads path
     *
     * @param        $path
     * @param        $state_data
     * @param string $location
     *
     * @return array|mixed
     */
    public function filter_uploads_path($path, $state_data, $location = 'local')
    {
        $target = 'push' === $state_data['intent'] ? 'source' : 'destination';
        $blog_id = $this->get_subsite_from_state_data($state_data, $target);

        if ($blog_id <= 1  || !$this->is_subsite_migration($state_data)) {
            return $path;
        }

        $uploads = $this->util->uploads_info($blog_id);

        if (isset($uploads['basedir'])) {
            $path = $uploads['basedir'];
        }

        $path = $location === 'remote' ? (array)$path : $path;

        return $path;
    }

    /**
     *
     * Filter excludes if subsite ID is 1, we don't want to migrate all the other subsites as well
     *
     * Call from the 'wpmdb_mf_excludes' hook
     *
     * @param $excludes
     * @param $state_data
     *
     * @return array
     */
    public function filter_media_excludes($excludes, $state_data)
    {
        if (!$this->is_subsite_migration($state_data)) {
            return $excludes;
        }
        $blog_id = $this->get_subsite_from_state_data($state_data, 'source');
        
        if ($blog_id !== 1) {
            return $excludes;
        }

        $excludes[] = '**/sites/*';
        
        return $excludes;
    }

    /**
     *
     * @param $state_data
     *
     * @return bool
     */
    private function is_subsite_migration($state_data) {
        return isset($state_data['mst_select_subsite']) && '1' === $state_data['mst_select_subsite'];
    }
}
