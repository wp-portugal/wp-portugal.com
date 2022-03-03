<?php
/*
 * This file is part of the ManageWP Worker plugin.
 *
 * (c) ManageWP LLC <contact@managewp.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class MWP_Event_ActionRequest extends Symfony_EventDispatcher_Event
{

    /**
     * @var MWP_Worker_Request
     */
    private $request;

    /**
     * @var array
     */
    private $params;

    private $actionDefinition;

    public function __construct(MWP_Worker_Request $request, array $params, MWP_Action_Definition $actionDefinition)
    {
        $this->request = $request;
        $this->params = $params;
        $this->actionDefinition = $actionDefinition;
    }

    /**
     * @return MWP_Worker_Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param array $params
     */
    public function setParams(array $params)
    {
        $this->params = $params;
    }

    /**
     * @return MWP_Action_Definition
     */
    public function getActionDefinition()
    {
        return $this->actionDefinition;
    }
}
