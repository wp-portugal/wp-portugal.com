<?php

/*************************************************************
 * stats.class.php
 * Get Site Stats
 * Copyright (c) 2011 Prelovac Media
 * www.prelovac.com
 **************************************************************/
class MMB_Stats extends MMB_Core
{
    public function get_site_statistics()
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        $siteStatistics = array();
        $prefix         = $wpdb->prefix;
        $basePrefix     = $wpdb->base_prefix;

        if (!$this->mmb_multisite) {
            $siteStatistics['users'] = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$basePrefix}users");
        } else {
            $siteStatistics['users'] = count(get_users(
                array(
                    'blog_id' => $wpdb->blogid,
                )
            ));
        }

        $siteStatistics['approvedComments'] = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$prefix}comments c INNER JOIN {$prefix}posts p ON c.comment_post_ID = p.ID WHERE comment_approved = '1' AND p.post_status = 'publish'");
        $siteStatistics['activePlugins']    = count((array)get_option('active_plugins', array()));
        $siteStatistics['publishedPosts']   = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$prefix}posts WHERE post_type='post' AND post_status='publish'");
        $siteStatistics['draftPosts']       = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$prefix}posts WHERE post_type='post' AND post_status='draft'");
        $siteStatistics['publishedPages']   = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$prefix}posts WHERE post_type='page' AND post_status='publish'");
        $siteStatistics['draftPages']       = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$prefix}posts WHERE post_type='page' AND post_status='draft'");

        return $siteStatistics;
    }

    public function get_core_update()
    {
        global $wp_version;
        $update = null;
        $locale = get_locale();
        $core   = $this->mmb_get_transient('update_core');

        if (empty($core->updates)) {
            return null;
        }

        foreach ($core->updates as $availableUpdate) {
            if ($availableUpdate->locale == $locale && strtolower($availableUpdate->response) === "upgrade") {
                $update = $availableUpdate;
                break;
            }
        }

        //fallback to first
        if (!$update) {
            $update = $core->updates[0];
        }
        // WordPress can actually have an update to the same version and locale if locale has not been updated
        if ($update->response === 'development' || $update->response === 'upgrade' || version_compare($wp_version, $update->current, '<') || $locale !== $update->locale) {
            $update->current_version = $wp_version;
            return $update;
        } else {
            return null;
        }
    }

    public function get_comments($type, $limit, $trimlen = 200)
    {
        $comments = (array)get_comments('status='.$type.'&number='.$limit);
        foreach ($comments as &$comment) {
            $commented_post           = get_post($comment->comment_post_ID);
            $comment->post_title      = $commented_post->post_title;
            $comment->comment_content = $this->trim_content($comment->comment_content, $trimlen);
            $comment->comment_content = seems_utf8($comment->comment_content) ? $comment->comment_content : utf8_encode($comment->comment_content);
            unset($comment->comment_author_IP);
            unset($comment->comment_karma);
            unset($comment->comment_agent);
            unset($comment->comment_type);
            unset($comment->comment_parent);
        }
        return $comments;
    }

    public function get_posts($limit)
    {
        $userIdMap = $this->getUsersIDs();
        $list      = array();


        $posts = (array)get_posts('post_status=publish&numberposts='.$limit.'&orderby=post_date&order=desc');
        foreach ($posts as $id => $post) {
            $add                 = new stdClass();
            $add->post_permalink = get_permalink($post->ID);
            $add->ID             = $post->ID;
            $add->post_date      = $post->post_date;
            $add->post_title     = $post->post_title;
            $add->post_type      = $post->post_type;
            $add->comment_count  = (int)$post->comment_count;

            $author_name           = isset($userIdMap[$post->post_author]) ? $userIdMap[$post->post_author] : '';
            $add->post_author_name = array('author_id' => $post->post_author, 'author_name' => $author_name);

            $list[] = $add;
        }

        $posts = (array)get_pages('post_status=publish&numberposts='.$limit.'&orderby=post_date&order=desc');
        foreach ($posts as $id => $post) {
            $add                 = new stdClass();
            $add->post_permalink = get_permalink($post->ID);
            $add->post_type      = $post->post_type;
            $add->ID             = $post->ID;
            $add->post_date      = $post->post_date;
            $add->post_title     = $post->post_title;

            $author_name      = isset($userIdMap[$post->post_author]) ? $userIdMap[$post->post_author] : '';
            $add->post_author = array('author_id' => $post->post_author, 'author_name' => $author_name);

            $list[] = $add;
        }
        usort($list, array($this, 'cmp_posts_worker'));
        return array_slice($list, 0, $limit);
    }

    public function get_drafts($limit)
    {
        $list = array();

        $posts = (array)get_posts('post_status=draft&numberposts='.$limit.'&orderby=post_date&order=desc');
        foreach ($posts as $id => $post) {
            $add                 = new stdClass();
            $add->post_permalink = get_permalink($post->ID);
            $add->post_type      = $post->post_type;
            $add->ID             = $post->ID;
            $add->post_date      = $post->post_date;
            $add->post_title     = $post->post_title;

            $list[] = $add;
        }
        $posts = (array)get_pages('post_status=draft&numberposts='.$limit.'&orderby=post_date&order=desc');
        foreach ($posts as $id => $post) {
            $add                 = new stdClass();
            $add->post_permalink = get_permalink($post->ID);
            $add->ID             = $post->ID;
            $add->post_type      = $post->post_type;
            $add->post_date      = $post->post_date;
            $add->post_title     = $post->post_title;

            $list[] = $add;
        }
        usort($list, array($this, 'cmp_posts_worker'));
        return array_slice($list, 0, $limit);
    }

    public function get_scheduled($numberOfItems)
    {
        $list = array();

        $posts = (array)get_posts('post_status=future&numberposts='.$numberOfItems.'&orderby=post_date&order=desc');
        foreach ($posts as $id => $post) {
            $add                 = new stdClass();
            $add->post_permalink = get_permalink($post->ID);
            $add->ID             = $post->ID;
            $add->post_date      = $post->post_date;
            $add->post_type      = $post->post_type;
            $add->post_title     = $post->post_title;

            $list[] = $add;
        }
        $posts = (array)get_pages('post_status=future&numberposts='.$numberOfItems.'&orderby=post_date&order=desc');
        foreach ((array)$posts as $id => $post) {
            $add                 = new stdClass();
            $add->post_permalink = get_permalink($post->ID);
            $add->ID             = $post->ID;
            $add->post_type      = $post->post_type;
            $add->post_date      = $post->post_date;
            $add->post_title     = $post->post_title;

            $list[] = $add;
        }
        usort($list, array($this, 'cmp_posts_worker'));
        return array_slice($list, 0, $numberOfItems);
    }

    /**
     * @return array Map of wp_users.ID (string) => wp_users.display_name (string) where wp_users.user_status == 0.
     */
    private function getUsersIDs()
    {
        global $wpdb;
        $users_authors = array();
        $users         = $wpdb->get_results("SELECT ID as user_id, display_name FROM $wpdb->users WHERE user_status=0");

        foreach ($users as $user_key => $user_val) {
            $users_authors[$user_val->user_id] = $user_val->display_name;
        }

        return $users_authors;
    }

    public function get_updates($stats, $options = array())
    {
        if (isset($options['themes']) && $options['themes']) {
            $upgrades = $this->get_installer_instance()->get_upgradable_themes();
            if (!empty($upgrades)) {
                $stats['upgradable_themes'] = $upgrades;
            }
        }

        if (isset($options['plugins']) && $options['plugins']) {
            $upgrades = $this->get_installer_instance()->get_upgradable_plugins();
            if (!empty($upgrades)) {
                $stats['upgradable_plugins'] = $upgrades;
            }
        }

        if (isset($options['translations']) && $options['translations']) {
            $upgrades = $this->get_installer_instance()->get_upgradable_translations();
            if (!empty($upgrades)) {
                $stats['upgradable_translations'] = $upgrades;
            }
        }

        return $stats;
    }

    public function getUserList()
    {
        $filter = array(
            'user_roles'      => array(
                'administrator',
            ),
            'username'        => '',
            'username_filter' => '',
        );
        $users  = $this->get_user_instance()->get_users($filter);

        if (empty($users['users']) || !is_array($users['users'])) {
            return array();
        }

        $userList = array();
        foreach ($users['users'] as $user) {
            $userList[] = $user['user_login'];
        }

        return $userList;
    }

    private function stats_arg(array $params, $widget, $arg)
    {
        // Cleanup plugin (the only one) has the following keys that hold options: overhead, revisions, spam.
        if (isset($params['item_filter']['get_stats']['plugins']['cleanup'][$widget][$arg])) {
            return $params['item_filter']['get_stats']['plugins']['cleanup'][$widget][$arg];
        }

        // Remaining "widgets" are number-indexed.
        foreach ((array)@$params['item_filter']['get_stats'] as $key => $data) {
            if ($key !== $widget) {
                continue;
            }
            if (isset($data[1][$arg])) {
                return $data[1][$arg];
            }
            break;
        }
        return null;
    }

    private function get_expired_transients(array $transientsData)
    {
        $expiredTransients = 0;
        foreach ($transientsData as $transient) {
            $expiredTransients += $this->get_expired_transients_size($transient['name'], $transient['suffix'], $transient['timeout'], $transient['mask'], $transient['limit']);
        }
        return $expiredTransients;
    }

    private function get_expired_transients_size($name, $suffix, $timeout, $mask, $limit)
    {
        /** @var wpdb $wpdb */
        global $wpdb;

        $prefix = $wpdb->prefix;

        $timeoutName  = $name.$suffix;
        $subStrLength = strlen($timeoutName) + 1;

        $escapedTimeoutName = str_replace('_', '\_', $timeoutName);

        $selectTimeOutedTransients = <<<SQL
SELECT SUBSTR(option_name, {$subStrLength}) AS transient_name FROM {$prefix}options WHERE option_name LIKE '{$escapedTimeoutName}{$mask}' AND option_value < {$timeout} LIMIT {$limit}
SQL;

        $transientsToDelete  = $wpdb->get_col($selectTimeOutedTransients);
        $timeoutsToDelete    = array();
        $transientsTotalSize = 0;

        if (count($transientsToDelete) === 0) {
            return $transientsTotalSize;
        }

        foreach ($transientsToDelete as &$transient) {
            $timeoutsToDelete[] = "'".$timeoutName.$transient."'";
            $transient          = "'".$name.$transient."'";
        }

        $transientSizeClause = implode(',', $transientsToDelete);

        $transientsSizeQuery = <<<SQL
SELECT SUM(LENGTH(option_value)) as valueSize, SUM(LENGTH(option_name)) as nameSize  FROM {$prefix}options WHERE option_name IN ({$transientSizeClause})
SQL;
        $transientsSize      = $wpdb->get_results($transientsSizeQuery);
        $transientsTotalSize += ((int)$transientsSize[0]->nameSize);
        $transientsTotalSize += ((int)$transientsSize[0]->valueSize);

        $transientSizeClause = implode(',', $timeoutsToDelete);
        $transientsSizeQuery = <<<SQL
SELECT SUM(LENGTH(option_value)) as valueSize, SUM(LENGTH(option_name)) as nameSize FROM {$prefix}options WHERE option_name IN ({$transientSizeClause})
SQL;
        $timeoutsSize        = $wpdb->get_results($transientsSizeQuery);
        $transientsTotalSize += ((int)$timeoutsSize[0]->valueSize);
        $transientsTotalSize += ((int)$timeoutsSize[0]->nameSize);

        return $transientsTotalSize;
    }

    public function get_stats(array $params)
    {
        $revisionLimit = (int)str_replace('r_', '', (string)$this->stats_arg($params, 'revisions', 'num_to_keep'));
        if (!$revisionLimit) {
            $revisionLimit = 5;
        }
        $commentLimit = (int)$this->stats_arg($params, 'comments', 'numberposts');
        if (!$commentLimit) {
            $commentLimit = 5;
        }
        // All post limits are the same on the API side.
        $postLimit = (int)$this->stats_arg($params, 'posts', 'numberposts');
        if (!$postLimit) {
            $postLimit = 5;
        }
        $transientsParams = (array)$this->stats_arg($params, 'transients', 'expire_data');

        unset($params);
        $save = array('plugins' => array('cleanup' => array('revisions' => array('num_to_keep' => $revisionLimit))));
        // These options are only used for the revision cleanup action.
        update_option('mmb_stats_filter', $save);

        include_once ABSPATH.'wp-includes/update.php';
        include_once ABSPATH.'wp-admin/includes/update.php';

        mwp_logger()->debug('Started initializing stats');

        $stats = array(
            'upgradable_themes'       => $this->get_installer_instance()->get_upgradable_themes(),
            'upgradable_plugins'      => $this->get_installer_instance()->get_upgradable_plugins(),
            'upgradable_translations' => $this->get_installer_instance()->get_upgradable_translations(),
            'core_updates'            => $this->get_core_update(),
            'posts'                   => $this->get_posts($postLimit),
            'drafts'                  => $this->get_drafts($postLimit),
            'scheduled'               => $this->get_scheduled($postLimit),
            'hit_counter'             => get_option('user_hit_count'),
            'comments'                => array(
                'pending'  => $this->get_comments('hold', $commentLimit),
                'approved' => $this->get_comments('approve', $commentLimit),
            ),
            'site_statistics'         => $this->get_site_statistics(),
            'num_revisions'           => mmb_num_revisions($revisionLimit),
            'overhead'                => mmb_handle_overhead(false),
            'expired_transients'      => $this->get_expired_transients($transientsParams),
            'num_spam_comments'       => mmb_num_spam_comments(),
        );

        mwp_logger()->debug('Finished initializing stats');

        /** @var $wpdb wpdb */
        global $wpdb, $wp_version, $mmb_plugin_dir;

        $stats['worker_version']        = $GLOBALS['MMB_WORKER_VERSION'];
        $stats['worker_revision']       = $GLOBALS['MMB_WORKER_REVISION'];
        $stats['wordpress_version']     = $wp_version;
        $stats['wordpress_locale_pckg'] = get_locale();
        $stats['php_version']           = phpversion();
        $stats['mysql_version']         = $wpdb->db_version();
        $stats['wp_multisite']          = $this->mmb_multisite;
        $stats['network_install']       = $this->network_admin_install;

        mwp_logger()->debug('Started encrypting cookies...');

        $stats['cookies'] = $this->get_stat_cookies();

        mwp_logger()->debug('Finished encrypting cookies...');

        $absPath = trailingslashit(ABSPATH); // This will prevent 2 backslash when WP in root

        $stats['admin_usernames'] = $this->getUserList();
        $stats['site_title']      = get_bloginfo('name');
        $stats['site_tagline']    = get_bloginfo('description');
        $stats['blog_public']     = get_option('blog_public');
        $stats['timezone']        = get_option('timezone_string');
        $stats['timezone_offset'] = get_option('gmt_offset');
        $stats['server_ip']       = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : null;
        $stats['hostname']        = php_uname('n');
        $stats['db_name']         = $this->get_active_db();
        $stats['db_prefix']       = $wpdb->prefix;
        $stats['content_path']    = WP_CONTENT_DIR;
        $stats['absolute_path']   = $absPath;
        $stats['worker_path']     = $mmb_plugin_dir;
        $stats['site_home']       = get_option('home');

        $fs = new Symfony_Filesystem_Filesystem();
        if (defined('WP_CONTENT_DIR')) {
            $contentDir = WP_CONTENT_DIR;
            // This will prevent 2 backslash when WP in root
            if (substr($contentDir, 0, 2) === '//') {
                $contentDir            = substr(WP_CONTENT_DIR, 1);
                $stats['content_path'] = $contentDir;
            }

            if (substr($contentDir, 0, 1) != '/' && strpos($contentDir, ABSPATH) === false) {
                $contentDir = ABSPATH.$contentDir;
            }
            $stats['content_relative_path'] = $fs->makePathRelative($contentDir, $absPath);
        }

        if (defined('WP_PLUGIN_DIR')) {
            $pluginDir = WP_PLUGIN_DIR;
            // This will prevent 2 backslash when WP in root
            if (substr($pluginDir, 0, 2) === '//') {
                $pluginDir = substr(WP_PLUGIN_DIR, 1);
            }

            if (substr($pluginDir, 0, 1) != '/' && strpos($pluginDir, ABSPATH) === false) {
                $pluginDir = ABSPATH.$pluginDir;
            }
            $stats['plugin_relative_path'] = $fs->makePathRelative($pluginDir, $absPath);
        }

        if (defined('WPMU_PLUGIN_DIR')) {
            $muPluginDir = WPMU_PLUGIN_DIR;
            // This will prevent 2 backslash when WP in root
            if (substr($muPluginDir, 0, 2) === '//') {
                $muPluginDir = substr(WPMU_PLUGIN_DIR, 1);
            }

            if (substr($muPluginDir, 0, 1) != '/' && strpos($muPluginDir, ABSPATH) === false) {
                $muPluginDir = ABSPATH.$muPluginDir;
            }
            $stats['mu_plugin_relative_path'] = $fs->makePathRelative($muPluginDir, $absPath);
        }

        $uploadDirArray = wp_upload_dir();
        if (false === $uploadDir = realpath($uploadDirArray['basedir'])) {
            $uploadDir = $uploadDirArray['basedir'];
        }

        $stats['uploads_relative_path'] = $fs->makePathRelative($uploadDir, $absPath);

        $stats['writable']  = $this->is_server_writable();
        $stats['fs_method'] = !$this->check_if_pantheon() ? get_filesystem_method() : '';

        $mmode = get_option('mwp_maintenace_mode');

        if (!empty($mmode) && isset($mmode['active']) && $mmode['active'] == true) {
            $stats['maintenance'] = true;
        }

        if ($this->mmb_multisite) {
            $stats = array_merge($stats, $this->get_multisite_stats($stats));
        }

        return $stats;
    }

    public function get_multisite_stats()
    {
        /** @var $wpdb wpdb */
        global $current_user, $wpdb;
        $user_blogs    = get_blogs_of_user($current_user->ID);
        $network_blogs = (array)$wpdb->get_results("select `blog_id`, `site_id` from `{$wpdb->blogs}`");
        $user_id       = !empty($GLOBALS['mwp_user_id']) ? $GLOBALS['mwp_user_id'] : false;
        $mainBlogId    = defined('BLOG_ID_CURRENT_SITE') ? BLOG_ID_CURRENT_SITE : false;

        if ($this->network_admin_install != '1' || !is_super_admin($user_id) || empty($network_blogs)) {
            return array();
        }

        $stats = array('network_blogs' => array(), 'other_blogs' => array());
        foreach ($network_blogs as $details) {
            if (($mainBlogId !== false && $details->blog_id == $mainBlogId) || ($mainBlogId === false && $details->site_id == $details->blog_id)) {
                continue;
            } else {
                $data = get_blog_details($details->blog_id);
                if (in_array($details->blog_id, array_keys($user_blogs))) {
                    $stats['network_blogs'][] = $data->siteurl;
                } else {
                    $user = get_users(
                        array(
                            'blog_id' => $details->blog_id,
                            'number'  => 1,
                        )
                    );
                    if (!empty($user)) {
                        $stats['other_blogs'][$data->siteurl] = $user[0]->user_login;
                    }
                }
            }
        }

        return $stats;
    }

    public function get_auth_cookies($user_id)
    {
        $cookies = array();
        $secure  = is_ssl();
        $secure  = apply_filters('secure_auth_cookie', $secure, $user_id);

        if ($secure) {
            $auth_cookie_name = SECURE_AUTH_COOKIE;
            $scheme           = 'secure_auth';
        } else {
            $auth_cookie_name = AUTH_COOKIE;
            $scheme           = 'auth';
        }

        $expiration = time() + 2592000;

        $cookies[$auth_cookie_name] = wp_generate_auth_cookie($user_id, $expiration, $scheme);
        $cookies[LOGGED_IN_COOKIE]  = wp_generate_auth_cookie($user_id, $expiration, 'logged_in');

        if (defined('WPE_APIKEY')) {
            $cookies['wpe-auth'] = md5('wpe_auth_salty_dog|'.WPE_APIKEY);
        }

        return $cookies;
    }

    public function get_stat_cookies()
    {
        if (!defined('WPE_APIKEY')) {
            return array();
        }

        global $current_user;

        $cookies   = $this->get_auth_cookies($current_user->ID);
        $publicKey = mwp_worker_configuration()->getLivePublicKey('cookie_service', true);

        if (empty($publicKey)) {
            $publicKey = $this->get_master_public_key();
        }

        if (empty($cookies) || empty($publicKey)) {
            return $cookies;
        }

        if (!class_exists('Crypt_RSA', false)) {
            require_once dirname(__FILE__).'/../../src/PHPSecLib/Crypt/RSA.php';
        }

        $rsa = new Crypt_RSA();
        $rsa->setEncryptionMode(CRYPT_RSA_SIGNATURE_PKCS1);
        $rsa->loadKey($publicKey, CRYPT_RSA_PUBLIC_FORMAT_PKCS1);

        foreach ($cookies as &$cookieValue) {
            $cookieValue = base64_encode($rsa->encrypt($cookieValue));
        }

        return $cookies;
    }

    public function get_initial_stats()
    {
        global $mmb_plugin_dir, $wpdb;

        $stats = array(
            'email'           => get_option('admin_email'),
            'content_path'    => WP_CONTENT_DIR,
            'worker_path'     => $mmb_plugin_dir,
            'worker_version'  => $GLOBALS['MMB_WORKER_VERSION'],
            'worker_revision' => $GLOBALS['MMB_WORKER_REVISION'],
            'site_title'      => get_bloginfo('name'),
            'site_tagline'    => get_bloginfo('description'),
            'db_name'         => $this->get_active_db(),
            'site_home'       => get_option('home'),
            'admin_url'       => admin_url(),
            'wp_multisite'    => $this->mmb_multisite,
            'network_install' => $this->network_admin_install,
            'cookies'         => $this->get_stat_cookies(),
            'timezone'        => get_option('timezone_string'),
            'timezone_offset' => get_option('gmt_offset'),
            'db_prefix'       => $wpdb->prefix,
            'service_key'     => mwp_get_service_key(),
        );

        if ($this->mmb_multisite) {
            $details = get_blog_details($this->mmb_multisite);
            if (isset($details->site_id)) {
                $details = get_blog_details($details->site_id);
                if (isset($details->siteurl)) {
                    $stats['network_parent'] = $details->siteurl;
                }
            }
        }

        $stats['writable']      = $this->is_server_writable();
        $stats['initial_stats'] = $this->get_stats(array());

        return $stats;
    }

    public function get_active_db()
    {
        global $wpdb;
        $sql = 'SELECT DATABASE() as db_name';

        $sqlresult = $wpdb->get_row($sql);
        $active_db = $sqlresult->db_name;

        return $active_db;
    }

    public function get_hit_count()
    {
        return get_option('user_hit_count');
    }

    public function cmp_posts_worker($a, $b)
    {
        return ($a->post_date < $b->post_date);
    }

    public function trim_content($content = '', $length = 200)
    {
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            $content = (mb_strlen($content) > ($length + 3)) ? mb_substr($content, 0, $length).'...' : $content;
        } else {
            $content = (strlen($content) > ($length + 3)) ? substr($content, 0, $length).'...' : $content;
        }

        return $content;
    }
}
