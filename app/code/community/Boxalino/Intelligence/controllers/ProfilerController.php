<?php

/**
 * Class Boxalino_Intelligence_ProfilerController
 * Boxalino Profiler controller - can be extended
 *
 * The triggered events are being configured (for logged in/non-logged in user)
 *
 * @author Dana Negrescu <dana.negrescu@boxalino.com>
 */
class Boxalino_Intelligence_ProfilerController extends Mage_Core_Controller_Front_Action
{

    /**
     * Triggered on submit (or per request, if the question is marked as a submit question)
     *
     * @return bool
     */
    public function saveAction()
    {
        if (!$this->getRequest()->isAjax()) {
            $this->_forward('no-route');
            return false;
        }

        $profiler = $this->getRequest()->getPost();
        $response = array();
        $response['profile_id'] = 0;
        try {
            if (Mage::getSingleton('customer/session')->isLoggedIn()) {
                Mage::dispatchEvent($profiler['customer_event'], array("bx_profiler"=>json_decode($profiler['data'])));
            } else {
                Mage::dispatchEvent($profiler['visitor_event'], array("bx_profiler"=>json_decode($profiler['data'])));
            }

            if (Mage::getSingleton('customer/session')->isLoggedIn()) {
                $response['profile_id'] = Mage::getSingleton("customer/session")->getId();
            }

            $response['question'] = $this->_getQuestionBlock($profiler['visualElement'], $profiler['bxNextIndex']);
            $response['order'] = $profiler['bxNextIndex'];
        } catch (\Exception $ex) {
            $response['error']['form_key'] = $ex->getMessage();
            Mage::logException($ex);
        }

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

    /**
     * Loading the question block
     *
     * @param $profiler
     * @return array
     */
    public function loadQuestionAction()
    {
        if (!$this->getRequest()->isAjax()) {
            $this->_forward('no-route');
            return false;
        }

        $response = array();
        $profiler = $this->getRequest()->getPost();
        $response['question'] = $this->_getQuestionBlock($profiler['visualElement'], $profiler['bxIndex']);
        $response['order'] = $profiler['bxIndex'];

        Mage::dispatchEvent("bx_profiler_load_question", array("bx_profiler_response"=>$response));

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

    /**
     * Generic question block rendering via model
     *
     * @param $visualElement
     * @param $index
     * @return mixed
     */
    protected function _getQuestionBlock($visualElement, $index)
    {
        return Mage::getModel("boxalino_intelligence/visualElement_renderer")->createVisualElement(
            $visualElement,
            ['bx_index' => $index]
        )->toHtml();
    }

    /**
     * Callback function that sends profiler information to Boxalino as well
     * If the call is not done via ajax, the profile_id and choice params are required for making the request
     */
    public function bxrequestAction()
    {
        if ($this->getRequest()->isAjax()) {
            $params = $this->getRequest()->getPost();
            $this->_bxrequest($params['choice'], json_decode($params['bxData']));
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(true));
        }

        $choice = $this->getRequest()->getParam("choice", false);
        $profileId = $this->getRequest()->getParam("profile_id", false);
        if($choice && $profileId)
        {
            return $this->_bxrequest($choice);
        }

        return true;
    }

    /**
     * Callback function that sends profiler information to Boxalino as well
     */
    protected function _bxrequest($choice, $params = array())
    {
        try {
            $bxAdapter = Mage::helper('boxalino_intelligence')->getAdapter();
            $bxAdapter->sendRequestWithParams($choice, $params);
        } catch (\Exception $ex) {
            Mage::logException($ex);
        }

        return true;
    }
}
