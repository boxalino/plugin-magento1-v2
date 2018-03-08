<?php

/**
 * Class Boxalino_Intelligence_Block_Product_List_Upsell
 */
class Boxalino_Intelligence_Block_Product_List_Upsell extends Mage_Catalog_Block_Product_List_Upsell{

    /**
     * 
     */
    public function _construct()
    {
        $this->_prepareData(false);
        parent::_construct(); 
    }

    /**
     * @param bool $execute
     * @return $this|null
     */
    protected function _prepareData($execute = true){

        $bxHelperData = Mage::helper('boxalino_intelligence');
        if($bxHelperData->isPluginEnabled() && $bxHelperData->isUpsellEnabled()){
            
            $mainProduct = Mage::registry('product');
            $config = Mage::getStoreConfig('bxRecommendations/upsell');
            $choiceId = (isset($config['widget']) && $config['widget'] != "") ? $config['widget'] : 'complementary';
            $entity_ids = array();
            try{
                parent::_prepareData();
                $relatedProducts[$mainProduct->getId()] = array();
                foreach ($this->_itemCollection as $product) {
                    $relatedProducts[$mainProduct->getId()][] = $product->getId();
                }
                $entity_ids = $bxHelperData->getAdapter()->getRecommendation(
                    $choiceId,
                    $mainProduct,
                    'product',
                    $config['min'],
                    $config['max'],
                    $execute,
                    array(),
                    $relatedProducts
                );
                $this->setData('title', $bxHelperData->getAdapter()->getSearchResultTitle($choiceId));
            }catch(\Exception $e){
                Mage::logException($e);
                return parent::_prepareData();
            }

            if(!$execute){
                return null;
            }
            
            if(empty($entity_ids)){
                $entity_ids = array(0);
            }
            
            $this->_itemCollection = $bxHelperData->prepareProductCollection($entity_ids)
                ->addAttributeToSelect('*');

            if (Mage::helper('catalog')->isModuleEnabled('Mage_Checkout')) {
                Mage::getResourceSingleton('checkout/cart')->addExcludeProductFilter($this->_itemCollection,
                    Mage::getSingleton('checkout/session')->getQuoteId()
                );
                $this->_addProductAttributesAndPrices($this->_itemCollection);
            }
            $this->_itemCollection->load()->setBxTotal(count($entity_ids));

            foreach ($this->_itemCollection as $product) {
                $product->setDoNotUseCategoryId(true);
            }
            return $this;
        }
        return parent::_prepareData(); 
    }

    public function bxRecommendationTitle() {
        return $this->getData('title');
    }
}
