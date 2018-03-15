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

    public function getClearUrl()
    {
        $bxHelperData = Mage::helper('boxalino_intelligence');
        if($bxHelperData->isEnabledOnLayer($this->getLayer())) {
            $filterState = array();
            $removeParams = $bxHelperData->getRemoveParams();
            foreach ($this->getActiveFilters() as $item) {
                $filterState[$item->getFilter()->getRequestVar()] = $item->getFilter()->getCleanValue();
            }
            foreach ($removeParams as $remove) {
                $filterState[$remove] = null;
            }
            $params['_current']     = true;
            $params['_use_rewrite'] = true;
            $params['_query']       = $filterState;
            $params['_escape']      = true;
            return Mage::getUrl('*/*/*', $params);
        }
        return parent::getClearUrl();
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
                $forceIncludedFacets = $facets->getForceIncludedFieldNames(true);
                foreach ($facetModel->getFacets() as $block){
                    $filter = $block->getFacet();
                    $fieldName = $filter->getFieldName();
                    if($facets->isSelected($fieldName)){
                        $items = $filter->getItems();
                        $selectedValues = $facets->getSelectedValues($fieldName);
                        if(!empty($selectedValues)) {
                            foreach ($selectedValues as $i => $v){

                                $value = $facets->getSelectedValueLabel($fieldName, $i);

                                if($fieldName == 'discountedPrice' && substr($value, -3) == '- 0') {
                                    $values = explode(' - ', $value);
                                    $values[1] = '*';
                                    $value = implode(' - ', $values);
                                }
                                if(isset($items[$value])){
                                    $item =  $items[$value];
                                    if(isset($forceIncludedFacets[$fieldName])) {
                                        $bxHelperData->setIncludedParams($item->getFilter()->getRequestVar(), $item->getBxValue());
                                    }
                                    $filters[] = $item;
                                }
                            }
                        } else {
                            $selectedValue = $facets->getSelectedValueLabel($fieldName);
                            if($selectedValue != '' && isset($items[$selectedValue])) {
                                $item = $items[$selectedValue];
                                if(isset($forceIncludedFacets[$fieldName])) {
                                    $bxHelperData->setIncludedParams($item->getFilter()->getRequestVar(), $item->getBxValue());
                                }
                                $filters[] = $item;
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
