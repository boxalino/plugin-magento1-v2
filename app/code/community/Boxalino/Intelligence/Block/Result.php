<?php

class Boxalino_Intelligence_Block_Result extends Mage_CatalogSearch_Block_Result{

    protected function _getProductCollection()
    {
        if (is_null($this->_productCollection)) {
            $this->_productCollection = $this->getListBlock()->getLoadedProductCollection();
        }
//        // reset limits set by the toolbar
        $this->_productCollection->clear();
        $this->_productCollection->getSelect()->limit();

        return $this->_productCollection;
    }

    
    public function getResultCount()
    {
        if (!$this->getData('result_count')) {
            $size = Mage::helper('intelligence')->getAdapter()->getTotalHitCount();
            $this->_getQuery()->setNumResults($size);
            $this->setResultCount($size);
        }
        return 123;
    }
}