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
 * @deprecated This is used to convert the result of PHP execution to raw response or an error response.
 */
class MWP_EventListener_ActionResponse_SetLegacyPhpExecutionData implements Symfony_EventDispatcher_EventSubscriberInterface
{

    private static $errorMap = array(
        E_PARSE         => 'E_PARSE',
        E_ERROR         => 'E_ERROR',
        E_CORE_ERROR    => 'E_CORE_ERROR',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
    );

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::ACTION_RESPONSE => array('onActionResponse', 500),
        );
    }

    public function onActionResponse(MWP_Event_ActionResponse $event)
    {
        $data = $event->getData();

        if ($event->getRequest()->getAction() !== 'execute_php_code') {
            return;
        }

        if ($event->getRequest()->getProtocol() >= 1) {
            return;
        }

        if (!empty($data['fatalError']['message'])) {
            throw new MWP_Worker_Exception(MWP_Worker_Exception::PHP_EVAL_ERROR, sprintf('Fatal error [%s]: %s in %s on line %d', self::$errorMap[$data['fatalError']['type']], $data['fatalError']['message'], $data['fatalError']['file'], $data['fatalError']['line']));
        }

        $event->setData(isset($data['output']) ? $data['output'] : '');
    }
}
