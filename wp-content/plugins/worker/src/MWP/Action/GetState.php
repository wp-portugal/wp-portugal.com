<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_GetState extends MWP_Action_Abstract
{
    const USERS = 'users';

    const POSTS = 'posts';

    const COMMENTS = 'comments';

    const SQL_RESULT = 'sqlResult';

    const SINGLE_SQL_RESULT = 'singleSqlResult';

    const PLUGINS = 'plugins';

    const THEMES = 'themes';

    const PLUGIN_UPDATES = 'pluginUpdates';

    const THEME_UPDATES = 'themeUpdates';

    const CORE_UPDATES = 'coreUpdates';

    const ROLES = 'roles';

    const SITE_INFO = 'siteInfo';

    const SERVER_INFO = 'serverInfo';

    public function execute(array $params = array())
    {
        $result = array();

        foreach ($params as $fieldName => $queryInfo) {
            $start              = microtime(true);
            mwp_logger()->debug('Started getting field '.$queryInfo['type']);
            $queryResult        = $this->getField($queryInfo['type'], $queryInfo['options']);
            mwp_logger()->debug('Finished getting field '.$queryInfo['type']);
            $end                = sprintf("%.6f", microtime(true) - $start);
            $result[$fieldName] = array(
                'type'      => $queryInfo['type'],
                'benchmark' => $end,
                'result'    => $queryResult,
            );
        }

        return $result;
    }

    private function getField($type, $options)
    {
        switch ($type) {
            case self::USERS:
                return $this->getUsers($options);
            case self::POSTS:
                return $this->getPosts($options);
            case self::COMMENTS:
                return $this->getComments($options);
            case self::SQL_RESULT:
                return $this->getSqlResult($options);
            case self::SINGLE_SQL_RESULT:
                return $this->getSingleSqlResult($options);
            case self::PLUGINS:
                return $this->getPlugins($options);
            case self::THEMES:
                return $this->getThemes($options);
            case self::PLUGIN_UPDATES:
                return $this->getPluginUpdates($options);
            case self::THEME_UPDATES:
                return $this->getThemeUpdates($options);
            case self::CORE_UPDATES:
                return $this->getCoreUpdates($options);
            case self::ROLES:
                return $this->getRoles($options);
            case self::SITE_INFO:
                return $this->getSiteInfo($options);
            case self::SERVER_INFO:
                return $this->getServerInfo($options);
            default:
                throw new RuntimeException(sprintf('Undefined field type provided: "%s".', $type));
        }
    }

    protected function getUsers(array $options = array())
    {
        $userQuery = $this->container->getUserQuery();
        $users     = $userQuery->query($options);

        if (function_exists('is_main_site') && is_main_site()) {
            $this->includeAllSuperAdmins($users);
        }

        return $users;
    }

    private function includeAllSuperAdmins(array &$users)
    {
        foreach (get_super_admins() as $superAdminUsername) {
            foreach ($users as &$user) {
                if ($user['username'] !== $superAdminUsername || $user['roles'] !== false) {
                    continue;
                }
                $user['roles'] = array('administrator' => true);
                break;
            }
        }
    }

    protected function getPosts(array $options = array())
    {
        $postQuery = $this->container->getPostQuery();
        $posts     = $postQuery->query($options);

        return $posts;
    }

    protected function getComments(array $options = array())
    {
        $commentQuery = $this->container->getCommentQuery();
        $comments     = $commentQuery->query($options);

        return $comments;
    }

    protected function getSingleSqlResult(array $options = array())
    {
        $options += array(
            'query' => null,
        );

        return $this->container->getWordPressContext()->getDb()->get_var($options['query']);
    }

    protected function getSqlResult(array $options = array())
    {
        $options += array(
            'query' => null,
        );

        return $this->container->getWordPressContext()->getDb()->get_results($options['query']);
    }

    protected function getPlugins(array $options = array())
    {
        $options += array(
            'fetchDescription'     => false,
            'fetchAutoUpdate'      => true,
            'fetchAvailableUpdate' => false,
            'fetchActivatedAt'     => true,
        );

        $pluginProvider    = $this->container->getPluginProvider();
        $plugins           = $pluginProvider->fetch($options);
        $autoUpdateManager = $this->container->getAutoUpdateManager();

        if ($options['fetchAutoUpdate']) {
            foreach ($plugins as &$plugin) {
                $plugin['autoUpdate'] = isset($plugin['slug']) ? $autoUpdateManager->isEnabledForPlugin($plugin['slug']) : false;
            }
        }

        if ($options['fetchAvailableUpdate']) {
            $um = $this->container->getUpdateManager();
            foreach ($plugins as &$plugin) {
                if (!isset($plugin['basename'])) {
                    continue;
                }

                $update = $um->getPluginUpdate($plugin['basename']);
                if ($update !== null) {
                    $plugin['updateVersion'] = $update->version;
                    $plugin['updatePackage'] = $update->package;
                }
            }
        }

        if ($options['fetchActivatedAt']) {
            $recentlyActivated = $this->container->getWordPressContext()->optionGet('recently_activated');
            foreach ($plugins as &$plugin) {
                if (isset($recentlyActivated[$plugin['basename']])) {
                    $plugin['activatedAt'] = date('Y-m-d\TH:i:sO', $recentlyActivated[$plugin['basename']]);
                }
            }
        }

        return $plugins;
    }

    protected function getThemes(array $options = array())
    {
        $options += array(
            'fetchDescription'     => false,
            'fetchAutoUpdate'      => true,
            'fetchAvailableUpdate' => false,
        );

        $themeProvider     = $this->container->getThemeProvider();
        $themes            = $themeProvider->fetch($options);
        $autoUpdateManager = $this->container->getAutoUpdateManager();

        if ($options['fetchAutoUpdate']) {
            foreach ($themes as &$theme) {
                $theme['autoUpdate'] = $autoUpdateManager->isEnabledForTheme($theme['name']);
            }
        }

        if ($options['fetchAvailableUpdate']) {
            $um = $this->container->getUpdateManager();
            foreach ($themes as &$theme) {
                if (!isset($theme['slug'])) {
                    continue;
                }

                $update = $um->getThemeUpdate($theme['slug']);
                if ($update !== null) {
                    $theme['updateVersion'] = $update->version;
                    $theme['updatePackage'] = $update->package;
                }
            }
        }

        return $themes;
    }

    public function getPluginUpdates(array $options = array())
    {
        $um = $this->container->getUpdateManager();

        return $um->getPluginUpdates();
    }

    public function getThemeUpdates(array $options = array())
    {
        $um = $this->container->getUpdateManager();

        return $um->getThemeUpdates();
    }

    public function getCoreUpdates(array $options = array())
    {
        $um = $this->container->getUpdateManager();

        return $um->getCoreUpdates();
    }

    public function getRoles(array $options = array())
    {
        $options += array(
            'capabilities' => false,
        );

        $roles = $this->container->getWordPressContext()->getUserRoles();

        $result = array();

        foreach ($roles as $roleSlug => $roleInfo) {
            $role = array(
                'slug' => $roleSlug,
                'name' => $roleInfo['name'],
            );
            if ($options['capabilities']) {
                $role['capabilities'] = $roleInfo['capabilities'];
            }
            $result[] = $role;
        }

        return $result;
    }

    public function getSiteInfo(array $options = array())
    {
        $context = $this->container->getWordPressContext();

        return array(
            'title'               => $context->getSiteTitle(),
            'description'         => $context->getSiteDescription(),
            'siteUrl'             => $context->getSiteUrl(),
            'homeUrl'             => $context->getHomeUrl(),
            'masterSiteUrl'       => $context->getMasterSiteUrl(),
            'isMultisite'         => $context->isMultisite(),
            'public'              => $context->optionGet('blog_public'),
            'siteId'              => $context->getSiteId(),
            'currentUserId'       => $context->getCurrentUser()->ID,
            'currentUserUsername' => $context->getCurrentUser()->user_login,
            'workerRealpath'      => $this->container->getParameter('worker_realpath'),
            'workerVersion'       => $this->container->getParameter('worker_version'),
            'workerRevision'      => $this->container->getParameter('worker_revision'),
            'timezone'            => $this->container->getWordPressContext()->optionGet('timezone_string'),
            'timezoneOffset'      => $this->container->getWordPressContext()->optionGet('gmt_offset'),
        );
    }

    public function getServerInfo(array $options = array())
    {
        $context = $this->container->getWordPressContext();

        return array(
            // @todo: move the checks below to a separate component.
            'phpVersion'          => PHP_VERSION,
            'mysqlVersion'        => $context->getDb()->db_version(),
            'extensionPdoMysql'   => extension_loaded('pdo_mysql'),
            'extensionOpenSsl'    => extension_loaded('openssl'),
            'extensionFtp'        => extension_loaded('ftp'),
            'extensionZlib'       => extension_loaded('zlib'),
            'extensionBz2'        => extension_loaded('bz2'),
            'extensionZip'        => extension_loaded('zip'),
            'extensionCurl'       => extension_loaded('curl'),
            'extensionGd'         => extension_loaded('gd'),
            'extensionImagick'    => extension_loaded('imagick'),
            'extensionSockets'    => extension_loaded('sockets'),
            'extensionSsh2'       => extension_loaded('ssh2'),
            'shellAvailable'      => mwp_is_shell_available(),
            'safeMode'            => mwp_is_safe_mode(),
            'memoryLimit'         => mwp_format_memory_limit(ini_get('memory_limit')),
            'disabledFunctions'   => mwp_get_disabled_functions(),
            'processArchitecture' => strlen(decbin(~0)), // Results in 32 or 62.
            'internalIp'          => $this->container->getRequestStack()->getMasterRequest()->server['SERVER_ADDR'],
            'uname'               => php_uname('a'),
            'hostname'            => php_uname('n'),
            'os'                  => (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'windows' : 'unix',
        );
    }
}
