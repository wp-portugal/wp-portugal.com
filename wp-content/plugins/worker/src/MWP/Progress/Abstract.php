<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

abstract class MWP_Progress_Abstract implements MWP_Progress_CurlCallbackInterface
{
    private $threshold;

    private $lastYield;

    protected function getThreshold()
    {
        return $this->threshold;
    }

    protected function setThreshold($threshold)
    {
        $this->threshold = $threshold;
    }

    protected function yieldCallback()
    {
        if (time() - $this->lastYield >= $this->threshold) {
            $this->lastYield = time();

            return true;
        }

        return false;
    }

    protected function formatBytes($bytes)
    {
        $bytes = (int) $bytes;

        if ($bytes > 1024 * 1024 * 1024) {
            return round($bytes / 1024 / 1024 / 1024, 2).' GB';
        } elseif ($bytes > 1024 * 1024) {
            return round($bytes / 1024 / 1024, 2).' MB';
        } elseif ($bytes > 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }

    protected function calculateOffset($curl)
    {
        /** @handled function */
        $info = curl_getinfo($curl);

        $url = parse_url($info['url']);

        if (!isset($url['query'])) {
            return 0;
        }

        parse_str($url['query'], $query);

        if (!isset($query['offset'])) {
            return 0;
        }

        return (int) $query['offset'];
    }

    public function getCallback()
    {
        return array($this, 'callback');
    }
}
