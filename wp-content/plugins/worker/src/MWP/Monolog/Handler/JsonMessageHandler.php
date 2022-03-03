<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Monolog_Handler_JsonMessageHandler extends Monolog_Handler_AbstractHandler
{

    private $implicitFlushEnabled = false;

    const PAD_CHARACTER = ' ';

    private $padLength;

    /**
     * @param int $padLength
     */
    public function setPadLength($padLength)
    {
        $this->padLength = $padLength;
    }

    public function handle(array $record)
    {
        if (!$this->implicitFlushEnabled) {
            ob_implicit_flush(1);
            $this->implicitFlushEnabled = true;
        }

        /** @var DateTime $date */
        $date    = $record['datetime'];
        $message = array(
            'level'   => $record['level_name'],
            'time'    => $date->format('Y-m-d H:i:s'),
            'message' => $record['message'],
            'context' => $record['context'],
            'extra'   => $record['extra'],
        );

        @ob_start();
        echo "\n", str_pad(json_encode($message), $this->padLength, self::PAD_CHARACTER), "\n";
        @ob_end_flush();

        return false;
    }
}
