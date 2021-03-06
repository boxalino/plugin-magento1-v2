<?php

/**
 * Class Boxalino_Intelligence_Block_Cart_Crosssell
 */
class Boxalino_Intelligence_Block_Cart_Crosssell extends Mage_Checkout_Block_Cart_Crosssell{

    /**
     * 
     */
    public function _construct()
    {
        $this->getItems(false);
        parent::_construct();
    }

    /**
     * @param bool $execute
     * @return array|null
     */
    public function getItems($execute = true)
    {
        if(!$this->checkIfPluginToBeUsed())
        {
            return parent::getItems();
        }

        $bxHelperData = Mage::helper('boxalino_intelligence');
        if($bxHelperData->isPluginEnabled() && $bxHelperData->isCrosssellEnabled()){
            try{
                $config = Mage::getStoreConfig('bxRecommendations/cart');
                $items = array();
                $products = array();
                $relatedProducts = array();
                foreach ($this->getQuote()->getAllItems() as $item){
                    $product = $item->getProduct();
                    if($product) {
                        $products[] = $product;
                        $collection = $product->getCrossSellProductCollection();
                        $relatedProducts[$product->getId()] = array();
                        foreach ($collection as $p) {
                            $relatedProducts[$product->getId()][] = $p->getId();
                        }
                    }
                }
                $choiceId = (isset($config['widget']) && $config['widget'] != "") ? $config['widget'] : 'basket';
                $entity_ids = $bxHelperData->getAdapter()->getRecommendation(
                    $choiceId,
                    $products,
                    'basket',
                    $config['min'],
                    $config['max'],
                    $execute,
                    array(),
                    $relatedProducts
                );
            }catch(\Exception $e){
                Mage::logException($e);
                return parent::getItems();
            }

            if(!$execute){
                return null;
            }

            $this->setData('title', $bxHelperData->getAdapter()->getSearchResultTitle($choiceId));
            if(empty($entity_ids)){
                return $items;
            }
            
            $itemCollection = Mage::getResourceModel('catalog/product_collection')
                ->addFieldToFilter('entity_id', $entity_ids)
                ->addAttributeToSelect('*');

            if (Mage::helper('catalog')->isModuleEnabled('Mage_Checkout')) {
                Mage::getResourceSingleton('checkout/cart')->addExcludeProductFilter($itemCollection,
                    Mage::getSingleton('checkout/session')->getQuoteId()
                );
                $this->_addProductAttributesAndPrices($itemCollection);
            }

            foreach ($itemCollection as $product){
                $product->setDoNotUseCategoryId(true);
                $items[] = $product;
            }

            return $items;
        }
        return parent::getItems(); // TODO: Change the autogenerated stub
    }

    public function bxRecommendationTitle() {
        return $this->getData('title');
    }

    /**
     * Before rewriting globally, check if the plugin is to be used
     * @return bool
     */
    public function checkIfPluginToBeUsed()
    {
        $boxalinoGlobalPluginStatus = Mage::helper('core')->isModuleOutputEnabled('Boxalino_Intelligence');
        if($boxalinoGlobalPluginStatus)
        {
            if(Mage::helper('boxalino_intelligence')->isPluginEnabled())
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Used for the narrative tracker
     *
     * @return string|null
     */
    public function getRequestUuid()
    {
        $bxHelperData = Mage::helper('boxalino_intelligence');
        if($this->checkIfPluginToBeUsed() && $bxHelperData->isPluginEnabled() && $bxHelperData->isCrosssellEnabled())
        {
            return $bxHelperData->getAdapter()->getRequestUuid("basket");
        }

        return null;
    }

    /**
     * Used for the narrative tracker
     *
     * @return string|null
     */
    public function getRequestGroupBy()
    {
        $bxHelperData = Mage::helper('boxalino_intelligence');
        if($this->checkIfPluginToBeUsed() && $bxHelperData->isPluginEnabled() && $bxHelperData->isCrosssellEnabled())
        {
            return $bxHelperData->getAdapter()->getRequestGroupBy("basket");
        }

        return null;
    }

}
