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

        $bxHelperData = Mage::helper('boxalino_intelligence');
        try{
            if($bxHelperData->isNavigationEnabled()){
                if(count($bxHelperData->getAdapter()->getEntitiesIds()) == 0){
                    $bxHelperData->setFallback(true);
                }
            }
        }catch(\Exception $e){
            Mage::logException($e);
            $bxHelperData->setFallback(true);
        }
        return parent::viewAction();
    }
}
