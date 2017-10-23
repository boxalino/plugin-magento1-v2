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
        $bxHelperData = Mage::helper('boxalino_intelligence');
        try{
            if($bxHelperData->isEnabledOnLayer($this->getLayer())) {
                $this->getBxFacets();
                $this->_template = 'boxalino/catalog/layer/state.phtml';
                return $this;
            }
        } catch(\Exception $e) {
            Mage::logException($e);
            $bxHelperData->setFallback(true);
        }
        $this->_template = $template;
        return $this;
    }


    public function getBxFacets(){
        $bxHelperData = Mage::helper('boxalino_intelligence');
        return $bxHelperData->getAdapter()->getFacets();
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
                    $fieldName = $filter->getFieldName();
                    if($facets->isSelected($fieldName)){
                        $items = $filter->getItems();
                        $selectedValues = $facets->getSelectedValues($fieldName);
                        if(!empty($selectedValues)) {
                            foreach ($selectedValues as $i => $v){
                                $value = $facets->getSelectedValueLabel($fieldName, $i);
                                if(isset($items[$value])){
                                    $item =  $items[$value];
                                    if($fieldName == 'discountedPrice'){
                                        $value = substr_replace($value, '0', strlen($value)-1);
                                        $item->setLabel($value);
                                    }
                                    $filters[] = $item;
                                }

                            }
                        } else {
                            $selectedValue = $facets->getSelectedValueLabel($fieldName);
                            if($selectedValue != '' && isset($items[$selectedValue])) {
                                $filters[] = $items[$selectedValue];
                            }
                        }
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
