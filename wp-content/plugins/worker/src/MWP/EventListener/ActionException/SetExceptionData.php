<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_EventListener_ActionException_SetExceptionData implements Symfony_EventDispatcher_EventSubscriberInterface
{
    private $configuration;

    function __construct(MWP_Worker_Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    public static function getSubscribedEvents()
    {
        return array(
            MWP_Event_Events::ACTION_EXCEPTION => array('onActionException', 200),
        );
    }

    public function onActionException(MWP_Event_ActionException $event)
    {
        $exception = $event->getException();

        $verbose = $event->getRequest()->isAuthenticated();

        if ($exception instanceof MWP_Worker_Exception) {
            $exceptionData = $this->getDataForWorkerException($exception, $verbose);
        } else {
            $exceptionData = $this->getDataForGenericException($exception, $verbose);
        }

        $data = array(
            'error'     => $exception->getMessage(),
            'exception' => $exceptionData,
        );

        $event->setData($data);
    }

    private function getExtraDataForWorkerException(MWP_Worker_Exception $exception, $verbose)
    {
        if (!$verbose) {
            return array();
        }

        $extraData = array();

        if ($exception->getCode() === MWP_Worker_Exception::CONNECTION_PUBLIC_KEY_EXISTS) {
            $extraData['publicKey'] = $this->configuration->getPublicKey();
        }

        return $extraData;
    }

    private function getDataForWorkerException(MWP_Worker_Exception $exception, $verbose)
    {
        return $this->getExtraDataForWorkerException($exception, $verbose) + array(
            'context' => $exception->getContext(),
            'type'    => $exception->getErrorName(),
        ) + $this->getDataForGenericException($exception, $verbose);
    }

    /**
     * @param Exception|Error $exception
     * @param bool            $verbose
     *
     * @return array
     */
    private function getDataForGenericException($exception, $verbose)
    {
        $data = array(
            'class'   => get_class($exception),
            'message' => $exception->getMessage(),
            'code'    => $exception->getCode(),
        );

        if ($verbose) {
            $data += array(
                'line'        => $exception->getLine(),
                'file'        => $exception->getFile(),
                'traceString' => $exception->getTraceAsString(),
                'memoryUsage' => memory_get_usage(true),
                'memoryLimit' => $this->convertToBytes(ini_get('memory_limit')),
            );
        }

        return $data;
    }

    private function convertToBytes($memoryLimit)
    {
        $memoryLimit = (string)$memoryLimit;

        if ('-1' === $memoryLimit) {
            return -1;
        }

        $memoryLimit = strtolower($memoryLimit);
        $max         = strtolower(ltrim($memoryLimit, '+'));
        if (0 === strpos($max, '0x')) {
            $max = intval($max, 16);
        } elseif (0 === strpos($max, '0')) {
            $max = intval($max, 8);
        } else {
            $max = intval($max);
        }

        switch (substr($memoryLimit, -1)) {
            /** @noinspection PhpMissingBreakStatementInspection */
            case 't':
                $max *= 1024;
            /** @noinspection PhpMissingBreakStatementInspection */
            case 'g':
                $max *= 1024;
            /** @noinspection PhpMissingBreakStatementInspection */
            case 'm':
                $max *= 1024;
            case 'k':
                $max *= 1024;
        }

        return $max;
    }
}
