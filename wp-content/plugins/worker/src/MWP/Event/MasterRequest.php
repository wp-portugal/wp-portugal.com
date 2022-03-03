<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Event_MasterRequest extends MWP_Event_AbstractRequest
{

    /**
     * @var array
     */
    private $params;

    /**
     * @var boolean
     */
    private $muContext;

    function __construct(MWP_Worker_Request $request, array $params, $muContext = false)
    {
        $this->params    = $params;
        $this->muContext = $muContext;

        parent::__construct($request);
    }

    public function getParams()
    {
        return $this->params;
    }

    public function setParams(array $params)
    {
        $this->params = $params;
    }

    public function isMuContext()
    {
        return $this->muContext;
    }
}
