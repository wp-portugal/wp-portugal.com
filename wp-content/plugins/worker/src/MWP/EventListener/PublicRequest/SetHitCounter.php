<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_PublicRequest_SetHitCounter implements Symfony_EventDispatcher_EventSubscriberInterface
{
    const METHOD_BLACKLIST = false;

    const METHOD_WHITELIST = true;

    private $context;

    /**
     * @var MWP_Extension_HitCounter
     */
    private $hitCounter;

    /**
     * If set to METHOD_BLACKLIST, all non-master requests except those that match at least one
     * rule from the list.
     * If set to METHOD_WHITELIST, only requests that match at least one rule from the list will
     * be counted.
     *
     * @var bool
     */
    private $userAgentMatchingMethod;

    /**
     * @var array
     */
    private $blacklistedIps = array();

    private $userAgentList = array();

    /**
     * @param MWP_WordPress_Context    $context
     * @param MWP_Extension_HitCounter $hitCounter
     * @param MWP_Worker_RequestStack  $requestStack
     * @param string[]                 $blacklistedIps
     * @param string[]                 $userAgentList
     * @param bool                     $userAgentMatchingMethod
     */
    public function __construct(MWP_WordPress_Context $context, MWP_Extension_HitCounter $hitCounter, MWP_Worker_RequestStack $requestStack, array $blacklistedIps = array(), array $userAgentList = array(), $userAgentMatchingMethod = self::METHOD_BLACKLIST)
    {
        $this->context                 = $context;
        $this->hitCounter              = $hitCounter;
        $this->requestStack            = $requestStack;
        $this->blacklistedIps          = $blacklistedIps;
        $this->userAgentList           = $userAgentList;
        $this->userAgentMatchingMethod = $userAgentMatchingMethod;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::PUBLIC_REQUEST => 'onPublicRequest',
        );
    }

    public function onPublicRequest(MWP_Event_PublicRequest $event)
    {
        $this->context->addAction('wp', array($this, 'countHit'));
    }

    public function countHit()
    {
        $request = $this->requestStack->getMasterRequest();

        if (!$this->context->hasConstant('WP_USE_THEMES') || !$this->context->getConstant('WP_USE_THEMES')) {
            // The file is not visited via "index.php".
            return;
        }

        if ($this->context->isInAdminPanel()) {
            return;
        }

        if ($this->isBlacklisted($request)) {
            return;
        }

        if (!$this->shouldTrack($request)) {
            return;
        }

        if ($this->isDisabled()) {
            return;
        }

        $this->hitCounter->increment();
    }

    /**
     * @param MWP_Worker_Request $request
     *
     * @return bool
     */
    protected function isBlacklisted(MWP_Worker_Request $request)
    {
        $userAgent = $request->getHeader('USER_AGENT');
        $ip        = $request->getHeader('REMOTE_ADDR');

        foreach ($this->blacklistedIps as $ipRegex) {
            if (preg_match($ipRegex, $ip)) {
                return true;
            }
        }

        foreach ($this->userAgentList as $uaRegex) {
            if (preg_match($uaRegex, $userAgent)) {
                return !$this->userAgentMatchingMethod;
            }
        }

        return $this->userAgentMatchingMethod;
    }

    /**
     * Check if request should be tracked by looking at the Do Not Track (DNT) header.
     *
     * @param MWP_Worker_Request $request
     *
     * @return bool
     */
    protected function shouldTrack(MWP_Worker_Request $request)
    {
        return $request->getHeader('DNT') !== "1";
    }

    /**
     * Check if user disabled hit count.
     *
     * @return bool
     */
    private function isDisabled()
    {
        return $this->context->optionGet('disabled_hit_count');
    }
}
