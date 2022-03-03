<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Proxy class for WordPress' function calls. This is the only class that should be able to use WordPress' internal functions.
 * The rule of thumb is that if a function does not exist since WordPress 3.0.0, it should be defined here.
 */
class MWP_WordPress_Context
{

    private $context;

    private $constants;

    private $useGlobals;

    /**
     * @param array $globals The context to work with. Defaults to $GLOBALS, using the same global variables as WordPress.
     * @param array $constants The list of constants to use. Defaults to global constants.
     */
    public function __construct(array &$globals = null, array $constants = null)
    {
        if ($globals !== null) {
            $this->context = $globals;
            $this->useGlobals = false;
        } else {
            $this->useGlobals = true;
        }

        if ($constants !== null) {
            $this->constants = $constants;
        }
    }

    public function set($name, $value)
    {
        if ($this->useGlobals) {
            $GLOBALS[$name] = $value;
        } else {
            $this->context[$name] = $value;
        }
    }

    public function get($name)
    {
        if ($this->useGlobals) {
            return isset($GLOBALS[$name]) ? $GLOBALS[$name] : null;
        }

        return isset($this->context[$name]) ? $this->context[$name] : null;
    }

    /**
     * @return wpdb
     */
    public function getDb()
    {
        return $this->get('wpdb');
    }

    /**
     * Escapes data for use in a MySQL query.
     *
     * Usually you should prepare queries using wpdb::prepare().
     * Sometimes, spot-escaping is required or useful. One example
     * is preparing an array for use in an IN clause.
     *
     * @param array|string $data
     *
     * @return array|string
     */
    public function escapeParameter($data)
    {
        return esc_sql($data);
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->get('wp_version');
    }

    /**
     * @param string $version
     *
     * @return bool
     */
    public function isVersionAtLeast($version)
    {
        if (version_compare($version, $this->getVersion(), '<=')) {
            return true;
        }

        return false;
    }

    /**
     * @param string   $tag
     * @param Callable $functionToAdd
     * @param int      $priority
     * @param int      $acceptedArgs
     *
     * @see  add_action()
     * @link http://codex.wordpress.org/Function_Reference/add_action
     */
    public function addAction($tag, $functionToAdd, $priority = 10, $acceptedArgs = 1)
    {
        add_action($tag, $functionToAdd, $priority, $acceptedArgs);
    }

    /**
     * @param string $name
     * @param array  $args
     */
    public function doAction($name, array $args = array())
    {
        if ($name == 'admin_init') {
            do_action($name);
        } else {
            do_action($name, $args);
        }
    }

    /**
     * @param string $optionName The option to delete.
     * @param bool   $global Whether to delete the option from the whole network. Used for network un-installation.
     *
     * @see  delete_site_option()
     * @see  delete_option()
     * @link http://codex.wordpress.org/Function_Reference/register_uninstall_hook
     */
    public function optionDelete($optionName, $global = false)
    {
        if ($global && is_multisite()) {
            $db      = $this->getDb();
            $blogIDs = $db->get_col("SELECT blog_id FROM $db->blogs");
            foreach ($blogIDs as $blogID) {
                delete_blog_option($blogID, $optionName);
            }
        } else {
            delete_option($optionName);
        }
    }

    /**
     * @param string $optionName
     * @param mixed  $optionValue
     * @param bool   $global
     *
     * @see update_site_option()
     * @see update_option()
     * @link
     */
    public function optionSet($optionName, $optionValue, $global = false)
    {
        if ($global && is_multisite()) {
            $db      = $this->getDb();
            $blogIDs = $db->get_col("SELECT blog_id FROM $db->blogs");
            foreach ($blogIDs as $blogID) {
                update_blog_option($blogID, $optionName, $optionValue);
            }
        } else {
            update_option($optionName, $optionValue, true);
        }
    }

    /**
     * @param string $option Name of option to retrieve.
     * @param mixed  $default Optional. Default value to return if the option does not exist.
     * @param int    $siteId Site ID to update. Only used in multisite installations.
     * @param bool   $useCache Whether to use cache. Multisite only.
     *
     * @return mixed Value set for the option.
     *
     * @see  get_option()
     * @link http://codex.wordpress.org/Function_Reference/get_option
     */
    public function optionGet($option, $default = false, $siteId = null, $useCache = true)
    {
        if ($siteId !== null && is_multisite()) {
            return get_site_option($option, $default, $useCache);
        }

        return get_option($option, $default);
    }

    /**
     * WordPress' function get_current_blog_id() is not available before version 3.1.0.
     *
     * @return int
     *
     * @see get_current_blog_id()
     */
    public function getCurrentBlogId()
    {
        return abs(intval($this->get('blog_id')));
    }

    /**
     * @param string $constant
     *
     * @return bool
     */
    public function hasConstant($constant)
    {
        if (is_array($this->constants)) {
            return isset($this->constants[$constant]);
        }

        return defined($constant);
    }

    /**
     * @param string $constant
     *
     * @return int|string
     * @throws Exception If the constant does not exist.
     */
    public function getConstant($constant)
    {
        if (!$this->hasConstant($constant)) {
            throw new Exception(sprintf('The constant "%s" is not defined', $constant));
        }

        if (is_array($this->constants)) {
            return $this->constants[$constant];
        }

        return constant($constant);
    }

    public function setConstant($name, $value, $throw = true)
    {
        if ($this->hasConstant($name)) {
            if ($throw) {
                throw new Exception(sprintf('The constant "%s" is already defined', $name));
            }

            return;
        }

        if (is_array($this->constants)) {
            $this->constants[$name] = $value;

            return;
        }

        define($name, $value);
    }

    /**
     * @return string
     *
     * @see plugin_basename()
     */
    public function getPluginBasename()
    {
        $dirName = explode('/', plugin_basename(__FILE__), 2);
        $dirName = $dirName[0];

        return $dirName.'/init.php';
    }

    public function getPlugins()
    {
        if (!function_exists('get_mu_plugins')) {
            require_once($this->getConstant('ABSPATH').'wp-admin/includes/plugin.php');
        }

        return get_plugins();
    }

    public function getMustUsePlugins()
    {
        if (!function_exists('get_mu_plugins')) {
            require_once($this->getConstant('ABSPATH').'wp-admin/includes/plugin.php');
        }

        return get_mu_plugins();
    }

    public function isPluginActive($pluginBasename)
    {
        return is_plugin_active($pluginBasename);
    }

    public function isPluginActiveForNetwork($pluginBasename)
    {
        return is_plugin_active_for_network($pluginBasename);
    }

    public function getThemes()
    {
        $wpThemeDirectories = $this->get('wp_theme_directories');

        // When the plugin is MU-loaded, the WordPress theme directories are not set.
        if (empty($wpThemeDirectories)) {
            // Register the default theme directory root.
            register_theme_directory(get_theme_root());
        }

        if ($this->isVersionAtLeast('3.4')) {
            return wp_get_themes();
        }

        return get_themes();
    }

    public function getCurrentTheme()
    {
        $wpThemeDirectories = $this->get('wp_theme_directories');

        // When the plugin is MU-loaded, the WordPress theme directories are not set.
        if ($this->isMustUse() && empty($wpThemeDirectories)) {
            // Register the default theme directory root.
            register_theme_directory(get_theme_root());
        }

        if ($this->isVersionAtLeast('3.4')) {
            return wp_get_theme();
        }

        return get_current_theme();
    }

    public function getStylesheetDirectory()
    {
        return get_stylesheet_directory();
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param int    $expire Expiration time in seconds from now.
     *
     * @return bool
     */
    public function transientSet($key, $value, $expire = 0)
    {
        return set_site_transient($key, $value, $expire);
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function transientGet($key)
    {
        return get_site_transient($key);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function transientDelete($key)
    {
        return delete_site_transient($key);
    }

    private function isMustUse()
    {
        $mwpIsMu = $this->get('mwp_is_mu');

        if (empty($mwpIsMu)) {
            return false;
        }

        return true;
    }

    /**
     * @param string   $tag
     * @param Callable $functionToAdd
     * @param int      $priority
     * @param int      $acceptedArgs
     */
    public function addFilter($tag, $functionToAdd, $priority = 10, $acceptedArgs = 1)
    {
        add_filter($tag, $functionToAdd, $priority, $acceptedArgs);
    }

    public function enqueueScript($handle, $src = false, $dependencies = array(), $ver = false, $inFooter = false)
    {
        wp_enqueue_script($handle, $src, $dependencies, $ver, $inFooter);
    }

    public function enqueueStyle($handle, $src = false, $dependencies = array(), $ver = false, $media = 'all')
    {
        wp_enqueue_style($handle, $src, $dependencies, $ver, $media);
    }

    public function addMenuPage($pageTitle, $menuTitle, $capability, $slug, $callback = '', $iconUrl = '', $position = null)
    {
        add_menu_page($pageTitle, $menuTitle, $capability, $slug, $callback, $iconUrl, $position);
    }

    public function translate($text, $domain = 'default')
    {
        return translate($text, $domain);
    }

    public function output($content)
    {
        print $content;
    }

    public function getCurrentUser()
    {
        $this->requirePluggable();
        $this->requireCookieConstants();

        return wp_get_current_user();
    }

    public function getHomeUrl()
    {
        return get_home_url();
    }

    public function sendMail($to, $subject, $message, $headers = '', $attachments = array())
    {
        $this->requirePluggable();

        return wp_mail($to, $subject, $message, $headers, $attachments);
    }

    public function getAdminUrl($where)
    {
        return admin_url($where);
    }

    public function isInAdminPanel()
    {
        return is_admin();
    }

    public function isGranted($capability)
    {
        $this->requirePluggable();
        $this->requireCookieConstants();

        return current_user_can($capability);
    }

    /**
     * @param string $name Value name.
     *
     * @return mixed Context (global) value. Null if one doesn't exist.
     *
     * @throws Exception If the context value does not exist.
     */
    public function &getContextValue($name)
    {
        if (!$this->hasContextValue($name)) {
            throw new Exception(sprintf('Context value "%s" does not exist', $name));
        }

        // Ternary operator not used since it breaks the needed reference
        if ($this->useGlobals) {
            return $GLOBALS[$name];
        }

        return $this->context[$name];
    }

    /**
     * @param string $name Value name.
     *
     * @return bool
     */
    public function hasContextValue($name)
    {
        return array_key_exists($name, $this->useGlobals ? $GLOBALS : $this->context);
    }

    public function getDropInPlugins()
    {
        if (!function_exists('get_dropins')) {
            require_once ABSPATH.'wp-admin/includes/plugin.php';
        }

        return get_dropins();
    }

    public function requirePluggable()
    {
        require_once $this->getConstant('ABSPATH').$this->getConstant('WPINC').'/pluggable.php';
    }

    public function requireCookieConstants()
    {
        wp_cookie_constants();
    }

    public function requireAdminUserLibrary()
    {
        require_once $this->getConstant('ABSPATH').'wp-admin/includes/user.php';
    }

    public function getUserRoles()
    {
        $this->requireAdminUserLibrary();

        return get_editable_roles();
    }

    /**
     * @param string $username
     *
     * @return WP_User|stdClass|null
     */
    public function getUserByUsername($username)
    {
        $this->requirePluggable();

        $user = get_user_by('login', $username);

        if (!$user) {
            return null;
        }

        return $user;
    }

    /**
     * @param $criteria
     *
     * @return WP_User[]|stdClass[]
     *
     * @link http://codex.wordpress.org/Function_Reference/get_users
     *
     * Defaults:
     *  'blog_id'      => $GLOBALS['blog_id']
     *  'role'         => ''
     *  'meta_key'     => ''
     *  'meta_value'   => ''
     *  'meta_compare' => ''
     *  'meta_query'   => array()
     *  'include'      => array()
     *  'exclude'      => array()
     *  'orderby'      => 'login'
     *  'order'        => 'ASC'
     *  'offset'       => ''
     *  'search'       => ''
     *  'number'       => ''
     *  'count_total'  => false
     *  'fields'       => 'all'
     *  'who'          => ''
     */
    public function getUsers($criteria)
    {
        return get_users($criteria);
    }

    public function isPluginEnabled($pluginBasename)
    {
        $plugins = (array)$this->optionGet('active_plugins', array());

        return in_array($pluginBasename, $plugins);
    }

    /**
     * @param WP_User|stdClass $user
     */
    public function setCurrentUser($user)
    {
        $this->requirePluggable();

        wp_set_current_user($user->ID);
    }

    /**
     * @param WP_User|stdClass $user
     * @param bool             $remember
     * @param string           $secure
     */
    public function setAuthCookie($user, $remember = false, $secure = '')
    {
        $this->requireCookieConstants();

        wp_set_auth_cookie($user->ID, $remember, $secure);
    }

    public function wpDie($message = '', $title = '', $args = array())
    {
        wp_die($message, $title, $args);
        // This is just a stub, the script will have exit()-ed just before this point.
        exit();
    }

    /**
     * Returns current site's URL.
     *
     * @return string|void
     */
    public function getSiteUrl()
    {
        return get_bloginfo('wpurl');
    }

    public function requireWpRewrite()
    {
        if ($this->get('wp_rewrite') instanceof WP_Rewrite) {
            return;
        }

        /** @handled class */
        $this->set('wp_rewrite', new WP_Rewrite());
    }

    public function requireTaxonomies()
    {
        $wpTaxonomies = $this->get('wp_taxonomies');

        if (!empty($wpTaxonomies)) {
            return;
        }

        create_initial_taxonomies();
    }

    public function requirePostTypes()
    {
        $wpPostTypes = $this->get('wp_post_types');

        if (!empty($wpPostTypes)) {
            return;
        }

        create_initial_post_types();
    }

    public function requireTheme()
    {
        $wpThemeDirectories = $this->get('wp_theme_directories');

        if (!empty($wpThemeDirectories)) {
            return;
        }

        register_theme_directory(get_theme_root());
    }

    public function getLocale()
    {
        return get_locale();
    }

    public function tryDeserialize($content)
    {
        return maybe_unserialize($content);
    }

    public function getSiteTitle()
    {
        return get_bloginfo('name');
    }

    public function getSiteDescription()
    {
        return get_bloginfo('description');
    }

    /**
     * Always returns main site's url (in multisite installations).
     *
     * @see getSiteUrl
     *
     * @return string|void
     */
    public function getMasterSiteUrl()
    {
        return site_url();
    }

    public function isMultisite()
    {
        return is_multisite();
    }

    public function isMainSite()
    {
        return is_main_site();
    }

    public function isNetworkAdmin()
    {
        return is_network_admin();
    }

    public function getSiteId()
    {
        return get_current_blog_id();
    }

    public function getDbName()
    {
        return $this->getConstant('DB_NAME');
    }

    /**
     * @param int    $attachmentId
     * @param string $style
     *
     * @return null
     */
    public function getImageInfo($attachmentId, $style)
    {
        $info = wp_get_attachment_image_src($attachmentId, $style);

        if (!$info) {
            return null;
        }

        return array(
            'url'      => $info[0],
            'width'    => $info[1],
            'height'   => $info[2],
            'original' => !$info[3],
        );
    }

    public function addImageStyle($name, $width = 0, $height = 0, $crop = false)
    {
        add_image_size($name, $width, $height);
    }

    public function setCookie($name, $value, $expire = 0)
    {
        setcookie($name, $value, $expire, $this->getConstant('SITECOOKIEPATH'), $this->getConstant('COOKIE_DOMAIN'), $this->isSsl(), true);
    }

    /**
     * @return bool
     */
    public function isSsl()
    {
        return (bool)is_ssl();
    }

    /**
     * @return bool
     */
    public function isSslAdmin()
    {
        return $this->isSsl() || force_ssl_admin();
    }

    public function removeAction($tag, $function, $priority = 10)
    {
        remove_action($tag, $function, $priority);
    }

    public function addSubMenuPage($parentSlug, $pageTitle, $menuTitle, $capability, $menuSlug, $function = '')
    {
        return add_submenu_page($parentSlug, $pageTitle, $menuTitle, $capability, $menuSlug, $function);
    }

    public function wpNonceUrl($url, $action = -1, $name = '_wpnonce')
    {
        return wp_nonce_url($url, $action, $name);
    }

    /**
     * @param int    $userId
     * @param string $metaKey
     *
     * @return mixed
     */
    public function getUserMeta($userId, $metaKey)
    {
        return get_user_meta($userId, $metaKey, true);
    }

    /**
     * @param int    $userId
     * @param string $metaKey
     * @param mixed  $metaValue
     *
     * @return int|bool Meta ID if the key didn't exist, true on successful update, false on failure.
     */
    public function setUserMeta($userId, $metaKey, $metaValue)
    {
        return update_user_meta($userId, $metaKey, $metaValue);
    }

    /**
     * @param int $userId
     *
     * @return WP_Session_Tokens|null Returns null if the class does not exist, ie. before WordPress version 4.0.0.
     */
    public function getSessionTokens($userId)
    {
        if (!class_exists('WP_Session_Tokens', false)) {
            return null;
        }

        /** @handled static */

        return WP_Session_Tokens::get_instance($userId);
    }

    public function getCurrentTime()
    {
        return new DateTime('@'.current_time('timestamp'));
    }

    /**
     * @param int    $userId
     * @param string $key
     * @param mixed  $value
     *
     * @return bool|int
     */
    public function updateUserMeta($userId, $key, $value)
    {
        return update_user_meta($userId, $key, $value);
    }

    /**
     * @param string $str
     *
     * @return bool
     */
    public function seemsUtf8($str)
    {
        return seems_utf8($str);
    }
}
