<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Http_JsonResponse extends MWP_Http_Response
{
    public function __construct($content, $statusCode = 200, array $headers = array())
    {
        $headers['content-type'] = 'application/json';
        parent::__construct($content, $statusCode, $headers);
    }

    public function getContentAsString()
    {
        $content = json_encode($this->content);

        if (!is_string($content)) {
            $invalidValues = array();
            $this->fixUtf8($this->content, $invalidValues);

            if (is_array($this->content) && count($invalidValues) > 0) {
                $this->content['utf8FixedPaths'] = $invalidValues;
            }

            $content = json_encode($this->content);
        }

        return "\n" . $content;
    }

    private function fixUtf8(&$structure, array &$found = array(), &$walkedRefs = array(), array $path = array('_'))
    {
        switch ($type = gettype($structure)) {
            case 'string':
                if (!seems_utf8($structure)) {
                    $found[implode('.', $path)] = urlencode($structure);
                    $structure = utf8_encode($structure);
                }
                break;
            /** @noinspection PhpMissingBreakStatementInspection */
            case 'object':
                // Handle recursion.
                // __PHP_Incomplete_Class will return false on is_object() call. Luckily, we can still get its object hash.
                $objectHash = spl_object_hash($structure);
                if (isset($walkedRefs[$objectHash])) {
                    break;
                }
                $walkedRefs[$objectHash] = true;
            // Fall through.
            case 'array':
                // Object and array are by default traversable.
                foreach ($structure as $key => &$value) {
                    $valuePath   = $path;
                    $valuePath[] = $key;
                    $this->fixUtf8($value, $found, $walkedRefs, $valuePath);
                }
                break;
        }
    }
}
