<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_IncrementalBackup_Stats extends MWP_Action_IncrementalBackup_AbstractFiles
{

    public function __construct()
    {
        parent::__construct();
    }

    public function execute(array $params = array())
    {

        $wpdb     = $this->container->getWordPressContext()->getDb();
        $getState = new MWP_Action_GetState();
        $getState->setContainer($this->container);
        $themesKey                         = MWP_Action_GetState::THEMES;
        $themes                            = $getState->execute(array($themesKey => array('type' => $themesKey, 'options' => array())));
        $pluginsKey                        = MWP_Action_GetState::PLUGINS;
        $plugins                           = $getState->execute(array($pluginsKey => array('type' => $pluginsKey, 'options' => array())));
        $activePlugins                     = array_filter($plugins[$pluginsKey]['result'], array($this, 'activePluginFilter'));
        $statistics                        = array();
        $statistics['themes']              = count($themes[$themesKey]['result']);
        $statistics['themes_list']         = $themes[$themesKey]['result'];
        $statistics['plugins']             = count($plugins[$pluginsKey]['result']);
        $statistics['plugins_list']        = $plugins[$pluginsKey]['result'];
        $statistics['active_plugins']      = count($activePlugins);
        $statistics['posts']               = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post'");
        $statistics['published_posts']     = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'");
        $statistics['pages']               = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='page'");
        $statistics['published_pages']     = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='page' AND post_status='publish'");
        $statistics['uploads']             = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment'");
        $statistics['comments']            = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} c INNER JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID WHERE p.post_status = 'publish'");
        $statistics['comments_approved']   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} c INNER JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID WHERE comment_approved = 1 AND p.post_status = 'publish'");
        $latestPost                        = $wpdb->get_row("SELECT * FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish' ORDER BY ID DESC LIMIT 1");
        $statistics['latest_post_title']   = isset($latestPost->post_title) ? $latestPost->post_title : '';
        $statistics['latest_post_title']   = seems_utf8($statistics['latest_post_title']) ? $statistics['latest_post_title'] : utf8_encode($statistics['latest_post_title']);
        $statistics['latest_post_url']     = sprintf("%s?p=%d", get_site_url(), $latestPost->ID);
        $statistics['wp_version']          = $this->container->getWordPressContext()->getVersion();
        $statistics['worker_version']      = $this->container->getParameter('worker_version');
        $currentTheme                      = $this->container->getWordPressContext()->getCurrentTheme();
        $statistics['active_theme']        = $currentTheme['Name'].' v'.$currentTheme['Version'];
        $statistics['platform']            = strtoupper(substr(PHP_OS, 0, 3));
        $statistics['external_config']     = !@file_exists(ABSPATH.'wp-config.php') && @file_exists(dirname(ABSPATH).'/wp-config.php') && !@file_exists(dirname(ABSPATH).'/wp-settings.php');
        $statistics['blog_list']           = $this->getBlogs();
        $statistics['db_prefix']           = $wpdb->prefix;
        $statistics['mu_upload_blogs_dir'] = $this->getOldMuBlogsUploadDir();

        if (!empty($params['file_count'])) {
            $paths    = !empty($params['file_paths']) ? $params['file_paths'] : array(ABSPATH);
            $pathSize = array();
            foreach ($paths as $path) {
                $pathSize[$this->replaceWindowsPath($path)] = $this->getFileCount($path);
            }
            $statistics['file_count'] = $pathSize;
        }

        return $this->createResult(array('statistic' => $statistics));
    }

    public function activePluginFilter($plugin)
    {
        return !empty($plugin['status']) && $plugin['status'] === 'active';
    }

    /**
     * @param $path
     *
     * @return int
     */
    private function getFileCount($path)
    {
        $size   = 0;
        $ignore = array('.', '..');
        $files  = @scandir($path);
        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if (in_array($file, $ignore)) {
                continue;
            }
            if (is_dir(rtrim($path, '/').'/'.$file) && !is_link(rtrim($path, '/').'/'.$file)) {
                $size += $this->getFileCount(rtrim($path, '/').'/'.$file);
            } else {
                $size++;
            }
        }

        return $size;
    }

    /**
     * @return array
     */
    private function getBlogs()
    {
        $limit = 10000;
        $blogs = array();

        if (function_exists('get_sites') && class_exists('WP_Site_Query')) { // WP 4.6+
            /** @handled function */
            $sites = get_sites(array('number' => $limit));
            foreach ($sites as $site) {
                $row            = array();
                $row['blog_id'] = $site->blog_id;
                $row['domain']  = $site->domain;
                $row['path']    = $site->path;
                $blogs[]        = $row;
            }
        } elseif (function_exists('wp_get_sites')) { //WP 3.7 - 4.6
            /** @handled function */
            $sites = wp_get_sites(array('limit' => $limit));
            foreach ($sites as $site) {
                $row = array();

                $row['blog_id'] = $site['blog_id'];
                $row['domain']  = $site['domain'];
                $row['path']    = $site['path'];
                $blogs[]        = $row;
            }
        } elseif (function_exists('get_blog_list')) { // WP < 3.7
            /** @handled function */
            $sites = get_blog_list(0, 'all');
            foreach ($sites as $site) {
                $row = array();

                $row['blog_id'] = $site['blog_id'];
                $row['domain']  = $site['domain'];
                $row['path']    = $site['path'];
                $blogs[]        = $row;
            }
        }

        return $blogs;
    }

    /**
     * @return string
     */
    private function getOldMuBlogsUploadDir()
    {
        if (defined('UPLOADBLOGSDIR')) {
            return UPLOADBLOGSDIR;
        }

        return '';
    }
}
