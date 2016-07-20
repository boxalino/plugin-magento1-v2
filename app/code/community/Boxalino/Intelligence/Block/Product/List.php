<?php

class Boxalino_Intelligence_Block_Product_List extends Mage_Catalog_Block_Product_List{

    protected $bxHelperData;

    public function __construct(array $args)
    {
        $this->bxHelperData = Mage::helper('intelligence');
            parent::__construct($args);
    }

    protected function _getProductCollection(){
        
        if(!$this->bxHelperData->isSearchEnabled()){
            return parent::_getProductCollection();
        }

        $layer = $this->getLayer();
        if($layer instanceof Mage_CatalogSearch_Model_Layer || $layer instanceof Mage_Catalog_Model_Layer){
            if(!$this->bxHelperData->isNavigationEnabled() && $layer instanceof Mage_Catalog_Model_Layer){
                return parent::_getProductCollection();
            }

            if(is_null($this->_productCollection)){
                $entity_ids = $this->bxHelperData->getAdapter()->getEntitiesIds();

                var_dump($entity_ids);
                $this->_productCollection = Mage::getResourceModel('catalog/product_collection');

                if (count($entity_ids) == 0) {
                    $entity_ids = array(0);
                }
                $this->_productCollection->addFieldToFilter('entity_id', $entity_ids)
                    ->addAttributeToSelect('*');

                $this->_productCollection->getSelect()->order(new Zend_Db_Expr('FIELD(e.entity_id,' . implode(',', $entity_ids).')'));
                Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($this->_productCollection);
                $this->_productCollection->load();
            }
        }
        return $this->_productCollection;
    }
}