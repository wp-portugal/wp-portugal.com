<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_PublicRequest_AddStatusPage implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $context;

    private $configuration;

    public function __construct(MWP_WordPress_Context $context, MWP_Worker_Configuration $configuration)
    {
        $this->context       = $context;
        $this->configuration = $configuration;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::PUBLIC_REQUEST => 'onPublicRequest',
        );
    }

    /**
     * @internal
     */
    public function onPublicRequest(MWP_Event_PublicRequest $event)
    {
        $this->context->addAction('admin_menu', array($this, 'addStatusPage'));
    }

    /**
     * @internal
     */
    public function addStatusPage()
    {
        $this->context->addSubMenuPage(null, 'ManageWP Worker Status', 'ManageWP Worker Status', 'activate_plugins', 'worker-status', array($this, 'statusPage'));
    }

    /**
     * @internal
     */
    public function statusPage()
    {
        $pluginFile    = 'worker/init.php';
        $deactivateUrl = $this->context->wpNonceUrl('plugins.php?action=deactivate&amp;plugin='.$pluginFile, 'deactivate-plugin_'.$pluginFile);

        $info = json_encode(array(
            'connected'       => (bool) $this->configuration->getPublicKey(),
            'connectedLegacy' => (bool) $this->configuration->getSecureKey(),
        ));
        ?>
        <div class="wrap">
            <div id="icon-tools" class="icon32"></div>
            <h2>ManageWP Worker Status</h2>
        </div>

        <p>
            <a id="worker-status-deactivate" class="button" href="<?php echo $deactivateUrl ?>">Deactivate</a>
        </p>

        <textarea id="worker-status-settings" style="display: none;"><?php echo htmlspecialchars($info, ENT_QUOTES) ?></textarea>
    <?php
    }
}
