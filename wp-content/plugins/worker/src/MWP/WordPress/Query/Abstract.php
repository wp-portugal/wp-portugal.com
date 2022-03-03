<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

abstract class MWP_WordPress_Query_Abstract implements MWP_WordPress_Query_Interface
{

    protected $context;

    public function __construct(MWP_WordPress_Context $context)
    {
        $this->context = $context;
    }

    protected function deserialize(array &$results, array $deserialize = array(), array $tryDeserialize = array())
    {
        if (count($results) == 0 || (count($deserialize) === 0 && count($tryDeserialize) === 0)) {
            return;
        }

        foreach ($results as &$result) {
            foreach ($deserialize as $field) {
                $result[$field] = unserialize($result[$field]);
            }
            foreach ($tryDeserialize as $tryField) {
                $result[$tryField] = $this->context->tryDeserialize($result[$tryField]);
            }
        }
    }

    protected function getDb()
    {
        return $this->context->getDb();
    }
}
