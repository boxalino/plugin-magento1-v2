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
        if(!Mage::helper('boxalino_intelligence')->isPluginEnabled()){
            return parent::setTemplate($template);
        }
        $this->_template = 'boxalino/catalog/layer/state.phtml';
        return $this;
    }

    /**
     * @return array
     */
    public function getActiveFilters(){

        $bxHelperData = Mage::helper('boxalino_intelligence');
        if ($bxHelperData->isEnabledOnLayer($this->getLayer()) && !$bxHelperData->getAdapter()->areThereSubPhrases()) {
            $filters = array();
            try{
                $facets = $bxHelperData->getAdapter()->getFacets();
                foreach ($facets->getLeftFacets() as $fieldName){
                    // Even if facets are empty we like to display filters.

                    if($facets && $facets->isSelected($fieldName)){
                        $value = $facets->getSelectedValueLabel($fieldName);
                        if($fieldName == 'discountedPrice'){
                            $value = substr_replace($value, '0', strlen($value)-1);
                        }
                        $filter = Mage::getModel('boxalino_intelligence/layer_filter_attribute')
                            ->setFacets($facets)
                            ->setFieldName($fieldName)
                            ->setRequestVar(str_replace('bx_products_', '', $facets->getFacetParameterName($fieldName)));
                        $filters[] = Mage::getModel('catalog/layer_filter_item')
                            ->setFilter($filter)
                            ->setLabel($value)
                            ->setValue($value)
                            ->setFieldName($fieldName);
                    }
                }
                return $filters;
            }catch(\Exception $e){
                Mage::logException($e);
            }
        }
        return parent::getActiveFilters();
    }
}
