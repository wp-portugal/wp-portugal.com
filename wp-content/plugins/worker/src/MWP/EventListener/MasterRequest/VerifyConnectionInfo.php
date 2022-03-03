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
 * Checks if the website connection attempt is providing valid signature for our keychain.
 * This works in conjunction with "AuthenticateRequest" class, since it handles all other cases.
 */
class MWP_EventListener_MasterRequest_VerifyConnectionInfo implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $context;

    private $signer;

    public function __construct(MWP_WordPress_Context $context, MWP_Signer_Interface $signer)
    {
        $this->context = $context;
        $this->signer  = $signer;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::MASTER_REQUEST => array('onMasterRequest', 300),
        );
    }

    public function onMasterRequest(MWP_Event_MasterRequest $event)
    {
        $request = $event->getRequest();

        if ($request->getAction() !== 'add_site') {
            return;
        }

        $data = $request->getData();

        if (empty($data['add_site_signature']) || empty($data['add_site_signature_id'])) {
            throw new MWP_Worker_Exception(MWP_Worker_Exception::CONNECTION_SIGNATURE_EMPTY);
        }

        $connectionSignature = base64_decode($data['add_site_signature']);
        $publicKeyId         = $data['add_site_signature_id'];

        $publicKeyId       = preg_replace('{[^a-z0-9_]}i', '', $publicKeyId);
        $publicKeyLocation = dirname(__FILE__).'/../../../../publickeys/'.$publicKeyId.'.pub';

        if (!file_exists($publicKeyLocation)) {
            throw new MWP_Worker_Exception(MWP_Worker_Exception::CONNECTION_PUBLIC_KEY_NOT_FOUND, null, array(
                'publicKeyId' => $publicKeyId,
            ));
        }

        $publicKey = file_get_contents($publicKeyLocation);

        $message = json_encode(array('setting' => $request->getSetting(), 'params' => $request->getParams())).strtolower($request->getCommunicationKey());

        $verify = $this->signer->verify($message, $connectionSignature, $publicKey);

        if (!$verify) {
            throw new MWP_Worker_Exception(MWP_Worker_Exception::CONNECTION_SIGNATURE_NOT_VALID, "Invalid message signature. Deactivate and activate the ManageWP Worker plugin on this site, then re-add it to your ManageWP account.");
        }

        $requestKey = strtolower($request->getCommunicationKey());

        if (empty($requestKey) || ($requestKey !== strtolower(mwp_get_potential_key()) && $requestKey !== strtolower(mwp_get_communication_key()))) {
            throw new MWP_Worker_Exception(MWP_Worker_Exception::CONNECTION_INVALID_KEY, "Invalid communication key provided. Please make sure to provide the latest communication key from your Worker plugin.");
        }

        $request->setAuthenticated(true);
    }
}
