<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_ActionRequest_VerifyNonce implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $nonceManager;

    public function __construct(MWP_Security_NonceManager $nonceManager)
    {
        $this->nonceManager = $nonceManager;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::ACTION_REQUEST => array('onActionRequest', 400),
        );
    }

    public function onActionRequest(MWP_Event_ActionRequest $event)
    {
        $nonce = $event->getRequest()->getNonce();

        try {
            $this->nonceManager->useNonce($nonce);
        } catch (MWP_Security_Exception_NonceFormatInvalid $e) {
            throw new MWP_Worker_Exception(MWP_Worker_Exception::NONCE_FORMAT_INVALID, "Invalid nonce used. Please contact support.", array(
                'nonce' => $nonce,
            ));
        } catch (MWP_Security_Exception_NonceExpired $e) {
            throw new MWP_Worker_Exception(MWP_Worker_Exception::NONCE_EXPIRED, "Nonce expired. Please contact support.", array(
                'nonce' => $nonce,
            ));
        } catch (MWP_Security_Exception_NonceAlreadyUsed $e) {
            throw new MWP_Worker_Exception(MWP_Worker_Exception::NONCE_ALREADY_USED, "Nonce already used. Please contact support.", array(
                'nonce' => $nonce,
            ));
        }
    }
}
