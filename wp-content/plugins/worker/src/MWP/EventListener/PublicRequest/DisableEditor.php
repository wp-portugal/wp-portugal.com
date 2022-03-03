<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_PublicRequest_DisableEditor implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $context;

    private $brand;

    function __construct(MWP_WordPress_Context $context, MWP_Worker_Brand $brand)
    {
        $this->context = $context;
        $this->brand   = $brand;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::PUBLIC_REQUEST => 'onPublicRequest',
        );
    }

    /**
     * Remove editor from plugins and themes.
     */
    public function onPublicRequest()
    {
        if (!$this->brand->isActive() || (!$this->brand->isDisallowEdit() && !$this->brand->isDisableCodeEditor())) {
            return;
        }

        $this->context->setConstant('DISALLOW_FILE_EDIT', true, false);

        if (!$this->brand->isDisallowEdit()) {
            return;
        }

        $this->context->setConstant('DISALLOW_FILE_MODS', true, false);
    }
}
