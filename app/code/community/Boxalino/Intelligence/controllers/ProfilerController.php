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
     * When the last question is answered, the data is being saved/set
     *
     * @return bool
     */
    public function saveAction()
    {
        $profilerData = $this->getRequest()->getPost();
        if (!$this->getRequest()->isAjax()) {
            $this->_forward('no-route');
            return false;
        }

        $profiler = new Varien_Object($profilerData);
        try {
            if (Mage::getSingleton('customer/session')->isLoggedIn()) {
                $customerId = Mage::getSingleton("customer/session")->getId();
                $profiler->setProfileId($customerId);
                Mage::dispatchEvent("bx_profiler_customer_update", array("bx_profiler"=>$profiler));
            } else {
                Mage::dispatchEvent("bx_profiler_customer_login", array("bx_profiler"=>$profiler));
            }

            $response = $this->setNextQuestionResponse($profiler);
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
        $response['active_question'] = Mage::getModel("boxalino_intelligence/visualElement_renderer")->createVisualElement(
            $profiler->getVisualElement(),
            ['bx_index' => $profiler->getNextIndex(), 'is_ajax'=>true]
        )->toHtml();

        return $response;
    }


    /**
     * If there is a login form, before submiting the data, the customer email validation must take place
     */
    public function isCustomerAction()
    {
        $profilerData = $this->getRequest()->getPost();

        if (!$this->getRequest()->isAjax()) {
            $this->_forward('no-route');
            return false;
        }

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
