<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Monolog_Processor_RequestIdProcessor implements Monolog_Processor_ProcessorInterface
{
    /**
     * @var string
     */
    private $requestId;

    /**
     * @param string $requestId
     */
    public function __construct($requestId)
    {
        $this->requestId = $requestId;
    }

    public function callback(array $record)
    {
        $record['extra']['request_id'] = $this->requestId;

        return $record;
    }
}
