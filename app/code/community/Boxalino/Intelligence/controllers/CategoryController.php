<?php
require_once "Mage/Catalog/controllers/CategoryController.php";

/**
 * Class Boxalino_Intelligence_CategoryController
 */
class Boxalino_Intelligence_CategoryController extends Mage_Catalog_CategoryController{

    /**
     * 
     */
    public function viewAction()
    {
        $bxHelperData = Mage::helper('boxalino_intelligence');
        try{
            if($bxHelperData->isNavigationEnabled()) {
                $this->_initCatagory();
                $adapter = $bxHelperData->getAdapter();
                $start = microtime(true);
                $adapter->addNotification('debug', "request start at " . $start);
                $redirect_link = $adapter->getResponse()->getRedirectLink();
                $adapter->addNotification('debug',
                    "request end, time: " . (microtime(true) - $start) * 1000 . "ms" .
                    ", memory: " . memory_get_usage(true));

                if($redirect_link != "") {
                    $this->getResponse()->setRedirect($redirect_link);
                }
                Mage::unregister('current_category');
                Mage::unregister('current_entity_key');
                if(isset($_REQUEST['bx_category_id']) && $_REQUEST['bx_category_id'] != 0) {
                    $catId = $this->getRequest()->getParam('id', false);

                    if ($catId) {
                        if ($catId != $_REQUEST['bx_category_id']) {
                            $_category = Mage::getModel('catalog/category')
                                ->setStore(Mage::app()->getStore()->getId())
                                ->load($_REQUEST['bx_category_id']);
                            $url = $_category->getUrl($_category);
                            $this->getResponse()->setRedirect($url)->sendResponse();
                        }
                    }
                }
            }
        } catch(\Exception $e) {
            Mage::logException($e);
            $bxHelperData->setFallback(true);
        }
        parent::viewAction();
    }
}
