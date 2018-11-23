<?php

/**
 * Class Boxalino_Intelligence_ProfilerController
 * Boxalino Profiler controller - can be extended
 *
 * When using ajax to load the content, the data on customer is only being saved once the customer authenticates
 * The customer authentication is expected to happen at the end of the cycle or at the beginning, per configuration
 * The triggered events are being configured (for logged in/non-logged in user)
 *
 * @author Dana Negrescu <dana.negrescu@boxalino.com>
 */
class Boxalino_Intelligence_ProfilerController extends Mage_Core_Controller_Front_Action
{

    /**
     * Triggered on submit
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
        try {
            if (Mage::getSingleton('customer/session')->isLoggedIn()) {
                $profiler['profile_id'] = Mage::getSingleton("customer/session")->getId();
                Mage::dispatchEvent($profiler['customer_event'], array("bx_profiler"=>$profiler));
            } else {
                Mage::dispatchEvent($profiler['visitor_event'], array("bx_profiler"=>$profiler));
            }

            $response['question'] = $this->_getQuestionBlock($profiler['visualElement'], $profiler['bxNextIndex']);
            $response['order'] = $profiler['bxNextIndex'];
        } catch (Exception $ex) {
            $response['error']['form_key'] = $ex->getMessage();
            Mage::logException($ex);
        }

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

    /**
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

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

    protected function _getQuestionBlock($visualElement, $index)
    {
        return Mage::getModel("boxalino_intelligence/visualElement_renderer")->createVisualElement(
            $visualElement,
            ['bx_index' => $index]
        )->toHtml();
    }


    /**
     * If there is a login form, before submiting the data, the customer email validation must take place
     * This is an example of a function that can be used as a dispatched event on a question
     */
    public function isCustomerAction()
    {
        if (!$this->getRequest()->isAjax()) {
            $this->_forward('no-route');
            return false;
        }

        $profilerData = $this->getRequest()->getPost();

        $response = array();
        $response['is_customer'] = true;
        $email = $profilerData['email'];

        $customer = Mage::getModel('customer/customer')
            ->setWebsiteId(Mage::app()->getWebsite()->getId())
            ->loadByEmail($email);

        if (!$customer || !$customer->getId()) {
            $response['is_customer'] = false;
        }

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

}
