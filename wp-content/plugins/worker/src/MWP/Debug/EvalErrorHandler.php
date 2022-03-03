<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Debug_EvalErrorHandler
{

    private $errorMessages = array();

    public function getErrorMessages()
    {
        return $this->errorMessages;
    }

    public function handleError($code, $message, $file = '', $line = 0, $context = array())
    {
        $this->errorMessages[] = array(
            // Use 'type' instead of 'code' to be consistent with error_get_last()
            'type'     => $code,
            'message'  => $message,
            'file'     => $file,
            'line'     => $line,
            'datetime' => date('Y-m-d H:i:s'),
        );
    }
}
