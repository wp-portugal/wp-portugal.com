<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Action_ClearTransient extends MWP_Action_Abstract
{
    public function execute(array $params = array())
    {
        $total = array(
            'deletedTransients'        => 0,
            'deletedTransientTimeouts' => 0,
        );

        if (is_array($params['transient'])) {
            foreach ($params['transient'] as $transient) {
                $cleared = $this->clearTransients($params['prefix'], $transient['name'], $transient['suffix'], $transient['timeout'], $transient['mask'], $transient['limit']);
                $total['deletedTransients'] += $cleared['deletedTransients'];
                $total['deletedTransientTimeouts'] += $cleared['deletedTransientTimeouts'];
            }
        }

        return $total;
    }

    private function clearTransients($prefix, $transientType, $suffix, $timeout, $mask, $limit)
    {
        $wpdb   = $this->container->getWordPressContext()->getDb();

        $timeoutName  = $transientType.$suffix;
        $subStrLength = strlen($timeoutName) + 1;

        $escapedTimeoutName = str_replace('_', '\_', $timeoutName);

        $selectTimeOutedTransients = <<<SQL
SELECT SUBSTR(option_name, {$subStrLength}) AS transient_name FROM {$prefix}options WHERE option_name LIKE '{$escapedTimeoutName}{$mask}' AND option_value < {$timeout} LIMIT {$limit}
SQL;

        $transientsToDelete = $wpdb->get_col($selectTimeOutedTransients);
        $timeoutsToDelete   = array();

        if (count($transientsToDelete) === 0) {
            return array(
                'deletedTransients'        => 0,
                'deletedTransientTimeouts' => 0,
            );
        }

        foreach ($transientsToDelete as &$transient) {
            $timeoutsToDelete[] = "'".$timeoutName.$transient."'";
            $transient          = "'".$transientType.$transient."'";
        }

        $deleteQuery = implode(',', $transientsToDelete);

        $deleteTransients = <<<SQL
DELETE FROM {$prefix}options WHERE option_name IN ({$deleteQuery})
SQL;

        $deletedTransients = $wpdb->query($deleteTransients);

        $deleteQuery = implode(',', $timeoutsToDelete);

        $deleteTransients = <<<SQL
DELETE FROM {$prefix}options WHERE option_name IN ({$deleteQuery})
SQL;

        $deletedTransientTimeouts = $wpdb->query($deleteTransients);

        return array(
            'deletedTransients'        => $deletedTransients,
            'deletedTransientTimeouts' => $deletedTransientTimeouts,
        );
    }
}
