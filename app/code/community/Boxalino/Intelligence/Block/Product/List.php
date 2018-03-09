<?php

/**
 * Class Boxalino_Intelligence_Block_Product_List
 */
class Boxalino_Intelligence_Block_Product_List extends Mage_Catalog_Block_Product_List{

    /**
     * @var int
     */
    public static $number = 0;

    protected $_subPhraseCollections = [];
    
    /**
     * @return Mage_Eav_Model_Entity_Collection_Abstract
     */
    protected function _getProductCollection()
    {
        /** @var Boxalino_Intelligence_Helper_Data $bxHelperData */
        $bxHelperData = Mage::helper('boxalino_intelligence');
        $p13nHelper = $bxHelperData->getAdapter();
        $layer = $this->getLayer();
        if (null === $this->_productCollection && !(isset($this->_subPhraseCollections[self::$number]))) {
            try {
                if ($bxHelperData->isEnabledOnLayer($layer)) {
                   
                    if ($bxHelperData->layerCheck($layer, 'Mage_Catalog_Model_Layer')) {
                        // We skip boxalino processing if category is static cms block only.

                        if (Mage::registry('current_category')
                            && Mage::getBlockSingleton('catalog/category_view')->getCurrentCategory()
                            && Mage::getBlockSingleton('catalog/category_view')->isContentMode()
                        ) {
                            return parent::_getProductCollection();
                        }
                    }

                    if ($p13nHelper->areThereSubPhrases()) {
                        $queries = $p13nHelper->getSubPhrasesQueries();
                        $entity_ids = $p13nHelper->getSubPhraseEntitiesIds($queries[self::$number]);
                        $entity_ids = array_slice($entity_ids, 0, $bxHelperData->getSubPhrasesLimit());
                    } else {
                        $entity_ids = $p13nHelper->getEntitiesIds();
                    }

                    if (count($entity_ids) == 0) {
                        $entity_ids = array(0);
                    }
                    $this->_setupCollection($entity_ids);
                    // Soft cache subphrases to prevent multiple requests.
                    if ($p13nHelper->areThereSubPhrases()) {
                        $this->_subPhraseCollections[self::$number] = $this->_productCollection;
                        $this->_productCollection = null;
                    }
                } else {
                    $this->_productCollection = parent::_getProductCollection();
                }
            } catch (\Exception $e) {
                Mage::logException($e);
                $bxHelperData->setFallback(true);
                $this->_productCollection = parent::_getProductCollection();
            }
        }
        return (isset($this->_subPhraseCollections[self::$number]))
            ? $this->_subPhraseCollections[self::$number] : $this->_productCollection;
    }

    /**
     * @param $id
     * @param $field
     * @return string
     */
    public function getHitValueForField($id, $field) {
        $bxHelperData = Mage::helper('boxalino_intelligence');
        $p13nHelper = $bxHelperData->getAdapter();
        $value = '';
        if($bxHelperData->isEnabledOnLayer($this->getLayer())){
            $value = $p13nHelper->getHitVariable($id, $field);
        }
        return is_array($value) ? reset($value) : $value;
    }

    /**
     * @param $entity_ids
     * @throws Exception
     */
    protected function _setupCollection($entity_ids){

        $helper = Mage::helper('boxalino_intelligence');
        $this->_productCollection = $helper->prepareProductCollection($entity_ids);
        $this->_productCollection
            ->setStore($this->getLayer()->getCurrentStore())
            ->addAttributeToSelect(Mage::getSingleton('catalog/config')->getProductAttributes())
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addStoreFilter()
            ->addUrlRewrite();

        Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($this->_productCollection);
        Mage::getSingleton('catalog/product_visibility')->addVisibleInSearchFilterToCollection($this->_productCollection);

        $this->_productCollection->setCurBxPage($this->getToolbarBlock()->getCurrentPage());
        $limit = $this->getRequest()->getParam('limit') ? $this->getRequest()->getParam('limit') : $this->getToolbarBlock()->getLimit();

        try{
            $p13nHelper = Mage::helper('boxalino_intelligence')->getAdapter();
            if ($p13nHelper->areThereSubPhrases()) {
                $queries = $p13nHelper->getSubPhrasesQueries();
                $totalHitCount = $p13nHelper->getSubPhraseTotalHitCount($queries[self::$number]);
            } else {
                $totalHitCount = $p13nHelper->getTotalHitCount();
            }
        }catch(\Exception $e){
            Mage::logException($e);
            throw $e;
        }
        $count = $totalHitCount == 0 ? $totalHitCount : count($entity_ids);
        $lastPage = ceil($totalHitCount / $limit);
        $this->_productCollection
            ->setLastBxPage($lastPage)
            ->setBxTotal($totalHitCount)
            ->setBxCount($count)
            ->load();
    }

    protected function _beforeToHtml(){

        if(!is_null(Mage::registry('current_category')) && Mage::helper('boxalino_intelligence')->isEnabledOnLayer($this->getLayer()) &&
                Mage::helper('boxalino_intelligence')->isNavigationSortEnabled())
        {
            $toolbar = $this->getToolbarBlock();
            $orders = $toolbar->getAvailableOrders();
            $orders = array_merge(['relevance' => $this->__('Relevance')], $orders);
            $toolbar->setAvailableOrders($orders);
            $toolbar->setDefaultOrder('relevance');
        }
        return parent::_beforeToHtml();
    }
}
