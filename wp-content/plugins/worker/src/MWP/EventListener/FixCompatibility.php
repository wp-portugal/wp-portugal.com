<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_FixCompatibility implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $context;

    public function __construct(MWP_WordPress_Context $context)
    {
        $this->context = $context;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::ACTION_RESPONSE => 'fixWpSuperCache',
            MWP_Event_Events::MASTER_REQUEST  => array(
                array('fixAllInOneSecurity', -10000),
                array('fixWpSimpleFirewall', -10000),
                array('fixDuoFactor', -10000),
                array('fixShieldUserManagementICWP', -10000),
                array('fixSidekickPlugin', -10000),
                array('fixSpamShield', -10000),
                array('fixWpSpamShieldBan', -10000),
                array('fixGlobals', -10000),
            ),
        );
    }

    public function fixWpSpamShieldBan()
    {
        $wpss_ubl_cache = $this->context->optionGet('spamshield_ubl_cache');

        if (empty($wpss_ubl_cache)){
            return;
        }

        $serverIp = !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

        foreach ($wpss_ubl_cache as $key => $singleIp) {
            if ($singleIp !== $serverIp) {
                continue;
            }

            unset($wpss_ubl_cache[$key]);
        }

        $this->context->optionSet('spamshield_ubl_cache', array_values($wpss_ubl_cache));
    }

    public function fixSpamShield()
    {
        if (!defined('WPSS_IP_BAN_CLEAR')) {
            define('WPSS_IP_BAN_CLEAR', true);
        }
    }

    public function fixSidekickPlugin()
    {
        $this->context->addAction('init', array($this, '_fixSidekickPlugin'), -1);
    }

    public function _fixSidekickPlugin()
    {
        $this->removeByPluginClass('admin_init', 'Sidekick', 'redirect', true);
    }

    public function fixShieldUserManagementICWP()
    {
        $this->context->addFilter('icwp-wpsf-visitor_is_whitelisted', '__return_true');
    }

    public function fixWpSuperCache()
    {
        if ($this->context->hasConstant('ADVANCEDCACHEPROBLEM') && $this->context->getConstant('ADVANCEDCACHEPROBLEM')) {
            $this->context->set('wp_cache_config_file', null);
        }
    }

    public function fixDuoFactor()
    {
        if (!$this->context->isPluginEnabled('duo-wordpress/duo_wordpress.php')) {
            return;
        }

        $this->context->addAction('init', array($this, '_fixDuoFactor'), -1);
    }

    /**
     * @internal
     */
    public function _fixDuoFactor()
    {
        $this->context->removeAction('init', 'duo_verify_auth', 10);
    }

    public function fixAllInOneSecurity()
    {
        if (!$this->context->isPluginEnabled('all-in-one-wp-security-and-firewall/wp-security.php')) {
            return;
        }

        $this->context->addAction('init', array($this, '_fixAllInOneSecurity'), -1);
    }

    /**
     * @internal
     */
    public function _fixAllInOneSecurity()
    {
        $user = $this->context->getCurrentUser();

        if (empty($user->ID)) {
            return;
        }

        $this->context->updateUserMeta($user->ID, 'last_login_time', $this->context->getCurrentTime()->format('Y-m-d H:i:s'));
    }

    public function fixWpSimpleFirewall()
    {
        if (!$this->context->isPluginEnabled('wp-simple-firewall/icwp-wpsf.php')) {
            return;
        }

        /** @handled function */
        MWP_FixCompatibility_ICWP_WPSF();
    }

    private function removeByPluginClass($tag, $class_name, $functionName, $isAction = false, $priority = 10)
    {
        if (!class_exists($class_name)) {
            return null;
        }

        global $wp_filter;

        if (empty($wp_filter[$tag][$priority])) {
            return null;
        }

        foreach ($wp_filter[$tag][$priority] as $callable) {
            if (empty($callable['function']) || !is_array($callable['function']) || count($callable['function']) < 2) {
                continue;
            }

            if (!is_a($callable['function'][0], $class_name)) {
                continue;
            }

            if ($callable['function'][1] !== $functionName) {
                continue;
            }

            if ($isAction) {
                $this->context->removeAction($tag, $callable['function'], $priority);
            } else {
                $this->context->removeFilter($tag, $callable['function'], $priority);
            }

            return $callable['function'];
        }

        return null;
    }

    public function fixGlobals()
    {
        if (!isset($GLOBALS['hook_suffix'])) {
            $GLOBALS['hook_suffix'] = null;
        }
    }
}

function MWP_FixCompatibility_ICWP_WPSF()
{
    if (class_exists('ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth', false)) {
        return;
    }

    class ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth
    {
        public function run()
        {
        }
    }
}
