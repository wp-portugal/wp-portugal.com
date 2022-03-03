<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_WordPress_Query_Comment extends MWP_WordPress_Query_Abstract
{
    public function query(array $options = array())
    {
        $options += array(
            'query'          => null,
            'prefixName'     => '{{prefix}}',
            'deserialize'    => array(),
            'tryDeserialize' => array(),
        );

        $query    = str_replace($options['prefixName'], $this->getDb()->prefix, $options['query']);
        $comments = $this->getDb()->get_results($query, ARRAY_A);
        $this->deserialize($comments, $options['deserialize'], $options['tryDeserialize']);

        return $comments;
    }
}
