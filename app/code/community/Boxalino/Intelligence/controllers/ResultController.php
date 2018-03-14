<?php
require_once "Mage/CatalogSearch/controllers/ResultController.php";

class Boxalino_Intelligence_ResultController extends Mage_CatalogSearch_ResultController
{

    public function indexAction()
    {
        $bxHelperData = Mage::helper('boxalino_intelligence');
        try{
            if($bxHelperData->isSearchEnabled()) {
                $adapter = $bxHelperData->getAdapter();
                if ($adapter->getResponse()->getRedirectLink() != "") {
                    $this->getResponse()->setRedirect($adapter->getResponse()->getRedirectLink());
                }
            }
        } catch(\Exception $e) {
            Mage::logException($e);
            $bxHelperData->setFallback(true);
        }

        return parent::indexAction();
    }
}
