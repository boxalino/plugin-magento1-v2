<?php

/**
 * Class Boxalino_Intelligence_Block_Product_List
 */
class Boxalino_Intelligence_Block_Product_List extends Mage_Catalog_Block_Product_List{

    /**
     * @var int
     */
    public static $number = 0;

    /**
     * @return Mage_Eav_Model_Entity_Collection_Abstract
     */
    protected function _getProductCollection(){

        $bxHelperData = Mage::helper('intelligence');
        $p13nHelper = $bxHelperData->getAdapter();
        $layer = $this->getLayer();

        try{
            if($bxHelperData->isEnabledOnLayer($layer)){
                if(count($this->_productCollection) && !$p13nHelper->areThereSubPhrases()){
                    return $this->_productCollection;
                }
    
                if(get_class($layer) == 'Mage_Catalog_Model_Layer'){
                    if(Mage::getBlockSingleton('catalog/category_view')->isContentMode()){
                        return parent::_getProductCollection();
                    }
                }
                
                if($p13nHelper->areThereSubPhrases()){
                    $queries = $p13nHelper->getSubPhrasesQueries();
                    $entity_ids = $p13nHelper->getSubPhraseEntitiesIds($queries[self::$number]);
                    $entity_ids = array_slice($entity_ids, 0, $bxHelperData->getSubPhrasesLimit());
                }else{
                    $entity_ids = $p13nHelper->getEntitiesIds();
                }

                if(count($entity_ids) == 0){
                    $entity_ids = array(0);
                }

                $this->_setupCollection($entity_ids);
                return $this->_productCollection;
                
            }else{
                return parent::_getProductCollection();
            }
        }catch(\Exception $e){
            Mage::logException($e);
        }
        return parent::_getProductCollection();
    }

    /**
     * @param $entity_ids
     * @throws Exception
     */
    private function _setupCollection($entity_ids){

        $this->_productCollection = Mage::getResourceModel('catalog/product_collection');

        $this->_productCollection
            ->setStore($this->getLayer()->getCurrentStore())
            ->addFieldToFilter('entity_id', $entity_ids)
            ->addAttributeToSelect(Mage::getSingleton('catalog/config')->getProductAttributes())
            ->addUrlRewrite($this->getLayer()->getCurrentCategory()->getId());

        $this->_productCollection
            ->getSelect()
            ->order(new Zend_Db_Expr('FIELD(e.entity_id,' . implode(',', $entity_ids).')'));

        $this->_productCollection->setCurBxPage($this->getToolbarBlock()->getCurrentPage());
        $limit = $this->getRequest()->getParam('limit') ? $this->getRequest()->getParam('limit') : $this->getToolbarBlock()->getLimit();
   
        try{
            $totalHitCount = Mage::helper('intelligence')->getAdapter()->getTotalHitCount();
        }catch(\Exception $e){
            Mage::logException($e);
            throw $e;
        }

        $lastPage = ceil($totalHitCount /$limit);
        $this->_productCollection
            ->setLastBxPage($lastPage)
            ->setBxTotal($totalHitCount)
            ->setBxCount(count($entity_ids))
            ->load();
    }
}