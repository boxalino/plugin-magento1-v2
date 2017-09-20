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
        if(!Mage::helper('boxalino_intelligence')->isEnabledOnLayer($this->getLayer())){
            return parent::setTemplate($template);
        }
        $this->_template = 'boxalino/catalog/layer/state.phtml';
        return $this;
    }

    /**
     * @return array
     */
    public function getActiveFilters(){
        $facetModel = Mage::getSingleton('boxalino_intelligence/facet');
        $bxHelperData = Mage::helper('boxalino_intelligence');
        if ($bxHelperData->isEnabledOnLayer($this->getLayer()) && !$bxHelperData->getAdapter()->areThereSubPhrases()) {
            $filters = array();
            try{
                $facets = $bxHelperData->getAdapter()->getFacets();
                foreach ($facetModel->getFacets() as $block){
                    $filter = $block->getFacet();
                    // Even if facets are empty we like to display filters.
                    $fieldName = $filter->getFieldName();
                    if($facets->isSelected($fieldName)){
                        $value = $facets->getSelectedValueLabel($fieldName);
                        if($fieldName == 'discountedPrice'){
                            $value = substr_replace($value, '0', strlen($value)-1);
                        }
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
                $bxHelperData->setFallback(true);
            }
        }
        return parent::getActiveFilters();
    }
}
