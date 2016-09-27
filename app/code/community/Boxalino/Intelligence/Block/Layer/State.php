<?php

/**
 * Class Boxalino_Intelligence_Block_Layer_State
 */
class Boxalino_Intelligence_Block_Layer_State extends Mage_Catalog_Block_Layer_State{

    /**
     * @param string $template
     * @return $this
     */
    public function setTemplate($template)
    {
        $this->_template = 'boxalino/catalog/layer/state.phtml';
        return $this;
    }

    /**
     * @return array
     */
    public function getActiveFilters(){
        
        $bxHelperData = Mage::helper('intelligence');
        if ($bxHelperData->isFilterLayoutEnabled($this->getLayer() instanceof Mage_Catalog_Model_Layer)) {
            $filters = array();
            $facets = $bxHelperData->getAdapter()->getFacets();
            foreach ($bxHelperData->getAllFacetFieldNames() as $fieldName){

                if($facets->isSelected($fieldName)){
                    $value = $facets->getSelectedValueLabel($fieldName);
                    if($fieldName == 'discountedPrice'){
                        $value = substr_replace($value, '0', strlen($value)-1);
                    }
                    $filter = Mage::getModel('intelligence/layer_filter_attribute')
                        ->setFacets($facets)
                        ->setFieldName($fieldName)
                        ->setRequestVar($facets->getFacetParameterName($fieldName));
                    $filters[] = Mage::getModel('catalog/layer_filter_item')
                        ->setFilter($filter)
                        ->setValue($value)
                        ->setFieldName($fieldName);
                }
            }
            return $filters;
        }
        return parent::getActiveFilters();
    }
}