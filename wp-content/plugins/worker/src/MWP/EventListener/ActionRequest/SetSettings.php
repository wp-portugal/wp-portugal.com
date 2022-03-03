<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_ActionRequest_SetSettings implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private $context;

    private $system;

    private $migration;

    public function __construct(MWP_WordPress_Context $context, MWP_System_Environment $system, MWP_Migration_Migration $migration)
    {
        $this->context   = $context;
        $this->system    = $system;
        $this->migration = $migration;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::ACTION_REQUEST => 'onActionRequest',
        );
    }

    public function onActionRequest(MWP_Event_ActionRequest $event)
    {
        $this->saveWorkerConfiguration($event->getRequest()->getData());
        // Prevent PHP Warning: set_time_limit() has been disabled for security reasons in __FILE__
        @set_time_limit(1800);
        $this->resetVersions();
        $this->migration->migrate();
    }

    /**
     * Reset versions that some "security" plugins scramble.
     */
    private function resetVersions()
    {
        $versionFile = $this->context->getConstant('ABSPATH').$this->context->getConstant('WPINC').'/version.php';
        if (!file_exists($versionFile)) {
            // For whatever reason.
            return;
        }

        include $versionFile;

        $varNames = array(
            'wp_version',
            'wp_db_version',
            'tinymce_version',
            'required_php_version',
            'required_mysql_version',
        );

        foreach ($varNames as $varName) {
            if (!isset($$varName)) {
                continue;
            }
            $this->context->set($varName, $$varName);
        }
    }

    private function saveWorkerConfiguration(array $data)
    {
        if (empty($data['setting'])) {
            return;
        }

        $configurationService = new MWP_Configuration_Service();
        $configuration        = new MWP_Configuration_Conf($data['setting']);
        $configurationService->saveConfiguration($configuration);
    }
}
