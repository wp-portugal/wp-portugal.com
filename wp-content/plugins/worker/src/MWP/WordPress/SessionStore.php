<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_WordPress_SessionStore
{

    private $context;

    private $sessionsKey = 'mwp_sessions';

    public function __construct(MWP_WordPress_Context $context)
    {
        $this->context = $context;
    }

    /**
     * @param int    $userId
     * @param string $token
     */
    public function add($userId, $token)
    {
        $sessions                  = $this->getSessions();
        $sessions[(int) $userId][] = (string) $token;

        $this->saveSessions($sessions);
    }

    /**
     * @return int Number of destroyed sessions.
     */
    public function destroyAll()
    {
        if (!$this->context->isVersionAtLeast('4.0.0')) {
            // Not supported before WordPress 4.0.0.
            return -1;
        }

        $removed = 0;
        foreach ($this->getSessions() as $userId => $tokens) {
            $sessionTokens = $this->context->getSessionTokens($userId);
            foreach ($tokens as $token) {
                $sessionTokens->destroy($token);
                $removed++;
            }
        }

        $this->saveSessions(array());

        return $removed;
    }

    /**
     * Returns array of arrays of session IDs, indexed by user ID.
     *
     * @example
     * -
     *  user_id_1:
     *    - token_id_1
     *    - token_id_2
     *  user_id_2:
     *    - token_id_3
     *    - token_id_4
     *    - token_id_5
     *  ...
     *
     * @return array[]
     */
    private function getSessions()
    {
        $sessions = $this->context->transientGet($this->sessionsKey);

        return $sessions ? $sessions : array();
    }

    private function saveSessions($sessions)
    {
        $this->context->transientSet($this->sessionsKey, $sessions);
    }
}
