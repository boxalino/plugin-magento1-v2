<?php

/**
 * Class Boxalino_Intelligence_Model_Layer_Filter_Attribute
 */
class Boxalino_Intelligence_Model_Layer_Filter_Item extends Mage_Catalog_Model_Layer_Filter_Item {

    public function getRemoveUrl() {
        if(Mage::helper('boxalino_intelligence')->isEnabledOnLayer($this->getFilter()->getLayer())){

            $query = array($this->getFilter()->getRequestVar()=>$this->getValue());
            $params['_current']     = true;
            $params['_use_rewrite'] = true;
            $params['_query']       = $query;
            $params['_escape']      = true;
            return Mage::getUrl('*/*/*', $params);
        }
        return parent::getRemoveUrl();
    }
}