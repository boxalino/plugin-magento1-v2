<?php

/**
 * Class Boxalino_Intelligence_Block_Slider
 */
class Boxalino_Intelligence_Block_Slider extends Mage_Core_Block_Template{

    /**
     * @param $price
     * @return array
     */
    private function explodePrice($price){

        return explode("-", $price);
    }

    /**
     * @return array|null
     */
    public function getSliderValues(){

        $bxHelperData = Mage::helper('boxalino_intelligence');
        $facets = $bxHelperData->getAdapter()->getFacets();
        if(empty($facets) || empty($facets->getPriceRanges())){
            return null;
        }

        $priceRange = $this->explodePrice($facets->getPriceRanges()[0]);
        $selectedPrice = $facets->getSelectedPriceRange() !== null ?
            $this->explodePrice($facets->getSelectedPriceRange()) : $priceRange;
        if($priceRange[0] == $priceRange[1]){
            $priceRange[1]++;
        }
        if($selectedPrice[0] == 0) {
            $selectedPrice[0] = $priceRange[0];
        }
        if($selectedPrice[1] == 0) {
            $selectedPrice[1] = $priceRange[1];
        }

        return array_merge($selectedPrice, $priceRange);
    }
}
