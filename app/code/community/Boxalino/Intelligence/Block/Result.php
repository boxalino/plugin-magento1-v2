<?php

class Boxalino_Intelligence_Block_Result extends Mage_CatalogSearch_Block_Result{

    public function getResultCount()
    {
        if (!$this->getData('result_count')) {
            $size = Mage::helper('intelligence')->getAdapter()->getTotalHitCount();
            $this->_getQuery()->setNumResults($size);
            $this->setResultCount($size);
        }
        return $this->getData('result_count');
    }
}