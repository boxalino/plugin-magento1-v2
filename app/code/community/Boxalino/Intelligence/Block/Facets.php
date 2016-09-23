<?php
class Boxalino_Intelligence_Block_Facets extends Mage_Core_Block_Template{

    public function getTopFilter(){
        $filter = [];
        $layer = Mage::getSingleton('catalog/layer');
        $bxHelperData = Mage::helper('intelligence');
        if($layer instanceof Mage_Catalog_Model_Layer && !$bxHelperData->isNavigationEnabled()){
            return array();
        }

        if($this->isTopFilterEnabled()){
            $facets = $bxHelperData->getAdapter()->getFacets();
            if($facets){
                $fieldName = $bxHelperData->getTopFacetFieldName();
                $filter = $this->getLayout()->createBlock('boxalino/layer_filter_attribute')
                    ->setLayer($this->getLayer())
                    ->setAttributeModel(Mage::getResourceModel('catalog/eav_attribute'))
                    ->setFieldName($fieldName)
                    ->setFacets($facets)
                    ->init();
                return $filter;
            }
        }
        return $filter;
    }

    public function isTopFilterEnabled(){

        return Mage::helper('intelligence')->isTopFilterEnabled();
    }
}