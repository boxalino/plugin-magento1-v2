<?php

/**
 * Class Boxalino_Intelligence_Model_Layer_Filter_Attribute
 */
class Boxalino_Intelligence_Model_Layer_Filter_Item extends Mage_Catalog_Model_Layer_Filter_Item {

    public function getUrl(){
        if(Mage::helper('boxalino_intelligence')->isEnabledOnLayer($this->getFilter()->getLayer())){
            $query = array(
            $this->getFilter()->getRequestVar()=>$this->getParamValue(),
                Mage::getBlockSingleton('page/html_pager')->getPageVarName() => null // exclude current page from urls
            );
            return Mage::getUrl('*/*/*', array('_current'=>true, '_use_rewrite'=>true, '_query'=>$query));
        }
        return parent::getUrl();
    }

    public function getRemoveUrl() {
        if(Mage::helper('boxalino_intelligence')->isEnabledOnLayer($this->getFilter()->getLayer())){
            $query = array($this->getFilter()->getRequestVar()=> $this->getParamValue());
            $params['_current']     = true;
            $params['_use_rewrite'] = true;
            $params['_query']       = $query;
            $params['_escape']      = true;
            return Mage::getUrl('*/*/*', $params);
        }
        return parent::getRemoveUrl();
    }
}