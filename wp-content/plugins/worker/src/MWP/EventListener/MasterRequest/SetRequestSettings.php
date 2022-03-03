<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_MasterRequest_SetRequestSettings implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $context;

    public function __construct(MWP_WordPress_Context $context)
    {
        $this->context = $context;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::MASTER_REQUEST => array('onMasterRequest', -1000),
        );
    }

    public function onMasterRequest(MWP_Event_MasterRequest $event)
    {
        if (!$event->getRequest()->isAuthenticated()) {
            return;
        }

        MWP_System_Utils::setMemoryLimit(MWP_System_Utils::getWPMaxMemoryLimit());

        $data = $event->getRequest()->getData();

        $this->defineWpAjax($data);
        $this->setWpPage($data);
        $this->simulateAdminEnvironment($data);

        // Master should never get redirected by the worker, since it expects worker response.
        $this->context->addFilter('wp_redirect', array($this, 'disableRedirect'));

        // Alternate WP cron can run on 'init' hook.
        $this->context->removeAction('init', 'wp_cron');

        $doNotUseExtCache = !empty($data['wpNoExtObjectCacheNew']);

        if ($doNotUseExtCache) {
            $this->context->set('_wp_using_ext_object_cache', false);
        }
    }

    private function simulateAdminEnvironment(array $data)
    {
        if (empty($data['wpAdmin'])) {
            return;
        }

        $context = $this->context;

        $_SERVER['PHP_SELF']       = '/wp-admin/'.(!empty($data['wpPage']) ? $data['wpPage'] : 'index.php');
        $_COOKIE['redirect_count'] = 10; // hack for the WordPress HTTPS plugin, so it doesn't redirect us

        if (defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN) {
            $_SERVER['HTTPS']       = 'on';
            $_SERVER['SERVER_PORT'] = '443';
        }

        $context->setConstant('WP_ADMIN', true);
        $context->isMultisite() ? $context->setConstant('WP_NETWORK_ADMIN', true) : $context->setConstant('WP_NETWORK_ADMIN', false);
        $context->setConstant('WP_USER_ADMIN', false);
        $context->setConstant('WP_BLOG_ADMIN', true);
        $context->addAction('wp_loaded', array($this, 'adminWpLoaded'), MAX_PRIORITY_HOOK - 1);
    }

    /**
     * @internal
     */
    public function adminWpLoaded()
    {
        $context = $this->context;

        MWP_System_Utils::setMemoryLimit(MWP_System_Utils::getWPMaxMemoryLimit());
        $this->context->addFilter('http_response', array($this, 'captureCacheUpdateCall'), MAX_PRIORITY_HOOK, 3);
        $this->context->addFilter('pre_http_request', array($this, 'interceptCacheUpdateCall'), MAX_PRIORITY_HOOK, 3);
        require_once $this->context->getConstant('ABSPATH').'wp-admin/includes/admin.php';
        $context->doAction('admin_init');
        global $wp_current_filter;
        $wp_current_filter[] = 'load-update-core.php';

        if (function_exists('wp_clean_update_cache')) {
            /** @handled function */
            wp_clean_update_cache();
        }

        /** @handled function */
        wp_update_plugins();

        /** @handled function */
        wp_update_themes();

        array_pop($wp_current_filter);

        /** @handled function */
        set_current_screen();
        $context->doAction('load-update-core.php');

        /** @handled function */
        wp_version_check();

        /** @handled function */
        wp_version_check(array(), true);
    }

    private function defineWpAjax(array $data)
    {
        if (empty($data['wpAjax'])) {
            return;
        }

        $this->context->setConstant('DOING_AJAX', true, false);
    }

    private function setWpPage(array $data)
    {
        if (empty($data['wpPage'])) {
            return;
        }

        $this->context->set('pagenow', $data['wpPage']);
    }

    /**
     * @internal
     */
    public function disableRedirect()
    {
        return false;
    }

    private $updateCallResponse;
    private $updateCallBody;

    public function captureCacheUpdateCall($response, $args, $url)
    {
        if ($url !== 'https://api.wordpress.org/plugins/update-check/1.1/') {
            return $response;
        }
        $this->updateCallResponse = $response;
        $this->updateCallBody     = $args['body'];
        return $response;
    }

    public function interceptCacheUpdateCall($response, $args, $url)
    {
        if ($url !== 'https://api.wordpress.org/plugins/update-check/1.1/') {
            return $response;
        }
        if ($this->updateCallResponse === null) {
            return $response;
        }
        if ($this->updateCallBody !== http_build_query($args['body'])) {
            return $response;
        }
        return $this->updateCallResponse;
    }
}
