<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Extension_HitCounter
{

    private $context;

    /**
     * @var int
     */
    private $numberOfDays;

    const OPTION_NAME = 'user_hit_count';

    /**
     * @param MWP_WordPress_Context $context
     * @param int                   $numberOfDays Number of days to keep the log.
     */
    public function __construct(MWP_WordPress_Context $context, $numberOfDays = 1)
    {
        $this->context      = $context;
        $this->numberOfDays = $numberOfDays;
    }

    /**
     * @param int      $incrementBy
     * @param DateTime $dateTime
     */
    public function increment($incrementBy = 1, DateTime $dateTime = null)
    {
        if ($dateTime === null) {
            $dateTime = new DateTime('now', new DateTimeZone('UTC'));
        }
        $date = $dateTime->format('Y-m-d');

        $hitCount = (array)$this->getHitCount();

        if (!isset($hitCount[$date])) {
            $hitCount[$date] = 0;

            ksort($hitCount);

            $logSince = clone $dateTime;
            $logSince->modify(sprintf('-%d day', $this->numberOfDays));
            $logSinceDate = $logSince->format('Y-m-d');
            foreach ($hitCount as $hitDate => $hitTotal) {
                // The old functionality had a bug where keys were invalid dates, hence the date length check.
                if ($hitDate <= $logSinceDate || strlen($hitDate) !== 10) {
                    unset($hitCount[$hitDate]);
                }
            }
        }

        $hitCount[$date] += $incrementBy;

        $this->context->optionSet(self::OPTION_NAME, $hitCount);
    }

    /**
     * @return array
     */
    public function getHitCount()
    {
        $hitCount = $this->context->optionGet(self::OPTION_NAME, array());

        return $hitCount;
    }
}
