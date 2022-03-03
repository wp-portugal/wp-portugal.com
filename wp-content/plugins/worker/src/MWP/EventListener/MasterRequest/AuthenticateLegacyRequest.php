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
 * Authenticates requests that use hash authentication instead of public/private key.
 *
 * @deprecated Rely on the more secure AuthenticateRequest class.
 */
class MWP_EventListener_MasterRequest_AuthenticateLegacyRequest implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $configuration;

    function __construct(MWP_Worker_Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::MASTER_REQUEST => array('onMasterRequest', 350),
        );
    }

    public function onMasterRequest(MWP_Event_MasterRequest $event)
    {
        $request           = $event->getRequest();
        $secureKey         = $this->configuration->getSecureKey();
        $params            = $event->getParams();
        $givenPublicKey    = isset($params['public_key']) ? base64_decode($params['public_key']) : null;
        $existingPublicKey = $this->configuration->getPublicKey();

        if ($request->getAction() === 'add_site') {
            if ($secureKey && (!$existingPublicKey || $givenPublicKey !== $existingPublicKey)) {
                // Secure key exists, and public key either doesn't exist, or doesn't match.
                throw new MWP_Worker_Exception(MWP_Worker_Exception::LEGACY_AUTHENTICATION_KEY_EXISTS, "Sorry, the site appears to be already added to a ManageWP account. Please deactivate, then activate ManageWP Worker plugin on your website and try again or contact our support.");
            }

            return;
        }

        if (!$secureKey) {
            // The site is relying on public key.
            return;
        }

        $messageId = $request->getAction().$request->getNonce();
        $signature = $request->getSignature();

        if (md5($messageId.$secureKey) !== $signature) {
            throw new MWP_Worker_Exception(MWP_Worker_Exception::LEGACY_AUTHENTICATION_INVALID_SIGNATURE, "Invalid message signature. Deactivate and activate the ManageWP Worker plugin on this site, then re-add it to your ManageWP account.");
        }

        $request->setAuthenticated(true);

        // Skip verification test because the signature is not an SSL signature.
        $params                         = $event->getParams();
        $params['skipVerificationTest'] = true;
        $event->setParams($params);
    }
}
