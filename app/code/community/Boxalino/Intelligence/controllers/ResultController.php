<?php
require_once "Mage/CatalogSearch/controllers/ResultController.php";

/**
 * Class Boxalino_Intelligence_ResultController
 */
class Boxalino_Intelligence_ResultController extends Mage_CatalogSearch_ResultController{
    
    /**
     * @return $this|void
     */
    public function indexAction()
    {
        $bxHelperData = Mage::helper('boxalino_intelligence');
        try{
            if($bxHelperData->isSearchEnabled() && $bxHelperData->getAdapter()->areThereSubPhrases()){
                $queries = $bxHelperData->getAdapter()->getSubPhrasesQueries();

                if(count($queries) < 2) {
                    $this->_redirect('*/*/*', array('q'=>$queries[0]));
                    return $this;
                }
            }
        }catch(\Exception $e){
            Mage::logException($e);
            $bxHelperData->setFallback(true);
        }
        return parent::indexAction();
    }
}
