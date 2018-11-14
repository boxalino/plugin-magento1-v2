<?php

/**
 * Class Boxalino_Intelligence_ProfilerController
 * Boxalino Profiler controller - can be extended
 *
 * When using ajax to load the content, the data on customer is only being saved once the customer authenticates
 * The customer authentication is expected to happen at the end of the cycle or at the begining, per configuration
 *
 * @author Dana Negrescu <dana.negrescu@boxalino.com>
 */
class Boxalino_Intelligence_ProfilerController extends Mage_Core_Controller_Front_Action
{

    /**
     * On each user select/move to next question, the profiler data is being refreshed
     * Data returned onSave is a profilerContext:: index, profilerData and next step
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

    protected function setNextQuestionResponse($profiler)
    {
        $response = array();
        $nextIndexId = $profiler->getBxIndex() + 1;

        $response['bx_profiler'] = $this->getLayout()
            ->createBlock('evozon_blog/post_view_comments_list')
            ->setBxIndex($nextIndexId)
            ->setChild(
                'evozon_blog_post_comments_reply',
                Mage::getBlockSingleton('evozon_blog/post_view_comments_reply')->setBxIndex($nextIndexId)
            )
            ->toHtml();

        return $response;
    }


    public function isCustomerAction()
    {
        $profilerData = $this->getRequest()->getPost();

        if (!$this->getRequest()->isAjax()) {
            $this->_forward('no-route');
            return false;
        }

        $url = Mage::getUrl('customer/account/login');
        $response = array();
        $response['is_customer'] = true;
        $response['message'] = $this->__('This email address is already registered in our system. Please <a href="%s">login</a> if you want to update your subscription preferences', $url);
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
