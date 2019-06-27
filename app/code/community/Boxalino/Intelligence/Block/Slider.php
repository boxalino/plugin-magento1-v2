<?php

/**
 * Class Boxalino_Intelligence_Block_Slider
 */
class Boxalino_Intelligence_Block_Slider extends Mage_Core_Block_Template
{

    /**
     * @var array slider values used in template
     */
    protected $sliderValues = array();

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
    public function getSliderValues()
    {
        if(!empty($this->sliderValues))
        {
            return $this->sliderValues;
        }

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

        $this->sliderValues = array_merge($selectedPrice, $priceRange);
        return $this->sliderValues;
    }

    public function getPriceFilterName()
    {
        return Mage::helper('boxalino_intelligence')->getPriceFacetFilterName();
    }

    /**
     * @return bool
     */
    public function getConnect()
    {
        if (empty($this->sliderValues))
        {
            $this->getSliderValues();
        }

        if(is_null($this->sliderValues))
        {
            return false;
        }

        if(isset($this->sliderValues[0]) && isset($this->sliderValues[1]))
        {
            return true;
        }

        if(isset($this->sliderValues[0]))
        {
            return "lower";
        }

        if(isset($this->sliderValues[1]))
        {
            return "upper";
        }

        return false;
    }
}
