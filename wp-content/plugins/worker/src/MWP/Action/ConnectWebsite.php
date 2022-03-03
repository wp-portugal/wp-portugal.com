<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_ConnectWebsite extends MWP_Action_Abstract
{

    public function execute(array $params = array(), MWP_Worker_Request $request)
    {
        if (empty($params['public_key'])) {
            throw new MWP_Worker_Exception(MWP_Worker_Exception::CONNECTION_PUBLIC_KEY_NOT_PROVIDED);
        }

        $siteId            = $request->getSiteId();
        $communicationKey  = mwp_get_communication_key();
        $publicKey         = base64_decode($params['public_key']);
        $configuration     = $this->container->getConfiguration();
        $existingPublicKey = $configuration->getPublicKey();

        if (empty($siteId) && ((!empty($existingPublicKey) && $publicKey !== $existingPublicKey) || !empty($communicationKey))) {
            throw new MWP_Worker_Exception(MWP_Worker_Exception::CONNECTION_PUBLIC_KEY_EXISTS, "Sorry, the site appears to be already added to a ManageWP account. Please deactivate, then activate ManageWP Worker plugin on your website and try again or contact our support.");
        }

        if (!empty($params['skipVerificationTest'])) {
            // Legacy support for worker key.
            $signer    = $this->container->getSigner();
            $messageId = $request->getAction().$request->getNonce();
            $verify    = $signer->verify($messageId, $request->getSignature(), $publicKey);

            if (!$verify) {
                throw new MWP_Worker_Exception(MWP_Worker_Exception::CONNECTION_VERIFICATION_TEST_FAILED, "Unable to verify security signature. Contact your hosting support to check the OpenSSL configuration.");
            }
        }

        if (empty($existingPublicKey)) {
            $configuration->setPublicKey($publicKey);
        }

        mwp_accept_potential_key($request->getCommunicationKey());

        $this->setBrand($params);

        return array();
    }

    private function setBrand(array $params)
    {
        if (!empty($params['brand']) && is_array($params['brand'])) {
            $this->container->getBrand()->update($params['brand']);

            return;
        }

        $this->container->getBrand()->delete();
    }
}
