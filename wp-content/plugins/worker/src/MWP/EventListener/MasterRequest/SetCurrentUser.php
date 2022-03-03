<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_MasterRequest_SetCurrentUser implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $context;

    public function __construct(MWP_WordPress_Context $context)
    {
        $this->context = $context;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::MASTER_REQUEST => array('onMasterRequest', -2000),
        );
    }

    public function onMasterRequest(MWP_Event_MasterRequest $event)
    {
        if (!$event->getRequest()->isAuthenticated()) {
            return;
        }

        if (!$event->isMuContext()) {
            // Set the user on the earliest hook after pluggable.php is loaded.
            $hookProxy = new MWP_WordPress_HookProxy(array($this, 'setCurrentUserFromEvent'), $event);
            $this->context->addAction('plugins_loaded', $hookProxy->getCallable(), -9999);

            return;
        }

        // We're inside the MU context, so set the user immediately.
        $this->setCurrentUserFromEvent($event);
    }

    public function setCurrentUserFromEvent(MWP_Event_MasterRequest $event)
    {
        $user         = null;
        $usernameUsed = $event->getRequest()->getUsername();

        if ($usernameUsed) {
            $user = $this->context->getUserByUsername($usernameUsed);
        }

        if ($user === null) {
            // No user provided, find one instead.
            $users = $this->context->getUsers(array('role' => 'administrator', 'number' => 1, 'orderby' => 'ID'));
            if (count($users) === 0) {
                throw new MWP_Worker_Exception(MWP_Worker_Exception::AUTHENTICATION_NO_ADMIN_USER, "We could not find an administrator user to use. Please contact support.");
            }
            $user = $users[0];
        }

        $this->context->setCurrentUser($user);
    }
}
