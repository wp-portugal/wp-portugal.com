<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Event_Events
{
    /**
     * Fired if a master request is detected.
     *
     * @see MWP_Event_MasterRequest
     */
    const MASTER_REQUEST = 'kernel.master_request';

    /**
     * Fired if a non-master request is detected.
     *
     * @see MWP_Event_PublicRequest
     */
    const PUBLIC_REQUEST = 'kernel.public_request';

    /**
     * Fired when the HTTP response is ready to be sent.
     *
     * @see MWP_Event_MasterResponse
     */
    const MASTER_RESPONSE = 'kernel.master_response';

    /**
     * Fired just before the action and arguments are passed to it.
     *
     * @see MWP_Event_ActionRequest
     */
    const ACTION_REQUEST = 'action.request';

    /**
     * Fired right after the action sends a response.
     *
     * @see MWP_Event_ActionResponse
     */
    const ACTION_RESPONSE = 'action.response';

    /**
     * Fired after an exception is thrown.
     *
     * @see MWP_Event_ActionException
     */
    const ACTION_EXCEPTION = 'action.exception';

    private function __construct()
    {
    }
}
